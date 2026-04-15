<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // exam_types: enum(1,2) -> unsignedSmallInteger
        Schema::table('exam_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('frequency_months')->default(12)->after('description');
        });
        // Copy data: 1 year -> 12 months, 2 years -> 24 months
        DB::statement("UPDATE exam_types SET frequency_months = frequency_years * 12");
        Schema::table('exam_types', function (Blueprint $table) {
            $table->dropColumn('frequency_years');
        });

        // periodic_exams: enum(1,2) -> unsignedSmallInteger
        Schema::table('periodic_exams', function (Blueprint $table) {
            $table->unsignedSmallInteger('frequency_months')->default(12)->after('exam_date');
        });
        DB::statement("UPDATE periodic_exams SET frequency_months = frequency_years * 12");
        Schema::table('periodic_exams', function (Blueprint $table) {
            $table->dropColumn('frequency_years');
        });
    }

    public function down(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->tinyInteger('frequency_years')->default(1)->after('description');
        });
        DB::statement("UPDATE exam_types SET frequency_years = CASE WHEN frequency_months <= 12 THEN 1 ELSE 2 END");
        Schema::table('exam_types', function (Blueprint $table) {
            $table->dropColumn('frequency_months');
        });

        Schema::table('periodic_exams', function (Blueprint $table) {
            $table->tinyInteger('frequency_years')->default(1)->after('exam_date');
        });
        DB::statement("UPDATE periodic_exams SET frequency_years = CASE WHEN frequency_months <= 12 THEN 1 ELSE 2 END");
        Schema::table('periodic_exams', function (Blueprint $table) {
            $table->dropColumn('frequency_months');
        });
    }
};
