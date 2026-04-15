<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamType extends Model
{
    protected $fillable = ['name', 'description', 'frequency_months', 'exam_month', 'exam_month_note'];

    public function periodicExams()
    {
        return $this->hasMany(PeriodicExam::class);
    }
}
