<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sertifikat turi: soat hajmi va sertifikatdagi sarlavha hamda kurs matni.
 */
class CertificateType extends Model
{
    public const DEFAULT_TITLE = 'Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida';

    public const DEFAULT_COURSE = 'malaka oshirish kursida';

    protected $fillable = ['name', 'hours', 'title_text', 'course_text'];

    protected $casts = ['hours' => 'integer'];

    public function periodicExams()
    {
        return $this->hasMany(PeriodicExam::class);
    }
}
