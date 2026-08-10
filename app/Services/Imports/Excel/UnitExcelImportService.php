<?php

namespace App\Services\Imports\Excel;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

class UnitExcelImportService
{
    /** @var list<string> */
    public const HEADERS = [
        'code',
        'name_ar',
        'symbol',
        'status',
        'notes',
    ];

    /**
     * @return array{
     *     valid: bool,
     *     row_count: int,
     *     valid_rows: int,
     *     rows: list<array{excel_row:int,code:string,name_ar:string,symbol:?string,status:string,notes:?string}>,
     *     errors: list<string>
     * }
     */
    public function analyze(string $path, ?string $originalName = null): array
    {
        if ($originalName !== null && strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
            return $this->failure('يجب أن يكون الملف بصيغة .xlsx فقط.');
        }

        if (! is_file($path) || ! is_readable($path)) {
            return $this->failure('تعذر الوصول إلى ملف Excel المرفوع.');
        }

        try {
            $reader = new Xlsx();
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = max(1, $sheet->getHighestDataRow());

            $headerRow = $sheet->rangeToArray('A1:E1', null, true, true, false)[0] ?? [];
            $headers = array_map(fn (mixed $value): string => $this->stringValue($value), $headerRow);

            if ($headers !== self::HEADERS) {
                return $this->failure(
                    'رؤوس الأعمدة غير مطابقة للقالب. الترتيب المطلوب: '.implode(', ', self::HEADERS).'.'
                );
            }

            $rows = [];
            $errors = [];
            $seenCodes = [];
            $validRows = 0;

            for ($excelRow = 2; $excelRow <= $highestRow; $excelRow++) {
                $values = $sheet->rangeToArray("A{$excelRow}:E{$excelRow}", null, true, true, false)[0] ?? [];
                $values = array_pad($values, 5, null);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $row = [
                    'excel_row' => $excelRow,
                    'code' => $this->stringValue($values[0]),
                    'name_ar' => $this->stringValue($values[1]),
                    'symbol' => $this->nullableString($values[2]),
                    'status' => $this->stringValue($values[3]) ?: 'active',
                    'notes' => $this->nullableString($values[4]),
                ];

                $validator = Validator::make(
                    $row,
                    [
                        'code' => ['required', 'string', 'max:255', Rule::unique('units', 'code')],
                        'name_ar' => ['required', 'string', 'max:255'],
                        'symbol' => ['nullable', 'string', 'max:255'],
                        'status' => ['required', Rule::in(['active', 'inactive'])],
                        'notes' => ['nullable', 'string'],
                    ],
                    [
                        'code.required' => 'رمز الوحدة مطلوب.',
                        'code.max' => 'رمز الوحدة لا يجوز أن يتجاوز 255 محرفًا.',
                        'code.unique' => 'رمز الوحدة موجود مسبقًا في النظام.',
                        'name_ar.required' => 'اسم الوحدة مطلوب.',
                        'name_ar.max' => 'اسم الوحدة لا يجوز أن يتجاوز 255 محرفًا.',
                        'symbol.max' => 'اختصار الوحدة لا يجوز أن يتجاوز 255 محرفًا.',
                        'status.in' => 'الحالة يجب أن تكون active أو inactive.',
                    ],
                );

                $rowErrors = $validator->errors()->all();
                $normalizedCode = mb_strtolower($row['code']);

                if ($normalizedCode !== '' && isset($seenCodes[$normalizedCode])) {
                    $rowErrors[] = 'رمز الوحدة مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenCodes[$normalizedCode].').';
                } elseif ($normalizedCode !== '') {
                    $seenCodes[$normalizedCode] = $excelRow;
                }

                if ($rowErrors !== []) {
                    foreach ($rowErrors as $message) {
                        $errors[] = "الصف {$excelRow}: {$message}";
                    }
                } else {
                    $validRows++;
                }

                $rows[] = $row;
            }

            if ($rows === []) {
                return $this->failure('لا يحتوي الملف على أي صف بيانات بعد صف العناوين.');
            }

            return [
                'valid' => $errors === [],
                'row_count' => count($rows),
                'valid_rows' => $validRows,
                'rows' => $rows,
                'errors' => $errors,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure('تعذر قراءة ملف Excel. تأكد من أنه ملف .xlsx سليم وغير تالف.');
        }
    }

    /**
     * @return array{
     *     valid: bool,
     *     row_count: int,
     *     valid_rows: int,
     *     rows: list<array{excel_row:int,code:string,name_ar:string,symbol:?string,status:string,notes:?string}>,
     *     errors: list<string>,
     *     imported_count: int
     * }
     */
    public function import(string $path, ?string $originalName = null): array
    {
        $analysis = $this->analyze($path, $originalName);

        if (! $analysis['valid']) {
            return [...$analysis, 'imported_count' => 0];
        }

        try {
            DB::transaction(function () use ($analysis): void {
                foreach ($analysis['rows'] as $row) {
                    unset($row['excel_row']);

                    Unit::query()->create($row);
                }
            });
        } catch (Throwable $exception) {
            report($exception);

            return [
                ...$analysis,
                'valid' => false,
                'errors' => ['حدث تعارض أثناء الحفظ. تم التراجع عن العملية بالكامل ولم يتم استيراد أي سجل.'],
                'imported_count' => 0,
            ];
        }

        return [...$analysis, 'imported_count' => count($analysis['rows'])];
    }

    /** @param list<mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->stringValue($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{valid:false,row_count:0,valid_rows:0,rows:array{},errors:list<string>}
     */
    private function failure(string $message): array
    {
        return [
            'valid' => false,
            'row_count' => 0,
            'valid_rows' => 0,
            'rows' => [],
            'errors' => [$message],
        ];
    }
}
