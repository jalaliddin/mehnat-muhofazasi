<?php

namespace Tests\Feature\Audit;

use App\Models\ExamResult;
use App\Models\ExamSession;
use App\Models\ExamSessionQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class AnswerAutosaveTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * @return array{0: ExamSession, 1: list<int>}
     */
    private function started(): array
    {
        $exam = $this->makeExam();
        $session = $this->makeSession($exam, $this->makeEmployee($exam));
        $ids = collect($this->postJson("/api/public/exam-sessions/{$session->token}/start")->assertOk()->json('questions'))
            ->pluck('exam_session_question_id')->all();

        return [$session->fresh(), $ids];
    }

    private function answer(ExamSession $session, int $questionId, string $option)
    {
        return $this->putJson("/api/public/exam-sessions/{$session->token}/answer", [
            'exam_session_question_id' => $questionId,
            'selected_option'          => $option,
        ]);
    }

    public function test_answer_is_saved_with_timestamp(): void
    {
        [$session, $ids] = $this->started();

        $this->answer($session, $ids[0], 'A')->assertOk()->assertJson(['saved' => true]);

        $row = ExamSessionQuestion::find($ids[0]);
        $this->assertSame('A', $row->selected_option);
        $this->assertNotNull($row->answered_at);
    }

    public function test_changing_answer_keeps_last(): void
    {
        [$session, $ids] = $this->started();

        $this->answer($session, $ids[0], 'A')->assertOk();
        $this->answer($session, $ids[0], 'B')->assertOk();

        $this->assertSame('B', ExamSessionQuestion::find($ids[0])->selected_option);
    }

    public function test_answer_on_pending_or_completed_session_returns_422(): void
    {
        $exam = $this->makeExam();
        $pending = $this->makeSession($exam, $this->makeEmployee($exam));
        $this->putJson("/api/public/exam-sessions/{$pending->token}/answer", ['exam_session_question_id' => 1, 'selected_option' => 'A'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Test boshlanmagan yoki yakunlangan');

        [$session, $ids] = $this->started();
        $this->postJson("/api/public/exam-sessions/{$session->token}/submit", ['answers' => [
            ['exam_session_question_id' => $ids[0], 'selected_option' => 'A'],
            ['exam_session_question_id' => $ids[1], 'selected_option' => 'A'],
        ]])->assertOk();
        $score = ExamResult::where('employee_id', $session->employee_id)->value('score');

        $this->answer($session, $ids[0], 'B')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Test boshlanmagan yoki yakunlangan');

        $this->assertSame('A', ExamSessionQuestion::find($ids[0])->selected_option);
        $this->assertSame($score, ExamResult::where('employee_id', $session->employee_id)->value('score'));
    }

    public function test_answer_after_deadline_returns_422_and_expires_session(): void
    {
        [$session, $ids] = $this->started();
        Carbon::setTestNow(now()->addMinutes(31));

        $this->answer($session, $ids[0], 'A')->assertStatus(422);

        $this->assertSame('expired', $session->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_answer_rejects_foreign_question_and_unknown_option(): void
    {
        [$session, $ids] = $this->started();
        [, $otherIds] = $this->started();

        $this->answer($session, $otherIds[0], 'A')->assertStatus(422);
        $this->answer($session, $ids[0], 'Z')->assertStatus(422);

        $this->assertNull(ExamSessionQuestion::find($ids[0])->selected_option);
    }

    public function test_questions_endpoint_returns_saved_answers_in_progress(): void
    {
        [$session, $ids] = $this->started();
        $this->answer($session, $ids[0], 'A')->assertOk();

        $res = $this->getJson("/api/public/exam-sessions/{$session->token}/questions")->assertOk();
        $this->assertNotNull($res->json('started_at'));
        $this->assertSame(30, $res->json('duration_minutes'));
        $saved = collect($res->json('questions'))->firstWhere('exam_session_question_id', $ids[0]);
        $this->assertSame('A', $saved['selected_option']);

        $exam = $this->makeExam();
        $pending = $this->makeSession($exam, $this->makeEmployee($exam));
        $this->getJson("/api/public/exam-sessions/{$pending->token}/questions")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Test boshlanmagan yoki yakunlangan');
    }
}
