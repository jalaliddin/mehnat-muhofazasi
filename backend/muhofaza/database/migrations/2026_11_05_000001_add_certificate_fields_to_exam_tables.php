<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->string('program_name')->nullable();
            $table->unsignedSmallInteger('training_hours')->nullable();
        });

        Schema::table('periodic_exams', function (Blueprint $table) {
            $table->date('course_start_date')->nullable();
            $table->date('course_end_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->dropColumn(['program_name', 'training_hours']);
        });

        Schema::table('periodic_exams', function (Blueprint $table) {
            $table->dropColumn(['course_start_date', 'course_end_date']);
        });
    }
};
