<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\ExamResult;
use App\Models\ExamSession;
use App\Models\ExamSessionQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamSessionController extends Controller
{
    public function show($token)
    {
        $session = $this->findSessionAndExpire($token);

        $session->load('periodicExam.examType', 'employee');

        return response()->json([
            'status'           => $session->status,
            'employee_name'    => $session->employee->full_name,
            'exam_title'       => $session->periodicExam->title,
            'exam_type_name'   => $session->periodicExam->examType->name,
            'duration_minutes' => $session->duration_minutes,
            'total_questions'  => $session->sessionQuestions()->count() ?: $session->periodicExam->examType->questions()->count(),
            'score_percent'    => $session->score_percent,
            'started_at'       => $session->started_at,
        ]);
    }

    public function start($token)
    {
        $session = $this->findSessionAndExpire($token);

        if ($session->status !== 'pending') {
            abort(422, 'Test allaqachon boshlangan yoki yakunlangan');
        }

        $questions = $session->periodicExam->examType->questions()->get();

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

        DB::transaction(function () use ($rows, $session, $now) {
            ExamSessionQuestion::insert($rows);
            $session->update(['status' => 'in_progress', 'started_at' => $now]);
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
            'started_at'       => $session->started_at,
            'duration_minutes' => $session->duration_minutes,
            'questions'        => $sessionQuestions,
        ]);
    }

    public function submit(Request $request, $token)
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

        return response()->json([
            'score_percent' => $scorePercent,
            'grade'         => $grade,
            'earned_points' => $earnedPoints,
            'total_points'  => $session->total_points,
            'results'       => $results,
        ]);
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
