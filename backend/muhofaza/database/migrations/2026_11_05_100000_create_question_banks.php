<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Savollar imtihon turidan mustaqil bazalarga ko'chiriladi. Har bir imtihon turining savollari
 * shu tur nomli bazaga o'tadi va tarkibga "hamma savol" yoziladi — mavjud testlar avvalgidek ishlaydi.
 * MySQL DDL implicit commit qilgani uchun faqat ma'lumot ko'chirish qadami tranzaksiyada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_type_question_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('question_count');
            $table->timestamps();

            $table->unique(['exam_type_id', 'question_bank_id']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('question_bank_id')->nullable()->after('id');
        });

        DB::transaction(function () {
            $now = now();
            $typeIds = DB::table('questions')->distinct()->orderBy('exam_type_id')->pluck('exam_type_id');

            foreach ($typeIds as $typeId) {
                $name = DB::table('exam_types')->where('id', $typeId)->value('name');
                if (DB::table('question_banks')->where('name', $name)->exists()) {
                    $name = mb_substr($name, 0, 230)." (ID {$typeId})";
                }

                $bankId = DB::table('question_banks')->insertGetId([
                    'name' => $name, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $count = DB::table('questions')->where('exam_type_id', $typeId)->update(['question_bank_id' => $bankId]);

                DB::table('exam_type_question_bank')->insert([
                    'exam_type_id' => $typeId, 'question_bank_id' => $bankId, 'question_count' => $count,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('question_bank_id')->nullable(false)->change();
            $table->foreign('question_bank_id')->references('id')->on('question_banks')->cascadeOnDelete();
            $table->dropForeign(['exam_type_id']);
            $table->dropColumn('exam_type_id');
        });
    }

    /**
     * Har bir bazaning savollari uni ishlatgan birinchi imtihon turiga qaytadi.
     * Hech bir turda ishlatilmagan bazalarning savollari o'chiriladi (hujjatlashtirilgan yo'qotish).
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_type_id')->nullable()->after('id');
        });

        DB::transaction(function () {
            $firstTypeByBank = DB::table('exam_type_question_bank')
                ->select('question_bank_id', DB::raw('MIN(exam_type_id) as exam_type_id'))
                ->groupBy('question_bank_id')
                ->pluck('exam_type_id', 'question_bank_id');

            foreach ($firstTypeByBank as $bankId => $typeId) {
                DB::table('questions')->where('question_bank_id', $bankId)->update(['exam_type_id' => $typeId]);
            }

            DB::table('questions')->whereNull('exam_type_id')->delete();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_type_id')->nullable(false)->change();
            $table->foreign('exam_type_id')->references('id')->on('exam_types')->cascadeOnDelete();
            $table->dropForeign(['question_bank_id']);
            $table->dropColumn('question_bank_id');
        });

        Schema::dropIfExists('exam_type_question_bank');
        Schema::dropIfExists('question_banks');
    }
};
