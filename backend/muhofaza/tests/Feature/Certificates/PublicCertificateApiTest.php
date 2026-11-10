<?php

namespace Tests\Feature\Certificates;

use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class PublicCertificateApiTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_show_unsigned_returns_details_without_internal_fields(): void
    {
        $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));

        $this->getJson("/api/public/certificates/{$cert->public_code}")
            ->assertOk()
            ->assertJsonPath('status', 'pending_signature')
            ->assertJsonPath('number', $cert->number)
            ->assertJsonMissingPath('registration_number')
            ->assertJsonPath('program_name', 'Mehnatni muhofaza qilish')
            ->assertJsonMissingPath('generated_path')
            ->assertJsonMissingPath('signed_path')
            ->assertJsonMissingPath('id');
    }

    public function test_download_unsigned_returns_404(): void
    {
        $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));

        $this->getJson("/api/public/certificates/{$cert->public_code}/download")
            ->assertNotFound()
            ->assertJsonPath('message', 'Sertifikat hali imzolanmagan');
    }

    public function test_download_signed_png_is_inline_with_image_type(): void
    {
        $svc = app(CertificateService::class);
        $cert = $svc->issue($this->makeResult($this->makeExam()));
        $svc->storeSigned($cert, UploadedFile::fake()->image('scan.png'), $this->makeUser());

        $res = $this->get("/api/public/certificates/{$cert->public_code}/download")->assertOk();

        $this->assertSame('image/png', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $res->headers->get('Content-Disposition'));
    }

    public function test_download_signed_file_missing_on_disk_returns_404(): void
    {
        $svc = app(CertificateService::class);
        $cert = $svc->issue($this->makeResult($this->makeExam()));
        $svc->storeSigned($cert, UploadedFile::fake()->image('scan.png'), $this->makeUser());
        Storage::disk('local')->delete($cert->fresh()->signed_path);

        $this->getJson("/api/public/certificates/{$cert->public_code}/download")
            ->assertNotFound()
            ->assertJsonPath('message', 'Fayl topilmadi');
    }

    public function test_unknown_code_returns_404(): void
    {
        $this->getJson('/api/public/certificates/nope')
            ->assertNotFound()
            ->assertJsonPath('message', 'Sertifikat topilmadi');
        $this->getJson('/api/public/certificates/nope/download')
            ->assertNotFound()
            ->assertJsonPath('message', 'Sertifikat topilmadi');
    }
}
