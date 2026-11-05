<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use Illuminate\Http\Request;

/**
 * Imtihon turining test tarkibi: qaysi bazadan nechta savol olinadi.
 */
class ExamTypeQuestionBankController extends Controller
{
    public function show($id)
    {
        return response()->json($this->composition(ExamType::findOrFail($id)));
    }

    public function update(Request $request, $id)
    {
        $type = ExamType::findOrFail($id);

        $data = $request->validate([
            'banks'                    => 'present|array',
            'banks.*.question_bank_id' => 'required|distinct|exists:question_banks,id',
            'banks.*.question_count'   => 'required|integer|min:1|max:500',
        ]);

        $type->questionBanks()->sync(
            collect($data['banks'])->mapWithKeys(fn ($row) => [
                $row['question_bank_id'] => ['question_count' => $row['question_count']],
            ])->all()
        );

        return response()->json($this->composition($type));
    }

    private function composition(ExamType $type): array
    {
        return $type->questionBanks()
            ->withCount('questions')
            ->orderBy('exam_type_question_bank.id')
            ->get()
            ->map(fn ($bank) => [
                'question_bank_id' => $bank->id,
                'name'             => $bank->name,
                'question_count'   => (int) $bank->pivot->question_count,
                'available'        => $bank->questions_count,
            ])
            ->values()
            ->all();
    }
}
