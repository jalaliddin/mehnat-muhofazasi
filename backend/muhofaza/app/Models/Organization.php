<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = ['name', 'code', 'type', 'address', 'phone'];

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function periodicExams()
    {
        return $this->hasMany(PeriodicExam::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
