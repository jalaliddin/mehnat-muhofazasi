<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSession extends Model
{
    protected $fillable = [
        'periodic_exam_id', 'employee_id', 'token', 'status', 'duration_minutes',
        'started_at', 'finished_at', 'total_points', 'earned_points',
        'score_percent', 'exam_result_id',
    ];

    protected $casts = [
        'started_at'    => 'datetime',
        'finished_at'   => 'datetime',
        'score_percent' => 'decimal:2',
    ];

    public function periodicExam()
    {
        return $this->belongsTo(PeriodicExam::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function examResult()
    {
        return $this->belongsTo(ExamResult::class);
    }

    public function sessionQuestions()
    {
        return $this->hasMany(ExamSessionQuestion::class)->orderBy('question_order');
    }
}
