<?php

namespace Tests\Concerns;

use App\Models\Certificate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExamResult;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\PeriodicExam;
use App\Models\Position;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait CertificateFixtures
{
    private static int $certificateCounter = 0;

    protected function makeOrganization(?string $code = null): Organization
    {
        $code ??= 'ORG-'.Str::upper(Str::random(6));

        return Organization::create([
            'name' => "Tashkilot {$code}",
            'code' => $code,
            'type' => 'branch',
        ]);
    }

    protected function makeUser(string $role = 'admin', ?Organization $org = null): User
    {
        $username = $role.'_'.Str::lower(Str::random(8));

        return User::create([
            'name'            => Str::ucfirst($role),
            'username'        => $username,
            'email'           => "{$username}@example.test",
            'password'        => 'secret123',
            'role'            => $role,
            'organization_id' => $org?->id,
            'is_active'       => true,
        ]);
    }

    protected function makeExam(?Organization $org = null, array $examAttrs = [], array $typeAttrs = []): PeriodicExam
    {
        $org ??= $this->makeOrganization();

        $type = ExamType::create(array_merge([
            'name'             => 'Mehnat muhofazasi',
            'frequency_months' => 12,
            'duration_minutes' => 30,
            'passing_score'    => 60,
            'program_name'     => 'Mehnatni muhofaza qilish',
            'training_hours'   => 72,
        ], $typeAttrs));

        foreach (['Birinchi savol', 'Ikkinchi savol'] as $text) {
            Question::create([
                'exam_type_id'   => $type->id,
                'question_text'  => $text,
                'options'        => [['key' => 'A', 'text' => "To'g'ri"], ['key' => 'B', 'text' => "Noto'g'ri"]],
                'correct_option' => 'A',
                'points'         => 1,
            ]);
        }

        return PeriodicExam::create(array_merge([
            'organization_id'   => $org->id,
            'exam_type_id'      => $type->id,
            'title'             => 'Davriy imtihon',
            'exam_date'         => '2026-11-07',
            'frequency_months'  => 12,
            'next_exam_date'    => '2027-11-07',
            'status'            => 'planned',
            'created_by'        => $this->makeUser('admin')->id,
            'course_start_date' => '2026-11-01',
            'course_end_date'   => '2026-11-07',
        ], $examAttrs));
    }

    protected function makeEmployee(PeriodicExam $exam, string $name = 'Aliyev Vali G‘aniyevich'): Employee
    {
        $department = Department::create(['organization_id' => $exam->organization_id, 'name' => "Texnik bo'lim"]);
        $position = Position::create(['organization_id' => $exam->organization_id, 'name' => 'Muhandis']);

        return Employee::create([
            'organization_id' => $exam->organization_id,
            'department_id'   => $department->id,
            'position_id'     => $position->id,
            'full_name'       => $name,
            'is_active'       => true,
        ]);
    }

    protected function makeResult(PeriodicExam $exam, string $grade = 'good', ?Employee $employee = null, ?Carbon $createdAt = null): ExamResult
    {
        $employee ??= $this->makeEmployee($exam);

        $result = new ExamResult(ExamResult::applyGradeStatus([
            'periodic_exam_id' => $exam->id,
            'employee_id'      => $employee->id,
            'grade'            => $grade,
            'score'            => $grade === 'unsatisfactory' ? 40 : 85,
        ]));

        if ($createdAt) {
            $result->created_at = $createdAt;
            $result->updated_at = $createdAt;
        }

        $result->save();

        return $result;
    }

    protected function makeCertificate(ExamResult $result, array $attrs = []): Certificate
    {
        $n = ++self::$certificateCounter;

        return Certificate::create(array_merge([
            'exam_result_id'      => $result->id,
            'employee_id'         => $result->employee_id,
            'periodic_exam_id'    => $result->periodic_exam_id,
            'year'                => 2026,
            'sequence'            => $n,
            'number'              => sprintf('2026-%04d', $n),
            'registration_number' => $n,
            'public_code'         => Str::random(32),
            'data'                => [
                'full_name'             => $result->employee->full_name,
                'institution_name'      => '"JAHON MEHNAT MUHOFAZASI" MCHJ',
                'program_name'          => 'Mehnatni muhofaza qilish',
                'training_hours'        => 72,
                'course_start_date'     => '2026-11-01',
                'course_end_date'       => '2026-11-07',
                'commission_chair_name' => 'J. Xasanov',
                'director_name'         => 'A. Bazarova',
                'inspector_name'        => 'M. Yurev',
                'city'                  => 'Urganch sh.',
                'issue_date'            => '2026-11-07',
            ],
        ], $attrs));
    }
}
