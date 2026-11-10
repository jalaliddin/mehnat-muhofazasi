<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        // Havolalar faqat shu davriy imtihonga qo'shilgan (bo'limlar bo'yicha tanlangan) faol xodimlarga yaratiladi.
        $employeeIds = $data['employee_ids'] ?? $exam->employees()
            ->where('employees.is_active', true)
            ->pluck('employees.id')
            ->all();

        if (empty($employeeIds)) {
            abort(422, "Avval imtihonga xodimlarni qo'shing (bo'limlarni tanlang)");
        }

        $banks = $exam->examType->questionBanks()->withCount('questions')->get();

        if ($banks->isEmpty()) {
            abort(422, 'Bu imtihon turi uchun test tarkibi belgilanmagan. Imtihon turlari sahifasida "Test tarkibi"ni to\'ldiring.');
        }

        foreach ($banks as $bank) {
            if ($bank->questions_count < $bank->pivot->question_count) {
                abort(422, "«{$bank->name}» bazasida savollar yetarli emas: {$bank->questions_count} ta bor, {$bank->pivot->question_count} ta kerak.");
            }
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
                    // Ballar test boshlanganda, tushgan savollar bo'yicha hisoblanadi.
                    'total_points'     => 0,
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
