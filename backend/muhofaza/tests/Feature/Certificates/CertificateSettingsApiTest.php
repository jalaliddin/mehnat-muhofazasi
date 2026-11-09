<?php

namespace Tests\Feature\Certificates;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class CertificateSettingsApiTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    public function test_admin_can_read_and_update_settings(): void
    {
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson('/api/certificate-settings', [
            'institution_name'      => '"JAHON MEHNAT MUHOFAZASI" MCHJ',
            'commission_chair_name' => 'J. Xasanov',
            'director_name'         => 'A. Bazarova',
            'inspector_name'        => 'M. Yurev',
            'city'                  => 'Urganch sh.',
        ])->assertOk()->assertJsonPath('city', 'Urganch sh.');

        $this->getJson('/api/certificate-settings')
            ->assertOk()
            ->assertJsonPath('city', 'Urganch sh.')
            ->assertJsonPath('director_name', 'A. Bazarova');
    }

    public function test_operator_cannot_access_settings(): void
    {
        Sanctum::actingAs($this->makeUser('operator', $this->makeOrganization()));

        $this->getJson('/api/certificate-settings')->assertForbidden();
        $this->putJson('/api/certificate-settings', ['city' => 'X'])->assertForbidden();
    }

    public function test_exam_type_update_stores_program_name(): void
    {
        $exam = $this->makeExam(null, [], ['program_name' => null]);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/exam-types/{$exam->exam_type_id}", ['program_name' => 'Mehnatni muhofaza qilish'])
            ->assertOk()
            ->assertJsonPath('program_name', 'Mehnatni muhofaza qilish')
            ->assertJsonMissingPath('training_hours');
    }

    public function test_periodic_exam_rejects_course_end_before_start(): void
    {
        $exam = $this->makeExam();
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/periodic-exams/{$exam->id}", ['course_start_date' => '2026-11-10', 'course_end_date' => '2026-11-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('course_end_date');

        $this->putJson("/api/periodic-exams/{$exam->id}", ['course_start_date' => '2026-11-01', 'course_end_date' => '2026-11-10'])
            ->assertOk();

        $this->assertSame('2026-11-10', $exam->fresh()->course_end_date->toDateString());
    }

    public function test_periodic_exam_end_only_update_compares_with_stored_start(): void
    {
        $exam = $this->makeExam(null, ['course_start_date' => '2026-11-01', 'course_end_date' => '2026-11-07']);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/periodic-exams/{$exam->id}", ['course_end_date' => '2026-11-09'])->assertOk();
        $this->putJson("/api/periodic-exams/{$exam->id}", ['course_end_date' => '2026-10-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('course_end_date');
    }

    public function test_exam_results_index_includes_certificate(): void
    {
        Storage::fake('local');
        $exam = $this->makeExam();
        $this->makeCertificate($this->makeResult($exam));
        Sanctum::actingAs($this->makeUser('admin'));

        $this->getJson("/api/exam-results?periodic_exam_id={$exam->id}")
            ->assertOk()
            ->assertJsonStructure([['certificate' => ['number', 'status']]]);
    }
}
