<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PeriodicExam;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PeriodicExamController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = PeriodicExam::with(['organization', 'examType', 'order', 'creator']);

        if ($user->role === 'operator' && $user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('exam_type_id')) {
            $query->where('exam_type_id', $request->exam_type_id);
        }

        return response()->json($query->orderByDesc('exam_date')->get());
    }

    public function show($id)
    {
        return response()->json(
            PeriodicExam::with(['organization', 'examType', 'order', 'creator', 'employees'])->findOrFail($id)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'exam_type_id'    => 'required|exists:exam_types,id',
            'order_id'        => 'nullable|exists:orders,id',
            'title'           => 'required|string|max:255',
            'exam_date'       => 'required|date',
            'frequency_months' => 'required|integer|min:1',
            'status'           => 'nullable|in:planned,in_progress,completed',
        ]);

        $examDate = Carbon::parse($data['exam_date']);
        $data['next_exam_date'] = $examDate->copy()->addMonths((int) $data['frequency_months']);
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'planned';

        $exam = PeriodicExam::create($data);

        return response()->json($exam->load(['organization', 'examType', 'order', 'creator']), 201);
    }

    public function update(Request $request, $id)
    {
        $exam = PeriodicExam::findOrFail($id);

        $data = $request->validate([
            'organization_id' => 'sometimes|exists:organizations,id',
            'exam_type_id'    => 'sometimes|exists:exam_types,id',
            'order_id'        => 'nullable|exists:orders,id',
            'title'           => 'sometimes|string|max:255',
            'exam_date'       => 'sometimes|date',
            'frequency_months' => 'sometimes|integer|min:1',
            'status'           => 'sometimes|in:planned,in_progress,completed',
        ]);

        if (isset($data['exam_date']) || isset($data['frequency_months'])) {
            $examDate = Carbon::parse($data['exam_date'] ?? $exam->exam_date);
            $months = (int) ($data['frequency_months'] ?? $exam->frequency_months);
            $data['next_exam_date'] = $examDate->copy()->addMonths($months);
        }

        $exam->update($data);

        return response()->json($exam->load(['organization', 'examType', 'order', 'creator']));
    }

    public function destroy($id)
    {
        PeriodicExam::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function start($id)
    {
        $exam = PeriodicExam::findOrFail($id);
        $exam->update(['status' => 'in_progress']);

        return response()->json($exam);
    }

    public function complete($id)
    {
        $exam = PeriodicExam::findOrFail($id);
        $exam->update(['status' => 'completed']);

        return response()->json($exam);
    }

    public function examEmployees($id)
    {
        $exam = PeriodicExam::with('employees.department', 'employees.position')->findOrFail($id);

        return response()->json($exam->employees);
    }
}
