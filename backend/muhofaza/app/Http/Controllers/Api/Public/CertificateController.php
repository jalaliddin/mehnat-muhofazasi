<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;

/**
 * QR kod orqali ochiladigan, loginsiz tekshirish endpoint'lari.
 * Imzolanmagan PDF bu yerdan hech qachon berilmaydi.
 */
class CertificateController extends Controller
{
    public function show(string $code)
    {
        $certificate = Certificate::where('public_code', $code)->first();

        if (! $certificate) {
            return $this->notFound();
        }

        $data = $certificate->data ?? [];

        return response()->json([
            'number'              => $certificate->number,
            'full_name'           => $data['full_name'] ?? null,
            'program_name'        => $data['program_name'] ?? null,
            'institution_name'    => $data['institution_name'] ?? null,
            'training_hours'      => $data['training_hours'] ?? null,
            'course_start_date'   => $data['course_start_date'] ?? null,
            'course_end_date'     => $data['course_end_date'] ?? null,
            'issue_date'          => $data['issue_date'] ?? null,
            'status'              => $certificate->status,
        ]);
    }

    public function download(string $code)
    {
        $certificate = Certificate::where('public_code', $code)->first();

        if (! $certificate) {
            return $this->notFound();
        }

        if (! $certificate->signed_path) {
            return response()->json(['message' => 'Sertifikat hali imzolanmagan'], 404);
        }

        if (! Storage::disk('local')->exists($certificate->signed_path)) {
            return response()->json(['message' => 'Fayl topilmadi'], 404);
        }

        $name = "sertifikat-{$certificate->fileLabel()}.".pathinfo($certificate->signed_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->response($certificate->signed_path, $name, [], 'inline');
    }

    private function notFound()
    {
        return response()->json(['message' => 'Sertifikat topilmadi'], 404);
    }
}
