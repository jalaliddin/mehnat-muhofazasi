<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Models\ExamResult;
use App\Models\ExamSession;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class OnlineTestCertificateTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    private function takeTest(ExamSession $session, bool $allCorrect): TestResponse
    {
        $questions = $this->postJson("/api/public/exam-sessions/{$session->token}/start")->assertOk()->json('questions');
        $answers = collect($questions)->map(fn ($q) => [
            'exam_session_question_id' => $q['exam_session_question_id'],
            'selected_option'          => $allCorrect ? 'A' : 'B',
        ])->all();

        return $this->postJson("/api/public/exam-sessions/{$session->token}/submit", ['answers' => $answers]);
    }

    public function test_passing_submission_issues_certificate(): void
    {
        Storage::fake('local');
        $exam = $this->makeExam();

        $this->takeTest($this->makeSession($exam, $this->makeEmployee($exam)), true)
            ->assertOk()
            ->assertJsonPath('certificate.status', 'pending_signature')
            ->assertJsonPath('certificate.number', null);

        $this->assertSame(1, Certificate::count());
    }

    public function test_failing_submission_has_no_certificate(): void
    {
        Storage::fake('local');
        $exam = $this->makeExam();

        $this->takeTest($this->makeSession($exam, $this->makeEmployee($exam)), false)
            ->assertOk()
            ->assertJsonStructure(['certificate'])
            ->assertJsonPath('certificate', null);

        $this->assertSame(0, Certificate::count());
    }

    public function test_certificate_failure_does_not_break_submission(): void
    {
        $this->mock(CertificateService::class)->shouldReceive('issue')->once()->andThrow(new RuntimeException('dompdf'));
        $exam = $this->makeExam();

        $this->takeTest($this->makeSession($exam, $this->makeEmployee($exam)), true)
            ->assertOk()
            ->assertJsonStructure(['certificate'])
            ->assertJsonPath('certificate', null);

        $this->assertSame(1, ExamResult::count());
    }

    public function test_show_includes_certificate_after_completion(): void
    {
        Storage::fake('local');
        $exam = $this->makeExam();
        $session = $this->makeSession($exam, $this->makeEmployee($exam));
        $this->takeTest($session, true);

        $this->getJson("/api/public/exam-sessions/{$session->token}")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonStructure(['certificate' => ['number', 'status', 'public_code']]);
    }
}
