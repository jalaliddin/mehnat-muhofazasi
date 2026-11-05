<?php

namespace Tests\Feature\Certificates;

use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class CertificateAdminApiTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_index_scopes_operator_to_own_organization(): void
    {
        $orgA = $this->makeOrganization('A');
        $this->makeCertificate($this->makeResult($this->makeExam($orgA)));
        $this->makeCertificate($this->makeResult($this->makeExam($this->makeOrganization('B'))));

        Sanctum::actingAs($this->makeUser('operator', $orgA));
        $this->getJson('/api/certificates')->assertOk()->assertJsonPath('total', 1);

        Sanctum::actingAs($this->makeUser('admin'));
        $this->getJson('/api/certificates')->assertOk()->assertJsonPath('total', 2);
    }

    public function test_index_filters_by_status_and_search(): void
    {
        $exam = $this->makeExam();
        $this->makeCertificate($this->makeResult($exam, 'good', $this->makeEmployee($exam, 'Karimov Anvar')), ['signed_path' => 'x.pdf']);
        $this->makeCertificate($this->makeResult($exam, 'good', $this->makeEmployee($exam, 'Saidova Nilufar')));
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/certificates?status=signed')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.employee.full_name', 'Karimov Anvar');
        $this->getJson('/api/certificates?status=pending_signature')->assertJsonPath('total', 1);
        $this->getJson('/api/certificates?search=Nilu')->assertJsonPath('total', 1);
    }

    public function test_generate_creates_regenerates_and_skips_signed(): void
    {
        $exam = $this->makeExam();
        $svc = app(CertificateService::class);
        $svc->issue($this->makeResult($exam))->update(['signed_path' => 'certificates/signed/s.pdf']);
        $svc->issue($this->makeResult($exam));
        $this->makeResult($exam);
        $failed = $this->makeResult($exam, 'unsatisfactory');
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/certificates/generate', ['periodic_exam_id' => $exam->id])
            ->assertOk()
            ->assertJson(['created' => 1, 'regenerated' => 1, 'skipped_signed' => 1]);

        $this->assertNull($failed->fresh()->certificate);
    }

    public function test_generate_returns_warnings_when_fields_missing(): void
    {
        $exam = $this->makeExam(null, ['course_start_date' => null]);
        $this->makeResult($exam);
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/certificates/generate', ['periodic_exam_id' => $exam->id])
            ->assertOk()
            ->assertJsonFragment(['Kurs sanalari kiritilmagan']);
    }

    public function test_regenerate_signed_returns_422(): void
    {
        $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));
        $cert->update(['signed_path' => 'certificates/signed/s.pdf']);
        Sanctum::actingAs($this->makeUser());

        $this->postJson("/api/certificates/{$cert->id}/regenerate")
            ->assertStatus(422)
            ->assertJsonPath('message', "Imzolangan sertifikatni qayta yaratib bo'lmaydi");
    }

    public function test_regenerate_unsigned_returns_certificate(): void
    {
        $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));
        Sanctum::actingAs($this->makeUser());

        $this->postJson("/api/certificates/{$cert->id}/regenerate")
            ->assertOk()
            ->assertJsonPath('number', $cert->number);
    }

    public function test_print_returns_pdf_of_unsigned_or_404(): void
    {
        $exam = $this->makeExam();
        Sanctum::actingAs($this->makeUser());

        $this->getJson("/api/certificates/print?periodic_exam_id={$exam->id}")
            ->assertNotFound()
            ->assertJsonPath('message', "Chop etish uchun imzolanmagan sertifikat yo'q");

        app(CertificateService::class)->issue($this->makeResult($exam));
        $res = $this->get("/api/certificates/print?periodic_exam_id={$exam->id}")->assertOk();

        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString("sertifikatlar-{$exam->id}.pdf", $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_operator_cannot_generate_regenerate_or_print_for_other_org(): void
    {
        $exam = $this->makeExam($this->makeOrganization('B'));
        $cert = app(CertificateService::class)->issue($this->makeResult($exam));
        Sanctum::actingAs($this->makeUser('operator', $this->makeOrganization('A')));

        $this->postJson('/api/certificates/generate', ['periodic_exam_id' => $exam->id])->assertForbidden();
        $this->postJson("/api/certificates/{$cert->id}/regenerate")->assertForbidden();
        $this->getJson("/api/certificates/print?periodic_exam_id={$exam->id}")->assertForbidden();
    }
}
