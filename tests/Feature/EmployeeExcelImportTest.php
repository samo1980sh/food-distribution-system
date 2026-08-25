<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\Imports\Excel\EmployeeExcelImportService;
use App\Services\Imports\Excel\EmployeeExcelTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_template_is_real_xlsx_with_dynamic_available_user_reference_list(): void
    {
        $driver = $this->makeUserWithRoles('driver.available@example.com', ['driver']);
        $available = $this->makeUserWithRoles('sales.available@example.com', ['sales_representative']);
        $dual = $this->makeUserWithRoles('dual.available@example.com', ['driver', 'sales_representative']);
        $linked = $this->makeUserWithRoles('linked@example.com', ['accountant']);
        $this->makeUserWithRoles('manager@example.com', ['manager']);
        $this->makeEmployee('EMP-LINKED', 'موظف مربوط', 'accountant', $linked->id);

        $spreadsheet = app(EmployeeExcelTemplateService::class)->makeSpreadsheet();
        $sheet = $spreadsheet->getSheet(0);
        $references = $spreadsheet->getSheetByName('القوائم المرجعية');
        $instructions = $spreadsheet->getSheetByName('تعليمات');

        $this->assertSame('الموظفون', $sheet->getTitle());
        $this->assertSame(EmployeeExcelImportService::HEADERS, $sheet->rangeToArray('A1:H1', null, true, true, false)[0]);
        $this->assertNotNull($instructions);
        $this->assertNotNull($references);
        $this->assertTrue($sheet->getRightToLeft());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('A2')->getNumberFormat()->getFormatCode());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('C2')->getNumberFormat()->getFormatCode());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('F2')->getNumberFormat()->getFormatCode());

        $referenceEmails = array_values(array_filter(array_map(
            static fn (array $row): mixed => $row[0] ?? null,
            $references->rangeToArray('A2:A20', null, true, true, false),
        )));

        $this->assertContains($available->email, $referenceEmails);
        $this->assertContains($dual->email, $referenceEmails);
        $this->assertNotContains($driver->email, $referenceEmails);
        $this->assertNotContains('linked@example.com', $referenceEmails);
        $this->assertNotContains('manager@example.com', $referenceEmails);
        $this->assertNotNull($spreadsheet->getNamedRange('AVAILABLE_EMPLOYEE_USER_EMAILS'));
        $this->assertTrue($sheet->dataValidationExists('E2'));
        $this->assertTrue($sheet->dataValidationExists('F2'));
        $this->assertTrue($sheet->dataValidationExists('G2'));
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('E2')->getType());
        $this->assertSame(
            '"sales_representative,warehouse_keeper,accountant,supervisor"',
            $sheet->getDataValidation('E2')->getFormula1(),
        );
        $this->assertStringNotContainsString('driver', $sheet->getDataValidation('E2')->getFormula1());
        $this->assertStringNotContainsString(
            'driver',
            json_encode($instructions->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
        $this->assertSame('=AVAILABLE_EMPLOYEE_USER_EMAILS', $sheet->getDataValidation('F2')->getFormula1());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('G2')->getType());
    }

    public function test_valid_employees_import_all_supported_values_and_optional_user_links(): void
    {
        $representative = $this->makeUserWithRoles('sales@example.com', ['sales_representative']);
        $accountant = $this->makeUserWithRoles('accountant@example.com', ['accountant']);

        $path = $this->makeWorkbook([
            ['EMP-001', 'مندوب تجريبي', '00963123456789', 'مندوب مبيعات', 'sales_representative', 'sales@example.com', 'active', 'ميداني'],
            ['EMP-002', 'محاسب تجريبي', null, 'محاسب', 'accountant', 'accountant@example.com', 'inactive', null],
            ['EMP-003', 'أمين بلا حساب', '0012345', 'أمين مستودع', 'warehouse_keeper', null, 'active', 'بدون حساب'],
        ]);

        $service = app(EmployeeExcelImportService::class);
        $analysis = $service->analyze($path, 'employees.xlsx');

        $this->assertTrue($analysis['valid'], implode(' | ', $analysis['errors']));
        $this->assertSame(3, $analysis['row_count']);
        $this->assertSame(3, $analysis['valid_rows']);

        $result = $service->import($path, 'employees.xlsx');

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertSame(3, $result['imported_count']);
        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-001',
            'user_id' => $representative->id,
            'phone' => '00963123456789',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-002',
            'user_id' => $accountant->id,
            'type' => 'accountant',
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-003',
            'user_id' => null,
            'type' => 'warehouse_keeper',
            'phone' => '0012345',
        ]);
    }

    public function test_blank_type_and_status_default_to_sales_representative_and_active(): void
    {
        $path = $this->makeWorkbook([
            ['EMP-DEFAULT', 'موظف افتراضي', null, null, null, null, null, null],
        ]);

        $result = app(EmployeeExcelImportService::class)->import($path, 'employees.xlsx');

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-DEFAULT',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
    }

    public function test_explicit_driver_type_is_rejected_without_creating_an_employee(): void
    {
        $path = $this->makeWorkbook([
            ['EMP-DRIVER', 'سائق جديد', null, null, 'driver', null, 'active', null],
        ]);

        $result = app(EmployeeExcelImportService::class)->import($path, 'employees.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertStringContainsString('نوع الموظف غير صالح', implode(' | ', $result['errors']));
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-DRIVER']);
    }

    public function test_user_role_mismatch_is_rejected_all_or_nothing(): void
    {
        $this->makeUserWithRoles('driver@example.com', ['driver']);

        $path = $this->makeWorkbook([
            ['EMP-OK', 'موظف سليم', null, null, 'sales_representative', null, 'active', null],
            ['EMP-BAD', 'موظف خاطئ', null, null, 'sales_representative', 'driver@example.com', 'active', null],
        ]);

        $result = app(EmployeeExcelImportService::class)->import($path, 'employees.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertStringContainsString('لا يحمل الدور المطابق', implode(' | ', $result['errors']));
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-OK']);
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-BAD']);
    }

    public function test_dual_role_user_can_match_either_supported_field_role(): void
    {
        $dual = $this->makeUserWithRoles('dual@example.com', ['driver', 'sales_representative']);

        $path = $this->makeWorkbook([
            ['EMP-DUAL', 'مندوب ثنائي الدور', null, null, 'sales_representative', 'dual@example.com', 'active', null],
        ]);

        $result = app(EmployeeExcelImportService::class)->import($path, 'employees.xlsx');

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-DUAL',
            'user_id' => $dual->id,
            'type' => 'sales_representative',
        ]);
    }

    public function test_missing_user_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['EMP-404', 'موظف', null, null, 'driver', 'missing@example.com', 'active', null],
        ]);

        $analysis = app(EmployeeExcelImportService::class)->analyze($path, 'employees.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('غير موجود في النظام', implode(' | ', $analysis['errors']));
    }

    public function test_user_already_linked_to_employee_is_rejected(): void
    {
        $user = $this->makeUserWithRoles('linked@example.com', ['supervisor']);
        $this->makeEmployee('EMP-OLD', 'الموظف القديم', 'supervisor', $user->id);

        $path = $this->makeWorkbook([
            ['EMP-NEW', 'الموظف الجديد', null, null, 'supervisor', 'linked@example.com', 'active', null],
        ]);

        $analysis = app(EmployeeExcelImportService::class)->analyze($path, 'employees.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('مرتبط مسبقًا بالموظف EMP-OLD', implode(' | ', $analysis['errors']));
    }

    public function test_same_user_cannot_be_used_twice_inside_workbook(): void
    {
        $this->makeUserWithRoles('driver@example.com', ['driver']);

        $path = $this->makeWorkbook([
            ['EMP-A', 'أول', null, null, 'driver', 'driver@example.com', 'active', null],
            ['EMP-B', 'ثان', null, null, 'driver', 'driver@example.com', 'active', null],
        ]);

        $analysis = app(EmployeeExcelImportService::class)->analyze($path, 'employees.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('حساب المستخدم مكرر داخل ملف Excel نفسه', implode(' | ', $analysis['errors']));
    }

    public function test_duplicate_employee_code_inside_workbook_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['EMP-DUP', 'أول', null, null, 'driver', null, 'active', null],
            ['emp-dup', 'ثان', null, null, 'accountant', null, 'active', null],
        ]);

        $analysis = app(EmployeeExcelImportService::class)->analyze($path, 'employees.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('رمز الموظف مكرر داخل ملف Excel نفسه', implode(' | ', $analysis['errors']));
    }

    public function test_existing_employee_code_is_rejected_before_import(): void
    {
        $this->makeEmployee('EMP-EXISTS', 'موجود', 'driver');

        $path = $this->makeWorkbook([
            ['EMP-EXISTS', 'جديد', null, null, 'driver', null, 'active', null],
        ]);

        $analysis = app(EmployeeExcelImportService::class)->analyze($path, 'employees.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('موجود مسبقًا في النظام', implode(' | ', $analysis['errors']));
    }

    public function test_invalid_type_and_status_are_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['EMP-BAD', 'موظف', null, null, 'manager', null, 'suspended', null],
        ]);

        $analysis = app(EmployeeExcelImportService::class)->analyze($path, 'employees.xlsx');

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('نوع الموظف غير صالح', $errors);
        $this->assertStringContainsString('الحالة يجب أن تكون active أو inactive', $errors);
    }

    public function test_required_employee_code_and_name_are_enforced(): void
    {
        $path = $this->makeWorkbook([
            [null, 'بلا رمز', null, null, 'driver', null, 'active', null],
            ['EMP-NAMELESS', null, null, null, 'driver', null, 'active', null],
        ]);

        $analysis = app(EmployeeExcelImportService::class)->analyze($path, 'employees.xlsx');

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('رمز الموظف مطلوب', $errors);
        $this->assertStringContainsString('اسم الموظف مطلوب', $errors);
    }

    public function test_preview_preserves_real_excel_row_numbers_when_blank_rows_exist(): void
    {
        $path = $this->makeWorkbook([
            ['EMP-ROW-2', 'أول', null, null, 'sales_representative', null, 'active', null],
            [null, null, null, null, null, null, null, null],
            ['EMP-ROW-4', 'ثان', null, null, 'accountant', null, 'active', null],
        ]);

        $analysis = app(EmployeeExcelImportService::class)->analyze($path, 'employees.xlsx');

        $this->assertTrue($analysis['valid'], implode(' | ', $analysis['errors']));
        $this->assertSame([2, 4], array_column($analysis['rows'], 'excel_row'));
    }

    public function test_employee_excel_preview_view_contains_business_reference_preview(): void
    {
        $contents = file_get_contents(resource_path('views/filament/imports/employee-excel-preview.blade.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('employee_code', $contents);
        $this->assertStringContainsString('user_email', $contents);
        $this->assertStringContainsString("row['excel_row']", $contents);
        $this->assertStringContainsString('fd-employee-import-preview', $contents);
    }

    public function test_non_xlsx_extension_is_rejected_even_if_contents_are_xlsx(): void
    {
        $path = $this->makeWorkbook([
            ['EMP-001', 'موظف', null, null, 'driver', null, 'active', null],
        ]);

        $analysis = app(EmployeeExcelImportService::class)->analyze($path, 'employees.csv');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('.xlsx', implode(' | ', $analysis['errors']));
    }

    /** @param list<string> $roles */
    private function makeUserWithRoles(string $email, array $roles): User
    {
        $user = User::factory()->create(['email' => $email]);

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user->syncRoles($roles);

        return $user->refresh();
    }

    private function makeEmployee(string $code, string $name, string $type, ?int $userId = null): Employee
    {
        return Employee::query()->create([
            'user_id' => $userId,
            'employee_code' => $code,
            'name' => $name,
            'type' => $type,
            'status' => 'active',
        ]);
    }

    /** @param list<list<mixed>> $rows */
    private function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(EmployeeExcelImportService::HEADERS, null, 'A1');

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;
            $row = array_pad($row, count(EmployeeExcelImportService::HEADERS), null);

            foreach ($row as $columnIndex => $value) {
                if ($value === null) {
                    continue;
                }

                $sheet->setCellValue([$columnIndex + 1, $excelRow], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'employee-import-').'.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);

        return $path;
    }
}
