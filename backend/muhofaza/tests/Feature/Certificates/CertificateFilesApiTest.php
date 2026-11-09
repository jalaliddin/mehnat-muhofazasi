<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class CertificateFilesApiTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function issued(?\App\Models\Organization $org = null): Certificate
    {
        return $this->issueNumbered($this->makeResult($this->makeExam($org)));
    }

    public function test_upload_signed_accepts_pdf_and_png(): void
    {
        $cert = $this->issued();
        Sanctum::actingAs($this->makeUser());

        $this->post("/api/certificates/{$cert->id}/signed", ['file' => UploadedFile::fake()->image('scan.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'signed');

        $this->post("/api/certificates/{$cert->id}/signed", ['file' => UploadedFile::fake()->create('scan.pdf', 200, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertStringEndsWith('.pdf', $cert->fresh()->signed_path);
    }

    public function test_upload_rejects_wrong_type_and_oversize(): void
    {
        $cert = $this->issued();
        Sanctum::actingAs($this->makeUser());

        $this->post("/api/certificates/{$cert->id}/signed", ['file' => UploadedFile::fake()->create('a.docx', 10)], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->post("/api/certificates/{$cert->id}/signed", ['file' => UploadedFile::fake()->create('a.pdf', 11000, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertNull($cert->fresh()->signed_path);
    }

    public function test_download_generated_and_signed(): void
    {
        $cert = $this->issued();
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $generated = $this->get("/api/certificates/{$cert->id}/download")->assertOk();
        $this->assertStringContainsString("sertifikat-{$cert->number}.pdf", $generated->headers->get('Content-Disposition'));

        app(CertificateService::class)->storeSigned($cert, UploadedFile::fake()->image('scan.png'), $user);
        $signed = $this->get("/api/certificates/{$cert->id}/download?type=signed")->assertOk();
        $this->assertStringContainsString("sertifikat-{$cert->number}-imzoli.png", $signed->headers->get('Content-Disposition'));
    }

    public function test_download_missing_file_returns_404(): void
    {
        $cert = $this->issued();
        Storage::disk('local')->delete($cert->generated_path);
        Sanctum::actingAs($this->makeUser());

        $this->getJson("/api/certificates/{$cert->id}/download")
            ->assertNotFound()
            ->assertJsonPath('message', 'Fayl topilmadi');
        $this->getJson("/api/certificates/{$cert->id}/download?type=signed")
            ->assertNotFound()
            ->assertJsonPath('message', 'Fayl topilmadi');
    }

    public function test_destroy_deletes_certificate_and_files(): void
    {
        $cert = $this->issued();
        Sanctum::actingAs($this->makeUser());

        $this->deleteJson("/api/certificates/{$cert->id}")->assertOk();

        $this->assertSame(0, Certificate::count());
        Storage::disk('local')->assertMissing($cert->generated_path);
    }

    public function test_operator_other_org_is_forbidden(): void
    {
        $cert = $this->issued($this->makeOrganization('B'));
        Sanctum::actingAs($this->makeUser('operator', $this->makeOrganization('A')));

        $this->getJson("/api/certificates/{$cert->id}/download")->assertForbidden();
        $this->post("/api/certificates/{$cert->id}/signed", ['file' => UploadedFile::fake()->image('scan.png')], ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->deleteJson("/api/certificates/{$cert->id}")->assertForbidden();

        $this->assertSame(1, Certificate::count());
    }

    public function test_operator_cannot_delete_other_org_result_with_certificate(): void
    {
        $cert = $this->issued($this->makeOrganization('B'));
        Sanctum::actingAs($this->makeUser('operator', $this->makeOrganization('A')));

        $this->deleteJson("/api/exam-results/{$cert->exam_result_id}")->assertForbidden();

        $this->assertSame(1, Certificate::count());
        Storage::disk('local')->assertExists($cert->generated_path);
    }
}
