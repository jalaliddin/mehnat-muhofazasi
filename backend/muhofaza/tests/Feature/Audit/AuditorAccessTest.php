<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class AuditorAccessTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    public function test_admin_can_create_auditor_without_organization(): void
    {
        Sanctum::actingAs($this->makeUser('admin'));

        $this->postJson('/api/users', [
            'name' => 'Auditor', 'username' => 'auditor1', 'email' => 'auditor1@example.test',
            'password' => 'secret123', 'role' => 'auditor',
        ])->assertCreated()
            ->assertJsonPath('role', 'auditor')
            ->assertJsonPath('organization_id', null);
    }

    public function test_auditor_can_read_own_profile(): void
    {
        Sanctum::actingAs($this->makeUser('auditor'));

        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_auditor_is_denied_everything_else(): void
    {
        $exam = $this->makeExam();
        Sanctum::actingAs($this->makeUser('auditor'));

        foreach (['/api/employees', '/api/periodic-exams', '/api/certificates', '/api/users', '/api/notifications/unread-count'] as $url) {
            $this->getJson($url)
                ->assertForbidden()
                ->assertJsonPath('message', "Auditor faqat Monitoring bo'limini ko'radi");
        }
        $this->postJson('/api/exam-sessions/generate', ['periodic_exam_id' => $exam->id])
            ->assertForbidden()
            ->assertJsonPath('message', "Auditor faqat Monitoring bo'limini ko'radi");
    }

    public function test_operator_and_admin_unaffected(): void
    {
        Sanctum::actingAs($this->makeUser('operator', $this->makeOrganization()));
        $this->getJson('/api/employees')->assertOk();

        Sanctum::actingAs($this->makeUser('admin'));
        $this->getJson('/api/users')->assertOk();
    }
}
