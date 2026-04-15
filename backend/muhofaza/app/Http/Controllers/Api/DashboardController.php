<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExamResult;
use App\Models\PeriodicExam;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();
        $isOperator = $user->role === 'operator';
        $orgId = $user->organization_id;

        $employeesQuery = Employee::query();
        $examsQuery = PeriodicExam::query();
        $resultsQuery = ExamResult::query();

        if ($isOperator && $orgId) {
            $employeesQuery->where('organization_id', $orgId);
            $examsQuery->where('organization_id', $orgId);
            $resultsQuery->whereHas('periodicExam', fn($q) => $q->where('organization_id', $orgId));
        }

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $in30Days = $now->copy()->addDays(30);

        $totalEmployees = $employeesQuery->where('is_active', true)->count();

        $thisMonthExams = (clone $examsQuery)
            ->whereBetween('exam_date', [$startOfMonth, $endOfMonth])
            ->count();

        $upcomingExams = (clone $examsQuery)
            ->where('status', '!=', 'completed')
            ->whereBetween('exam_date', [$now, $in30Days])
            ->count();

        $unsatisfactoryResults = (clone $resultsQuery)
            ->where('grade', 'unsatisfactory')
            ->count();

        return response()->json([
            'total_employees'        => $totalEmployees,
            'this_month_exams'       => $thisMonthExams,
            'upcoming_30_days'       => $upcomingExams,
            'unsatisfactory_results' => $unsatisfactoryResults,
        ]);
    }

    public function upcomingExams(Request $request)
    {
        $user = $request->user();
        $isOperator = $user->role === 'operator';
        $orgId = $user->organization_id;

        $now = Carbon::now();
        $in30Days = $now->copy()->addDays(30);

        $query = PeriodicExam::with(['organization', 'examType'])
            ->where('status', '!=', 'completed')
            ->whereBetween('exam_date', [$now, $in30Days])
            ->orderBy('exam_date');

        if ($isOperator && $orgId) {
            $query->where('organization_id', $orgId);
        }

        return response()->json($query->get());
    }

    public function overdueExams(Request $request)
    {
        $user = $request->user();
        $isOperator = $user->role === 'operator';
        $orgId = $user->organization_id;

        $now = Carbon::now();

        $query = PeriodicExam::with(['organization', 'examType'])
            ->where('status', '!=', 'completed')
            ->where('exam_date', '<', $now)
            ->orderBy('exam_date');

        if ($isOperator && $orgId) {
            $query->where('organization_id', $orgId);
        }

        return response()->json($query->get());
    }

    public function upcomingEmployees(Request $request)
    {
        $user = $request->user();
        $isOperator = $user->role === 'operator';
        $orgId = $user->organization_id;

        $employeesQuery = Employee::with(['organization', 'position'])->where('is_active', true);
        if ($isOperator && $orgId) {
            $employeesQuery->where('organization_id', $orgId);
        }
        $employees = $employeesQuery->get();

        $today = Carbon::today();
        $rows = [];

        foreach ($employees as $employee) {
            // Get latest passed result per exam type
            $results = ExamResult::with(['periodicExam.examType'])
                ->where('employee_id', $employee->id)
                ->where('is_passed', true)
                ->orderByDesc('created_at')
                ->get();

            $latestByType = [];
            foreach ($results as $result) {
                $typeId = $result->periodicExam?->exam_type_id;
                if ($typeId && !isset($latestByType[$typeId])) {
                    $latestByType[$typeId] = $result;
                }
            }

            foreach ($latestByType as $typeId => $result) {
                $exam = $result->periodicExam;
                if (!$exam) continue;

                $lastExamDate = Carbon::parse($exam->exam_date);
                $nextExamDate = $lastExamDate->copy()->addMonths((int)$exam->frequency_months);
                $daysRemaining = $today->diffInDays($nextExamDate, false);

                // Only include: overdue or within 60 days
                if ($daysRemaining > 60) continue;

                $status = 'normal';
                if ($daysRemaining < 0) $status = 'overdue';
                elseif ($daysRemaining <= 7) $status = 'urgent';
                elseif ($daysRemaining <= 30) $status = 'warning';

                $rows[] = [
                    'employee_id'       => $employee->id,
                    'full_name'         => $employee->full_name,
                    'organization'      => $employee->organization?->name,
                    'position'          => $employee->position?->name,
                    'exam_type'         => $exam->examType?->name ?? '-',
                    'last_exam_date'    => $lastExamDate->format('d.m.Y'),
                    'next_exam_date'    => $nextExamDate->format('d.m.Y'),
                    'days_remaining'    => (int)$daysRemaining,
                    'status'            => $status,
                ];
            }
        }

        // Sort: overdue first, then by days_remaining
        usort($rows, fn($a, $b) => $a['days_remaining'] <=> $b['days_remaining']);

        return response()->json(array_slice($rows, 0, 50));
    }
}
