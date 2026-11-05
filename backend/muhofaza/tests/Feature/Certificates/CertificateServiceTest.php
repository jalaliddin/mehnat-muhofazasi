<?php

namespace Tests\Feature\Certificates;

use App\Exceptions\CertificateException;
use App\Models\CertificateSetting;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class CertificateServiceTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_numbers_restart_each_year_and_registration_continues(): void
    {
        $exam = $this->makeExam();
        $svc = app(CertificateService::class);

        $a = $svc->issue($this->makeResult($exam, 'good', null, Carbon::parse('2026-03-01')));
        $b = $svc->issue($this->makeResult($exam, 'good', null, Carbon::parse('2026-05-01')));
        $c = $svc->issue($this->makeResult($exam, 'good', null, Carbon::parse('2027-01-10')));

        $this->assertSame(['2026-0001', '2026-0002', '2027-0001'], [$a->number, $b->number, $c->number]);
        $this->assertSame([1, 2, 3], [$a->registration_number, $b->registration_number, $c->registration_number]);
        $this->assertSame(32, strlen($a->public_code));
    }

    public function test_issue_builds_data_snapshot(): void
    {
        CertificateSetting::current()->update([
            'institution_name'      => '"JAHON MEHNAT MUHOFAZASI" MCHJ',
            'commission_chair_name' => 'J. Xasanov',
            'director_name'         => 'A. Bazarova',
            'inspector_name'        => 'M. Yurev',
            'city'                  => 'Urganch sh.',
        ]);
        $exam = $this->makeExam(null, ['course_start_date' => '2026-11-01', 'course_end_date' => '2026-11-07'],
            ['program_name' => 'Mehnatni muhofaza qilish', 'training_hours' => 72]);
        $result = $this->makeResult($exam, 'good', $this->makeEmployee($exam, 'Aliyev Vali'), Carbon::parse('2026-11-07 10:00'));

        $this->assertSame([
            'full_name'             => 'Aliyev Vali',
            'institution_name'      => '"JAHON MEHNAT MUHOFAZASI" MCHJ',
            'program_name'          => 'Mehnatni muhofaza qilish',
            'training_hours'        => 72,
            'course_start_date'     => '2026-11-01',
            'course_end_date'       => '2026-11-07',
            'commission_chair_name' => 'J. Xasanov',
            'director_name'         => 'A. Bazarova',
            'inspector_name'        => 'M. Yurev',
            'city'                  => 'Urganch sh.',
            'issue_date'            => '2026-11-07',
        ], app(CertificateService::class)->issue($result)->data);
    }

    public function test_program_name_falls_back_to_exam_type_name(): void
    {
        $exam = $this->makeExam(null, [], ['name' => 'Elektr xavfsizligi', 'program_name' => null]);

        $this->assertSame('Elektr xavfsizligi', app(CertificateService::class)->buildData($this->makeResult($exam))['program_name']);
    }

    public function test_issue_writes_generated_pdf(): void
    {
        $cert = app(CertificateService::class)->issue($this->makeResult($this->makeExam()));

        $this->assertSame("certificates/generated/{$cert->number}.pdf", $cert->generated_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($cert->generated_path));
    }

    public function test_reissue_unsigned_keeps_numbers_and_refreshes_data(): void
    {
        $svc = app(CertificateService::class);
        $result = $this->makeResult($this->makeExam());

        $first = $svc->issue($result);
        CertificateSetting::current()->update(['city' => 'Xiva sh.']);
        $second = $svc->issue($result->fresh());

        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame(
            [$first->id, $first->number, $first->registration_number, $first->public_code],
            [$second->id, $second->number, $second->registration_number, $second->public_code]
        );
        $this->assertSame('Xiva sh.', $second->data['city']);
    }

    public function test_reissue_signed_throws(): void
    {
        $svc = app(CertificateService::class);
        $result = $this->makeResult($this->makeExam());
        $svc->issue($result)->update(['signed_path' => 'certificates/signed/x.pdf']);

        $this->expectExceptionObject(new CertificateException("Imzolangan sertifikatni qayta yaratib bo'lmaydi"));
        $svc->issue($result->fresh());
    }

    public function test_issue_failed_result_throws(): void
    {
        $this->expectExceptionObject(new CertificateException('Natija qoniqarsiz — sertifikat berilmaydi'));
        app(CertificateService::class)->issue($this->makeResult($this->makeExam(), 'unsatisfactory'));
    }

    public function test_store_signed_replaces_previous_file(): void
    {
        $svc = app(CertificateService::class);
        $user = $this->makeUser();
        $cert = $svc->issue($this->makeResult($this->makeExam()));

        $svc->storeSigned($cert, UploadedFile::fake()->image('scan.png'), $user);
        $old = $cert->fresh()->signed_path;
        $svc->storeSigned($cert->fresh(), UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'), $user);
        $fresh = $cert->fresh();

        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($fresh->signed_path);
        $this->assertStringStartsWith("certificates/signed/{$cert->number}-", $fresh->signed_path);
        $this->assertStringEndsWith('.pdf', $fresh->signed_path);
        $this->assertSame($user->id, $fresh->signed_by);
        $this->assertNotNull($fresh->signed_at);
    }

    public function test_missing_field_warnings(): void
    {
        $exam = $this->makeExam(null, ['course_start_date' => null], ['training_hours' => null]);

        $this->assertSame(
            ['Kurs sanalari kiritilmagan', 'Soat hajmi kiritilmagan', "Sertifikat sozlamalari to'ldirilmagan"],
            app(CertificateService::class)->missingFieldWarnings($exam)
        );
    }
}
