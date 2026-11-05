<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Models\CertificateSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class CertificateModelTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    public function test_status_is_pending_until_signed_path_is_set(): void
    {
        $cert = $this->makeCertificate($this->makeResult($this->makeExam()));
        $this->assertSame('pending_signature', $cert->status);

        $cert->update(['signed_path' => 'certificates/signed/x.pdf']);
        $this->assertSame('signed', $cert->fresh()->status);
    }

    public function test_registration_label_is_zero_padded(): void
    {
        $cert = $this->makeCertificate($this->makeResult($this->makeExam()), ['registration_number' => 123]);
        $this->assertSame('000123', $cert->registrationLabel());
    }

    public function test_deleting_certificate_removes_its_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('certificates/generated/a.pdf', 'x');
        Storage::disk('local')->put('certificates/signed/a.png', 'y');

        $cert = $this->makeCertificate($this->makeResult($this->makeExam()), [
            'generated_path' => 'certificates/generated/a.pdf',
            'signed_path'    => 'certificates/signed/a.png',
        ]);
        $cert->delete();

        Storage::disk('local')->assertMissing(['certificates/generated/a.pdf', 'certificates/signed/a.png']);
    }

    public function test_deleting_exam_result_deletes_certificate_and_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('certificates/generated/b.pdf', 'x');

        $result = $this->makeResult($this->makeExam());
        $this->makeCertificate($result, ['generated_path' => 'certificates/generated/b.pdf']);
        $result->delete();

        $this->assertSame(0, Certificate::count());
        Storage::disk('local')->assertMissing('certificates/generated/b.pdf');
    }

    public function test_setting_current_is_a_single_row(): void
    {
        $this->assertSame(CertificateSetting::current()->id, CertificateSetting::current()->id);
        $this->assertSame(1, CertificateSetting::count());
    }
}
