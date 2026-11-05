<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBank extends Model
{
    protected $fillable = ['name', 'description'];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function examTypes()
    {
        return $this->belongsToMany(ExamType::class, 'exam_type_question_bank')
            ->withPivot('question_count')
            ->withTimestamps();
    }
}
