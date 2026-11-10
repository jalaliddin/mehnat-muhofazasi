<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSessionQuestion extends Model
{
    protected $fillable = [
        'exam_session_id', 'question_id', 'question_order', 'shuffled_options',
        'selected_option', 'answered_at', 'is_correct', 'points_earned',
    ];

    protected $casts = [
        'shuffled_options' => 'array',
        'is_correct'       => 'boolean',
        'answered_at'      => 'datetime',
    ];

    public function examSession()
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
