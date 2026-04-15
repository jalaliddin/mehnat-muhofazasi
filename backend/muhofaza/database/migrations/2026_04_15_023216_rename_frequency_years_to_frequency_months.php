<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // exam_types: rename only if frequency_years still exists (live DB upgrade)
        if (Schema::hasColumn('exam_types', 'frequency_years')) {
            Schema::table('exam_types', function (Blueprint $table) {
                $table->unsignedSmallInteger('frequency_months')->default(12)->after('description');
            });
            DB::statement("UPDATE exam_types SET frequency_months = frequency_years * 12");
            Schema::table('exam_types', function (Blueprint $table) {
                $table->dropColumn('frequency_years');
            });
        }

        // periodic_exams: rename only if frequency_years still exists
        if (Schema::hasColumn('periodic_exams', 'frequency_years')) {
            Schema::table('periodic_exams', function (Blueprint $table) {
                $table->unsignedSmallInteger('frequency_months')->default(12)->after('exam_date');
            });
            DB::statement("UPDATE periodic_exams SET frequency_months = frequency_years * 12");
            Schema::table('periodic_exams', function (Blueprint $table) {
                $table->dropColumn('frequency_years');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_types', 'frequency_months')) {
            Schema::table('exam_types', function (Blueprint $table) {
                $table->tinyInteger('frequency_years')->default(1)->after('description');
            });
            DB::statement("UPDATE exam_types SET frequency_years = CASE WHEN frequency_months <= 12 THEN 1 ELSE 2 END");
            Schema::table('exam_types', function (Blueprint $table) {
                $table->dropColumn('frequency_months');
            });
        }

        if (Schema::hasColumn('periodic_exams', 'frequency_months')) {
            Schema::table('periodic_exams', function (Blueprint $table) {
                $table->tinyInteger('frequency_years')->default(1)->after('exam_date');
            });
            DB::statement("UPDATE periodic_exams SET frequency_years = CASE WHEN frequency_months <= 12 THEN 1 ELSE 2 END");
            Schema::table('periodic_exams', function (Blueprint $table) {
                $table->dropColumn('frequency_months');
            });
        }
    }
};
