<?php

namespace App\Services\Imports\Excel;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;
use Throwable;

class DistributionRouteExcelImportService
{
    /** @var list<string> */
    public const HEADERS = [
        'code',
        'name',
        'area_code',
        'vehicle_code',
        'driver_code',
        'sales_representative_code',
        'visit_days',
        'status',
        'notes',
    ];

    /** @var list<string> */
    public const VISIT_DAYS = [
        'saturday',
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
    ];

    /** @var list<string> */
    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array{
     *     valid: bool,
     *     row_count: int,
     *     valid_rows: int,
     *     rows: list<array{excel_row:int,code:string,name:string,area_code:string,vehicle_code:?string,driver_code:?string,sales_representative_code:?string,visit_days:list<string>,status:string,notes:?string}>,
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

            $headerRow = $sheet->rangeToArray('A1:I1', null, true, true, false)[0] ?? [];
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
                $values = $sheet->rangeToArray("A{$excelRow}:I{$excelRow}", null, true, true, false)[0] ?? [];
                $values = array_pad($values, 9, null);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $visitDaysInput = $this->nullableString($values[6]);
                $visitDaysResult = $this->parseVisitDays($visitDaysInput);

                $row = [
                    'excel_row' => $excelRow,
                    'code' => $this->stringValue($values[0]),
                    'name' => $this->stringValue($values[1]),
                    'area_code' => $this->stringValue($values[2]),
                    'vehicle_code' => $this->nullableString($values[3]),
                    'driver_code' => $this->nullableString($values[4]),
                    'sales_representative_code' => $this->nullableString($values[5]),
                    'visit_days' => $visitDaysResult['days'],
                    'status' => $this->stringValue($values[7]) ?: 'active',
                    'notes' => $this->nullableString($values[8]),
                ];

                $validator = Validator::make(
                    $row,
                    [
                        'code' => ['required', 'string', 'max:255', Rule::unique('distribution_routes', 'code')],
                        'name' => ['required', 'string', 'max:255'],
                        'area_code' => ['required', 'string', 'max:255'],
                        'vehicle_code' => ['nullable', 'string', 'max:255'],
                        'driver_code' => ['nullable', 'string', 'max:255'],
                        'sales_representative_code' => ['nullable', 'string', 'max:255'],
                        'visit_days' => ['array'],
                        'status' => ['required', Rule::in(self::STATUSES)],
                        'notes' => ['nullable', 'string'],
                    ],
                    [
                        'code.required' => 'رمز خط التوزيع مطلوب.',
                        'code.max' => 'رمز خط التوزيع لا يجوز أن يتجاوز 255 محرفًا.',
                        'code.unique' => 'رمز خط التوزيع موجود مسبقًا في النظام.',
                        'name.required' => 'اسم خط التوزيع مطلوب.',
                        'name.max' => 'اسم خط التوزيع لا يجوز أن يتجاوز 255 محرفًا.',
                        'area_code.required' => 'رمز المنطقة مطلوب.',
                        'area_code.max' => 'رمز المنطقة لا يجوز أن يتجاوز 255 محرفًا.',
                        'vehicle_code.max' => 'رمز السيارة لا يجوز أن يتجاوز 255 محرفًا.',
                        'driver_code.max' => 'رمز السائق لا يجوز أن يتجاوز 255 محرفًا.',
                        'sales_representative_code.max' => 'رمز مندوب المبيعات لا يجوز أن يتجاوز 255 محرفًا.',
                        'status.in' => 'الحالة يجب أن تكون active أو inactive.',
                    ],
                );

                $rowErrors = [
                    ...$validator->errors()->all(),
                    ...$visitDaysResult['errors'],
                ];

                $normalizedCode = $this->normalizeKey($row['code']);
                if ($normalizedCode !== '' && isset($seenCodes[$normalizedCode])) {
                    $rowErrors[] = 'رمز خط التوزيع مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenCodes[$normalizedCode].').';
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
            $vehicles = $this->loadVehiclesByCode($this->referenceCodes($rows, 'vehicle_code'));
            $drivers = $this->loadEmployeesByCode($this->referenceCodes($rows, 'driver_code'));
            $representatives = $this->loadEmployeesByCode($this->referenceCodes($rows, 'sales_representative_code'));

            foreach ($rows as $row) {
                $excelRow = $row['excel_row'];

                $area = $areas->get($this->normalizeKey($row['area_code']));
                if (! $area) {
                    $errorsByRow[$excelRow][] = 'رمز المنطقة '.$row['area_code'].' غير موجود في النظام.';
                } elseif ($area->status !== 'active') {
                    $errorsByRow[$excelRow][] = 'المنطقة '.$row['area_code'].' موجودة لكنها غير فعالة.';
                }

                if ($row['vehicle_code'] !== null) {
                    $vehicle = $vehicles->get($this->normalizeKey($row['vehicle_code']));

                    if (! $vehicle) {
                        $errorsByRow[$excelRow][] = 'رمز السيارة '.$row['vehicle_code'].' غير موجود في النظام.';
                    } elseif ($vehicle->status !== 'active') {
                        $errorsByRow[$excelRow][] = 'السيارة '.$row['vehicle_code'].' موجودة لكنها غير فعالة.';
                    }
                }

                if ($row['driver_code'] !== null) {
                    $driver = $drivers->get($this->normalizeKey($row['driver_code']));

                    if (! $driver) {
                        $errorsByRow[$excelRow][] = 'رمز السائق '.$row['driver_code'].' غير موجود في النظام.';
                    } elseif ($driver->status !== 'active') {
                        $errorsByRow[$excelRow][] = 'السائق '.$row['driver_code'].' موجود لكنه غير فعال.';
                    } elseif (! $driver->canFulfillOperationalRole(UserRole::DRIVER)) {
                        $errorsByRow[$excelRow][] = 'الموظف '.$row['driver_code'].' غير مؤهل للعمل كسائق على خط التوزيع.';
                    }
                }

                if ($row['sales_representative_code'] !== null) {
                    $representative = $representatives->get($this->normalizeKey($row['sales_representative_code']));

                    if (! $representative) {
                        $errorsByRow[$excelRow][] = 'رمز مندوب المبيعات '.$row['sales_representative_code'].' غير موجود في النظام.';
                    } elseif ($representative->status !== 'active') {
                        $errorsByRow[$excelRow][] = 'مندوب المبيعات '.$row['sales_representative_code'].' موجود لكنه غير فعال.';
                    } elseif (! $representative->canFulfillOperationalRole(UserRole::SALES_REPRESENTATIVE)) {
                        $errorsByRow[$excelRow][] = 'الموظف '.$row['sales_representative_code'].' غير مؤهل للعمل كمندوب مبيعات على خط التوزيع.';
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
     *     rows: list<array{excel_row:int,code:string,name:string,area_code:string,vehicle_code:?string,driver_code:?string,sales_representative_code:?string,visit_days:list<string>,status:string,notes:?string}>,
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

                $vehicles = Vehicle::query()
                    ->whereIn('code', $this->referenceCodes($analysis['rows'], 'vehicle_code'))
                    ->where('status', 'active')
                    ->get(['id', 'code', 'status'])
                    ->keyBy(fn (Vehicle $vehicle): string => $this->normalizeKey($vehicle->code));

                $employees = Employee::query()
                    ->whereIn('employee_code', array_values(array_unique([
                        ...$this->referenceCodes($analysis['rows'], 'driver_code'),
                        ...$this->referenceCodes($analysis['rows'], 'sales_representative_code'),
                    ])))
                    ->with('user.roles:id,name')
                    ->get()
                    ->keyBy(fn (Employee $employee): string => $this->normalizeKey($employee->employee_code));

                foreach ($analysis['rows'] as $row) {
                    $area = $areas->get($this->normalizeKey($row['area_code']));
                    if (! $area) {
                        throw new RuntimeException('Area changed while importing.');
                    }

                    $vehicleId = null;
                    if ($row['vehicle_code'] !== null) {
                        $vehicle = $vehicles->get($this->normalizeKey($row['vehicle_code']));
                        if (! $vehicle) {
                            throw new RuntimeException('Vehicle changed while importing.');
                        }
                        $vehicleId = $vehicle->id;
                    }

                    $driverId = null;
                    if ($row['driver_code'] !== null) {
                        $driver = $employees->get($this->normalizeKey($row['driver_code']));
                        if (
                            ! $driver
                            || $driver->status !== 'active'
                            || ! $driver->canFulfillOperationalRole(UserRole::DRIVER)
                        ) {
                            throw new RuntimeException('Driver eligibility changed while importing.');
                        }
                        $driverId = $driver->id;
                    }

                    $representativeId = null;
                    if ($row['sales_representative_code'] !== null) {
                        $representative = $employees->get($this->normalizeKey($row['sales_representative_code']));
                        if (
                            ! $representative
                            || $representative->status !== 'active'
                            || ! $representative->canFulfillOperationalRole(UserRole::SALES_REPRESENTATIVE)
                        ) {
                            throw new RuntimeException('Sales representative eligibility changed while importing.');
                        }
                        $representativeId = $representative->id;
                    }

                    // Creating through the real DistributionRoute model intentionally keeps
                    // OperationalContextValidator::validateRoute() active during every save.
                    DistributionRoute::query()->create([
                        'area_id' => $area->id,
                        'vehicle_id' => $vehicleId,
                        'driver_id' => $driverId,
                        'sales_representative_id' => $representativeId,
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'visit_days' => $row['visit_days'],
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

    /** @param list<string> $codes @return Collection<string,Vehicle> */
    private function loadVehiclesByCode(array $codes): Collection
    {
        if ($codes === []) {
            return collect();
        }

        return Vehicle::query()
            ->whereIn('code', $codes)
            ->get(['id', 'code', 'status'])
            ->keyBy(fn (Vehicle $vehicle): string => $this->normalizeKey($vehicle->code));
    }

    /** @param list<string> $codes @return Collection<string,Employee> */
    private function loadEmployeesByCode(array $codes): Collection
    {
        if ($codes === []) {
            return collect();
        }

        return Employee::query()
            ->whereIn('employee_code', $codes)
            ->with('user.roles:id,name')
            ->get()
            ->keyBy(fn (Employee $employee): string => $this->normalizeKey($employee->employee_code));
    }

    /** @return array{days:list<string>,errors:list<string>} */
    private function parseVisitDays(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return ['days' => [], 'errors' => []];
        }

        $rawDays = preg_split('/\s*,\s*/u', trim($value)) ?: [];
        $days = [];
        $errors = [];
        $seen = [];

        foreach ($rawDays as $rawDay) {
            $day = mb_strtolower(trim($rawDay));

            if ($day === '') {
                $errors[] = 'visit_days يحتوي قيمة فارغة بين الفواصل.';
                continue;
            }

            if (! in_array($day, self::VISIT_DAYS, true)) {
                $errors[] = 'يوم الزيارة '.$rawDay.' غير صالح. استخدم: '.implode(', ', self::VISIT_DAYS).'.';
                continue;
            }

            if (isset($seen[$day])) {
                $errors[] = 'يوم الزيارة '.$day.' مكرر داخل نفس الصف.';
                continue;
            }

            $seen[$day] = true;
            $days[] = $day;
        }

        return ['days' => $days, 'errors' => array_values(array_unique($errors))];
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
