<?php

namespace Tests\Feature\QuestionBanks;

use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class QuestionBankApiTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    public function test_admin_crud_bank(): void
    {
        $exam = $this->makeExam();
        Sanctum::actingAs($this->makeUser('admin'));

        $id = $this->postJson('/api/question-banks', ['name' => 'Elektr xavfsizligi', 'description' => 'Elektr'])
            ->assertCreated()->json('id');
        $this->postJson('/api/question-banks', ['name' => 'Elektr xavfsizligi'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->putJson("/api/question-banks/{$id}", ['name' => "Elektr xavfsizligi (yangi)"])
            ->assertOk()->assertJsonPath('name', 'Elektr xavfsizligi (yangi)');

        $used = $exam->examType->questionBanks()->first();
        $this->getJson("/api/question-banks/{$used->id}")
            ->assertOk()
            ->assertJsonPath('questions_count', 2)
            ->assertJsonPath('exam_types.0.name', 'Mehnat muhofazasi')
            ->assertJsonPath('exam_types.0.question_count', 2);

        $names = collect($this->getJson('/api/question-banks')->assertOk()->json())->pluck('name');
        $this->assertTrue($names->contains('Elektr xavfsizligi (yangi)'));
    }

    public function test_delete_bank_in_use_returns_422(): void
    {
        $bank = $this->makeExam()->examType->questionBanks()->first();
        Sanctum::actingAs($this->makeUser('admin'));

        $this->deleteJson("/api/question-banks/{$bank->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Baza testlarda ishlatilmoqda: Mehnat muhofazasi');
        $this->assertNotNull($bank->fresh());
    }

    public function test_delete_unused_bank_deletes_its_questions(): void
    {
        $bank = $this->makeBank(null, 3);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->deleteJson("/api/question-banks/{$bank->id}")->assertOk();

        $this->assertNull($bank->fresh());
        $this->assertSame(0, Question::where('question_bank_id', $bank->id)->count());
    }

    public function test_operator_can_read_but_not_write(): void
    {
        $bank = $this->makeBank(null, 1);
        $question = $bank->questions()->first();
        Sanctum::actingAs($this->makeUser('operator', $this->makeOrganization()));

        $this->getJson('/api/question-banks')->assertOk();
        $this->getJson("/api/question-banks/{$bank->id}")->assertOk();
        $this->getJson("/api/questions?question_bank_id={$bank->id}")->assertOk();

        $this->postJson('/api/question-banks', ['name' => 'X'])->assertForbidden();
        $this->putJson("/api/question-banks/{$bank->id}", ['name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/question-banks/{$bank->id}")->assertForbidden();
        $this->postJson('/api/questions', $this->questionPayload($bank))->assertForbidden();
        $this->putJson("/api/questions/{$question->id}", $this->questionPayload($bank))->assertForbidden();
        $this->deleteJson("/api/questions/{$question->id}")->assertForbidden();
        $this->postJson('/api/questions/import', ['question_bank_id' => $bank->id])->assertForbidden();
        $this->getJson('/api/questions/template')->assertForbidden();
    }

    public function test_questions_index_filters_by_bank(): void
    {
        $first = $this->makeBank(null, 2);
        $this->makeBank(null, 3);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->getJson("/api/questions?question_bank_id={$first->id}")->assertOk()->assertJsonCount(2);
    }

    public function test_store_question_into_bank(): void
    {
        $bank = $this->makeBank(null, 0);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->postJson('/api/questions', $this->questionPayload($bank))
            ->assertCreated()
            ->assertJsonPath('question_bank_id', $bank->id);
    }

    public function test_import_writes_into_bank(): void
    {
        $bank = $this->makeBank(null, 0);
        Sanctum::actingAs($this->makeUser('admin'));

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['savol', 'variant_a', 'variant_b', 'variant_c', 'variant_d', 'togri_javob', 'ball'],
            ['Birinchi savol?', 'Ha', "Yo'q", null, null, 'A', 1],
            ['Ikkinchi savol?', 'Ha', "Yo'q", 'Bilmayman', null, 'B', 2],
        ], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'q').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $file = new UploadedFile($path, 'savollar.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->post('/api/questions/import', ['question_bank_id' => $bank->id, 'file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported', 2);

        $this->assertSame(2, $bank->questions()->count());
    }

    private function questionPayload(QuestionBank $bank): array
    {
        return [
            'question_bank_id' => $bank->id,
            'question_text'    => 'Yangi savol?',
            'options'          => [['key' => 'A', 'text' => 'Ha'], ['key' => 'B', 'text' => "Yo'q"]],
            'correct_option'   => 'A',
        ];
    }
}
