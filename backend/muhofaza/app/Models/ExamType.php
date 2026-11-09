<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamType extends Model
{
    protected $fillable = [
        'name', 'description', 'frequency_months', 'exam_month', 'exam_month_note',
        'duration_minutes', 'passing_score', 'program_name',
    ];

    public function periodicExams()
    {
        return $this->hasMany(PeriodicExam::class);
    }

    public function questionBanks()
    {
        return $this->belongsToMany(QuestionBank::class, 'exam_type_question_bank')
            ->withPivot('question_count')
            ->withTimestamps();
    }

    /**
     * Testdagi jami savollar soni — tarkibdagi question_count yig'indisi.
     */
    public function questionTotal(): int
    {
        if ($this->relationLoaded('questionBanks')) {
            return (int) $this->questionBanks->sum('pivot.question_count');
        }

        return (int) $this->questionBanks()->sum('exam_type_question_bank.question_count');
    }
}
