<?php

namespace Tests\Feature\QuestionBanks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class CompositionApiTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    public function test_put_replaces_composition_and_returns_available(): void
    {
        $type = $this->makeExam()->examType;
        $big = $this->makeBank('Elektr xavfsizligi', 10);
        $small = $this->makeBank("Yong'in xavfsizligi", 3);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/exam-types/{$type->id}/question-banks", ['banks' => [
            ['question_bank_id' => $big->id, 'question_count' => 4],
            ['question_bank_id' => $small->id, 'question_count' => 5],
        ]])->assertOk()->assertExactJson([
            ['question_bank_id' => $big->id, 'name' => 'Elektr xavfsizligi', 'question_count' => 4, 'available' => 10],
            ['question_bank_id' => $small->id, 'name' => "Yong'in xavfsizligi", 'question_count' => 5, 'available' => 3],
        ]);

        $this->assertSame(9, $type->fresh()->questionTotal());
        $this->getJson("/api/exam-types/{$type->id}/question-banks")->assertOk()->assertJsonCount(2);
    }

    public function test_put_rejects_duplicate_bank(): void
    {
        $type = $this->makeExam()->examType;
        $bank = $this->makeBank(null, 5);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/exam-types/{$type->id}/question-banks", ['banks' => [
            ['question_bank_id' => $bank->id, 'question_count' => 1],
            ['question_bank_id' => $bank->id, 'question_count' => 2],
        ]])->assertStatus(422)->assertJsonValidationErrors('banks.1.question_bank_id');
    }

    public function test_put_rejects_zero_count(): void
    {
        $type = $this->makeExam()->examType;
        $bank = $this->makeBank(null, 5);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/exam-types/{$type->id}/question-banks", ['banks' => [
            ['question_bank_id' => $bank->id, 'question_count' => 0],
        ]])->assertStatus(422)->assertJsonValidationErrors('banks.0.question_count');
    }

    public function test_put_empty_list_clears_composition(): void
    {
        $type = $this->makeExam()->examType;
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/exam-types/{$type->id}/question-banks", ['banks' => []])->assertOk()->assertExactJson([]);

        $this->assertSame(0, $type->fresh()->questionTotal());
    }

    public function test_saving_one_type_leaves_other_type_using_same_bank(): void
    {
        $first = $this->makeExam()->examType;
        $second = $this->makeExam()->examType;
        $shared = $this->makeBank(null, 5);
        $first->questionBanks()->attach($shared->id, ['question_count' => 1]);
        $second->questionBanks()->attach($shared->id, ['question_count' => 2]);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/exam-types/{$first->id}/question-banks", ['banks' => []])->assertOk();

        $this->assertSame(1, DB::table('exam_type_question_bank')
            ->where('exam_type_id', $second->id)->where('question_bank_id', $shared->id)->count());
    }

    public function test_operator_reads_but_cannot_update(): void
    {
        $type = $this->makeExam()->examType;
        Sanctum::actingAs($this->makeUser('operator', $this->makeOrganization()));

        $this->getJson("/api/exam-types/{$type->id}/question-banks")->assertOk()->assertJsonCount(1);
        $this->putJson("/api/exam-types/{$type->id}/question-banks", ['banks' => []])->assertForbidden();
    }
}
