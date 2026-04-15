<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamResult;
use App\Models\PeriodicExam;
use Illuminate\Http\Request;

class RetakeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = ExamResult::with(['periodicExam.organization', 'employee', 'order'])
            ->where('retake_required', true)
            ->where('is_passed', false);

        if ($user->role === 'operator' && $user->organization_id) {
            $query->whereHas('periodicExam', fn($q) => $q->where('organization_id', $user->organization_id));
        }

        return response()->json($query->get());
    }

    public function store(Request $request, $resultId)
    {
        $oldResult = ExamResult::findOrFail($resultId);

        $data = $request->validate([
            'periodic_exam_id' => 'required|exists:periodic_exams,id',
            'grade'            => 'required|in:excellent,good,satisfactory,unsatisfactory',
            'order_id'         => 'nullable|exists:orders,id',
            'score'            => 'nullable|integer|min:0|max:100',
            'notes'            => 'nullable|string',
        ]);

        $isPassed = $data['grade'] !== 'unsatisfactory';

        $newResult = ExamResult::create([
            'periodic_exam_id' => $data['periodic_exam_id'],
            'employee_id'      => $oldResult->employee_id,
            'order_id'         => $data['order_id'] ?? null,
            'grade'            => $data['grade'],
            'score'            => $data['score'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'is_passed'        => $isPassed,
            'retake_required'  => !$isPassed,
        ]);

        // Mark old result as retake assigned
        $oldResult->update([
            'retake_required' => false,
            'retake_exam_id'  => $data['periodic_exam_id'],
        ]);

        return response()->json($newResult->load(['periodicExam', 'employee']), 201);
    }
}
