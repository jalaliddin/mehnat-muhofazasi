<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Certificate extends Model
{
    protected $fillable = [
        'exam_result_id', 'employee_id', 'periodic_exam_id',
        'year', 'sequence', 'number', 'registration_number', 'public_code', 'data',
        'generated_path', 'signed_path', 'signed_at', 'signed_by',
    ];

    protected $casts = [
        'data'      => 'array',
        'signed_at' => 'datetime',
    ];

    protected $appends = ['status'];

    protected static function booted(): void
    {
        static::deleting(function (Certificate $certificate) {
            $paths = array_filter([$certificate->generated_path, $certificate->signed_path]);

            if ($paths) {
                Storage::disk('local')->delete($paths);
            }
        });
    }

    public function getStatusAttribute(): string
    {
        return $this->signed_path ? 'signed' : 'pending_signature';
    }

    public function registrationLabel(): string
    {
        return str_pad((string) $this->registration_number, 6, '0', STR_PAD_LEFT);
    }

    public function examResult()
    {
        return $this->belongsTo(ExamResult::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function periodicExam()
    {
        return $this->belongsTo(PeriodicExam::class);
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
