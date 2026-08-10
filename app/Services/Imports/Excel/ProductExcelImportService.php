<?php

namespace App\Services\Imports\Excel;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

class ProductExcelImportService
{
    /** @var list<string> */
    public const HEADERS = [
        'sku',
        'barcode',
        'name_ar',
        'category_code',
        'unit_code',
        'purchase_price',
        'sale_price',
        'wholesale_price',
        'min_stock',
        'has_expiry',
        'status',
        'notes',
    ];

    /**
     * @return array{
     *     valid: bool,
     *     row_count: int,
     *     valid_rows: int,
     *     rows: list<array{excel_row:int,sku:string,barcode:?string,name_ar:string,category_code:?string,unit_code:?string,purchase_price:int|float|string,sale_price:int|float|string,wholesale_price:int|float|string,min_stock:int|float|string,has_expiry:bool|string,status:string,notes:?string}>,
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

            $headerRow = $sheet->rangeToArray('A1:L1', null, true, true, false)[0] ?? [];
            $headers = array_map(fn (mixed $value): string => $this->stringValue($value), $headerRow);

            if ($headers !== self::HEADERS) {
                return $this->failure(
                    'رؤوس الأعمدة غير مطابقة للقالب. الترتيب المطلوب: '.implode(', ', self::HEADERS).'.'
                );
            }

            $rows = [];
            $errorsByRow = [];
            $seenSkus = [];
            $seenBarcodes = [];

            for ($excelRow = 2; $excelRow <= $highestRow; $excelRow++) {
                $values = $sheet->rangeToArray("A{$excelRow}:L{$excelRow}", null, true, true, false)[0] ?? [];
                $values = array_pad($values, 12, null);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $purchasePrice = $this->stringValue($values[5]);
                $salePrice = $this->stringValue($values[6]);
                $wholesalePrice = $this->stringValue($values[7]);
                $minStock = $this->stringValue($values[8]);
                [$hasExpiryIsValid, $hasExpiry] = $this->parseBoolean($values[9]);

                $row = [
                    'excel_row' => $excelRow,
                    'sku' => $this->stringValue($values[0]),
                    'barcode' => $this->nullableString($values[1]),
                    'name_ar' => $this->stringValue($values[2]),
                    'category_code' => $this->nullableString($values[3]),
                    'unit_code' => $this->nullableString($values[4]),
                    'purchase_price' => $purchasePrice === '' ? 0 : $purchasePrice,
                    'sale_price' => $salePrice === '' ? 0 : $salePrice,
                    'wholesale_price' => $wholesalePrice === '' ? 0 : $wholesalePrice,
                    'min_stock' => $minStock === '' ? 0 : $minStock,
                    'has_expiry' => $hasExpiryIsValid ? $hasExpiry : $this->stringValue($values[9]),
                    'status' => $this->stringValue($values[10]) ?: 'active',
                    'notes' => $this->nullableString($values[11]),
                ];

                $validator = Validator::make(
                    $row,
                    [
                        'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')],
                        'barcode' => ['nullable', 'string', 'max:255', Rule::unique('products', 'barcode')],
                        'name_ar' => ['required', 'string', 'max:255'],
                        'category_code' => ['nullable', 'string', 'max:255'],
                        'unit_code' => ['nullable', 'string', 'max:255'],
                        'purchase_price' => ['required', 'numeric', 'min:0'],
                        'sale_price' => ['required', 'numeric', 'min:0'],
                        'wholesale_price' => ['required', 'numeric', 'min:0'],
                        'min_stock' => ['required', 'numeric', 'min:0'],
                        'has_expiry' => ['required', 'boolean'],
                        'status' => ['required', Rule::in(['active', 'inactive'])],
                        'notes' => ['nullable', 'string'],
                    ],
                    [
                        'sku.required' => 'SKU / رمز المنتج مطلوب.',
                        'sku.max' => 'SKU / رمز المنتج لا يجوز أن يتجاوز 255 محرفًا.',
                        'sku.unique' => 'SKU / رمز المنتج موجود مسبقًا في النظام.',
                        'barcode.max' => 'الباركود لا يجوز أن يتجاوز 255 محرفًا.',
                        'barcode.unique' => 'الباركود موجود مسبقًا في النظام.',
                        'name_ar.required' => 'اسم المنتج مطلوب.',
                        'name_ar.max' => 'اسم المنتج لا يجوز أن يتجاوز 255 محرفًا.',
                        'category_code.max' => 'رمز التصنيف لا يجوز أن يتجاوز 255 محرفًا.',
                        'unit_code.max' => 'رمز الوحدة لا يجوز أن يتجاوز 255 محرفًا.',
                        'purchase_price.numeric' => 'سعر الشراء المرجعي يجب أن يكون رقمًا.',
                        'purchase_price.min' => 'سعر الشراء المرجعي لا يجوز أن يكون سالبًا.',
                        'sale_price.numeric' => 'سعر البيع يجب أن يكون رقمًا.',
                        'sale_price.min' => 'سعر البيع لا يجوز أن يكون سالبًا.',
                        'wholesale_price.numeric' => 'سعر الجملة يجب أن يكون رقمًا.',
                        'wholesale_price.min' => 'سعر الجملة لا يجوز أن يكون سالبًا.',
                        'min_stock.numeric' => 'حد التنبيه للمخزون يجب أن يكون رقمًا.',
                        'min_stock.min' => 'حد التنبيه للمخزون لا يجوز أن يكون سالبًا.',
                        'has_expiry.boolean' => 'has_expiry يجب أن تكون 1 أو 0.',
                        'status.in' => 'الحالة يجب أن تكون active أو inactive.',
                    ],
                );

                $rowErrors = $validator->errors()->all();

                if (! $hasExpiryIsValid) {
                    $rowErrors[] = 'has_expiry يجب أن تكون 1 أو 0.';
                }

                $normalizedSku = $this->normalizeKey($row['sku']);
                if ($normalizedSku !== '' && isset($seenSkus[$normalizedSku])) {
                    $rowErrors[] = 'SKU / رمز المنتج مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenSkus[$normalizedSku].').';
                } elseif ($normalizedSku !== '') {
                    $seenSkus[$normalizedSku] = $excelRow;
                }

                if ($row['barcode'] !== null) {
                    $normalizedBarcode = $this->normalizeKey($row['barcode']);

                    if (isset($seenBarcodes[$normalizedBarcode])) {
                        $rowErrors[] = 'الباركود مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenBarcodes[$normalizedBarcode].').';
                    } else {
                        $seenBarcodes[$normalizedBarcode] = $excelRow;
                    }
                }

                $errorsByRow[$excelRow] = $rowErrors;
                $rows[] = $row;
            }

            if ($rows === []) {
                return $this->failure('لا يحتوي الملف على أي صف بيانات بعد صف العناوين.');
            }

            $categoryCodes = array_values(array_unique(array_filter(
                array_map(static fn (array $row): ?string => $row['category_code'], $rows),
                static fn (?string $value): bool => $value !== null && $value !== '',
            )));
            $unitCodes = array_values(array_unique(array_filter(
                array_map(static fn (array $row): ?string => $row['unit_code'], $rows),
                static fn (?string $value): bool => $value !== null && $value !== '',
            )));

            $categories = ProductCategory::query()
                ->whereIn('code', $categoryCodes)
                ->get(['id', 'code', 'status'])
                ->keyBy(fn (ProductCategory $category): string => $this->normalizeKey($category->code));

            $units = Unit::query()
                ->whereIn('code', $unitCodes)
                ->get(['id', 'code', 'status'])
                ->keyBy(fn (Unit $unit): string => $this->normalizeKey($unit->code));

            foreach ($rows as $row) {
                $excelRow = $row['excel_row'];

                if ($row['category_code'] !== null) {
                    $category = $categories->get($this->normalizeKey($row['category_code']));

                    if (! $category) {
                        $errorsByRow[$excelRow][] = 'رمز التصنيف '.$row['category_code'].' غير موجود في النظام.';
                    } elseif ($category->status !== 'active') {
                        $errorsByRow[$excelRow][] = 'التصنيف '.$row['category_code'].' موجود لكنه غير فعال.';
                    }
                }

                if ($row['unit_code'] !== null) {
                    $unit = $units->get($this->normalizeKey($row['unit_code']));

                    if (! $unit) {
                        $errorsByRow[$excelRow][] = 'رمز الوحدة '.$row['unit_code'].' غير موجود في النظام.';
                    } elseif ($unit->status !== 'active') {
                        $errorsByRow[$excelRow][] = 'الوحدة '.$row['unit_code'].' موجودة لكنها غير فعالة.';
                    }
                }
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
     *     rows: list<array{excel_row:int,sku:string,barcode:?string,name_ar:string,category_code:?string,unit_code:?string,purchase_price:int|float|string,sale_price:int|float|string,wholesale_price:int|float|string,min_stock:int|float|string,has_expiry:bool|string,status:string,notes:?string}>,
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
                $categoryCodes = array_values(array_unique(array_filter(
                    array_map(static fn (array $row): ?string => $row['category_code'], $analysis['rows']),
                    static fn (?string $value): bool => $value !== null && $value !== '',
                )));
                $unitCodes = array_values(array_unique(array_filter(
                    array_map(static fn (array $row): ?string => $row['unit_code'], $analysis['rows']),
                    static fn (?string $value): bool => $value !== null && $value !== '',
                )));

                $categories = ProductCategory::query()
                    ->whereIn('code', $categoryCodes)
                    ->where('status', 'active')
                    ->get(['id', 'code'])
                    ->keyBy(fn (ProductCategory $category): string => $this->normalizeKey($category->code));

                $units = Unit::query()
                    ->whereIn('code', $unitCodes)
                    ->where('status', 'active')
                    ->get(['id', 'code'])
                    ->keyBy(fn (Unit $unit): string => $this->normalizeKey($unit->code));

                foreach ($analysis['rows'] as $row) {
                    $categoryId = null;
                    if ($row['category_code'] !== null) {
                        $categoryId = $categories->get($this->normalizeKey($row['category_code']))?->id;

                        if ($categoryId === null) {
                            throw new \RuntimeException('Product category changed while importing.');
                        }
                    }

                    $unitId = null;
                    if ($row['unit_code'] !== null) {
                        $unitId = $units->get($this->normalizeKey($row['unit_code']))?->id;

                        if ($unitId === null) {
                            throw new \RuntimeException('Product unit changed while importing.');
                        }
                    }

                    Product::query()->create([
                        'sku' => $row['sku'],
                        'barcode' => $row['barcode'],
                        'name_ar' => $row['name_ar'],
                        'category_id' => $categoryId,
                        'unit_id' => $unitId,
                        'purchase_price' => $row['purchase_price'],
                        'sale_price' => $row['sale_price'],
                        'wholesale_price' => $row['wholesale_price'],
                        'min_stock' => $row['min_stock'],
                        'has_expiry' => (bool) $row['has_expiry'],
                        'status' => $row['status'],
                        'notes' => $row['notes'],
                    ]);
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

    /** @return array{0:bool,1:bool} */
    private function parseBoolean(mixed $value): array
    {
        if (is_bool($value)) {
            return [true, $value];
        }

        if ($value === null || $this->stringValue($value) === '') {
            return [true, true];
        }

        if (is_int($value) || is_float($value)) {
            if ((float) $value === 1.0) {
                return [true, true];
            }

            if ((float) $value === 0.0) {
                return [true, false];
            }

            return [false, false];
        }

        $normalized = mb_strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'yes', 'نعم'], true)) {
            return [true, true];
        }

        if (in_array($normalized, ['0', 'false', 'no', 'لا'], true)) {
            return [true, false];
        }

        return [false, false];
    }

    private function normalizeKey(?string $value): string
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
