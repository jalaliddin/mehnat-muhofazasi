<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class QuestionImportController extends Controller
{
    private const OPTION_KEYS = ['A', 'B', 'C', 'D'];

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['savol', 'variant_a', 'variant_b', 'variant_c', 'variant_d', 'togri_javob', 'ball'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            'Mehnat muhofazasi bo\'yicha asosiy hujjat qaysi?', 'Mehnat kodeksi', 'Fuqarolik kodeksi', 'Soliq kodeksi', 'Jinoyat kodeksi', 'A', 1,
        ], null, 'A2');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'savollar-shablon.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'exam_type_id' => 'required|exists:exam_types,id',
            'file'         => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $imported = 0;
        $errors = [];
        $toInsert = [];
        $now = now();

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === 1) {
                continue; // header row
            }

            $questionText = trim((string) ($row['A'] ?? ''));
            if ($questionText === '') {
                continue;
            }

            $options = [];
            foreach (self::OPTION_KEYS as $i => $key) {
                $text = trim((string) ($row[chr(ord('B') + $i)] ?? ''));
                if ($text !== '') {
                    $options[] = ['key' => $key, 'text' => $text];
                }
            }

            $correctOption = trim(strtoupper((string) ($row['F'] ?? '')));
            $points = (int) ($row['G'] ?? 1) ?: 1;

            if (count($options) < 2) {
                $errors[] = "Qator {$rowNumber}: kamida 2 ta variant kerak";
                continue;
            }

            if (! in_array($correctOption, array_column($options, 'key'), true)) {
                $errors[] = "Qator {$rowNumber}: to'g'ri javob ({$correctOption}) variantlar orasida topilmadi";
                continue;
            }

            $toInsert[] = [
                'exam_type_id'   => $request->exam_type_id,
                'question_text'  => $questionText,
                'options'        => json_encode($options),
                'correct_option' => $correctOption,
                'points'         => $points,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
            $imported++;
        }

        if ($toInsert) {
            DB::transaction(function () use ($toInsert) {
                foreach (array_chunk($toInsert, 200) as $chunk) {
                    Question::insert($chunk);
                }
            });
        }

        return response()->json([
            'imported' => $imported,
            'errors'   => $errors,
        ]);
    }
}
