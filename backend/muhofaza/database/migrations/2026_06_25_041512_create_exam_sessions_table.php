<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodic_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'expired'])->default('pending');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedInteger('earned_points')->nullable();
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->foreignId('exam_result_id')->nullable()->constrained('exam_results')->nullOnDelete();
            $table->timestamps();

            $table->unique(['periodic_exam_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
