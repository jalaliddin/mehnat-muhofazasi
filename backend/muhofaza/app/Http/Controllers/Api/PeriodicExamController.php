<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PeriodicExam;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class PeriodicExamController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = PeriodicExam::with([
            'organization', 'examType', 'order', 'creator', 'certificateType',
            // Tahrirlash shaklida tanlangan xodimlar ko'rinishi uchun — faqat ID'lar.
            'employees' => fn ($q) => $q->select('employees.id'),
        ]);

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
            PeriodicExam::with(['organization', 'examType', 'order', 'creator', 'employees', 'certificateType'])->findOrFail($id)
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
            ...$this->courseDateRules($request->input('course_start_date')),
            ...self::PARTICIPANT_RULES,
        ]);

        $employeeIds = $this->pullParticipants($data, (int) $data['organization_id']);

        $examDate = Carbon::parse($data['exam_date']);
        $data['next_exam_date'] = $examDate->copy()->addMonths((int) $data['frequency_months']);
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'planned';

        $exam = PeriodicExam::create($data);
        $exam->employees()->sync($employeeIds ?? []);

        return response()->json($exam->load(['organization', 'examType', 'order', 'creator', 'certificateType']), 201);
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
            ...$this->courseDateRules(
                $request->exists('course_start_date')
                    ? $request->input('course_start_date')
                    : $exam->course_start_date?->toDateString()
            ),
            ...self::PARTICIPANT_RULES,
        ]);

        $employeeIds = $this->pullParticipants($data, (int) ($data['organization_id'] ?? $exam->organization_id));

        if (isset($data['exam_date']) || isset($data['frequency_months'])) {
            $examDate = Carbon::parse($data['exam_date'] ?? $exam->exam_date);
            $months = (int) ($data['frequency_months'] ?? $exam->frequency_months);
            $data['next_exam_date'] = $examDate->copy()->addMonths($months);
        }

        $exam->update($data);

        // employee_ids yuborilmagan bo'lsa (masalan, faqat nom o'zgartirilsa) ishtirokchilarga tegilmaydi.
        if ($employeeIds !== null) {
            $exam->employees()->sync($employeeIds);
        }

        return response()->json($exam->load(['organization', 'examType', 'order', 'creator', 'certificateType']));
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

    /**
     * Kurs tugash sanasi boshlanish sanasidan oldin bo'lmasligi kerak. Boshlanish sanasi so'rovda
     * bo'lmasa (faqat tugash sanasi yangilansa), bazadagi qiymat bilan solishtiriladi.
     */
    private const PARTICIPANT_RULES = [
        'employee_ids'   => 'sometimes|array',
        'employee_ids.*' => 'integer|distinct|exists:employees,id',
    ];

    /**
     * Ishtirokchilar ro'yxatini $data dan ajratib oladi va ularning imtihon tashkilotiga tegishliligini tekshiradi.
     * Kalit yuborilmagan bo'lsa null qaytaradi.
     *
     * @return list<int>|null
     */
    private function pullParticipants(array &$data, int $organizationId): ?array
    {
        if (! array_key_exists('employee_ids', $data)) {
            return null;
        }

        $ids = array_values(array_unique(array_map('intval', $data['employee_ids'] ?? [])));
        unset($data['employee_ids']);

        $foreign = Employee::whereIn('id', $ids)->where('organization_id', '!=', $organizationId)->exists();
        if ($foreign) {
            throw ValidationException::withMessages([
                'employee_ids' => ["Xodimlar imtihon tashkilotiga tegishli bo'lishi kerak"],
            ]);
        }

        return $ids;
    }

    private function courseDateRules(?string $start): array
    {
        $endRules = ['nullable', 'date'];

        if ($start && strtotime($start) !== false) {
            $endRules[] = 'after_or_equal:'.$start;
        }

        return [
            'course_start_date'   => 'nullable|date',
            'course_end_date'     => $endRules,
            'certificate_type_id' => 'nullable|integer|exists:certificate_types,id',
        ];
    }
}
