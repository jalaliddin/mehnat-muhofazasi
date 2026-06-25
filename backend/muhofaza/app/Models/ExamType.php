<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamType extends Model
{
    protected $fillable = [
        'name', 'description', 'frequency_months', 'exam_month', 'exam_month_note',
        'duration_minutes', 'passing_score',
    ];

    public function periodicExams()
    {
        return $this->hasMany(PeriodicExam::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
