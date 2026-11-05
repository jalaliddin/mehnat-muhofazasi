<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Test tarkibi qatori: imtihon turi shu bazadan question_count ta savol oladi.
 */
class ExamTypeQuestionBank extends Model
{
    protected $table = 'exam_type_question_bank';

    protected $fillable = ['exam_type_id', 'question_bank_id', 'question_count'];
}
