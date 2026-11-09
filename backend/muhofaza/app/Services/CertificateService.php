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
     * Natija uchun sertifikat yaratadi yoki (imzolanmagan bo'lsa) ma'lumotini yangilaydi.
     * Yangi sertifikat raqamsiz va PDF'siz bo'ladi — raqamni admin kiritadi (setNumber), PDF shunda chiziladi.
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
            if ($certificate->data == $data && $this->hasFreshPdf($certificate)) {
                return $certificate;
            }

            $certificate->update(['data' => $data]);
        }

        if ($certificate->number) {
            $this->renderPdf($certificate);
        }

        return $certificate;
    }

    /**
     * Sertifikat raqamini qo'lda belgilaydi va PDF ni shu raqam bilan chizadi.
     */
    public function setNumber(Certificate $certificate, string $number): Certificate
    {
        $number = trim($number);

        if ($certificate->signed_path) {
            throw new CertificateException("Imzolangan sertifikat raqamini o'zgartirib bo'lmaydi");
        }

        if (Certificate::where('number', $number)->where('id', '!=', $certificate->id)->exists()) {
            throw new CertificateException('Bu raqam boshqa sertifikatga berilgan');
        }

        try {
            $certificate->update(['number' => $number]);
        } catch (UniqueConstraintViolationException) {
            throw new CertificateException('Bu raqam boshqa sertifikatga berilgan');
        }

        $this->refreshData($certificate);
        $this->renderPdf($certificate);

        return $certificate;
    }

    /**
     * Bir nechta sertifikatga raqamni bitta tranzaksiyada beradi. Avval hammasining raqami bo'shatiladi —
     * shunda o'zaro almashtirish (A↔B) unique indeksga urilmaydi. PDF'lar tranzaksiyadan keyin chiziladi.
     *
     * @param  list<array{certificate: Certificate, number: string}>  $items
     */
    public function setNumbers(array $items): void
    {
        foreach ($items as $item) {
            if ($item['certificate']->signed_path) {
                throw new CertificateException("Imzolangan sertifikat raqamini o'zgartirib bo'lmaydi");
            }
        }

        try {
            DB::transaction(function () use ($items) {
                $ids = array_map(fn ($item) => $item['certificate']->id, $items);
                Certificate::whereIn('id', $ids)->update(['number' => null]);

                // Model "o'zgarmagan" deb UPDATE'ni o'tkazib yubormasligi uchun to'g'ridan-to'g'ri so'rov bilan yoziladi
                // (bazada raqam hozirgina bo'shatildi, modelda esa eski qiymat turibdi).
                foreach ($items as $item) {
                    $number = trim($item['number']);
                    Certificate::whereKey($item['certificate']->id)->update(['number' => $number]);
                    $item['certificate']->number = $number;
                    $item['certificate']->syncOriginalAttribute('number');
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw new CertificateException('Bu raqam boshqa sertifikatga berilgan');
        }

        foreach ($items as $item) {
            $this->refreshData($item['certificate']);
            $this->renderPdf($item['certificate']);
        }
    }

    /**
     * Raqam berilayotganda PDF davriy imtihonning hozirgi sozlamalari (sertifikat turi, kurs sanalari va h.k.)
     * bilan chizilishi uchun ma'lumot surati yangilanadi — sertifikat test paytida, tur tanlanmasdan oldin
     * yaratilgan bo'lishi mumkin. Faqat imzolanmagan sertifikatlar uchun chaqiriladi.
     */
    private function refreshData(Certificate $certificate): void
    {
        $certificate->update(['data' => $this->buildData($certificate->examResult()->firstOrFail())]);
    }

    private function hasFreshPdf(Certificate $certificate): bool
    {
        return $certificate->generated_path && Storage::disk('local')->exists($certificate->generated_path);
    }

    /**
     * PDF raqam bo'yicha emas, ID bo'yicha saqlanadi — qo'lda kiritilgan raqamda "/" yoki bo'sh joy bo'lishi mumkin.
     */
    private function renderPdf(Certificate $certificate): void
    {
        $path = "certificates/generated/{$certificate->id}.pdf";
        Storage::disk('local')->put($path, $this->pdf->render([$certificate]));

        if ($certificate->generated_path && $certificate->generated_path !== $path) {
            Storage::disk('local')->delete($certificate->generated_path);
        }

        $certificate->update(['generated_path' => $path]);
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
        $name = "{$certificate->id}-".Str::random(8).".{$extension}";
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

    /**
     * Yangi sertifikat: faqat ro'yxatdan o'tish raqami avtomatik beriladi (№ ni admin qo'lda kiritadi).
     */
    private function createWithNumbers(ExamResult $result, array $data): Certificate
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($result, $data) {
                    $registration = (int) Certificate::lockForUpdate()->max('registration_number') + 1;

                    return Certificate::create([
                        'exam_result_id'      => $result->id,
                        'employee_id'         => $result->employee_id,
                        'periodic_exam_id'    => $result->periodic_exam_id,
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
