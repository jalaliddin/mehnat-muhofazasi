<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CertificateType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CertificateTypeController extends Controller
{
    private const MESSAGES = [
        'required'    => "Maydon to'ldirilishi shart",
        'name.unique' => 'Bu nomdagi tur allaqachon mavjud',
        'hours.min'   => 'Soat 1 dan kam bo\'lmasligi kerak',
        'hours.max'   => 'Soat 2000 dan oshmasligi kerak',
        'max'         => 'Matn 255 belgidan oshmasligi kerak',
    ];

    public function index()
    {
        return response()->json(CertificateType::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $type = CertificateType::create($this->validated($request));

        return response()->json($type, 201);
    }

    public function update(Request $request, $id)
    {
        $type = CertificateType::findOrFail($id);
        $type->update($this->validated($request, $type->id));

        return response()->json($type);
    }

    public function destroy($id)
    {
        $type = CertificateType::findOrFail($id);

        if ($type->periodicExams()->exists()) {
            return response()->json(['message' => 'Tur davriy imtihonlarda ishlatilmoqda'], 422);
        }

        $type->delete();

        return response()->json(['message' => "O'chirildi"]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255', Rule::unique('certificate_types', 'name')->ignore($ignoreId)],
            'hours'       => 'required|integer|min:1|max:2000',
            'title_text'  => 'required|string|max:255',
            'course_text' => 'required|string|max:255',
        ], self::MESSAGES);
    }
}
