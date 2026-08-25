<?php

namespace App\Services\Imports\Excel;

use App\Enums\EmployeeType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;
use Throwable;

class EmployeeExcelImportService
{
    /** @var list<string> */
    public const HEADERS = [
        'employee_code',
        'name',
        'phone',
        'job_title',
        'type',
        'user_email',
        'status',
        'notes',
    ];

    /** @var list<string> */
    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array{
     *     valid: bool,
     *     row_count: int,
     *     valid_rows: int,
     *     rows: list<array{excel_row:int,employee_code:string,name:string,phone:?string,job_title:?string,type:string,user_email:?string,status:string,notes:?string}>,
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
            $reader = new Xlsx;
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
            $seenEmployeeCodes = [];
            $seenUserEmails = [];

            for ($excelRow = 2; $excelRow <= $highestRow; $excelRow++) {
                $values = $sheet->rangeToArray("A{$excelRow}:H{$excelRow}", null, true, true, false)[0] ?? [];
                $values = array_pad($values, 8, null);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $row = [
                    'excel_row' => $excelRow,
                    'employee_code' => $this->stringValue($values[0]),
                    'name' => $this->stringValue($values[1]),
                    'phone' => $this->nullableString($values[2]),
                    'job_title' => $this->nullableString($values[3]),
                    'type' => $this->stringValue($values[4]) ?: 'sales_representative',
                    'user_email' => $this->nullableString($values[5]),
                    'status' => $this->stringValue($values[6]) ?: 'active',
                    'notes' => $this->nullableString($values[7]),
                ];

                $validator = Validator::make(
                    $row,
                    [
                        'employee_code' => ['required', 'string', 'max:255', Rule::unique('employees', 'employee_code')],
                        'name' => ['required', 'string', 'max:255'],
                        'phone' => ['nullable', 'string', 'max:255'],
                        'job_title' => ['nullable', 'string', 'max:255'],
                        'type' => ['required', Rule::in(EmployeeType::values())],
                        'user_email' => ['nullable', 'string', 'max:255'],
                        'status' => ['required', Rule::in(self::STATUSES)],
                        'notes' => ['nullable', 'string'],
                    ],
                    [
                        'employee_code.required' => 'رمز الموظف مطلوب.',
                        'employee_code.max' => 'رمز الموظف لا يجوز أن يتجاوز 255 محرفًا.',
                        'employee_code.unique' => 'رمز الموظف موجود مسبقًا في النظام.',
                        'name.required' => 'اسم الموظف مطلوب.',
                        'name.max' => 'اسم الموظف لا يجوز أن يتجاوز 255 محرفًا.',
                        'phone.max' => 'رقم الهاتف لا يجوز أن يتجاوز 255 محرفًا.',
                        'job_title.max' => 'المسمى الوظيفي لا يجوز أن يتجاوز 255 محرفًا.',
                        'type.in' => 'نوع الموظف غير صالح. استخدم sales_representative أو warehouse_keeper أو accountant أو supervisor.',
                        'user_email.max' => 'البريد الإلكتروني لحساب المستخدم لا يجوز أن يتجاوز 255 محرفًا.',
                        'status.in' => 'الحالة يجب أن تكون active أو inactive.',
                    ],
                );

                $rowErrors = $validator->errors()->all();

                $normalizedEmployeeCode = $this->normalizeKey($row['employee_code']);
                if ($normalizedEmployeeCode !== '' && isset($seenEmployeeCodes[$normalizedEmployeeCode])) {
                    $rowErrors[] = 'رمز الموظف مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenEmployeeCodes[$normalizedEmployeeCode].').';
                } elseif ($normalizedEmployeeCode !== '') {
                    $seenEmployeeCodes[$normalizedEmployeeCode] = $excelRow;
                }

                if ($row['user_email'] !== null) {
                    $normalizedUserEmail = $this->normalizeKey($row['user_email']);

                    if (isset($seenUserEmails[$normalizedUserEmail])) {
                        $rowErrors[] = 'حساب المستخدم مكرر داخل ملف Excel نفسه (ظهر أولًا في الصف '.$seenUserEmails[$normalizedUserEmail].').';
                    } else {
                        $seenUserEmails[$normalizedUserEmail] = $excelRow;
                    }
                }

                $errorsByRow[$excelRow] = $rowErrors;
                $rows[] = $row;
            }

            if ($rows === []) {
                return $this->failure('لا يحتوي الملف على أي صف بيانات بعد صف العناوين.');
            }

            $userEmails = array_values(array_unique(array_filter(
                array_map(static fn (array $row): ?string => $row['user_email'], $rows),
                static fn (?string $value): bool => $value !== null && $value !== '',
            )));

            $users = $this->loadUsersByEmail($userEmails);

            foreach ($rows as $row) {
                if ($row['user_email'] === null) {
                    continue;
                }

                $excelRow = $row['excel_row'];
                $user = $users->get($this->normalizeKey($row['user_email']));

                if (! $user) {
                    $errorsByRow[$excelRow][] = 'حساب المستخدم بالبريد '.$row['user_email'].' غير موجود في النظام.';

                    continue;
                }

                $linkedEmployee = Employee::query()
                    ->where('user_id', $user->id)
                    ->first(['id', 'employee_code']);

                if ($linkedEmployee) {
                    $errorsByRow[$excelRow][] = 'حساب المستخدم '.$row['user_email'].' مرتبط مسبقًا بالموظف '.$linkedEmployee->employee_code.'.';

                    continue;
                }

                if (! $user->hasRole($row['type'])) {
                    $errorsByRow[$excelRow][] = 'حساب المستخدم '.$row['user_email'].' لا يحمل الدور المطابق لنوع الموظف '.$row['type'].'.';
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
     *     rows: list<array{excel_row:int,employee_code:string,name:string,phone:?string,job_title:?string,type:string,user_email:?string,status:string,notes:?string}>,
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
                $userEmails = array_values(array_unique(array_filter(
                    array_map(static fn (array $row): ?string => $row['user_email'], $analysis['rows']),
                    static fn (?string $value): bool => $value !== null && $value !== '',
                )));

                $users = $this->loadUsersByEmail($userEmails);

                foreach ($analysis['rows'] as $row) {
                    $userId = null;

                    if ($row['user_email'] !== null) {
                        $user = $users->get($this->normalizeKey($row['user_email']));

                        if (! $user || ! $user->hasRole($row['type'])) {
                            throw new RuntimeException('User account role changed while importing.');
                        }

                        if (Employee::query()->where('user_id', $user->id)->exists()) {
                            throw new RuntimeException('User account link changed while importing.');
                        }

                        $userId = $user->id;
                    }

                    // Creating through the real Employee model intentionally keeps the
                    // model saving hook that validates the linked account role in force.
                    Employee::query()->create([
                        'user_id' => $userId,
                        'employee_code' => $row['employee_code'],
                        'name' => $row['name'],
                        'phone' => $row['phone'],
                        'job_title' => $row['job_title'],
                        'type' => $row['type'],
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

    /** @param list<string> $emails @return Collection<string,User> */
    private function loadUsersByEmail(array $emails): Collection
    {
        if ($emails === []) {
            return collect();
        }

        return User::query()
            ->whereIn('email', $emails)
            ->with('roles:id,name')
            ->get()
            ->keyBy(fn (User $user): string => $this->normalizeKey($user->email));
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
