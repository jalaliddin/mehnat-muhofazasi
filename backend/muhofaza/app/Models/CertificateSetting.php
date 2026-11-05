<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateSetting extends Model
{
    public const FIELDS = [
        'institution_name', 'commission_chair_name', 'director_name', 'inspector_name', 'city',
    ];

    protected $fillable = self::FIELDS;

    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? static::create();
    }
}
