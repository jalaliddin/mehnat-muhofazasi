<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'organization_id', 'department_id', 'position_id',
        'full_name', 'personnel_number', 'phone', 'hire_date', 'is_active',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'is_active'  => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function examResults()
    {
        return $this->hasMany(ExamResult::class);
    }
}
