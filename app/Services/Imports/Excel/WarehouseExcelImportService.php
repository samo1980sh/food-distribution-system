<?php

namespace App\Services\Imports\Excel;

use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;
use Throwable;

class WarehouseExcelImportService
{
    /** @var list<string> */
    public const HEADERS = [
        'code',
        'name',
        'type',
        'vehicle_code',
        'address',
        'status',
        'notes',
    ];

    /**
     * @return array{
     *     valid: bool,
     *     row_count: int,
     *     valid_rows: int,
     *     rows: list<array{excel_row:int,code:string,name:string,type:string,vehicle_code:?string,address:?string,status:string,notes:?string}>,
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

            $headerRow = $sheet->rangeToArray('A1:G1', null, true, true, false)[0] ?? [];
            $headers = array_map(fn (mixed $value): string => $this->stringValue($value), $headerRow);

            if ($headers !== self::HEADERS) {
                return $this->failure(
                    'رؤوس الأعمدة غير مطابقة للقالب. الترتيب المطلوب: '.implode(', ', self::HEADERS).'.'
                );
            }

            $rows = [];
            $errorsByRow = [];
            $seenCodes = [];
            $seenVehicleCodes = [];

            for ($excelRow = 2; $excelRow <= $highestRow; $excelRow++) {
                $values = $sheet->rangeToArray("A{$excelRow}:G{$excelRow}", null, true, true, false)[0] ?? [];
                $values = array_pad($values, 7, null);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $row = [
                    'excel_row' => $excelRow,
                    'code' => $this->stringValue($values[0]),
                    'name' => $this->stringValue($values[1]),
                    'type' => $this->stringValue($values[2]) ?: 'main',
                    'vehicle_code' => $this->nullableString($values[3]),
                    'address' => $this->nullableString($values[4]),
                    'status' => $this->stringValue($values[5]) ?: 'active',
                    'notes' => $this->nullableString($values[6]),
                ];

                $validator = Validator::make(
                    $row,
                    [
                        'code' => ['required', 'string', 'max:255', Rule::unique('warehouses', 'code')],
                        'name' => ['required', 'string', 'max:255'],
                        'type' => ['required', Rule::in(['main', 'branch', 'vehicle'])],
                        'vehicle_code' => ['nullable', 'string', 'max:255'],
                        'address' => ['nullable', 'string', 'max:255'],
                        'status' => ['required', Rule::in(['active', 'inactive'])],
                        'notes' => ['nullable', 'string'],
                    ],
                    [
                        'code.required' => 'رمز المستودع مطلوب.',
                        'code.max' => 'رمز المستودع لا يجوز أن يتجاوز 255 محرفًا.',
                        'code.unique' => 'رمز المستودع موجود مسبقًا في النظام.',
                        'name.required' => 'اسم المستودع مطلوب.',
                        'name.max' => 'اسم المستودع لا يجوز أن يتجاوز 255 محرفًا.',
                        'type.in' => 'نوع المستودع يجب أن يكون main أو branch أو vehicle.',
                        'vehicle_code.max' => 'رمز السيارة لا يجوز أن يتجاوز 255 محرفًا.',
                        'address.max' => 'عنوان المستودع لا يجوز أن يتجاوز 255 محرفًا.',
                        'status.in' => 'الحالة يجب أن تكون active أو inactive.',
                    ],
                );

                $rowErrors = $validator->errors()->all();

                $normalizedCode = $this->normalizeKey($row['code']);
                if ($normalizedCode !== '' && isset($seenCodes[$normalizedCode])) {
                    $rowErrors[] = 'رمز المستودع مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenCodes[$normalizedCode].').';
                } elseif ($normalizedCode !== '') {
                    $seenCodes[$normalizedCode] = $excelRow;
                }

                if ($row['type'] === 'vehicle') {
                    if ($row['vehicle_code'] === null) {
                        $rowErrors[] = 'vehicle_code مطلوب عندما يكون type = vehicle.';
                    } else {
                        $normalizedVehicleCode = $this->normalizeKey($row['vehicle_code']);

                        if (isset($seenVehicleCodes[$normalizedVehicleCode])) {
                            $rowErrors[] = 'رمز السيارة مكرر داخل ملف Excel نفسه لمستودعين متنقلين (ظهر أولًا في الصف '.$seenVehicleCodes[$normalizedVehicleCode].').';
                        } else {
                            $seenVehicleCodes[$normalizedVehicleCode] = $excelRow;
                        }
                    }
                } elseif ($row['vehicle_code'] !== null) {
                    $rowErrors[] = 'vehicle_code يجب أن يكون فارغًا عندما يكون type = main أو branch.';
                }

                $errorsByRow[$excelRow] = $rowErrors;
                $rows[] = $row;
            }

            if ($rows === []) {
                return $this->failure('لا يحتوي الملف على أي صف بيانات بعد صف العناوين.');
            }

            $vehicleCodes = array_values(array_unique(array_filter(
                array_map(static fn (array $row): ?string => $row['vehicle_code'], $rows),
                static fn (?string $value): bool => $value !== null && $value !== '',
            )));

            $vehicles = $this->loadVehiclesByCode($vehicleCodes);

            foreach ($rows as $row) {
                if ($row['type'] !== 'vehicle' || $row['vehicle_code'] === null) {
                    continue;
                }

                $excelRow = $row['excel_row'];
                $vehicle = $vehicles->get($this->normalizeKey($row['vehicle_code']));

                if (! $vehicle) {
                    $errorsByRow[$excelRow][] = 'رمز السيارة '.$row['vehicle_code'].' غير موجود في النظام.';
                    continue;
                }

                if ($vehicle->status !== 'active') {
                    $errorsByRow[$excelRow][] = 'السيارة '.$row['vehicle_code'].' موجودة لكنها غير فعالة.';
                    continue;
                }

                $linkedWarehouse = Warehouse::withoutGlobalScopes()
                    ->where('vehicle_id', $vehicle->id)
                    ->first(['id', 'code']);

                if ($linkedWarehouse) {
                    $errorsByRow[$excelRow][] = 'السيارة '.$row['vehicle_code'].' مرتبطة مسبقًا بالمستودع '.$linkedWarehouse->code.'.';
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
     *     rows: list<array{excel_row:int,code:string,name:string,type:string,vehicle_code:?string,address:?string,status:string,notes:?string}>,
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
                $vehicleCodes = array_values(array_unique(array_filter(
                    array_map(static fn (array $row): ?string => $row['vehicle_code'], $analysis['rows']),
                    static fn (?string $value): bool => $value !== null && $value !== '',
                )));

                $vehicles = Vehicle::query()
                    ->whereIn('code', $vehicleCodes)
                    ->where('status', 'active')
                    ->get(['id', 'code', 'status'])
                    ->keyBy(fn (Vehicle $vehicle): string => $this->normalizeKey($vehicle->code));

                foreach ($analysis['rows'] as $row) {
                    $vehicleId = null;

                    if ($row['type'] === 'vehicle') {
                        $vehicle = $vehicles->get($this->normalizeKey($row['vehicle_code']));

                        if (! $vehicle) {
                            throw new RuntimeException('Vehicle changed while importing.');
                        }

                        if (Warehouse::withoutGlobalScopes()->where('vehicle_id', $vehicle->id)->exists()) {
                            throw new RuntimeException('Vehicle warehouse link changed while importing.');
                        }

                        $vehicleId = $vehicle->id;
                    }

                    // Creating through the real Warehouse model intentionally keeps the
                    // model saving hook and OperationalContextValidator in force.
                    Warehouse::query()->create([
                        'vehicle_id' => $vehicleId,
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'address' => $row['address'],
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
