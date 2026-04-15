<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodicExam extends Model
{
    protected $fillable = [
        'organization_id', 'exam_type_id', 'order_id', 'title',
        'exam_date', 'frequency_months', 'next_exam_date', 'status', 'created_by',
    ];

    protected $casts = [
        'exam_date'      => 'date',
        'next_exam_date' => 'date',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function examType()
    {
        return $this->belongsTo(ExamType::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results()
    {
        return $this->hasMany(ExamResult::class);
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'exam_participants');
    }
}
