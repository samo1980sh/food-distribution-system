<?php

namespace App\Services\Imports\Excel;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

class ProductCategoryExcelImportService
{
    /** @var list<string> */
    public const HEADERS = [
        'code',
        'name_ar',
        'parent_code',
        'sort_order',
        'status',
        'notes',
    ];

    /**
     * @return array{
     *     valid: bool,
     *     row_count: int,
     *     valid_rows: int,
     *     rows: list<array{excel_row:int,code:string,name_ar:string,parent_code:?string,sort_order:int|string,status:string,notes:?string}>,
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

            $headerRow = $sheet->rangeToArray('A1:F1', null, true, true, false)[0] ?? [];
            $headers = array_map(fn (mixed $value): string => $this->stringValue($value), $headerRow);

            if ($headers !== self::HEADERS) {
                return $this->failure(
                    'رؤوس الأعمدة غير مطابقة للقالب. الترتيب المطلوب: '.implode(', ', self::HEADERS).'.'
                );
            }

            $rows = [];
            $errorsByRow = [];
            $seenCodes = [];
            $workbookRowsByCode = [];

            for ($excelRow = 2; $excelRow <= $highestRow; $excelRow++) {
                $values = $sheet->rangeToArray("A{$excelRow}:F{$excelRow}", null, true, true, false)[0] ?? [];
                $values = array_pad($values, 6, null);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $sortOrderValue = $this->stringValue($values[3]);
                $row = [
                    'excel_row' => $excelRow,
                    'code' => $this->stringValue($values[0]),
                    'name_ar' => $this->stringValue($values[1]),
                    'parent_code' => $this->nullableString($values[2]),
                    'sort_order' => $sortOrderValue === '' ? 0 : $sortOrderValue,
                    'status' => $this->stringValue($values[4]) ?: 'active',
                    'notes' => $this->nullableString($values[5]),
                ];

                $validator = Validator::make(
                    $row,
                    [
                        'code' => ['required', 'string', 'max:255', Rule::unique('product_categories', 'code')],
                        'name_ar' => ['required', 'string', 'max:255'],
                        'parent_code' => ['nullable', 'string', 'max:255'],
                        'sort_order' => ['required', 'integer', 'min:0'],
                        'status' => ['required', Rule::in(['active', 'inactive'])],
                        'notes' => ['nullable', 'string'],
                    ],
                    [
                        'code.required' => 'رمز التصنيف مطلوب.',
                        'code.max' => 'رمز التصنيف لا يجوز أن يتجاوز 255 محرفًا.',
                        'code.unique' => 'رمز التصنيف موجود مسبقًا في النظام.',
                        'name_ar.required' => 'اسم التصنيف مطلوب.',
                        'name_ar.max' => 'اسم التصنيف لا يجوز أن يتجاوز 255 محرفًا.',
                        'parent_code.max' => 'رمز التصنيف الأب لا يجوز أن يتجاوز 255 محرفًا.',
                        'sort_order.integer' => 'ترتيب العرض يجب أن يكون رقمًا صحيحًا.',
                        'sort_order.min' => 'ترتيب العرض لا يجوز أن يكون سالبًا.',
                        'status.in' => 'الحالة يجب أن تكون active أو inactive.',
                    ],
                );

                $rowErrors = $validator->errors()->all();
                $normalizedCode = $this->normalizeCode($row['code']);

                if ($normalizedCode !== '' && isset($seenCodes[$normalizedCode])) {
                    $rowErrors[] = 'رمز التصنيف مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenCodes[$normalizedCode].').';
                } elseif ($normalizedCode !== '') {
                    $seenCodes[$normalizedCode] = $excelRow;
                    $workbookRowsByCode[$normalizedCode] = $row;
                }

                $errorsByRow[$excelRow] = $rowErrors;
                $rows[] = $row;
            }

            if ($rows === []) {
                return $this->failure('لا يحتوي الملف على أي صف بيانات بعد صف العناوين.');
            }

            $parentCodes = [];
            foreach ($rows as $row) {
                if ($row['parent_code'] !== null) {
                    $parentCodes[] = $row['parent_code'];
                }
            }

            $existingParents = ProductCategory::query()
                ->whereIn('code', array_values(array_unique($parentCodes)))
                ->get(['id', 'code', 'status'])
                ->keyBy(fn (ProductCategory $category): string => $this->normalizeCode($category->code));

            foreach ($rows as $row) {
                $excelRow = $row['excel_row'];
                $parentCode = $row['parent_code'];

                if ($parentCode === null) {
                    continue;
                }

                $normalizedCode = $this->normalizeCode($row['code']);
                $normalizedParentCode = $this->normalizeCode($parentCode);

                if ($normalizedCode !== '' && $normalizedCode === $normalizedParentCode) {
                    $errorsByRow[$excelRow][] = 'لا يمكن أن يكون التصنيف أبًا لنفسه.';
                    continue;
                }

                if (isset($workbookRowsByCode[$normalizedParentCode])) {
                    $parentRow = $workbookRowsByCode[$normalizedParentCode];

                    if ($parentRow['status'] !== 'active') {
                        $errorsByRow[$excelRow][] = 'التصنيف الأب '.$parentCode.' موجود داخل الملف لكنه غير فعال.';
                    }

                    continue;
                }

                $existingParent = $existingParents->get($normalizedParentCode);

                if (! $existingParent) {
                    $errorsByRow[$excelRow][] = 'رمز التصنيف الأب '.$parentCode.' غير موجود في النظام ولا داخل ملف Excel.';
                    continue;
                }

                if ($existingParent->status !== 'active') {
                    $errorsByRow[$excelRow][] = 'التصنيف الأب '.$parentCode.' موجود في النظام لكنه غير فعال.';
                }
            }

            foreach ($this->detectWorkbookCycles($workbookRowsByCode) as $cycleError) {
                $errorsByRow[$cycleError['excel_row']][] = $cycleError['message'];
            }

            $errors = [];
            $validRows = 0;

            foreach ($rows as $row) {
                $rowErrors = array_values(array_unique($errorsByRow[$row['excel_row']] ?? []));

                if ($rowErrors === []) {
                    $validRows++;
                    continue;
                }

                foreach ($rowErrors as $message) {
                    $errors[] = 'الصف '.$row['excel_row'].': '.$message;
                }
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
     *     rows: list<array{excel_row:int,code:string,name_ar:string,parent_code:?string,sort_order:int|string,status:string,notes:?string}>,
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
                $rowsByCode = [];
                foreach ($analysis['rows'] as $row) {
                    $rowsByCode[$this->normalizeCode($row['code'])] = $row;
                }

                $externalParentCodes = [];
                foreach ($analysis['rows'] as $row) {
                    if ($row['parent_code'] === null) {
                        continue;
                    }

                    $normalizedParent = $this->normalizeCode($row['parent_code']);
                    if (! isset($rowsByCode[$normalizedParent])) {
                        $externalParentCodes[] = $row['parent_code'];
                    }
                }

                $existingParents = ProductCategory::query()
                    ->whereIn('code', array_values(array_unique($externalParentCodes)))
                    ->get(['id', 'code'])
                    ->keyBy(fn (ProductCategory $category): string => $this->normalizeCode($category->code));

                $createdIds = [];
                $pending = $analysis['rows'];

                while ($pending !== []) {
                    $nextPending = [];
                    $createdThisPass = 0;

                    foreach ($pending as $row) {
                        $parentId = null;

                        if ($row['parent_code'] !== null) {
                            $normalizedParent = $this->normalizeCode($row['parent_code']);

                            if (isset($rowsByCode[$normalizedParent])) {
                                if (! isset($createdIds[$normalizedParent])) {
                                    $nextPending[] = $row;
                                    continue;
                                }

                                $parentId = $createdIds[$normalizedParent];
                            } else {
                                $parentId = $existingParents->get($normalizedParent)?->id;
                            }
                        }

                        $category = ProductCategory::query()->create([
                            'parent_id' => $parentId,
                            'code' => $row['code'],
                            'name_ar' => $row['name_ar'],
                            'sort_order' => (int) $row['sort_order'],
                            'status' => $row['status'],
                            'notes' => $row['notes'],
                        ]);

                        $createdIds[$this->normalizeCode($row['code'])] = $category->id;
                        $createdThisPass++;
                    }

                    if ($createdThisPass === 0 && $nextPending !== []) {
                        throw new \RuntimeException('Unable to resolve product category parent hierarchy.');
                    }

                    $pending = $nextPending;
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

    /**
     * @param array<string, array{excel_row:int,code:string,name_ar:string,parent_code:?string,sort_order:int|string,status:string,notes:?string}> $rowsByCode
     * @return list<array{excel_row:int,message:string}>
     */
    private function detectWorkbookCycles(array $rowsByCode): array
    {
        $state = [];
        $errors = [];
        $reported = [];

        $visit = function (string $code) use (&$visit, &$state, &$errors, &$reported, $rowsByCode): void {
            if (($state[$code] ?? 0) === 2) {
                return;
            }

            if (($state[$code] ?? 0) === 1) {
                return;
            }

            $state[$code] = 1;
            $row = $rowsByCode[$code] ?? null;

            if ($row !== null && $row['parent_code'] !== null) {
                $parentCode = $this->normalizeCode($row['parent_code']);

                if (isset($rowsByCode[$parentCode])) {
                    if (($state[$parentCode] ?? 0) === 1) {
                        foreach ([$code, $parentCode] as $cycleCode) {
                            if (isset($reported[$cycleCode], $rowsByCode[$cycleCode])) {
                                continue;
                            }

                            $reported[$cycleCode] = true;
                            $errors[] = [
                                'excel_row' => $rowsByCode[$cycleCode]['excel_row'],
                                'message' => 'يوجد تسلسل دائري في علاقة التصنيف الأب داخل ملف Excel.',
                            ];
                        }
                    } else {
                        $visit($parentCode);
                    }
                }
            }

            $state[$code] = 2;
        };

        foreach (array_keys($rowsByCode) as $code) {
            $visit($code);
        }

        return $errors;
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

    private function normalizeCode(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
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
