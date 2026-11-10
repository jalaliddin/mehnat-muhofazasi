<?php

namespace Tests\Feature\PeriodicExams;

use App\Models\ExamSession;
use App\Models\PeriodicExam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class ParticipantsTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    private function storePayload(PeriodicExam $template, array $employeeIds): array
    {
        return [
            'organization_id'  => $template->organization_id,
            'exam_type_id'     => $template->exam_type_id,
            'title'            => 'Yangi imtihon',
            'exam_date'        => '2026-11-20',
            'frequency_months' => 12,
            'employee_ids'     => $employeeIds,
        ];
    }

    public function test_store_saves_employee_ids_as_participants(): void
    {
        $template = $this->makeExam();
        $a = $this->makeEmployee($template, 'Aliyev A');
        $b = $this->makeEmployee($template, 'Boboyev B');
        Sanctum::actingAs($this->makeUser('admin'));

        $id = $this->postJson('/api/periodic-exams', $this->storePayload($template, [$a->id, $b->id]))->assertCreated()->json('id');

        $this->assertEqualsCanonicalizing([$a->id, $b->id], PeriodicExam::find($id)->employees()->pluck('employees.id')->all());
    }

    public function test_update_replaces_participants_and_keeps_them_when_absent(): void
    {
        $exam = $this->makeExam();
        $a = $this->makeEmployee($exam, 'Aliyev A');
        $b = $this->makeEmployee($exam, 'Boboyev B');
        $exam->employees()->attach([$a->id, $b->id]);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/periodic-exams/{$exam->id}", ['employee_ids' => [$a->id]])->assertOk();
        $this->assertSame([$a->id], $exam->employees()->pluck('employees.id')->all());

        $this->putJson("/api/periodic-exams/{$exam->id}", ['title' => 'Boshqa nom'])->assertOk();
        $this->assertSame([$a->id], $exam->employees()->pluck('employees.id')->all());
    }

    public function test_rejects_employee_from_other_organization(): void
    {
        $exam = $this->makeExam();
        $stranger = $this->makeEmployee($this->makeExam($this->makeOrganization('OTHER')), 'Begona');
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/periodic-exams/{$exam->id}", ['employee_ids' => [$stranger->id]])
            ->assertStatus(422)
            ->assertJsonPath('errors.employee_ids.0', 'Xodimlar imtihon tashkilotiga tegishli bo\'lishi kerak');

        $this->assertSame(0, $exam->employees()->count());
    }

    public function test_index_includes_participant_ids(): void
    {
        $exam = $this->makeExam();
        $a = $this->makeEmployee($exam, 'Aliyev A');
        $exam->employees()->attach($a->id);
        Sanctum::actingAs($this->makeUser('admin'));

        $row = collect($this->getJson('/api/periodic-exams')->assertOk()->json())->firstWhere('id', $exam->id);

        $this->assertSame([$a->id], collect($row['employees'])->pluck('id')->all());
    }

    public function test_generate_creates_links_only_for_participants(): void
    {
        $exam = $this->makeExam();
        $participant = $this->makeEmployee($exam, 'Ishtirokchi');
        $this->makeEmployee($exam, 'Boshqa bo\'lim xodimi');
        $exam->employees()->attach($participant->id);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->postJson('/api/exam-sessions/generate', ['periodic_exam_id' => $exam->id])->assertOk();

        $this->assertSame([$participant->id], ExamSession::where('periodic_exam_id', $exam->id)->pluck('employee_id')->all());
    }

    public function test_generate_without_participants_returns_422(): void
    {
        $exam = $this->makeExam();
        $this->makeEmployee($exam, 'Qo\'shilmagan');
        Sanctum::actingAs($this->makeUser('admin'));

        $this->postJson('/api/exam-sessions/generate', ['periodic_exam_id' => $exam->id])
            ->assertStatus(422)
            ->assertJsonPath('message', "Avval imtihonga xodimlarni qo'shing (bo'limlarni tanlang)");

        $this->assertSame(0, ExamSession::count());
    }

    public function test_generate_removes_pending_links_of_non_participants(): void
    {
        $exam = $this->makeExam();
        $participant = $this->makeEmployee($exam, 'Ishtirokchi');
        $exam->employees()->attach($participant->id);

        $pending = $this->makeSession($exam, $this->makeEmployee($exam, 'Eski havola'));
        $inProgress = $this->makeSession($exam, $this->makeEmployee($exam, 'Yechayotgan'));
        $inProgress->update(['status' => 'in_progress', 'started_at' => now()]);
        $completed = $this->makeSession($exam, $this->makeEmployee($exam, 'Tugatgan'));
        $completed->update(['status' => 'completed', 'finished_at' => now()]);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->postJson('/api/exam-sessions/generate', ['periodic_exam_id' => $exam->id])->assertOk();

        $this->assertNull(ExamSession::find($pending->id));
        $this->assertNotNull(ExamSession::find($inProgress->id));
        $this->assertNotNull(ExamSession::find($completed->id));
        $this->assertTrue(ExamSession::where('periodic_exam_id', $exam->id)->where('employee_id', $participant->id)->exists());
    }
}
