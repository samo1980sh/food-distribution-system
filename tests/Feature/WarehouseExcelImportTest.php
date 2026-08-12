<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\Imports\Excel\WarehouseExcelImportService;
use App\Services\Imports\Excel\WarehouseExcelTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class WarehouseExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_template_is_real_xlsx_with_dynamic_available_vehicle_reference_list(): void
    {
        $available = $this->makeVehicle('VEH-AVAILABLE', '001100', 'active');
        $linked = $this->makeVehicle('VEH-LINKED', '001101', 'active');
        $this->makeVehicle('VEH-INACTIVE', '001102', 'inactive');
        $this->makeWarehouse('WH-LINKED', 'مخزن سيارة مربوط', 'vehicle', $linked->id);

        $spreadsheet = app(WarehouseExcelTemplateService::class)->makeSpreadsheet();
        $sheet = $spreadsheet->getSheet(0);
        $references = $spreadsheet->getSheetByName('القوائم المرجعية');

        $this->assertSame('المستودعات', $sheet->getTitle());
        $this->assertSame(WarehouseExcelImportService::HEADERS, $sheet->rangeToArray('A1:G1', null, true, true, false)[0]);
        $this->assertNotNull($spreadsheet->getSheetByName('تعليمات'));
        $this->assertNotNull($references);
        $this->assertTrue($sheet->getRightToLeft());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('A2')->getNumberFormat()->getFormatCode());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('D2')->getNumberFormat()->getFormatCode());
        $this->assertSame($available->code, $references->getCell('A2')->getValue());

        $referenceCodes = array_map(
            static fn (array $row): mixed => $row[0] ?? null,
            $references->rangeToArray('A2:A20', null, true, true, false),
        );
        $this->assertNotContains('VEH-LINKED', $referenceCodes);
        $this->assertNotContains('VEH-INACTIVE', $referenceCodes);
        $this->assertNotNull($spreadsheet->getNamedRange('AVAILABLE_VEHICLE_CODES'));
        $this->assertTrue($sheet->dataValidationExists('C2'));
        $this->assertTrue($sheet->dataValidationExists('D2'));
        $this->assertTrue($sheet->dataValidationExists('F2'));
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('C2')->getType());
        $this->assertSame('=AVAILABLE_VEHICLE_CODES', $sheet->getDataValidation('D2')->getFormula1());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('F2')->getType());
    }

    public function test_valid_main_branch_and_vehicle_warehouses_import_all_supported_values(): void
    {
        $vehicle = $this->makeVehicle('VEH-ACTIVE', '009001', 'active');

        $path = $this->makeWorkbook([
            ['WH-MAIN-01', 'المستودع الرئيسي', 'main', null, 'دمشق', 'active', 'رئيسي'],
            ['WH-BR-01', 'مستودع فرعي', 'branch', null, 'ريف دمشق', 'inactive', null],
            ['WH-VEH-01', 'مستودع السيارة', 'vehicle', 'VEH-ACTIVE', null, 'active', 'متنقل'],
        ]);

        $service = app(WarehouseExcelImportService::class);
        $analysis = $service->analyze($path, 'warehouses.xlsx');

        $this->assertTrue($analysis['valid'], implode(' | ', $analysis['errors']));
        $this->assertSame(3, $analysis['row_count']);
        $this->assertSame(3, $analysis['valid_rows']);

        $result = $service->import($path, 'warehouses.xlsx');

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertSame(3, $result['imported_count']);
        $this->assertDatabaseHas('warehouses', [
            'code' => 'WH-MAIN-01',
            'type' => 'main',
            'vehicle_id' => null,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('warehouses', [
            'code' => 'WH-BR-01',
            'type' => 'branch',
            'vehicle_id' => null,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('warehouses', [
            'code' => 'WH-VEH-01',
            'type' => 'vehicle',
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
        ]);
    }

    public function test_blank_type_and_status_default_to_main_and_active(): void
    {
        $path = $this->makeWorkbook([
            ['WH-DEFAULT', 'افتراضي', null, null, null, null, null],
        ]);

        $result = app(WarehouseExcelImportService::class)->import($path, 'warehouses.xlsx');

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $warehouse = Warehouse::query()->where('code', 'WH-DEFAULT')->firstOrFail();
        $this->assertSame('main', $warehouse->type);
        $this->assertSame('active', $warehouse->status);
        $this->assertNull($warehouse->vehicle_id);
    }

    public function test_vehicle_type_requires_vehicle_code_all_or_nothing(): void
    {
        $path = $this->makeWorkbook([
            ['WH-OK', 'سليم', 'main', null, null, 'active', null],
            ['WH-BAD', 'متنقل ناقص', 'vehicle', null, null, 'active', null],
        ]);

        $result = app(WarehouseExcelImportService::class)->import($path, 'warehouses.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertStringContainsString('الصف 3', implode(' ', $result['errors']));
        $this->assertStringContainsString('vehicle_code مطلوب', implode(' ', $result['errors']));
        $this->assertDatabaseMissing('warehouses', ['code' => 'WH-OK']);
        $this->assertDatabaseMissing('warehouses', ['code' => 'WH-BAD']);
    }

    public function test_main_or_branch_rejects_vehicle_code(): void
    {
        $this->makeVehicle('VEH-001', '100001', 'active');
        $path = $this->makeWorkbook([
            ['WH-BAD', 'رئيسي مع سيارة', 'main', 'VEH-001', null, 'active', null],
        ]);

        $analysis = app(WarehouseExcelImportService::class)->analyze($path, 'warehouses.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('يجب أن يكون فارغًا', implode(' ', $analysis['errors']));
    }

    public function test_missing_vehicle_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['WH-VEH', 'مخزن متنقل', 'vehicle', 'VEH-MISSING', null, 'active', null],
        ]);

        $analysis = app(WarehouseExcelImportService::class)->analyze($path, 'warehouses.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('VEH-MISSING', implode(' ', $analysis['errors']));
        $this->assertStringContainsString('غير موجود', implode(' ', $analysis['errors']));
    }

    public function test_inactive_vehicle_is_rejected(): void
    {
        $this->makeVehicle('VEH-INACTIVE', '100002', 'inactive');
        $path = $this->makeWorkbook([
            ['WH-VEH', 'مخزن متنقل', 'vehicle', 'VEH-INACTIVE', null, 'active', null],
        ]);

        $analysis = app(WarehouseExcelImportService::class)->analyze($path, 'warehouses.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('غير فعالة', implode(' ', $analysis['errors']));
    }

    public function test_vehicle_already_linked_to_warehouse_is_rejected(): void
    {
        $vehicle = $this->makeVehicle('VEH-LINKED', '100003', 'active');
        $this->makeWarehouse('WH-EXISTING', 'مخزن موجود', 'vehicle', $vehicle->id);

        $path = $this->makeWorkbook([
            ['WH-NEW', 'مخزن جديد', 'vehicle', 'VEH-LINKED', null, 'active', null],
        ]);

        $analysis = app(WarehouseExcelImportService::class)->analyze($path, 'warehouses.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('مرتبطة مسبقًا', implode(' ', $analysis['errors']));
        $this->assertStringContainsString('WH-EXISTING', implode(' ', $analysis['errors']));
    }

    public function test_duplicate_warehouse_code_inside_workbook_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['WH-DUP', 'الأول', 'main', null, null, 'active', null],
            ['WH-DUP', 'الثاني', 'branch', null, null, 'active', null],
        ]);

        $analysis = app(WarehouseExcelImportService::class)->analyze($path, 'warehouses.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('مكرر داخل ملف Excel', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('warehouses', 0);
    }

    public function test_existing_warehouse_code_is_rejected_before_import(): void
    {
        $this->makeWarehouse('WH-EXISTING', 'موجود', 'main');
        $path = $this->makeWorkbook([
            ['WH-EXISTING', 'نسخة', 'branch', null, null, 'active', null],
        ]);

        $analysis = app(WarehouseExcelImportService::class)->analyze($path, 'warehouses.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('رمز المستودع موجود مسبقًا', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('warehouses', 1);
    }

    public function test_same_vehicle_cannot_be_used_twice_inside_workbook(): void
    {
        $this->makeVehicle('VEH-DUP', '100004', 'active');
        $path = $this->makeWorkbook([
            ['WH-V1', 'الأول', 'vehicle', 'VEH-DUP', null, 'active', null],
            ['WH-V2', 'الثاني', 'vehicle', 'VEH-DUP', null, 'active', null],
        ]);

        $result = app(WarehouseExcelImportService::class)->import($path, 'warehouses.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertStringContainsString('رمز السيارة مكرر', implode(' ', $result['errors']));
        $this->assertDatabaseMissing('warehouses', ['code' => 'WH-V1']);
        $this->assertDatabaseMissing('warehouses', ['code' => 'WH-V2']);
    }

    public function test_invalid_type_and_status_are_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['WH-BAD', 'غير صالح', 'mobile', null, null, 'maintenance', null],
        ]);

        $analysis = app(WarehouseExcelImportService::class)->analyze($path, 'warehouses.xlsx');
        $errors = implode(' ', $analysis['errors']);

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('main أو branch أو vehicle', $errors);
        $this->assertStringContainsString('active أو inactive', $errors);
    }

    public function test_preview_preserves_real_excel_row_numbers_when_blank_rows_exist(): void
    {
        $path = $this->makeWorkbook([
            ['WH-001', 'الأول', 'main', null, null, 'active', null],
            [null, null, null, null, null, null, null],
            ['WH-002', 'الثاني', 'branch', null, null, 'active', null],
        ]);

        $analysis = app(WarehouseExcelImportService::class)->analyze($path, 'warehouses.xlsx');

        $this->assertTrue($analysis['valid'], implode(' | ', $analysis['errors']));
        $this->assertSame([2, 4], array_column($analysis['rows'], 'excel_row'));
    }

    public function test_warehouse_excel_preview_view_contains_business_reference_preview(): void
    {
        $source = file_get_contents(resource_path('views/filament/imports/warehouse-excel-preview.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('fd-warehouse-import-preview', $source);
        $this->assertStringContainsString('fd-import-metrics', $source);
        $this->assertStringContainsString('fd-import-errors', $source);
        $this->assertStringContainsString('$row[\'vehicle_code\']', $source);
        $this->assertStringContainsString('$row[\'type\']', $source);
        $this->assertStringContainsString('$row[\'excel_row\']', $source);
    }

    public function test_non_xlsx_extension_is_rejected_even_if_contents_are_xlsx(): void
    {
        $path = $this->makeWorkbook([
            ['WH-001', 'مستودع', 'main', null, null, 'active', null],
        ]);

        $analysis = app(WarehouseExcelImportService::class)->analyze($path, 'warehouses.csv');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('.xlsx', implode(' ', $analysis['errors']));
    }

    private function makeVehicle(string $code, string $plate, string $status): Vehicle
    {
        return Vehicle::query()->create([
            'code' => $code,
            'plate_number' => $plate,
            'name' => $code,
            'status' => $status,
        ]);
    }

    private function makeWarehouse(
        string $code,
        string $name,
        string $type,
        ?int $vehicleId = null,
    ): Warehouse {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'vehicle_id' => $vehicleId,
            'status' => 'active',
        ]);
    }

    /** @param list<list<mixed>> $rows */
    private function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(WarehouseExcelImportService::HEADERS, null, 'A1');
        $sheet->getStyle('A2:A1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('D2:D1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                if ($value === null) {
                    continue;
                }

                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'warehouses-xlsx-');
        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        (new XlsxWriter($spreadsheet))->save($xlsxPath);

        $this->beforeApplicationDestroyed(static function () use ($xlsxPath): void {
            @unlink($xlsxPath);
        });

        return $xlsxPath;
    }
}
