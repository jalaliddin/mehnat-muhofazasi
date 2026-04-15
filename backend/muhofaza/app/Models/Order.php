<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'organization_id', 'order_number', 'order_date', 'title', 'description', 'file_path',
    ];

    protected $casts = [
        'order_date' => 'date',
    ];

    protected $appends = ['file_url'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function getFileUrlAttribute()
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }
}
