<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'exam_type_id', 'question_text', 'options', 'correct_option', 'points',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function examType()
    {
        return $this->belongsTo(ExamType::class);
    }
}
