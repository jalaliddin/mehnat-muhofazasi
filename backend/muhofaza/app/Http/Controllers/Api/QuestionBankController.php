<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionBankController extends Controller
{
    private const MESSAGES = [
        'name.required' => 'Baza nomi kiritilishi shart',
        'name.unique'   => 'Bu nomdagi baza allaqachon mavjud',
    ];

    public function index()
    {
        return response()->json(
            $this->query()->orderBy('name')->get()->map(fn (QuestionBank $bank) => $this->present($bank))
        );
    }

    public function show($id)
    {
        return response()->json($this->present($this->query()->findOrFail($id)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:question_banks,name',
            'description' => 'nullable|string',
        ], self::MESSAGES);

        $bank = QuestionBank::create($data);

        return response()->json($this->present($this->query()->findOrFail($bank->id)), 201);
    }

    public function update(Request $request, $id)
    {
        $bank = QuestionBank::findOrFail($id);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', Rule::unique('question_banks', 'name')->ignore($bank->id)],
            'description' => 'nullable|string',
        ], self::MESSAGES);

        $bank->update($data);

        return response()->json($this->present($this->query()->findOrFail($bank->id)));
    }

    public function destroy($id)
    {
        $bank = QuestionBank::with('examTypes:id,name')->findOrFail($id);

        if ($bank->examTypes->isNotEmpty()) {
            return response()->json([
                'message' => 'Baza testlarda ishlatilmoqda: '.$bank->examTypes->pluck('name')->implode(', '),
            ], 422);
        }

        $bank->delete(); // savollari DB cascade bilan o'chadi

        return response()->json(['message' => "O'chirildi"]);
    }

    private function query()
    {
        return QuestionBank::withCount('questions')->with('examTypes:id,name');
    }

    private function present(QuestionBank $bank): array
    {
        return [
            'id'              => $bank->id,
            'name'            => $bank->name,
            'description'     => $bank->description,
            'questions_count' => $bank->questions_count,
            'exam_types'      => $bank->examTypes->map(fn ($type) => [
                'id'             => $type->id,
                'name'           => $type->name,
                'question_count' => $type->pivot->question_count,
            ])->values(),
        ];
    }
}
