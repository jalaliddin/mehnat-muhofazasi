<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\PeriodicExam;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Testlarni real vaqtda kuzatish (faqat o'qish). Admin — hammasi; tashkiloti biriktirilgan
 * operator va auditor — faqat o'sha tashkilot; tashkilotsiz auditor — hammasi.
 */
class MonitoringController extends Controller
{
    public function active(Request $request)
    {
        $sessions = $this->scopedSessions($request->user())
            ->where('status', 'in_progress')
            ->whereNotNull('started_at')
            ->with(['employee:id,full_name', 'periodicExam:id,title,organization_id', 'periodicExam.organization:id,name'])
            ->withCount([
                'sessionQuestions as total',
                'sessionQuestions as answered' => fn ($q) => $q->whereNotNull('selected_option'),
            ])
            ->withMax('sessionQuestions as last_activity_at', 'answered_at')
            ->orderByDesc('started_at')
            ->get()
            // Brauzer yopilib, vaqti o'tib ketgan (lekin holati hali in_progress) testlar ko'rsatilmaydi.
            ->filter(fn (ExamSession $s) => $this->remainingSeconds($s) > 0)
            ->map(fn (ExamSession $s) => [
                'id'                => $s->id,
                'employee'          => $this->employee($s),
                'organization'      => $this->organization($s),
                'periodic_exam'     => ['id' => $s->periodicExam->id, 'title' => $s->periodicExam->title],
                'started_at'        => $s->started_at,
                'remaining_seconds' => $this->remainingSeconds($s),
                'answered'          => (int) $s->answered,
                'total'             => (int) $s->total,
                'last_activity_at'  => $this->isoUtc($s->last_activity_at),
            ])
            ->values();

        return response()->json($sessions);
    }

    public function finished(Request $request)
    {
        $query = $this->scopedSessions($request->user())
            ->where('status', 'completed')
            ->with(['employee:id,full_name', 'periodicExam:id,title,organization_id', 'periodicExam.organization:id,name', 'examResult:id,grade']);

        if ($request->filled('periodic_exam_id')) {
            $query->where('periodic_exam_id', $request->periodic_exam_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('finished_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('finished_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
        }

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        return response()->json(
            $query->orderByDesc('finished_at')->paginate($perPage)->through(fn (ExamSession $s) => [
                'id'            => $s->id,
                'employee'      => $this->employee($s),
                'organization'  => $this->organization($s),
                'periodic_exam' => ['id' => $s->periodicExam->id, 'title' => $s->periodicExam->title],
                'finished_at'   => $s->finished_at,
                'score_percent' => $s->score_percent === null ? null : (float) $s->score_percent,
                'grade'         => $s->examResult?->grade,
            ])
        );
    }

    public function show(Request $request, $id)
    {
        $session = ExamSession::with(['employee', 'periodicExam.organization', 'examResult', 'sessionQuestions.question'])->findOrFail($id);
        $this->authorizeSession($request->user(), $session);

        $live = $session->status === 'in_progress';

        $questions = $session->sessionQuestions->map(function ($sq) use ($live) {
            $selected = $sq->selected_option;
            $correctOption = $sq->question->correct_option;

            return [
                'order'           => $sq->question_order,
                'question_text'   => $sq->question->question_text,
                'options'         => $sq->shuffled_options,
                'selected_option' => $selected,
                'correct_option'  => $correctOption,
                // Davom etayotgan testda jonli hisoblanadi, tugaganida saqlangan baho olinadi.
                'is_correct'      => $selected === null ? null : ($live ? $selected === $correctOption : (bool) $sq->is_correct),
                'answered_at'     => $sq->answered_at,
                'points'          => $sq->question->points,
            ];
        });

        $correct = $questions->where('is_correct', true);
        $scorePercent = $live
            ? round($correct->sum('points') / max($session->total_points, 1) * 100, 2)
            : ($session->score_percent === null ? null : (float) $session->score_percent);

        return response()->json([
            'id'                => $session->id,
            'status'            => $session->status,
            'employee'          => $this->employee($session),
            'organization'      => $this->organization($session),
            'periodic_exam'     => ['id' => $session->periodicExam->id, 'title' => $session->periodicExam->title],
            'started_at'        => $session->started_at,
            'finished_at'       => $session->finished_at,
            'remaining_seconds' => $live ? $this->remainingSeconds($session) : null,
            'summary'           => [
                'answered'      => $questions->whereNotNull('selected_option')->count(),
                'total'         => $questions->count(),
                'correct'       => $correct->count(),
                'score_percent' => $scorePercent,
            ],
            'grade'             => $session->examResult?->grade,
            'questions'         => $questions->map(fn ($q) => collect($q)->except('points'))->values(),
        ]);
    }

    public function exams(Request $request)
    {
        $query = PeriodicExam::query()->select(['id', 'title'])->orderByDesc('exam_date')->orderByDesc('id');

        if ($this->limitedTo($request->user())) {
            $query->where('organization_id', $request->user()->organization_id);
        }

        return response()->json($query->get()->map(fn ($e) => ['id' => $e->id, 'title' => $e->title]));
    }

    private function scopedSessions(User $user): Builder
    {
        $query = ExamSession::query();

        if ($this->limitedTo($user)) {
            $query->whereHas('periodicExam', fn ($q) => $q->where('organization_id', $user->organization_id));
        }

        return $query;
    }

    private function authorizeSession(User $user, ExamSession $session): void
    {
        if ($this->limitedTo($user) && $session->periodicExam->organization_id !== $user->organization_id) {
            abort(403);
        }
    }

    private function limitedTo(User $user): bool
    {
        return $user->role !== 'admin' && (bool) $user->organization_id;
    }

    private function remainingSeconds(ExamSession $session): int
    {
        $deadline = $session->started_at->copy()->addMinutes($session->duration_minutes);

        return max(0, $deadline->getTimestamp() - now()->getTimestamp());
    }

    /**
     * Agregat (withMax) qiymati xom DB satri bo'lib keladi — vaqt mintaqasisiz. Uni boshqa sanalar kabi
     * UTC ISO 8601 ("…Z") ga keltiramiz, aks holda brauzer uni mahalliy vaqt deb o'qiydi.
     */
    private function isoUtc(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value, config('app.timezone'))->utc()->toJSON();
    }

    private function employee(ExamSession $session): array
    {
        return ['id' => $session->employee->id, 'full_name' => $session->employee->full_name];
    }

    private function organization(ExamSession $session): ?array
    {
        $org = $session->periodicExam->organization;

        return $org ? ['id' => $org->id, 'name' => $org->name] : null;
    }
}
