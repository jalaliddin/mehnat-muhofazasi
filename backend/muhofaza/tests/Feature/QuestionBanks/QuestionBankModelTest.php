<?php

namespace Tests\Feature\QuestionBanks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class QuestionBankModelTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    public function test_question_total_sums_composition(): void
    {
        $type = $this->makeExam()->examType;
        $type->questionBanks()->attach($this->makeBank(null, 5)->id, ['question_count' => 4]);

        $this->assertSame(6, $type->fresh()->questionTotal());
    }

    public function test_deleting_exam_type_keeps_bank_and_questions(): void
    {
        $exam = $this->makeExam();
        $bank = $exam->examType->questionBanks()->first();

        $exam->examType->delete();

        $this->assertSame(0, DB::table('exam_type_question_bank')->count());
        $this->assertSame(2, $bank->fresh()->questions()->count());
    }
}
