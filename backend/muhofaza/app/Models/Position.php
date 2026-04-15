<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = ['organization_id', 'name'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
