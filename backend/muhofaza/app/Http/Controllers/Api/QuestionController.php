<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['exam_type_id' => 'required|exists:exam_types,id']);

        return response()->json(
            Question::where('exam_type_id', $request->exam_type_id)->orderBy('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $question = Question::create($data);

        return response()->json($question, 201);
    }

    public function update(Request $request, $id)
    {
        $question = Question::findOrFail($id);

        $data = $this->validateData($request);

        $question->update($data);

        return response()->json($question);
    }

    public function destroy($id)
    {
        Question::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'exam_type_id'   => 'required|exists:exam_types,id',
            'question_text'  => 'required|string',
            'options'        => 'required|array|min:2',
            'options.*.key'  => 'required|string|size:1',
            'options.*.text' => 'required|string',
            'correct_option' => 'required|string|size:1',
            'points'         => 'nullable|integer|min:1|max:100',
        ]);

        $validKeys = array_column($data['options'], 'key');
        if (! in_array($data['correct_option'], $validKeys, true)) {
            abort(422, 'correct_option mavjud variantlardan biriga mos kelishi kerak');
        }

        $data['points'] = $data['points'] ?? 1;

        return $data;
    }
}
