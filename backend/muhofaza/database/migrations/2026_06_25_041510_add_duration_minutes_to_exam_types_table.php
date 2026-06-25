<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedTinyInteger('passing_score')->default(60);
        });
    }

    public function down(): void
    {
        Schema::table('exam_types', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'passing_score']);
        });
    }
};
