<?php

namespace App\Services\Imports\Excel;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;
use Throwable;

class CustomerExcelImportService
{
    /** @var list<string> */
    public const HEADERS = [
        'code',
        'name',
        'owner_name',
        'customer_type',
        'phone',
        'mobile',
        'area_code',
        'route_code',
        'address',
        'latitude',
        'longitude',
        'credit_limit',
        'credit_days',
        'payment_type',
        'status',
        'notes',
    ];

    /** @var list<string> */
    public const CUSTOMER_TYPES = [
        'grocery',
        'supermarket',
        'restaurant',
        'wholesaler',
        'mini_market',
        'other',
    ];

    /** @var list<string> */
    public const PAYMENT_TYPES = ['cash', 'credit', 'weekly', 'monthly'];

    /** @var list<string> */
    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array{
     *     valid: bool,
     *     row_count: int,
     *     valid_rows: int,
     *     rows: list<array{excel_row:int,code:string,name:string,owner_name:?string,customer_type:string,phone:?string,mobile:?string,area_code:?string,route_code:?string,address:?string,latitude:int|float|string|null,longitude:int|float|string|null,credit_limit:int|float|string,credit_days:int|string,payment_type:string,status:string,notes:?string}>,
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

            $headerRow = $sheet->rangeToArray('A1:P1', null, true, true, false)[0] ?? [];
            $headers = array_map(fn (mixed $value): string => $this->stringValue($value), $headerRow);

            if ($headers !== self::HEADERS) {
                return $this->failure(
                    'رؤوس الأعمدة غير مطابقة للقالب. الترتيب المطلوب: '.implode(', ', self::HEADERS).'.'
                );
            }

            $rows = [];
            $errorsByRow = [];
            $seenCodes = [];

            for ($excelRow = 2; $excelRow <= $highestRow; $excelRow++) {
                $values = $sheet->rangeToArray("A{$excelRow}:P{$excelRow}", null, true, true, false)[0] ?? [];
                $values = array_pad($values, 16, null);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $row = [
                    'excel_row' => $excelRow,
                    'code' => $this->stringValue($values[0]),
                    'name' => $this->stringValue($values[1]),
                    'owner_name' => $this->nullableString($values[2]),
                    'customer_type' => $this->stringValue($values[3]) ?: 'grocery',
                    'phone' => $this->nullableString($values[4]),
                    'mobile' => $this->nullableString($values[5]),
                    'area_code' => $this->nullableString($values[6]),
                    'route_code' => $this->nullableString($values[7]),
                    'address' => $this->nullableString($values[8]),
                    'latitude' => $this->numberCandidate($values[9]),
                    'longitude' => $this->numberCandidate($values[10]),
                    'credit_limit' => $this->numberCandidate($values[11], 0),
                    'credit_days' => $this->integerCandidate($values[12], 30),
                    'payment_type' => $this->stringValue($values[13]) ?: 'cash',
                    'status' => $this->stringValue($values[14]) ?: 'active',
                    'notes' => $this->nullableString($values[15]),
                ];

                $validator = Validator::make(
                    $row,
                    [
                        'code' => ['required', 'string', 'max:255', Rule::unique('customers', 'code')],
                        'name' => ['required', 'string', 'max:255'],
                        'owner_name' => ['nullable', 'string', 'max:255'],
                        'customer_type' => ['required', Rule::in(self::CUSTOMER_TYPES)],
                        'phone' => ['nullable', 'string', 'max:255'],
                        'mobile' => ['nullable', 'string', 'max:255'],
                        'area_code' => ['nullable', 'string', 'max:255'],
                        'route_code' => ['nullable', 'string', 'max:255'],
                        'address' => ['nullable', 'string', 'max:255'],
                        'latitude' => ['nullable', 'numeric'],
                        'longitude' => ['nullable', 'numeric'],
                        'credit_limit' => ['required', 'numeric', 'min:0'],
                        'credit_days' => ['required', 'integer', 'min:1', 'max:365'],
                        'payment_type' => ['required', Rule::in(self::PAYMENT_TYPES)],
                        'status' => ['required', Rule::in(self::STATUSES)],
                        'notes' => ['nullable', 'string'],
                    ],
                    [
                        'code.required' => 'رمز العميل مطلوب.',
                        'code.max' => 'رمز العميل لا يجوز أن يتجاوز 255 محرفًا.',
                        'code.unique' => 'رمز العميل موجود مسبقًا في النظام.',
                        'name.required' => 'اسم العميل مطلوب.',
                        'name.max' => 'اسم العميل لا يجوز أن يتجاوز 255 محرفًا.',
                        'owner_name.max' => 'اسم صاحب المحل لا يجوز أن يتجاوز 255 محرفًا.',
                        'customer_type.in' => 'نوع العميل غير صالح. استخدم: '.implode(', ', self::CUSTOMER_TYPES).'.',
                        'phone.max' => 'الهاتف لا يجوز أن يتجاوز 255 محرفًا.',
                        'mobile.max' => 'الموبايل لا يجوز أن يتجاوز 255 محرفًا.',
                        'area_code.max' => 'رمز المنطقة لا يجوز أن يتجاوز 255 محرفًا.',
                        'route_code.max' => 'رمز خط التوزيع لا يجوز أن يتجاوز 255 محرفًا.',
                        'address.max' => 'العنوان لا يجوز أن يتجاوز 255 محرفًا.',
                        'latitude.numeric' => 'خط العرض يجب أن يكون رقمًا صالحًا.',
                        'longitude.numeric' => 'خط الطول يجب أن يكون رقمًا صالحًا.',
                        'credit_limit.numeric' => 'حد الائتمان يجب أن يكون رقمًا صالحًا.',
                        'credit_limit.min' => 'حد الائتمان لا يمكن أن يكون سالبًا.',
                        'credit_days.integer' => 'مدة الائتمان يجب أن تكون عددًا صحيحًا من الأيام.',
                        'credit_days.min' => 'مدة الائتمان يجب ألا تقل عن يوم واحد.',
                        'credit_days.max' => 'مدة الائتمان يجب ألا تتجاوز 365 يومًا.',
                        'payment_type.in' => 'طريقة الدفع غير صالحة. استخدم: '.implode(', ', self::PAYMENT_TYPES).'.',
                        'status.in' => 'الحالة يجب أن تكون active أو inactive.',
                    ],
                );

                $rowErrors = $validator->errors()->all();

                $normalizedCode = $this->normalizeKey($row['code']);
                if ($normalizedCode !== '' && isset($seenCodes[$normalizedCode])) {
                    $rowErrors[] = 'رمز العميل مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenCodes[$normalizedCode].').';
                } elseif ($normalizedCode !== '') {
                    $seenCodes[$normalizedCode] = $excelRow;
                }

                $errorsByRow[$excelRow] = $rowErrors;
                $rows[] = $row;
            }

            if ($rows === []) {
                return $this->failure('لا يحتوي الملف على أي صف بيانات بعد صف العناوين.');
            }

            $areas = $this->loadAreasByCode($this->referenceCodes($rows, 'area_code'));
            $routes = $this->loadRoutesByCode($this->referenceCodes($rows, 'route_code'));

            foreach ($rows as $index => $row) {
                $excelRow = $row['excel_row'];
                $area = null;

                if ($row['area_code'] !== null) {
                    $area = $areas->get($this->normalizeKey($row['area_code']));

                    if (! $area) {
                        $errorsByRow[$excelRow][] = 'رمز المنطقة '.$row['area_code'].' غير موجود في النظام.';
                    } elseif ($area->status !== 'active') {
                        $errorsByRow[$excelRow][] = 'المنطقة '.$row['area_code'].' موجودة لكنها غير فعالة.';
                    }
                }

                if ($row['route_code'] === null) {
                    continue;
                }

                $route = $routes->get($this->normalizeKey($row['route_code']));

                if (! $route) {
                    $errorsByRow[$excelRow][] = 'رمز خط التوزيع '.$row['route_code'].' غير موجود في النظام.';
                    continue;
                }

                if ($route->status !== 'active') {
                    $errorsByRow[$excelRow][] = 'خط التوزيع '.$row['route_code'].' موجود لكنه غير فعال.';
                    continue;
                }

                if (! $route->area) {
                    $errorsByRow[$excelRow][] = 'خط التوزيع '.$row['route_code'].' لا يرتبط بمنطقة صالحة.';
                    continue;
                }

                if ($route->area->status !== 'active') {
                    $errorsByRow[$excelRow][] = 'خط التوزيع '.$row['route_code'].' يتبع منطقة غير فعالة.';
                    continue;
                }

                if ($row['area_code'] === null) {
                    $rows[$index]['area_code'] = (string) $route->area->code;
                    continue;
                }

                if ($area && $area->status === 'active' && (int) $route->area_id !== (int) $area->id) {
                    $errorsByRow[$excelRow][] = 'خط التوزيع '.$row['route_code'].' لا يتبع المنطقة '.$row['area_code'].'.';
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
     *     rows: list<array<string,mixed>>,
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
                $areas = Area::query()
                    ->whereIn('code', $this->referenceCodes($analysis['rows'], 'area_code'))
                    ->where('status', 'active')
                    ->get(['id', 'code', 'status'])
                    ->keyBy(fn (Area $area): string => $this->normalizeKey($area->code));

                $routes = DistributionRoute::query()
                    ->whereIn('code', $this->referenceCodes($analysis['rows'], 'route_code'))
                    ->where('status', 'active')
                    ->whereHas('area', fn ($query) => $query->where('status', 'active'))
                    ->with('area:id,code,name_ar,status')
                    ->get()
                    ->keyBy(fn (DistributionRoute $route): string => $this->normalizeKey($route->code));

                foreach ($analysis['rows'] as $row) {
                    $areaId = null;
                    if ($row['area_code'] !== null) {
                        $area = $areas->get($this->normalizeKey($row['area_code']));
                        if (! $area) {
                            throw new RuntimeException('Area changed while importing.');
                        }
                        $areaId = $area->id;
                    }

                    $routeId = null;
                    if ($row['route_code'] !== null) {
                        $route = $routes->get($this->normalizeKey($row['route_code']));
                        if (! $route || ! $route->area || $route->area->status !== 'active') {
                            throw new RuntimeException('Route changed while importing.');
                        }

                        $routeId = $route->id;
                        $areaId ??= $route->area_id;

                        if ((int) $route->area_id !== (int) $areaId) {
                            throw new RuntimeException('Route area changed while importing.');
                        }
                    }

                    // Creating through the real Customer model intentionally keeps
                    // OperationalContextValidator::validateCustomer() active on every save.
                    Customer::query()->create([
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'owner_name' => $row['owner_name'],
                        'phone' => $row['phone'],
                        'mobile' => $row['mobile'],
                        'customer_type' => $row['customer_type'],
                        'area_id' => $areaId,
                        'route_id' => $routeId,
                        'address' => $row['address'],
                        'latitude' => $row['latitude'],
                        'longitude' => $row['longitude'],
                        'credit_limit' => $row['credit_limit'],
                        'credit_days' => $row['credit_days'],
                        'payment_type' => $row['payment_type'],
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

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private function referenceCodes(array $rows, string $key): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (array $row): ?string => $row[$key] ?? null, $rows),
            static fn (?string $value): bool => $value !== null && $value !== '',
        )));
    }

    /** @param list<string> $codes @return Collection<string,Area> */
    private function loadAreasByCode(array $codes): Collection
    {
        if ($codes === []) {
            return collect();
        }

        return Area::query()
            ->whereIn('code', $codes)
            ->get(['id', 'code', 'status'])
            ->keyBy(fn (Area $area): string => $this->normalizeKey($area->code));
    }

    /** @param list<string> $codes @return Collection<string,DistributionRoute> */
    private function loadRoutesByCode(array $codes): Collection
    {
        if ($codes === []) {
            return collect();
        }

        return DistributionRoute::query()
            ->whereIn('code', $codes)
            ->with('area:id,code,name_ar,status')
            ->get()
            ->keyBy(fn (DistributionRoute $route): string => $this->normalizeKey($route->code));
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

    private function numberCandidate(mixed $value, int|float|null $default = null): int|float|string|null
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $default;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return trim((string) $value);
    }

    private function integerCandidate(mixed $value, int $default): int|string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return floor($value) === $value ? (int) $value : (string) $value;
        }

        return trim((string) $value);
    }

    /** @return array{valid:false,row_count:0,valid_rows:0,rows:array{},errors:list<string>} */
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
