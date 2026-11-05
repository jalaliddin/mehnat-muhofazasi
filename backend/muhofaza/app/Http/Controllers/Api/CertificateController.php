<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CertificateException;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\PeriodicExam;
use App\Models\User;
use App\Services\CertificatePdf;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function __construct(
        private CertificateService $certificates,
        private CertificatePdf $pdf,
    ) {
    }

    public function index(Request $request)
    {
        $query = Certificate::with(['employee.organization', 'periodicExam'])
            ->visibleTo($request->user());

        if ($request->filled('organization_id')) {
            $query->whereHas('periodicExam', fn ($q) => $q->where('organization_id', $request->organization_id));
        }

        if ($request->filled('periodic_exam_id')) {
            $query->where('periodic_exam_id', $request->periodic_exam_id);
        }

        if ($request->status === 'signed') {
            $query->whereNotNull('signed_path');
        } elseif ($request->status === 'pending_signature') {
            $query->whereNull('signed_path');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereHas('employee', fn ($e) => $e->where('full_name', 'like', "%{$search}%"));
            });
        }

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        return response()->json($query->orderByDesc('id')->paginate($perPage));
    }

    public function generate(Request $request)
    {
        $data = $request->validate(['periodic_exam_id' => 'required|exists:periodic_exams,id']);

        $exam = PeriodicExam::with('examType')->findOrFail($data['periodic_exam_id']);
        $this->authorizeExam($request->user(), $exam);

        $counts = ['created' => 0, 'regenerated' => 0, 'skipped_signed' => 0];

        foreach ($exam->results()->where('is_passed', true)->with('certificate')->get() as $result) {
            if ($result->certificate?->signed_path) {
                $counts['skipped_signed']++;
                continue;
            }

            $certificate = $this->certificates->issue($result);
            $counts[$certificate->wasRecentlyCreated ? 'created' : 'regenerated']++;
        }

        return response()->json($counts + [
            'warnings' => $this->certificates->missingFieldWarnings($exam),
        ]);
    }

    public function regenerate(Request $request, $id)
    {
        $certificate = Certificate::with('periodicExam', 'examResult')->findOrFail($id);
        $this->authorizeExam($request->user(), $certificate->periodicExam);

        try {
            $certificate = $this->certificates->issue($certificate->examResult);
        } catch (CertificateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($certificate->fresh(['employee.organization', 'periodicExam']));
    }

    public function print(Request $request)
    {
        $data = $request->validate(['periodic_exam_id' => 'required|exists:periodic_exams,id']);

        $exam = PeriodicExam::findOrFail($data['periodic_exam_id']);
        $this->authorizeExam($request->user(), $exam);

        $certificates = Certificate::where('periodic_exam_id', $exam->id)
            ->whereNull('signed_path')
            ->orderBy('id')
            ->get();

        if ($certificates->isEmpty()) {
            return response()->json(['message' => "Chop etish uchun imzolanmagan sertifikat yo'q"], 404);
        }

        return response($this->pdf->render($certificates), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"sertifikatlar-{$exam->id}.pdf\"",
        ]);
    }

    public function download(Request $request, $id)
    {
        $certificate = Certificate::with('periodicExam')->findOrFail($id);
        $this->authorizeExam($request->user(), $certificate->periodicExam);

        $signed = $request->query('type') === 'signed';
        $path = $signed ? $certificate->signed_path : $certificate->generated_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Fayl topilmadi'], 404);
        }

        $name = $signed
            ? "sertifikat-{$certificate->number}-imzoli.".pathinfo($path, PATHINFO_EXTENSION)
            : "sertifikat-{$certificate->number}.pdf";

        return Storage::disk('local')->download($path, $name);
    }

    public function uploadSigned(Request $request, $id)
    {
        $certificate = Certificate::with('periodicExam')->findOrFail($id);
        $this->authorizeExam($request->user(), $certificate->periodicExam);

        $data = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $this->certificates->storeSigned($certificate, $data['file'], $request->user());

        return response()->json($certificate->fresh(['employee.organization', 'periodicExam']));
    }

    public function destroy(Request $request, $id)
    {
        $certificate = Certificate::with('periodicExam')->findOrFail($id);
        $this->authorizeExam($request->user(), $certificate->periodicExam);

        $certificate->delete();

        return response()->json(['message' => "O'chirildi"]);
    }

    private function authorizeExam(User $user, PeriodicExam $exam): void
    {
        if ($user->role === 'operator' && $user->organization_id && $exam->organization_id !== $user->organization_id) {
            abort(403);
        }
    }
}
