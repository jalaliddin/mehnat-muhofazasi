<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\ExamResult;
use App\Models\ExamSession;
use App\Models\ExamSessionQuestion;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamSessionController extends Controller
{
    public function show($token)
    {
        $session = $this->findSessionAndExpire($token);

        $session->load('periodicExam.examType', 'employee', 'examResult.certificate');

        return response()->json([
            'status'           => $session->status,
            'employee_name'    => $session->employee->full_name,
            'exam_title'       => $session->periodicExam->title,
            'exam_type_name'   => $session->periodicExam->examType->name,
            'duration_minutes' => $session->duration_minutes,
            'total_questions'  => $session->sessionQuestions()->count() ?: $session->periodicExam->examType->questionTotal(),
            'score_percent'    => $session->score_percent,
            'started_at'       => $session->started_at,
            'certificate'      => $session->examResult?->certificate?->publicSummary(),
        ]);
    }

    public function start($token)
    {
        $session = $this->findSessionAndExpire($token);

        if ($session->status !== 'pending') {
            abort(422, 'Test allaqachon boshlangan yoki yakunlangan');
        }

        // Tarkibdagi har bir bazadan belgilangan sondagi savol tasodifiy olinadi (kamroq qolgan bo'lsa — borining hammasi).
        $questions = $session->periodicExam->examType->questionBanks()->get()->flatMap(
            fn ($bank) => $bank->questions()->inRandomOrder()->limit($bank->pivot->question_count)->get()
        );

        if ($questions->isEmpty()) {
            abort(422, 'Bu test turi uchun savollar topilmadi');
        }

        $shuffledQuestions = $questions->shuffle()->values();
        $now = now();
        $rows = [];

        foreach ($shuffledQuestions as $order => $question) {
            $shuffledOptions = collect($question->options)->shuffle()->values()->all();

            $rows[] = [
                'exam_session_id'  => $session->id,
                'question_id'      => $question->id,
                'question_order'   => $order + 1,
                'shuffled_options' => json_encode($shuffledOptions),
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        $totalPoints = (int) $questions->sum('points');

        DB::transaction(function () use ($rows, $session, $now, $totalPoints) {
            ExamSessionQuestion::insert($rows);
            $session->update(['status' => 'in_progress', 'started_at' => $now, 'total_points' => $totalPoints]);
        });

        $sessionQuestions = $session->sessionQuestions()->with('question')->get()->map(function ($sq) {
            return [
                'exam_session_question_id' => $sq->id,
                'question_order'           => $sq->question_order,
                'question_text'            => $sq->question->question_text,
                'options'                  => $sq->shuffled_options,
            ];
        });

        return response()->json([
            'started_at'        => $session->started_at,
            'duration_minutes'  => $session->duration_minutes,
            'remaining_seconds' => $this->remainingSeconds($session),
            'questions'         => $sessionQuestions,
        ]);
    }

    public function submit(Request $request, $token, CertificateService $certificates)
    {
        $session = ExamSession::where('token', $token)->firstOrFail();

        if ($session->status !== 'in_progress') {
            abort(422, 'Test boshlanmagan yoki allaqachon yakunlangan');
        }

        $data = $request->validate([
            'answers'                            => 'required|array',
            'answers.*.exam_session_question_id' => 'required|integer',
            'answers.*.selected_option'           => 'required|string|size:1',
        ]);

        $answersByQuestionId = collect($data['answers'])->keyBy('exam_session_question_id');

        $sessionQuestions = $session->sessionQuestions()->with('question')->get();
        $earnedPoints = 0;
        $results = [];

        foreach ($sessionQuestions as $sq) {
            $answer = $answersByQuestionId->get($sq->id);
            $selected = $answer['selected_option'] ?? null;
            $isCorrect = $selected !== null && $selected === $sq->question->correct_option;
            $pointsEarned = $isCorrect ? $sq->question->points : 0;

            $sq->update([
                'selected_option' => $selected,
                'is_correct'      => $isCorrect,
                'points_earned'   => $pointsEarned,
            ]);

            $earnedPoints += $pointsEarned;
            $results[] = [
                'question_order' => $sq->question_order,
                'is_correct'     => $isCorrect,
                'correct_option' => $sq->question->correct_option,
            ];
        }

        $totalPoints = max($session->total_points, 1);
        $scorePercent = round(($earnedPoints / $totalPoints) * 100, 2);
        $passingScore = $session->periodicExam->examType->passing_score;
        $grade = ExamResult::gradeForScore((int) $scorePercent, $passingScore);
        $statusData = ExamResult::applyGradeStatus(['grade' => $grade]);

        $examResult = ExamResult::create([
            'periodic_exam_id' => $session->periodic_exam_id,
            'employee_id'      => $session->employee_id,
            'grade'            => $grade,
            'score'            => (int) $scorePercent,
            'notes'            => 'Onlayn test orqali topshirildi',
            'is_passed'        => $statusData['is_passed'],
            'retake_required'  => $statusData['retake_required'],
        ]);

        $session->update([
            'status'        => 'completed',
            'finished_at'   => now(),
            'earned_points' => $earnedPoints,
            'score_percent' => $scorePercent,
            'exam_result_id' => $examResult->id,
        ]);

        $certificate = null;

        if ($examResult->is_passed) {
            try {
                $certificate = $certificates->issue($examResult);
            } catch (\Throwable $e) {
                // Sertifikat yaratilmasa ham test natijasi saqlanib qolishi kerak.
                report($e);
            }
        }

        return response()->json([
            'score_percent' => $scorePercent,
            'grade'         => $grade,
            'earned_points' => $earnedPoints,
            'total_points'  => $session->total_points,
            'results'       => $results,
            'certificate'   => $certificate?->publicSummary(),
        ]);
    }

    /**
     * Javob har tanlovda saqlanadi — Monitoring jarayonni real vaqtda ko'rishi va sahifa yangilanganda
     * javoblar tiklanishi uchun. Baholash hozirgidek submit'da.
     */
    public function answer(Request $request, $token)
    {
        // Faqat o'qiladi — holat o'zgartirilmaydi: kechikkan avtosaqlash (mijoz soati farqi yoki oxirgi
        // soniyadagi bosish) sessiyani "expired" qilib qo'ysa, undan keyin kelgan avtomatik topshirish
        // rad etilib, test butunlay yo'qolardi.
        $session = ExamSession::where('token', $token)->firstOrFail();

        if ($session->status !== 'in_progress' || $this->remainingSeconds($session) <= 0) {
            abort(422, 'Test boshlanmagan yoki yakunlangan');
        }

        $data = $request->validate([
            'exam_session_question_id' => 'required|integer',
            'selected_option'          => 'required|string|size:1',
        ]);

        $sessionQuestion = $session->sessionQuestions()->whereKey($data['exam_session_question_id'])->first();
        if (! $sessionQuestion) {
            abort(422, 'Savol bu testga tegishli emas');
        }

        if (! in_array($data['selected_option'], array_column($sessionQuestion->shuffled_options, 'key'), true)) {
            abort(422, "Bunday variant yo'q");
        }

        $sessionQuestion->update(['selected_option' => $data['selected_option'], 'answered_at' => now()]);

        return response()->json(['saved' => true]);
    }

    /**
     * Boshlangan testni davom ettirish (sahifa yangilansa yoki qayta ochilsa): savollar va saqlangan javoblar.
     */
    public function questions($token)
    {
        $session = $this->findSessionAndExpire($token);

        if ($session->status !== 'in_progress') {
            abort(422, 'Test boshlanmagan yoki yakunlangan');
        }

        return response()->json([
            'started_at'        => $session->started_at,
            'duration_minutes'  => $session->duration_minutes,
            'remaining_seconds' => $this->remainingSeconds($session),
            'questions'         => $session->sessionQuestions()->with('question')->get()->map(fn ($sq) => [
                'exam_session_question_id' => $sq->id,
                'question_order'           => $sq->question_order,
                'question_text'            => $sq->question->question_text,
                'options'                  => $sq->shuffled_options,
                'selected_option'          => $sq->selected_option,
            ]),
        ]);
    }

    /**
     * Qolgan vaqt server soatidan — mijoz taymeri shundan hisoblanadi (kompyuter soati noto'g'ri bo'lsa ham).
     */
    private function remainingSeconds(ExamSession $session): int
    {
        if (! $session->started_at) {
            return 0;
        }

        $deadline = $session->started_at->copy()->addMinutes($session->duration_minutes);

        return max(0, $deadline->getTimestamp() - now()->getTimestamp());
    }

    private function findSessionAndExpire(string $token): ExamSession
    {
        $session = ExamSession::where('token', $token)->firstOrFail();

        if ($session->status === 'in_progress' && $session->started_at) {
            $deadline = $session->started_at->copy()->addMinutes($session->duration_minutes);
            if (now()->greaterThan($deadline)) {
                $session->update(['status' => 'expired']);
            }
        }

        return $session;
    }
}
