<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditor roli (faqat Monitoring) va javob vaqti (real vaqt kuzatuvi uchun).
 * Qayta ishga tushirishga chidamli.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','operator','auditor') NOT NULL DEFAULT 'operator'");

        if (! Schema::hasColumn('exam_session_questions', 'answered_at')) {
            Schema::table('exam_session_questions', function (Blueprint $table) {
                $table->timestamp('answered_at')->nullable()->after('selected_option');
            });
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'auditor')->update(['role' => 'operator']);
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','operator') NOT NULL DEFAULT 'operator'");

        if (Schema::hasColumn('exam_session_questions', 'answered_at')) {
            Schema::table('exam_session_questions', function (Blueprint $table) {
                $table->dropColumn('answered_at');
            });
        }
    }
};
