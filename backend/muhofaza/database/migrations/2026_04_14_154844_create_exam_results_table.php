<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodic_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('grade', ['excellent', 'good', 'satisfactory', 'unsatisfactory']);
            $table->integer('score')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_passed')->default(true);
            $table->boolean('retake_required')->default(false);
            $table->foreignId('retake_exam_id')->nullable()->constrained('periodic_exams')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
