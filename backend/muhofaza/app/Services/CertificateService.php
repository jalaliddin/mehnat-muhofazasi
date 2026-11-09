<?php

namespace App\Services;

use App\Exceptions\CertificateException;
use App\Models\Certificate;
use App\Models\CertificateSetting;
use App\Models\ExamResult;
use App\Models\PeriodicExam;
use App\Models\User;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CertificateService
{
    use DetectsConcurrencyErrors;

    private const ALLOCATION_ATTEMPTS = 3;

    public function __construct(private CertificatePdf $pdf)
    {
    }

    /**
     * Natija uchun sertifikat yaratadi yoki (imzolanmagan bo'lsa) ma'lumot va PDF ni yangilaydi.
     * Raqam, ro'yxat raqami va public_code qayta yaratishda o'zgarmaydi.
     */
    public function issue(ExamResult $result): Certificate
    {
        if (! $result->is_passed) {
            throw new CertificateException('Natija qoniqarsiz — sertifikat berilmaydi');
        }

        $certificate = Certificate::where('exam_result_id', $result->id)->first();

        if ($certificate?->signed_path) {
            throw new CertificateException("Imzolangan sertifikatni qayta yaratib bo'lmaydi");
        }

        $data = $this->buildData($result);

        if (! $certificate) {
            // Parallel so'rov shu natijaga sertifikatni allaqachon yaratgan bo'lsa, o'shani qaytaradi.
            $certificate = $this->createWithNumbers($result, $data);

            if ($certificate->signed_path) {
                throw new CertificateException("Imzolangan sertifikatni qayta yaratib bo'lmaydi");
            }
        }

        if (! $certificate->wasRecentlyCreated) {
            // Ma'lumot o'zgarmagan va PDF joyida bo'lsa, qayta chizish shart emas (ommaviy yaratish tez bo'ladi).
            if ($certificate->data == $data && $certificate->generated_path
                && Storage::disk('local')->exists($certificate->generated_path)) {
                return $certificate;
            }

            $certificate->update(['data' => $data]);
        }

        $path = "certificates/generated/{$certificate->number}.pdf";
        Storage::disk('local')->put($path, $this->pdf->render([$certificate]));
        $certificate->update(['generated_path' => $path]);

        return $certificate;
    }

    public function buildData(ExamResult $result): array
    {
        $result->loadMissing('employee', 'periodicExam.examType', 'periodicExam.certificateType');
        $exam = $result->periodicExam;
        $type = $exam->examType;
        $certificateType = $exam->certificateType;
        $settings = CertificateSetting::current();

        return [
            'full_name'             => $result->employee->full_name,
            'institution_name'      => $settings->institution_name,
            'program_name'          => $type->program_name ?: $type->name,
            'training_hours'        => $certificateType?->hours,
            'course_start_date'     => $exam->course_start_date?->toDateString(),
            'course_end_date'       => $exam->course_end_date?->toDateString(),
            'commission_chair_name' => $settings->commission_chair_name,
            'director_name'         => $settings->director_name,
            'inspector_name'        => $settings->inspector_name,
            'city'                  => $settings->city,
            'issue_date'            => $result->created_at->toDateString(),
            'certificate_type_name' => $certificateType?->name,
            'title_text'            => $certificateType?->title_text,
            'course_text'           => $certificateType?->course_text,
        ];
    }

    public function storeSigned(Certificate $certificate, UploadedFile $file, User $by): Certificate
    {
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $name = "{$certificate->number}-".Str::random(8).".{$extension}";
        $path = $file->storeAs('certificates/signed', $name, 'local');

        if ($certificate->signed_path && $certificate->signed_path !== $path) {
            Storage::disk('local')->delete($certificate->signed_path);
        }

        $certificate->update([
            'signed_path' => $path,
            'signed_at'   => now(),
            'signed_by'   => $by->id,
        ]);

        return $certificate;
    }

    /**
     * @return list<string>
     */
    public function missingFieldWarnings(PeriodicExam $exam): array
    {
        $exam->loadMissing('examType');
        $warnings = [];

        if (! $exam->course_start_date || ! $exam->course_end_date) {
            $warnings[] = 'Kurs sanalari kiritilmagan';
        }

        if (! $exam->certificate_type_id) {
            $warnings[] = 'Sertifikat turi tanlanmagan';
        }

        $settings = CertificateSetting::current();
        foreach (CertificateSetting::FIELDS as $field) {
            if (blank($settings->{$field})) {
                $warnings[] = "Sertifikat sozlamalari to'ldirilmagan";
                break;
            }
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (config('app.env') === 'production' && in_array($host, [null, 'localhost', '127.0.0.1', '::1'], true)) {
            $warnings[] = "APP_URL localhost'ga qaragan — QR kod ishlamaydi. Serverda APP_URL ni sayt domeniga sozlang";
        }

        return $warnings;
    }

    private function createWithNumbers(ExamResult $result, array $data): Certificate
    {
        $year = $result->created_at->year;

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($result, $data, $year) {
                    $sequence = (int) Certificate::where('year', $year)->lockForUpdate()->max('sequence') + 1;
                    $registration = (int) Certificate::lockForUpdate()->max('registration_number') + 1;

                    return Certificate::create([
                        'exam_result_id'      => $result->id,
                        'employee_id'         => $result->employee_id,
                        'periodic_exam_id'    => $result->periodic_exam_id,
                        'year'                => $year,
                        'sequence'            => $sequence,
                        'number'              => sprintf('%d-%04d', $year, $sequence),
                        'registration_number' => $registration,
                        'public_code'         => Str::random(32),
                        'data'                => $data,
                    ]);
                });
            } catch (Throwable $e) {
                // Raqam uchun poyga: MySQL odatda deadlock (40001) beradi, ba'zan unique buzilishi.
                if (! $e instanceof UniqueConstraintViolationException && ! $this->causedByConcurrencyError($e)) {
                    throw $e;
                }

                $existing = Certificate::where('exam_result_id', $result->id)->first();
                if ($existing) {
                    return $existing;
                }

                if ($attempt >= self::ALLOCATION_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }
}
