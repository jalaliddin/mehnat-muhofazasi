<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sertifikat turlari (soat + sarlavha + kurs matni) davriy imtihonda tanlanadi; exam_types.training_hours
 * shu turlarga ko'chiriladi. Sertifikat raqami endi qo'lda kiritiladi — number/year/sequence nullable.
 * Har qadam qayta ishga tushirishga chidamli (entrypoint `migrate --force` ni `set -e` bilan ishlatadi).
 */
return new class extends Migration
{
    private const DEFAULT_TYPES = [
        ['36 soatlik malaka oshirish', 36, 'Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida', 'malaka oshirish kursida'],
        ["36 soatlik o'qish", 36, "Mehnatni muhofaza qilish sohasida o'qiganlik haqida", "o'quv kursida"],
        ['72 soatlik malaka oshirish', 72, 'Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida', 'malaka oshirish kursida'],
        ['288 soatlik qayta tayyorlov', 288, "Mehnatni muhofaza qilish sohasida qayta tayyorlovdan o'tganlik haqida", 'qayta tayyorlov kursida'],
    ];

    /** exam_types.training_hours → tur nomi */
    private const HOURS_TO_TYPE = [
        36  => '36 soatlik malaka oshirish',
        72  => '72 soatlik malaka oshirish',
        288 => '288 soatlik qayta tayyorlov',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('certificate_types')) {
            Schema::create('certificate_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->unsignedSmallInteger('hours');
                $table->string('title_text');
                $table->string('course_text');
                $table->timestamps();
            });
        }

        $now = now();
        foreach (self::DEFAULT_TYPES as [$name, $hours, $title, $course]) {
            if (! DB::table('certificate_types')->where('name', $name)->exists()) {
                DB::table('certificate_types')->insert([
                    'name' => $name, 'hours' => $hours, 'title_text' => $title, 'course_text' => $course,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        if (! Schema::hasColumn('periodic_exams', 'certificate_type_id')) {
            Schema::table('periodic_exams', function (Blueprint $table) {
                $table->unsignedBigInteger('certificate_type_id')->nullable()->after('course_end_date');
            });
        }

        if (! collect(Schema::getForeignKeys('periodic_exams'))->pluck('name')->contains('periodic_exams_certificate_type_id_foreign')) {
            Schema::table('periodic_exams', function (Blueprint $table) {
                $table->foreign('certificate_type_id')->references('id')->on('certificate_types')->restrictOnDelete();
            });
        }

        if (Schema::hasColumn('exam_types', 'training_hours')) {
            DB::transaction(function () {
                foreach (self::HOURS_TO_TYPE as $hours => $name) {
                    $typeId = DB::table('certificate_types')->where('name', $name)->value('id');
                    $examTypeIds = DB::table('exam_types')->where('training_hours', $hours)->pluck('id');

                    DB::table('periodic_exams')
                        ->whereIn('exam_type_id', $examTypeIds)
                        ->whereNull('certificate_type_id')
                        ->update(['certificate_type_id' => $typeId]);
                }
            });

            Schema::table('exam_types', function (Blueprint $table) {
                $table->dropColumn('training_hours');
            });
        }

        Schema::table('certificates', function (Blueprint $table) {
            $table->string('number', 50)->nullable()->change();
            $table->unsignedSmallInteger('year')->nullable()->change();
            $table->unsignedInteger('sequence')->nullable()->change();
        });
    }

    /**
     * training_hours har imtihon turining birinchi turga ega davriy imtihonidan qaytariladi.
     * certificates.number NOT NULL ga qaytarilmaydi — raqamsiz sertifikatlar bo'lishi mumkin (hujjatlashtirilgan).
     */
    public function down(): void
    {
        if (! Schema::hasColumn('exam_types', 'training_hours')) {
            Schema::table('exam_types', function (Blueprint $table) {
                $table->unsignedSmallInteger('training_hours')->nullable();
            });
        }

        if (Schema::hasColumn('periodic_exams', 'certificate_type_id')) {
            $hoursByExamType = DB::table('periodic_exams')
                ->join('certificate_types', 'certificate_types.id', '=', 'periodic_exams.certificate_type_id')
                ->orderBy('periodic_exams.id')
                ->get(['periodic_exams.exam_type_id', 'certificate_types.hours'])
                ->unique('exam_type_id');

            foreach ($hoursByExamType as $row) {
                DB::table('exam_types')->where('id', $row->exam_type_id)->update(['training_hours' => $row->hours]);
            }

            Schema::table('periodic_exams', function (Blueprint $table) {
                if (collect(Schema::getForeignKeys('periodic_exams'))->pluck('name')->contains('periodic_exams_certificate_type_id_foreign')) {
                    $table->dropForeign(['certificate_type_id']);
                }
                $table->dropColumn('certificate_type_id');
            });
        }

        Schema::dropIfExists('certificate_types');
    }
};
