<?php

namespace App\Services\Imports\Excel;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use App\Services\Inventory\InventoryMovementService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LogicException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class OpeningInventoryExcelImportService
{
    /** @var list<string> */
    public const HEADERS = [
        'warehouse_code',
        'sku',
        'quantity',
        'batch_number',
        'expiry_date',
        'unit_cost',
        'movement_date',
        'notes',
    ];

    /**
     * @return array{
     *     valid: bool,
     *     row_count: int,
     *     valid_rows: int,
     *     rows: list<array{excel_row:int,warehouse_code:string,sku:string,quantity:int|float|string,batch_number:?string,expiry_date:?string,unit_cost:int|float|string,movement_date:?string,notes:string}>,
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

            $headerRow = $sheet->rangeToArray('A1:H1', null, true, true, false)[0] ?? [];
            $headers = array_map(fn (mixed $value): string => $this->stringValue($value), $headerRow);

            if ($headers !== self::HEADERS) {
                return $this->failure(
                    'رؤوس الأعمدة غير مطابقة للقالب. الترتيب المطلوب: '.implode(', ', self::HEADERS).'.'
                );
            }

            $rows = [];
            $errorsByRow = [];

            for ($excelRow = 2; $excelRow <= $highestRow; $excelRow++) {
                $values = $sheet->rangeToArray("A{$excelRow}:H{$excelRow}", null, true, true, false)[0] ?? [];
                $values = array_pad($values, 8, null);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                [$expiryDateValid, $expiryDate] = $this->parseDateValue($values[4]);
                [$movementDateValid, $movementDate] = $this->parseDateValue($values[6]);

                $row = [
                    'excel_row' => $excelRow,
                    'warehouse_code' => $this->stringValue($values[0]),
                    'sku' => $this->stringValue($values[1]),
                    'quantity' => $this->stringValue($values[2]),
                    'batch_number' => $this->nullableString($values[3]),
                    'expiry_date' => $expiryDateValid ? $expiryDate : $this->nullableString($values[4]),
                    'unit_cost' => $this->stringValue($values[5]),
                    'movement_date' => $movementDateValid ? $movementDate : $this->nullableString($values[6]),
                    'notes' => $this->stringValue($values[7]),
                ];

                $validator = Validator::make(
                    $row,
                    [
                        'warehouse_code' => ['required', 'string', 'max:255'],
                        'sku' => ['required', 'string', 'max:255'],
                        'quantity' => ['required', 'numeric', 'gt:0'],
                        'batch_number' => ['nullable', 'string', 'max:255'],
                        'expiry_date' => ['nullable', 'date_format:Y-m-d'],
                        'unit_cost' => ['required', 'numeric', 'min:0'],
                        'movement_date' => ['required', 'date_format:Y-m-d'],
                        'notes' => ['required', 'string', 'min:10', 'max:2000'],
                    ],
                    [
                        'warehouse_code.required' => 'رمز المستودع مطلوب.',
                        'warehouse_code.max' => 'رمز المستودع لا يجوز أن يتجاوز 255 محرفًا.',
                        'sku.required' => 'SKU / رمز المنتج مطلوب.',
                        'sku.max' => 'SKU / رمز المنتج لا يجوز أن يتجاوز 255 محرفًا.',
                        'quantity.required' => 'الكمية مطلوبة.',
                        'quantity.numeric' => 'الكمية يجب أن تكون رقمًا.',
                        'quantity.gt' => 'الكمية يجب أن تكون أكبر من الصفر.',
                        'batch_number.max' => 'رقم التشغيلة لا يجوز أن يتجاوز 255 محرفًا.',
                        'expiry_date.date_format' => 'تاريخ الصلاحية يجب أن يكون بصيغة YYYY-MM-DD.',
                        'unit_cost.required' => 'تكلفة الوحدة مطلوبة.',
                        'unit_cost.numeric' => 'تكلفة الوحدة يجب أن تكون رقمًا.',
                        'unit_cost.min' => 'تكلفة الوحدة لا يمكن أن تكون سالبة.',
                        'movement_date.required' => 'تاريخ الرصيد الافتتاحي مطلوب.',
                        'movement_date.date_format' => 'تاريخ الرصيد الافتتاحي يجب أن يكون بصيغة YYYY-MM-DD.',
                        'notes.required' => 'سبب الرصيد الافتتاحي مطلوب للتدقيق.',
                        'notes.min' => 'سبب الرصيد الافتتاحي يجب ألا يقل عن 10 محارف.',
                        'notes.max' => 'سبب الرصيد الافتتاحي لا يجوز أن يتجاوز 2000 محرف.',
                    ],
                );

                $rowErrors = $validator->errors()->all();

                if (! $expiryDateValid) {
                    $rowErrors[] = 'تاريخ الصلاحية يجب أن يكون تاريخ Excel صالحًا أو بصيغة YYYY-MM-DD.';
                }

                if (! $movementDateValid) {
                    $rowErrors[] = 'تاريخ الرصيد الافتتاحي يجب أن يكون تاريخ Excel صالحًا أو بصيغة YYYY-MM-DD.';
                }

                $errorsByRow[$excelRow] = $rowErrors;
                $rows[] = $row;
            }

            if ($rows === []) {
                return $this->failure('لا يحتوي الملف على أي صف بيانات بعد صف العناوين.');
            }

            $warehouseCodes = array_values(array_unique(array_filter(
                array_map(static fn (array $row): string => $row['warehouse_code'], $rows),
            )));
            $productSkus = array_values(array_unique(array_filter(
                array_map(static fn (array $row): string => $row['sku'], $rows),
            )));

            $warehouses = Warehouse::withoutGlobalScopes()
                ->whereIn('code', $warehouseCodes)
                ->get(['id', 'code', 'status'])
                ->keyBy(fn (Warehouse $warehouse): string => $this->normalizeKey($warehouse->code));

            $products = Product::withoutGlobalScopes()
                ->whereIn('sku', $productSkus)
                ->get(['id', 'sku', 'status', 'has_expiry'])
                ->keyBy(fn (Product $product): string => $this->normalizeKey($product->sku));

            $user = Auth::user();
            $scope = $user instanceof User
                ? app(AccessScopeService::class)->for($user)
                : null;
            $seenIdentities = [];

            foreach ($rows as $row) {
                $excelRow = $row['excel_row'];
                $warehouse = $warehouses->get($this->normalizeKey($row['warehouse_code']));
                $product = $products->get($this->normalizeKey($row['sku']));

                if (! $warehouse) {
                    $errorsByRow[$excelRow][] = 'المستودع '.$row['warehouse_code'].' غير موجود في النظام.';
                } elseif ($warehouse->status !== 'active') {
                    $errorsByRow[$excelRow][] = 'المستودع '.$row['warehouse_code'].' موجود لكنه غير فعال.';
                } elseif ($scope !== null && ! $scope->unrestricted && ! in_array((int) $warehouse->id, $scope->warehouseIds, true)) {
                    $errorsByRow[$excelRow][] = 'المستودع '.$row['warehouse_code'].' خارج نطاق وصول المستخدم الحالي.';
                }

                if (! $product) {
                    $errorsByRow[$excelRow][] = 'المنتج '.$row['sku'].' غير موجود في النظام.';
                } elseif ($product->status !== 'active') {
                    $errorsByRow[$excelRow][] = 'المنتج '.$row['sku'].' موجود لكنه غير فعال.';
                } elseif ((bool) $product->has_expiry && $row['expiry_date'] === null) {
                    $errorsByRow[$excelRow][] = 'المنتج '.$row['sku'].' يتطلب تاريخ صلاحية.';
                } elseif (! (bool) $product->has_expiry && $row['expiry_date'] !== null) {
                    $errorsByRow[$excelRow][] = 'المنتج '.$row['sku'].' لا يتطلب تتبع تاريخ صلاحية؛ اترك expiry_date فارغًا.';
                }

                if (($errorsByRow[$excelRow] ?? []) !== []) {
                    continue;
                }

                $identityKey = $this->stockIdentityKey(
                    (int) $warehouse->id,
                    (int) $product->id,
                    $row['batch_number'],
                    $row['expiry_date'],
                );

                if (isset($seenIdentities[$identityKey])) {
                    $firstRow = $seenIdentities[$identityKey];
                    $message = 'هوية المخزون '.$this->identityContext($row)
                        .' مكررة داخل الملف في الصفين '.$firstRow.' و'.$excelRow
                        .'. يجب إدخال كل هوية مرة واحدة فقط.';

                    $errorsByRow[$firstRow][] = $message;
                    $errorsByRow[$excelRow][] = $message;
                } else {
                    $seenIdentities[$identityKey] = $excelRow;
                }

                $existingConflict = $this->existingIdentityConflict($warehouse, $product, $row);

                if ($existingConflict !== null) {
                    $errorsByRow[$excelRow][] = $existingConflict;
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
     *     rows: list<array{excel_row:int,warehouse_code:string,sku:string,quantity:int|float|string,batch_number:?string,expiry_date:?string,unit_cost:int|float|string,movement_date:?string,notes:string}>,
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
                $inventory = app(InventoryMovementService::class);
                $warehouseCodes = array_values(array_unique(array_map(
                    static fn (array $row): string => $row['warehouse_code'],
                    $analysis['rows'],
                )));

                Warehouse::query()
                    ->whereIn('code', $warehouseCodes)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);

                $resolvedRows = [];

                foreach ($analysis['rows'] as $row) {
                    $warehouse = Warehouse::query()
                        ->where('code', $row['warehouse_code'])
                        ->where('status', 'active')
                        ->firstOrFail();

                    $product = Product::query()
                        ->where('sku', $row['sku'])
                        ->where('status', 'active')
                        ->firstOrFail();

                    $existingConflict = $this->existingIdentityConflict($warehouse, $product, $row);

                    if ($existingConflict !== null) {
                        throw new LogicException('الصف '.$row['excel_row'].': '.$existingConflict);
                    }

                    $resolvedRows[] = [$row, $warehouse, $product];
                }

                foreach ($resolvedRows as [$row, $warehouse, $product]) {
                    $inventory->addStock(
                        warehouse: $warehouse,
                        product: $product,
                        quantity: $row['quantity'],
                        batchNumber: $row['batch_number'],
                        expiryDate: $row['expiry_date'],
                        unitCost: $row['unit_cost'],
                        movementType: 'opening_balance',
                        notes: $row['notes'],
                        movementDate: $row['movement_date'],
                    );
                }
            });
        } catch (LogicException $exception) {
            return [
                ...$analysis,
                'valid' => false,
                'errors' => [$exception->getMessage()],
                'imported_count' => 0,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                ...$analysis,
                'valid' => false,
                'errors' => ['حدث تعارض أثناء حفظ الرصيد الافتتاحي. تم التراجع عن الملف بالكامل ولم يتم إنشاء أي حركة.'],
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

    /** @return array{0:bool,1:?string} */
    private function parseDateValue(mixed $value): array
    {
        if ($value === null || $this->stringValue($value) === '') {
            return [true, null];
        }

        if ($value instanceof DateTimeInterface) {
            return [true, $value->format('Y-m-d')];
        }

        $numericDateValue = null;

        if (is_int($value) || is_float($value)) {
            $numericDateValue = (float) $value;
        } elseif (is_string($value)) {
            $trimmedValue = trim($value);

            if ($trimmedValue !== '' && is_numeric($trimmedValue)) {
                $numericDateValue = (float) $trimmedValue;
            }
        }

        if ($numericDateValue !== null) {
            try {
                if ($numericDateValue <= 0) {
                    return [false, null];
                }

                return [true, ExcelDate::excelToDateTimeObject($numericDateValue)->format('Y-m-d')];
            } catch (Throwable) {
                return [false, null];
            }
        }

        $string = trim((string) $value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $string)) {
            return [false, null];
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $string);

            if (! $date || $date->format('Y-m-d') !== $string) {
                return [false, null];
            }

            return [true, $string];
        } catch (Throwable) {
            return [false, null];
        }
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
     * @param array{batch_number:?string,expiry_date:?string} $row
     */
    private function existingIdentityConflict(Warehouse $warehouse, Product $product, array $row): ?string
    {
        $history = $this->movementHistoryQuery(
            (int) $warehouse->id,
            (int) $product->id,
            $row['batch_number'],
            $row['expiry_date'],
        );

        if ((clone $history)->where('movement_type', 'opening_balance')->exists()) {
            return 'يوجد رصيد افتتاحي سابق لهوية المخزون '.$this->identityContext($row)
                .'. الرصيد الافتتاحي للتهيئة فقط؛ استخدم التصحيح أو التسوية المعتمدة لأي تغيير لاحق.';
        }

        if ($history->exists()) {
            return 'توجد حركات مخزون تشغيلية أو إدارية سابقة لهوية المخزون '.$this->identityContext($row)
                .'. لا يمكن إدخال رصيد افتتاحي بأثر رجعي؛ استخدم التصحيح أو التسوية المعتمدة.';
        }

        $balanceExists = StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->whereRaw("LOWER(batch_key) = ?", [$this->normalizeKey($row['batch_number'])])
            ->where('expiry_key', $row['expiry_date'] ?? '')
            ->exists();

        if ($balanceExists) {
            return 'يوجد رصيد مخزون حالي لهوية المخزون '.$this->identityContext($row)
                .'. لا يمكن تهيئتها كرصد افتتاحي جديد؛ استخدم التصحيح أو التسوية المعتمدة.';
        }

        return null;
    }

    private function movementHistoryQuery(
        int $warehouseId,
        int $productId,
        ?string $batchNumber,
        ?string $expiryDate,
    ): Builder {
        return StockMovement::query()
            ->where('product_id', $productId)
            ->where(function (Builder $query) use ($warehouseId): void {
                $query->where('from_warehouse_id', $warehouseId)
                    ->orWhere('to_warehouse_id', $warehouseId);
            })
            ->whereRaw("LOWER(TRIM(COALESCE(batch_number, ''))) = ?", [$this->normalizeKey($batchNumber)])
            ->when(
                $expiryDate === null,
                fn (Builder $query): Builder => $query->whereNull('expiry_date'),
                fn (Builder $query): Builder => $query->whereDate('expiry_date', $expiryDate),
            );
    }

    private function stockIdentityKey(
        int $warehouseId,
        int $productId,
        ?string $batchNumber,
        ?string $expiryDate,
    ): string {
        return implode('|', [
            $warehouseId,
            $productId,
            $this->normalizeKey($batchNumber),
            $expiryDate ?? '',
        ]);
    }

    /** @param array{warehouse_code:string,sku:string,batch_number:?string,expiry_date:?string} $row */
    private function identityContext(array $row): string
    {
        return '['
            .'المستودع: '.$row['warehouse_code']
            .'، المنتج: '.$row['sku']
            .'، التشغيلة: '.($row['batch_number'] ?? 'بدون تشغيلة')
            .'، الصلاحية: '.($row['expiry_date'] ?? 'بدون صلاحية')
            .']';
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
