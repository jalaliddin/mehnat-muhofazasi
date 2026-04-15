<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamResult;
use Illuminate\Http\Request;

class ExamResultController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = ExamResult::with(['periodicExam.organization', 'employee', 'order']);

        if ($user->role === 'operator' && $user->organization_id) {
            $query->whereHas('periodicExam', fn($q) => $q->where('organization_id', $user->organization_id));
        }

        if ($request->has('periodic_exam_id')) {
            $query->where('periodic_exam_id', $request->periodic_exam_id);
        }

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('grade')) {
            $query->where('grade', $request->grade);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        return response()->json(
            ExamResult::with(['periodicExam', 'employee', 'order'])->findOrFail($id)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'periodic_exam_id' => 'required|exists:periodic_exams,id',
            'employee_id'      => 'required|exists:employees,id',
            'order_id'         => 'nullable|exists:orders,id',
            'grade'            => 'required|in:excellent,good,satisfactory,unsatisfactory',
            'score'            => 'nullable|integer|min:0|max:100',
            'notes'            => 'nullable|string',
            'retake_exam_id'   => 'nullable|exists:periodic_exams,id',
        ]);

        $data = $this->setPassedStatus($data);

        $result = ExamResult::create($data);

        return response()->json($result->load(['periodicExam', 'employee', 'order']), 201);
    }

    public function update(Request $request, $id)
    {
        $result = ExamResult::findOrFail($id);

        $data = $request->validate([
            'periodic_exam_id' => 'sometimes|exists:periodic_exams,id',
            'employee_id'      => 'sometimes|exists:employees,id',
            'order_id'         => 'nullable|exists:orders,id',
            'grade'            => 'sometimes|in:excellent,good,satisfactory,unsatisfactory',
            'score'            => 'nullable|integer|min:0|max:100',
            'notes'            => 'nullable|string',
            'retake_exam_id'   => 'nullable|exists:periodic_exams,id',
        ]);

        $data = $this->setPassedStatus($data);

        $result->update($data);

        return response()->json($result->load(['periodicExam', 'employee', 'order']));
    }

    public function destroy($id)
    {
        ExamResult::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function bulk(Request $request)
    {
        $request->validate([
            'results'                  => 'required|array',
            'results.*.periodic_exam_id' => 'required|exists:periodic_exams,id',
            'results.*.employee_id'    => 'required|exists:employees,id',
            'results.*.grade'          => 'required|in:excellent,good,satisfactory,unsatisfactory',
            'results.*.order_id'       => 'nullable|exists:orders,id',
            'results.*.score'          => 'nullable|integer|min:0|max:100',
            'results.*.notes'          => 'nullable|string',
        ]);

        $created = [];

        foreach ($request->results as $resultData) {
            $resultData = $this->setPassedStatus($resultData);
            $result = ExamResult::updateOrCreate(
                [
                    'periodic_exam_id' => $resultData['periodic_exam_id'],
                    'employee_id'      => $resultData['employee_id'],
                ],
                $resultData
            );
            $created[] = $result;
        }

        return response()->json($created, 201);
    }

    public function unsatisfactory(Request $request)
    {
        $user = $request->user();
        $query = ExamResult::with(['periodicExam.organization', 'employee', 'order'])
            ->where('grade', 'unsatisfactory')
            ->where('is_passed', false);

        if ($user->role === 'operator' && $user->organization_id) {
            $query->whereHas('periodicExam', fn($q) => $q->where('organization_id', $user->organization_id));
        }

        return response()->json($query->get());
    }

    private function setPassedStatus(array $data): array
    {
        if (isset($data['grade'])) {
            if ($data['grade'] === 'unsatisfactory') {
                $data['is_passed'] = false;
                $data['retake_required'] = true;
            } else {
                $data['is_passed'] = true;
                $data['retake_required'] = false;
            }
        }

        return $data;
    }
}
