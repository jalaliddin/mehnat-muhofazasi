<?php

namespace Tests\Feature\QuestionBanks;

use App\Models\ExamType;
use App\Models\QuestionBank;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migratsiya DDL bajargani uchun RefreshDatabase tranzaksiyasi o'rniga DatabaseMigrations ishlatiladi.
 */
class QuestionBankMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migration = require database_path('migrations/2026_11_05_100000_create_question_banks.php');
        $this->migration->down(); // migratsiyadan oldingi sxema: questions.exam_type_id
    }

    private function oldStyleType(string $name, int $questions): int
    {
        $id = DB::table('exam_types')->insertGetId([
            'name' => $name, 'frequency_months' => 12, 'created_at' => now(), 'updated_at' => now(),
        ]);
        for ($i = 1; $i <= $questions; $i++) {
            DB::table('questions')->insert([
                'exam_type_id' => $id, 'question_text' => "{$name} savol {$i}",
                'options' => json_encode([['key' => 'A', 'text' => 'Ha'], ['key' => 'B', 'text' => "Yo'q"]]),
                'correct_option' => 'A', 'points' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }

    public function test_up_moves_questions_into_bank_named_after_exam_type(): void
    {
        $a = $this->oldStyleType('Mehnat muhofazasi', 3);
        $b = $this->oldStyleType("Bo'sh tur", 0);

        $this->migration->up();

        $bank = QuestionBank::where('name', 'Mehnat muhofazasi')->firstOrFail();
        $this->assertSame(3, $bank->questions()->count());
        $this->assertSame(1, QuestionBank::count());
        $this->assertSame(3, ExamType::find($a)->questionBanks()->first()->pivot->question_count);
        $this->assertSame(0, ExamType::find($b)->questionBanks()->count());
        $this->assertFalse(Schema::hasColumn('questions', 'exam_type_id'));
    }

    public function test_up_suffixes_duplicate_bank_names(): void
    {
        $this->oldStyleType('Test', 1);
        $second = $this->oldStyleType('Test', 2);

        $this->migration->up();

        $this->assertEqualsCanonicalizing(['Test', "Test (ID {$second})"], QuestionBank::pluck('name')->all());
    }

    public function test_down_restores_exam_type_id(): void
    {
        $a = $this->oldStyleType('Mehnat muhofazasi', 3);
        $this->migration->up();

        $this->migration->down();

        $this->assertTrue(Schema::hasColumn('questions', 'exam_type_id'));
        $this->assertSame(3, DB::table('questions')->where('exam_type_id', $a)->count());
        $this->assertFalse(Schema::hasTable('question_banks'));

        $this->migration->up(); // keyingi rollback uchun holatni tiklash
    }

    public function test_up_resumes_after_previous_partial_run(): void
    {
        $a = $this->oldStyleType('Mehnat muhofazasi', 3);
        // Oldingi urinish ma'lumot qadamida yiqilgandan keyingi holat: jadvallar va bo'sh ustun qolgan.
        Schema::create('question_banks', function ($t) {
            $t->id();
            $t->string('name')->unique();
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('exam_type_question_bank', function ($t) {
            $t->id();
            $t->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $t->foreignId('question_bank_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('question_count');
            $t->timestamps();
            $t->unique(['exam_type_id', 'question_bank_id']);
        });
        Schema::table('questions', fn ($t) => $t->unsignedBigInteger('question_bank_id')->nullable()->after('id'));

        $this->migration->up();

        $this->assertSame(3, QuestionBank::where('name', 'Mehnat muhofazasi')->firstOrFail()->questions()->count());
        $this->assertSame(3, ExamType::find($a)->questionBanks()->first()->pivot->question_count);
        $this->assertFalse(Schema::hasColumn('questions', 'exam_type_id'));
    }

    public function test_up_keeps_suffixing_until_name_is_free(): void
    {
        $first = $this->oldStyleType('A', 1);
        $second = $this->oldStyleType('placeholder', 1);
        $third = $this->oldStyleType('A', 1);
        DB::table('exam_types')->where('id', $second)->update(['name' => "A (ID {$third})"]);

        $this->migration->up();

        $this->assertSame(3, QuestionBank::count());
        $this->assertSame(3, QuestionBank::pluck('name')->unique()->count());
        $this->assertSame(1, ExamType::find($third)->questionBanks()->first()->questions()->count());
    }
}
