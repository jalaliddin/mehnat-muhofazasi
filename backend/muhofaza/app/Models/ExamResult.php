<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    protected $fillable = [
        'periodic_exam_id', 'employee_id', 'order_id',
        'grade', 'score', 'notes', 'is_passed', 'retake_required', 'retake_exam_id',
    ];

    protected $casts = [
        'is_passed'       => 'boolean',
        'retake_required' => 'boolean',
    ];

    public function periodicExam()
    {
        return $this->belongsTo(PeriodicExam::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function retakeExam()
    {
        return $this->belongsTo(PeriodicExam::class, 'retake_exam_id');
    }
}
