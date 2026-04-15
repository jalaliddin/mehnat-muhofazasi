<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExamResult;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\PeriodicExam;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function employees(Request $request)
    {
        $user = $request->user();
        $query = ExamResult::with([
            'employee.organization',
            'employee.department',
            'employee.position',
            'periodicExam.examType',
        ]);

        if ($user->role === 'operator' && $user->organization_id) {
            $query->whereHas('periodicExam', fn($q) => $q->where('organization_id', $user->organization_id));
        }

        if ($request->organization_id) {
            $query->whereHas('periodicExam', fn($q) => $q->where('organization_id', $request->organization_id));
        }
        if ($request->department_id) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $request->department_id));
        }
        if ($request->exam_type_id) {
            $query->whereHas('periodicExam', fn($q) => $q->where('exam_type_id', $request->exam_type_id));
        }
        if ($request->year) {
            $query->whereHas('periodicExam', fn($q) => $q->whereYear('exam_date', $request->year));
        }
        if ($request->grade) {
            $query->where('grade', $request->grade);
        }
        if ($request->passed_only === 'true') {
            $query->where('is_passed', true);
        }

        $results = $query->orderBy('created_at', 'desc')->get();

        $total = $results->count();
        $passed = $results->where('is_passed', true)->count();
        $failed = $results->where('is_passed', false)->count();

        return response()->json([
            'data' => $results,
            'summary' => [
                'total' => $total,
                'passed' => $passed,
                'failed' => $failed,
                'pass_rate' => $total > 0 ? round($passed / $total * 100, 1) : 0,
            ],
        ]);
    }

    public function exams(Request $request)
    {
        $user = $request->user();
        $query = PeriodicExam::with(['organization', 'examType', 'results']);

        if ($user->role === 'operator' && $user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }
        if ($request->organization_id) $query->where('organization_id', $request->organization_id);
        if ($request->exam_type_id) $query->where('exam_type_id', $request->exam_type_id);
        if ($request->status) $query->where('status', $request->status);
        if ($request->year) $query->whereYear('exam_date', $request->year);
        if ($request->month) $query->whereMonth('exam_date', $request->month);

        $exams = $query->get();

        $allResults = $exams->flatMap->results;
        $total = $allResults->count();
        $passed = $allResults->where('is_passed', true)->count();
        $failed = $allResults->where('is_passed', false)->count();
        $retakes = $allResults->where('retake_required', true)->count();

        $gradeStats = [
            'excellent' => $allResults->where('grade', 'excellent')->count(),
            'good' => $allResults->where('grade', 'good')->count(),
            'satisfactory' => $allResults->where('grade', 'satisfactory')->count(),
            'unsatisfactory' => $allResults->where('grade', 'unsatisfactory')->count(),
        ];

        // Monthly breakdown
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthExams = $exams->filter(fn($e) => Carbon::parse($e->exam_date)->month === $m);
            $monthlyData[$m] = [
                'month' => $m,
                'count' => $monthExams->count(),
                'results' => $monthExams->flatMap->results->count(),
            ];
        }

        return response()->json([
            'exams' => $exams,
            'summary' => [
                'total_exams' => $exams->count(),
                'total_results' => $total,
                'passed' => $passed,
                'failed' => $failed,
                'retakes' => $retakes,
                'pass_rate' => $total > 0 ? round($passed / $total * 100, 1) : 0,
                'grade_stats' => $gradeStats,
            ],
            'monthly' => $monthlyData,
        ]);
    }

    public function organizations(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $today = Carbon::today();

        $orgs = Organization::with(['employees', 'periodicExams.results'])->get();

        $data = $orgs->map(function ($org) use ($year, $today) {
            $employees = $org->employees->count();
            $exams = $org->periodicExams->filter(fn($e) => Carbon::parse($e->exam_date)->year == $year);
            $allResults = $exams->flatMap->results;
            $total = $allResults->count();
            $passed = $allResults->where('is_passed', true)->count();
            $failed = $allResults->where('is_passed', false)->count();

            // Grade average: excellent=5, good=4, satisfactory=3, unsatisfactory=2
            $gradeMap = ['excellent' => 5, 'good' => 4, 'satisfactory' => 3, 'unsatisfactory' => 2];
            $avgGrade = $total > 0
                ? round($allResults->avg(fn($r) => $gradeMap[$r->grade] ?? 0), 1)
                : 0;

            // Upcoming (next_exam_date within 30 days)
            $upcoming = $org->periodicExams->filter(function ($e) use ($today) {
                $next = Carbon::parse($e->next_exam_date);
                $days = $today->diffInDays($next, false);
                return $days >= 0 && $days <= 30;
            })->count();

            return [
                'id' => $org->id,
                'name' => $org->name,
                'type' => $org->type,
                'employees' => $employees,
                'total_exams' => $exams->count(),
                'total_results' => $total,
                'passed' => $passed,
                'failed' => $failed,
                'pass_rate' => $total > 0 ? round($passed / $total * 100, 1) : 0,
                'avg_grade' => $avgGrade,
                'upcoming_count' => $upcoming,
            ];
        });

        return response()->json([
            'year' => (int)$year,
            'organizations' => $data,
            'totals' => [
                'employees' => $data->sum('employees'),
                'total_exams' => $data->sum('total_exams'),
                'total_results' => $data->sum('total_results'),
                'passed' => $data->sum('passed'),
                'failed' => $data->sum('failed'),
                'upcoming_count' => $data->sum('upcoming_count'),
            ],
        ]);
    }

    public function annualPlan(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $user = $request->user();

        $monthNames = [
            1=>'Yanvar',2=>'Fevral',3=>'Mart',4=>'Aprel',
            5=>'May',6=>'Iyun',7=>'Iyul',8=>'Avgust',
            9=>'Sentabr',10=>'Oktabr',11=>'Noyabr',12=>'Dekabr'
        ];

        $examTypesQuery = ExamType::whereNotNull('exam_month');
        $examsQuery = PeriodicExam::with(['examType', 'organization'])
            ->whereYear('exam_date', $year);

        if ($user->role === 'operator' && $user->organization_id) {
            $examsQuery->where('organization_id', $user->organization_id);
        }
        if ($request->organization_id) {
            $examsQuery->where('organization_id', $request->organization_id);
        }

        $examTypes = $examTypesQuery->get();
        $exams = $examsQuery->get();

        $months = [];
        $totalPlanned = 0;
        $totalCompleted = 0;

        foreach (range(1, 12) as $m) {
            $plannedTypes = $examTypes->where('exam_month', $m);
            $monthExams = $exams->filter(fn($e) => Carbon::parse($e->exam_date)->month === $m);
            $completed = $monthExams->where('status', 'completed')->count();
            $planned = $plannedTypes->count();

            $totalPlanned += $planned;
            $totalCompleted += $completed;

            $months[] = [
                'month' => $m,
                'month_name' => $monthNames[$m],
                'planned_types' => $plannedTypes->values(),
                'planned' => $planned,
                'completed' => $completed,
                'actual_exams' => $monthExams->values(),
                'percentage' => $planned > 0 ? min(100, round($completed / $planned * 100)) : null,
            ];
        }

        return response()->json([
            'year' => (int)$year,
            'months' => $months,
            'totals' => [
                'planned' => $totalPlanned,
                'completed' => $totalCompleted,
                'percentage' => $totalPlanned > 0 ? round($totalCompleted / $totalPlanned * 100) : 0,
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $type = $request->type ?? 'employees';
        // Basic PDF response - return data for now
        return response()->json(['message' => 'PDF export', 'type' => $type]);
    }
}
