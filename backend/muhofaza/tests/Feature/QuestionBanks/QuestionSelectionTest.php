<?php

namespace Tests\Feature\QuestionBanks;

use App\Models\ExamSessionQuestion;
use App\Models\PeriodicExam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class QuestionSelectionTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    /**
     * @param  list<array{0:int,1:int,2:int}>  $banks  [bazadagi savollar, testga olinadi, har savol bali]
     */
    private function examWith(array $banks): PeriodicExam
    {
        $exam = $this->makeExam();
        $exam->examType->questionBanks()->detach();

        foreach ($banks as [$questions, $take, $points]) {
            $exam->examType->questionBanks()->attach($this->makeBank(null, $questions, $points)->id, ['question_count' => $take]);
        }

        return $exam->fresh();
    }

    public function test_start_draws_configured_count_from_each_bank(): void
    {
        $exam = $this->examWith([[10, 3, 1], [10, 2, 5]]);
        $session = $this->makeSession($exam, $this->makeEmployee($exam));

        $questions = $this->postJson("/api/public/exam-sessions/{$session->token}/start")->assertOk()->json('questions');

        $this->assertCount(5, $questions);
        $perBank = ExamSessionQuestion::where('exam_session_id', $session->id)->with('question')->get()
            ->countBy(fn ($sq) => $sq->question->question_bank_id);
        $this->assertSame([2, 3], $perBank->values()->sort()->values()->all());
        $this->assertSame(3 * 1 + 2 * 5, $session->fresh()->total_points);
    }

    public function test_start_overwrites_total_points_of_session_created_before_deploy(): void
    {
        $exam = $this->examWith([[4, 2, 1]]);
        $session = $this->makeSession($exam, $this->makeEmployee($exam));
        $session->update(['total_points' => 99]);

        $this->postJson("/api/public/exam-sessions/{$session->token}/start")->assertOk();

        $this->assertSame(2, $session->fresh()->total_points);
    }

    public function test_start_takes_all_remaining_when_bank_shrank(): void
    {
        $exam = $this->examWith([[5, 4, 1]]);
        $session = $this->makeSession($exam, $this->makeEmployee($exam));
        $exam->examType->questionBanks()->first()->questions()->limit(3)->get()->each->delete();

        $this->assertCount(2, $this->postJson("/api/public/exam-sessions/{$session->token}/start")->assertOk()->json('questions'));
    }

    public function test_start_with_empty_bank_returns_422(): void
    {
        $exam = $this->examWith([[2, 2, 1]]);
        $session = $this->makeSession($exam, $this->makeEmployee($exam));
        $exam->examType->questionBanks()->first()->questions()->delete();

        $this->postJson("/api/public/exam-sessions/{$session->token}/start")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bu test turi uchun savollar topilmadi');
    }

    public function test_generate_requires_composition(): void
    {
        $exam = $this->makeExam();
        $exam->examType->questionBanks()->detach();
        $exam->employees()->attach($this->makeEmployee($exam)->id);
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/exam-sessions/generate', ['periodic_exam_id' => $exam->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bu imtihon turi uchun test tarkibi belgilanmagan. Imtihon turlari sahifasida "Test tarkibi"ni to\'ldiring.');
    }

    public function test_generate_rejects_bank_with_too_few_questions(): void
    {
        $exam = $this->examWith([[8, 10, 1]]);
        $exam->examType->questionBanks()->first()->update(['name' => 'Elektr xavfsizligi']);
        $exam->employees()->attach($this->makeEmployee($exam)->id);
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/exam-sessions/generate', ['periodic_exam_id' => $exam->id])
            ->assertStatus(422)
            ->assertJsonPath('message', '«Elektr xavfsizligi» bazasida savollar yetarli emas: 8 ta bor, 10 ta kerak.');
    }

    public function test_generate_creates_sessions_with_zero_total_points(): void
    {
        $exam = $this->examWith([[4, 2, 3]]);
        $employee = $this->makeEmployee($exam);
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/exam-sessions/generate', ['periodic_exam_id' => $exam->id, 'employee_ids' => [$employee->id]])
            ->assertOk()
            ->assertJsonPath('0.total_points', 0);
    }

    public function test_show_reports_composition_total_before_start(): void
    {
        $exam = $this->examWith([[10, 3, 1], [10, 2, 1]]);
        $session = $this->makeSession($exam, $this->makeEmployee($exam));

        $this->getJson("/api/public/exam-sessions/{$session->token}")->assertOk()->assertJsonPath('total_questions', 5);
    }

    public function test_exam_types_index_returns_question_total(): void
    {
        $exam = $this->examWith([[10, 3, 1], [10, 2, 1]]);
        Sanctum::actingAs($this->makeUser());

        $row = collect($this->getJson('/api/exam-types')->assertOk()->json())->firstWhere('id', $exam->exam_type_id);

        $this->assertSame(5, $row['question_total']);
    }
}
