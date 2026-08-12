<?php

namespace App\Services\Imports\Excel;

use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class VehicleExcelImportService
{
    /** @var list<string> */
    public const HEADERS = [
        'code',
        'plate_number',
        'name',
        'vehicle_type',
        'capacity',
        'current_odometer',
        'insurance_expiry_date',
        'license_expiry_date',
        'status',
        'notes',
    ];

    /**
     * @return array{
     *     valid: bool,
     *     row_count: int,
     *     valid_rows: int,
     *     rows: list<array{excel_row:int,code:string,plate_number:string,name:?string,vehicle_type:?string,capacity:int|float|string|null,current_odometer:int|float|string|null,insurance_expiry_date:?string,license_expiry_date:?string,status:string,notes:?string}>,
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

            $headerRow = $sheet->rangeToArray('A1:J1', null, true, true, false)[0] ?? [];
            $headers = array_map(fn (mixed $value): string => $this->stringValue($value), $headerRow);

            if ($headers !== self::HEADERS) {
                return $this->failure(
                    'رؤوس الأعمدة غير مطابقة للقالب. الترتيب المطلوب: '.implode(', ', self::HEADERS).'.'
                );
            }

            $rows = [];
            $errorsByRow = [];
            $seenCodes = [];
            $seenPlates = [];

            for ($excelRow = 2; $excelRow <= $highestRow; $excelRow++) {
                $values = $sheet->rangeToArray("A{$excelRow}:J{$excelRow}", null, true, true, false)[0] ?? [];
                $values = array_pad($values, 10, null);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                [$insuranceDateValid, $insuranceDate] = $this->parseDateValue($values[6]);
                [$licenseDateValid, $licenseDate] = $this->parseDateValue($values[7]);

                $capacity = $this->stringValue($values[4]);
                $odometer = $this->stringValue($values[5]);

                $row = [
                    'excel_row' => $excelRow,
                    'code' => $this->stringValue($values[0]),
                    'plate_number' => $this->stringValue($values[1]),
                    'name' => $this->nullableString($values[2]),
                    'vehicle_type' => $this->nullableString($values[3]),
                    'capacity' => $capacity === '' ? null : $capacity,
                    'current_odometer' => $odometer === '' ? null : $odometer,
                    'insurance_expiry_date' => $insuranceDateValid ? $insuranceDate : $this->nullableString($values[6]),
                    'license_expiry_date' => $licenseDateValid ? $licenseDate : $this->nullableString($values[7]),
                    'status' => $this->stringValue($values[8]) ?: 'active',
                    'notes' => $this->nullableString($values[9]),
                ];

                $validator = Validator::make(
                    $row,
                    [
                        'code' => ['required', 'string', 'max:255', Rule::unique('vehicles', 'code')],
                        'plate_number' => ['required', 'string', 'max:255', Rule::unique('vehicles', 'plate_number')],
                        'name' => ['nullable', 'string', 'max:255'],
                        'vehicle_type' => ['nullable', 'string', 'max:255'],
                        'capacity' => ['nullable', 'numeric', 'min:0'],
                        'current_odometer' => ['nullable', 'integer', 'min:0'],
                        'insurance_expiry_date' => ['nullable', 'date_format:Y-m-d'],
                        'license_expiry_date' => ['nullable', 'date_format:Y-m-d'],
                        'status' => ['required', Rule::in(['active', 'maintenance', 'inactive'])],
                        'notes' => ['nullable', 'string'],
                    ],
                    [
                        'code.required' => 'رمز السيارة مطلوب.',
                        'code.max' => 'رمز السيارة لا يجوز أن يتجاوز 255 محرفًا.',
                        'code.unique' => 'رمز السيارة موجود مسبقًا في النظام.',
                        'plate_number.required' => 'رقم اللوحة مطلوب.',
                        'plate_number.max' => 'رقم اللوحة لا يجوز أن يتجاوز 255 محرفًا.',
                        'plate_number.unique' => 'رقم اللوحة موجود مسبقًا في النظام.',
                        'name.max' => 'اسم / وصف السيارة لا يجوز أن يتجاوز 255 محرفًا.',
                        'vehicle_type.max' => 'نوع السيارة لا يجوز أن يتجاوز 255 محرفًا.',
                        'capacity.numeric' => 'سعة التحميل يجب أن تكون رقمًا.',
                        'capacity.min' => 'سعة التحميل لا يجوز أن تكون سالبة.',
                        'current_odometer.integer' => 'عداد الكيلومترات يجب أن يكون عددًا صحيحًا.',
                        'current_odometer.min' => 'عداد الكيلومترات لا يجوز أن يكون سالبًا.',
                        'insurance_expiry_date.date_format' => 'تاريخ انتهاء التأمين يجب أن يكون بصيغة YYYY-MM-DD.',
                        'license_expiry_date.date_format' => 'تاريخ انتهاء الترخيص يجب أن يكون بصيغة YYYY-MM-DD.',
                        'status.in' => 'الحالة يجب أن تكون active أو maintenance أو inactive.',
                    ],
                );

                $rowErrors = $validator->errors()->all();

                if (! $insuranceDateValid) {
                    $rowErrors[] = 'تاريخ انتهاء التأمين يجب أن يكون تاريخ Excel صالحًا أو بصيغة YYYY-MM-DD.';
                }

                if (! $licenseDateValid) {
                    $rowErrors[] = 'تاريخ انتهاء الترخيص يجب أن يكون تاريخ Excel صالحًا أو بصيغة YYYY-MM-DD.';
                }

                $normalizedCode = $this->normalizeKey($row['code']);
                if ($normalizedCode !== '' && isset($seenCodes[$normalizedCode])) {
                    $rowErrors[] = 'رمز السيارة مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenCodes[$normalizedCode].').';
                } elseif ($normalizedCode !== '') {
                    $seenCodes[$normalizedCode] = $excelRow;
                }

                $normalizedPlate = $this->normalizeKey($row['plate_number']);
                if ($normalizedPlate !== '' && isset($seenPlates[$normalizedPlate])) {
                    $rowErrors[] = 'رقم اللوحة مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenPlates[$normalizedPlate].').';
                } elseif ($normalizedPlate !== '') {
                    $seenPlates[$normalizedPlate] = $excelRow;
                }

                $errorsByRow[$excelRow] = $rowErrors;
                $rows[] = $row;
            }

            if ($rows === []) {
                return $this->failure('لا يحتوي الملف على أي صف بيانات بعد صف العناوين.');
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
     *     rows: list<array{excel_row:int,code:string,plate_number:string,name:?string,vehicle_type:?string,capacity:int|float|string|null,current_odometer:int|float|string|null,insurance_expiry_date:?string,license_expiry_date:?string,status:string,notes:?string}>,
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
                    Vehicle::query()->create([
                        'code' => $row['code'],
                        'plate_number' => $row['plate_number'],
                        'name' => $row['name'],
                        'vehicle_type' => $row['vehicle_type'],
                        'capacity' => $row['capacity'],
                        'current_odometer' => $row['current_odometer'],
                        'insurance_expiry_date' => $row['insurance_expiry_date'],
                        'license_expiry_date' => $row['license_expiry_date'],
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
