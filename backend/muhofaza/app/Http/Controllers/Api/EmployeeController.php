<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExamResult;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Employee::with(['organization', 'department', 'position']);

        if ($user->role === 'operator' && $user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('personnel_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        return response()->json(
            Employee::with(['organization', 'department', 'position'])->findOrFail($id)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'organization_id'  => 'required|exists:organizations,id',
            'department_id'    => 'required|exists:departments,id',
            'position_id'      => 'required|exists:positions,id',
            'full_name'        => 'required|string|max:255',
            'personnel_number' => 'nullable|string|max:100',
            'phone'            => 'nullable|string|max:50',
            'hire_date'        => 'nullable|date',
            'is_active'        => 'boolean',
        ]);

        $employee = Employee::create($data);

        return response()->json($employee->load(['organization', 'department', 'position']), 201);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'organization_id'  => 'sometimes|exists:organizations,id',
            'department_id'    => 'sometimes|exists:departments,id',
            'position_id'      => 'sometimes|exists:positions,id',
            'full_name'        => 'sometimes|string|max:255',
            'personnel_number' => 'nullable|string|max:100',
            'phone'            => 'nullable|string|max:50',
            'hire_date'        => 'nullable|date',
            'is_active'        => 'boolean',
        ]);

        $employee->update($data);

        return response()->json($employee->load(['organization', 'department', 'position']));
    }

    public function destroy($id)
    {
        Employee::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function examHistory($id)
    {
        $employee = Employee::findOrFail($id);

        $results = $employee->examResults()
            ->with(['periodicExam.examType', 'periodicExam.organization', 'order'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'employee' => $employee->load(['organization', 'department', 'position']),
            'results'  => $results,
        ]);
    }

    public function upcomingExams($id)
    {
        $employee = Employee::with(['organization'])->findOrFail($id);

        // Get all passed exam results for this employee, with periodic exam and exam type
        $results = ExamResult::with(['periodicExam.examType'])
            ->where('employee_id', $id)
            ->where('is_passed', true)
            ->orderByDesc('created_at')
            ->get();

        // Group by exam_type_id, keep only the latest result per exam type
        $latestByType = [];
        foreach ($results as $result) {
            $typeId = $result->periodicExam?->exam_type_id;
            if ($typeId && !isset($latestByType[$typeId])) {
                $latestByType[$typeId] = $result;
            }
        }

        $today = \Carbon\Carbon::today();
        $upcoming = [];

        foreach ($latestByType as $typeId => $result) {
            $exam = $result->periodicExam;
            if (!$exam) continue;

            $examType = $exam->examType;
            $lastExamDate = \Carbon\Carbon::parse($exam->exam_date);
            $nextExamDate = $lastExamDate->copy()->addMonths((int)$exam->frequency_months);
            $daysRemaining = $today->diffInDays($nextExamDate, false); // negative if overdue

            // Only include: overdue OR within 6 months (180 days)
            if ($daysRemaining > 180) continue;

            $status = 'normal';
            if ($daysRemaining < 0) $status = 'overdue';
            elseif ($daysRemaining <= 7) $status = 'urgent';
            elseif ($daysRemaining <= 30) $status = 'warning';

            $upcoming[] = [
                'exam_type' => $examType?->name ?? '-',
                'exam_type_id' => $typeId,
                'periodic_exam_id' => $exam->id,
                'last_exam_date' => $lastExamDate->format('d.m.Y'),
                'next_exam_date' => $nextExamDate->format('d.m.Y'),
                'next_exam_date_raw' => $nextExamDate->toDateString(),
                'days_remaining' => (int)$daysRemaining,
                'status' => $status,
                'last_grade' => $result->grade,
                'frequency_months' => $exam->frequency_months,
            ];
        }

        // Sort: overdue first, then by days_remaining ascending
        usort($upcoming, fn($a, $b) => $a['days_remaining'] <=> $b['days_remaining']);

        return response()->json([
            'employee_id' => $employee->id,
            'full_name' => $employee->full_name,
            'upcoming_exams' => $upcoming,
        ]);
    }
}
