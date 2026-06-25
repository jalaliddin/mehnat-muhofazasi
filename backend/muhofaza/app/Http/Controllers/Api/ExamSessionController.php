<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExamSession;
use App\Models\PeriodicExam;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExamSessionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['periodic_exam_id' => 'required|exists:periodic_exams,id']);

        $user = $request->user();
        $exam = PeriodicExam::findOrFail($request->periodic_exam_id);

        if ($user->role === 'operator' && $user->organization_id && $exam->organization_id !== $user->organization_id) {
            abort(403);
        }

        return response()->json(
            ExamSession::with('employee')
                ->where('periodic_exam_id', $request->periodic_exam_id)
                ->get()
        );
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'periodic_exam_id' => 'required|exists:periodic_exams,id',
            'employee_ids'     => 'nullable|array',
            'employee_ids.*'   => 'integer|exists:employees,id',
        ]);

        $user = $request->user();
        $exam = PeriodicExam::with('examType')->findOrFail($data['periodic_exam_id']);

        if ($user->role === 'operator' && $user->organization_id && $exam->organization_id !== $user->organization_id) {
            abort(403);
        }

        $employeeIds = $data['employee_ids'] ?? Employee::where('organization_id', $exam->organization_id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (empty($employeeIds)) {
            abort(422, 'Tashkilotda faol xodimlar topilmadi');
        }

        $totalPoints = $exam->examType->questions()->sum('points');

        if ($totalPoints === 0) {
            abort(422, 'Bu imtihon turi uchun savollar kiritilmagan. Avval "Savollar" bo\'limida savol qo\'shing.');
        }

        $sessions = [];

        foreach ($employeeIds as $employeeId) {
            $session = ExamSession::firstOrCreate(
                [
                    'periodic_exam_id' => $exam->id,
                    'employee_id'      => $employeeId,
                ],
                [
                    'token'            => Str::random(48),
                    'duration_minutes' => $exam->examType->duration_minutes,
                    'total_points'     => $totalPoints,
                ]
            );

            $sessions[] = $session;
        }

        return response()->json(
            ExamSession::with('employee')->whereIn('id', collect($sessions)->pluck('id'))->get()
        );
    }

    public function destroy($id)
    {
        $session = ExamSession::findOrFail($id);

        if ($session->status !== 'pending') {
            abort(422, 'Faqat boshlanmagan sessiyani o\'chirish mumkin');
        }

        $session->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
