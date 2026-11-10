<?php

namespace Tests\Feature\Audit;

use App\Models\ExamSession;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class MonitoringApiTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Boshlangan sessiya va uning savol ID'lari. */
    private function started(?Organization $org = null, string $name = 'Aliyev Vali', array $examAttrs = []): array
    {
        $exam = $this->makeExam($org, $examAttrs);
        $session = $this->makeSession($exam, $this->makeEmployee($exam, $name));
        $ids = collect($this->postJson("/api/public/exam-sessions/{$session->token}/start")->assertOk()->json('questions'))
            ->pluck('exam_session_question_id')->all();

        return [$session->fresh(), $ids];
    }

    private function answer(ExamSession $session, int $id, string $option): void
    {
        $this->putJson("/api/public/exam-sessions/{$session->token}/answer", ['exam_session_question_id' => $id, 'selected_option' => $option])->assertOk();
    }

    private function submitAll(ExamSession $session, array $ids, string $option = 'A'): void
    {
        $this->postJson("/api/public/exam-sessions/{$session->token}/submit", [
            'answers' => array_map(fn ($id) => ['exam_session_question_id' => $id, 'selected_option' => $option], $ids),
        ])->assertOk();
    }

    public function test_active_lists_only_running_sessions(): void
    {
        [$running, $ids] = $this->started(null, 'Ishlayapti');
        $this->answer($running, $ids[0], 'A');

        $pendingExam = $this->makeExam();
        $this->makeSession($pendingExam, $this->makeEmployee($pendingExam, 'Boshlamagan'));

        [$done, $doneIds] = $this->started(null, 'Tugatgan');
        $this->submitAll($done, $doneIds);

        [$stale] = $this->started(null, 'Brauzerni yopgan');
        $stale->update(['started_at' => now()->subMinutes(40)]);

        Sanctum::actingAs($this->makeUser('auditor'));
        $rows = $this->getJson('/api/monitoring/active')->assertOk()->json();

        $this->assertCount(1, $rows);
        $this->assertSame('Ishlayapti', $rows[0]['employee']['full_name']);
        $this->assertSame(1, $rows[0]['answered']);
        $this->assertSame(2, $rows[0]['total']);
        $this->assertNotNull($rows[0]['last_activity_at']);
    }

    public function test_active_remaining_seconds_uses_server_clock(): void
    {
        [$session] = $this->started();
        $session->update(['started_at' => Carbon::parse('2026-11-10 09:50:00')]);
        Carbon::setTestNow('2026-11-10 10:00:00');
        Sanctum::actingAs($this->makeUser('admin'));

        $this->getJson('/api/monitoring/active')->assertOk()->assertJsonPath('0.remaining_seconds', 1200);
    }

    public function test_scoping_by_organization(): void
    {
        $orgA = $this->makeOrganization('A');
        $orgB = $this->makeOrganization('B');
        $this->started($orgA, 'A xodimi');
        $this->started($orgB, 'B xodimi');

        $count = function ($user) {
            Sanctum::actingAs($user);

            return count($this->getJson('/api/monitoring/active')->assertOk()->json());
        };

        $this->assertSame(1, $count($this->makeUser('auditor', $orgA)));
        $this->assertSame(2, $count($this->makeUser('auditor')));
        $this->assertSame(1, $count($this->makeUser('operator', $orgA)));
        $this->assertSame(2, $count($this->makeUser('admin')));
    }

    public function test_session_detail_live_values(): void
    {
        [$session, $ids] = $this->started();
        $this->answer($session, $ids[0], 'A');
        Sanctum::actingAs($this->makeUser('auditor'));

        $res = $this->getJson("/api/monitoring/sessions/{$session->id}")->assertOk();

        $this->assertSame('in_progress', $res->json('status'));
        $this->assertEquals(['answered' => 1, 'total' => 2, 'correct' => 1, 'score_percent' => 50], $res->json('summary'));
        $questions = collect($res->json('questions'))->keyBy('order');
        $first = $questions->firstWhere('selected_option', 'A');
        $this->assertTrue($first['is_correct']);
        $this->assertSame('A', $first['correct_option']);
        $this->assertNotNull($first['answered_at']);
        $unanswered = $questions->firstWhere('selected_option', null);
        $this->assertNull($unanswered['is_correct']);
        $this->assertGreaterThan(0, $res->json('remaining_seconds'));
    }

    public function test_session_detail_after_submit_uses_saved_values_and_grade(): void
    {
        [$session, $ids] = $this->started();
        $this->submitAll($session, $ids);
        Sanctum::actingAs($this->makeUser('auditor'));

        $res = $this->getJson("/api/monitoring/sessions/{$session->id}")->assertOk();

        $this->assertSame('completed', $res->json('status'));
        $this->assertSame('excellent', $res->json('grade'));
        $this->assertNull($res->json('remaining_seconds'));
        $this->assertEquals(100, $res->json('summary.score_percent'));
        $this->assertSame(2, $res->json('summary.correct'));
    }

    public function test_session_detail_other_org_forbidden(): void
    {
        [$session] = $this->started($this->makeOrganization('B'));
        Sanctum::actingAs($this->makeUser('auditor', $this->makeOrganization('A')));

        $this->getJson("/api/monitoring/sessions/{$session->id}")->assertForbidden();
    }

    public function test_finished_filters(): void
    {
        [$first, $firstIds] = $this->started(null, 'Karimov Anvar');
        $this->submitAll($first, $firstIds);
        [$second, $secondIds] = $this->started(null, 'Saidova Nilufar');
        $this->submitAll($second, $secondIds, 'B');
        Sanctum::actingAs($this->makeUser('auditor'));

        $this->getJson('/api/monitoring/finished')->assertOk()->assertJsonPath('total', 2);
        $this->getJson("/api/monitoring/finished?periodic_exam_id={$first->periodic_exam_id}")
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.employee.full_name', 'Karimov Anvar')
            ->assertJsonPath('data.0.grade', 'excellent');
        $this->getJson('/api/monitoring/finished?search=Nilu')->assertJsonPath('total', 1)->assertJsonPath('data.0.grade', 'unsatisfactory');
        $this->getJson('/api/monitoring/finished?date_from='.now()->addDay()->toDateString())->assertJsonPath('total', 0);
    }

    public function test_exams_list_is_scoped(): void
    {
        $orgA = $this->makeOrganization('A');
        $examA = $this->makeExam($orgA, ['title' => 'A imtihoni']);
        $this->makeExam($this->makeOrganization('B'), ['title' => 'B imtihoni']);
        Sanctum::actingAs($this->makeUser('auditor', $orgA));

        $this->assertSame([['id' => $examA->id, 'title' => 'A imtihoni']], $this->getJson('/api/monitoring/exams')->assertOk()->json());
    }
}
