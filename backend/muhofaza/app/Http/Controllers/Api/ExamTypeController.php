<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use Illuminate\Http\Request;

class ExamTypeController extends Controller
{
    public function index()
    {
        return response()->json(ExamType::all()->map(function ($t) {
            $arr = $t->toArray();
            $arr['exam_month_name'] = $this->getMonthName($t->exam_month);
            return $arr;
        }));
    }

    public function show($id)
    {
        $examType = ExamType::findOrFail($id);
        $type = $examType->toArray();
        $type['exam_month_name'] = $this->getMonthName($examType->exam_month);
        return response()->json($type);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'frequency_months' => 'required|integer|min:1',
            'exam_month'       => 'nullable|integer|min:1|max:12',
            'exam_month_note'  => 'nullable|string|max:255',
        ]);

        $examType = ExamType::create($data);

        $type = $examType->toArray();
        $type['exam_month_name'] = $this->getMonthName($examType->exam_month);
        return response()->json($type, 201);
    }

    public function update(Request $request, $id)
    {
        $examType = ExamType::findOrFail($id);

        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'frequency_months' => 'sometimes|integer|min:1',
            'exam_month'      => 'nullable|integer|min:1|max:12',
            'exam_month_note' => 'nullable|string|max:255',
        ]);

        $examType->update($data);

        $type = $examType->toArray();
        $type['exam_month_name'] = $this->getMonthName($examType->exam_month);
        return response()->json($type);
    }

    public function destroy($id)
    {
        ExamType::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function byMonth(Request $request)
    {
        $month = $request->validate(['month' => 'required|integer|min:1|max:12'])['month'];
        return response()->json(ExamType::where('exam_month', $month)->get());
    }

    public function calendar()
    {
        $months = [
            1=>'Yanvar',2=>'Fevral',3=>'Mart',4=>'Aprel',
            5=>'May',6=>'Iyun',7=>'Iyul',8=>'Avgust',
            9=>'Sentabr',10=>'Oktabr',11=>'Noyabr',12=>'Dekabr'
        ];
        $all = ExamType::all();
        $calendar = [];
        foreach (range(1,12) as $m) {
            $calendar[$m] = [
                'month_name' => $months[$m],
                'exam_types' => $all->where('exam_month', $m)->values(),
            ];
        }
        $calendar['null'] = [
            'month_name' => 'Belgilanmagan',
            'exam_types' => $all->whereNull('exam_month')->values(),
        ];
        return response()->json(['calendar' => $calendar]);
    }

    private function getMonthName(?int $month): ?string
    {
        if (!$month) return null;
        $months = [
            1=>'Yanvar',2=>'Fevral',3=>'Mart',4=>'Aprel',
            5=>'May',6=>'Iyun',7=>'Iyul',8=>'Avgust',
            9=>'Sentabr',10=>'Oktabr',11=>'Noyabr',12=>'Dekabr'
        ];
        return $months[$month] ?? null;
    }
}
