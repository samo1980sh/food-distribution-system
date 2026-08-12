<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Services\Imports\Excel\VehicleExcelImportService;
use App\Services\Imports\Excel\VehicleExcelTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class VehicleExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_template_is_a_real_xlsx_with_expected_headers_instructions_and_validations(): void
    {
        $spreadsheet = app(VehicleExcelTemplateService::class)->makeSpreadsheet();
        $sheet = $spreadsheet->getSheet(0);

        $this->assertSame('السيارات', $sheet->getTitle());
        $this->assertSame(VehicleExcelImportService::HEADERS, $sheet->rangeToArray('A1:J1', null, true, true, false)[0]);
        $this->assertNotNull($spreadsheet->getSheetByName('تعليمات'));
        $this->assertTrue($sheet->getRightToLeft());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('A2')->getNumberFormat()->getFormatCode());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('B2')->getNumberFormat()->getFormatCode());
        $this->assertSame('yyyy-mm-dd', $sheet->getStyle('G2')->getNumberFormat()->getFormatCode());
        $this->assertSame('yyyy-mm-dd', $sheet->getStyle('H2')->getNumberFormat()->getFormatCode());
        $this->assertTrue($sheet->dataValidationExists('E2'));
        $this->assertTrue($sheet->dataValidationExists('F2'));
        $this->assertTrue($sheet->dataValidationExists('I2'));
        $this->assertSame(DataValidation::TYPE_DECIMAL, $sheet->getDataValidation('E2')->getType());
        $this->assertSame(DataValidation::TYPE_WHOLE, $sheet->getDataValidation('F2')->getType());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('I2')->getType());
        $this->assertSame('"active,maintenance,inactive"', $sheet->getDataValidation('I2')->getFormula1());
    }

    public function test_valid_vehicles_import_all_supported_values(): void
    {
        $path = $this->makeWorkbook([
            ['VEH-001', '001234', 'شاحنة توزيع', 'شاحنة مبردة', 1250.500, 45000, '2027-12-31', '2027-10-15', 'active', 'اختبار'],
            ['VEH-002', '009999', null, null, null, null, null, null, 'maintenance', null],
        ]);

        $service = app(VehicleExcelImportService::class);
        $analysis = $service->analyze($path, 'vehicles.xlsx');

        $this->assertTrue($analysis['valid']);
        $this->assertSame(2, $analysis['row_count']);
        $this->assertSame(2, $analysis['valid_rows']);

        $result = $service->import($path, 'vehicles.xlsx');

        $this->assertTrue($result['valid']);
        $this->assertSame(2, $result['imported_count']);

        $vehicle = Vehicle::query()->where('code', 'VEH-001')->firstOrFail();
        $this->assertSame('001234', $vehicle->plate_number);
        $this->assertSame('شاحنة توزيع', $vehicle->name);
        $this->assertSame('شاحنة مبردة', $vehicle->vehicle_type);
        $this->assertSame('1250.500', $vehicle->capacity);
        $this->assertSame(45000, $vehicle->current_odometer);
        $this->assertSame('2027-12-31', $vehicle->insurance_expiry_date?->format('Y-m-d'));
        $this->assertSame('2027-10-15', $vehicle->license_expiry_date?->format('Y-m-d'));
        $this->assertSame('active', $vehicle->status);
    }

    public function test_optional_values_and_active_status_default_are_applied(): void
    {
        $path = $this->makeWorkbook([
            ['VEH-010', 'PLATE-010', null, null, null, null, null, null, null, null],
        ]);

        $result = app(VehicleExcelImportService::class)->import($path, 'vehicles.xlsx');

        $this->assertTrue($result['valid']);
        $vehicle = Vehicle::query()->where('code', 'VEH-010')->firstOrFail();
        $this->assertNull($vehicle->name);
        $this->assertNull($vehicle->vehicle_type);
        $this->assertNull($vehicle->capacity);
        $this->assertNull($vehicle->current_odometer);
        $this->assertNull($vehicle->insurance_expiry_date);
        $this->assertNull($vehicle->license_expiry_date);
        $this->assertSame('active', $vehicle->status);
    }

    public function test_duplicate_code_inside_workbook_is_rejected_all_or_nothing(): void
    {
        $path = $this->makeWorkbook([
            ['VEH-DUP', 'PLATE-020', 'الأول', null, 10, 0, null, null, 'active', null],
            ['veh-dup', 'PLATE-021', 'الثاني', null, 10, 0, null, null, 'active', null],
        ]);

        $result = app(VehicleExcelImportService::class)->import($path, 'vehicles.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertStringContainsString('رمز السيارة مكرر', implode(' ', $result['errors']));
        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_duplicate_plate_inside_workbook_is_rejected_all_or_nothing(): void
    {
        $path = $this->makeWorkbook([
            ['VEH-030', 'ABC-123', 'الأول', null, 10, 0, null, null, 'active', null],
            ['VEH-031', 'abc-123', 'الثاني', null, 10, 0, null, null, 'active', null],
        ]);

        $result = app(VehicleExcelImportService::class)->import($path, 'vehicles.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertStringContainsString('رقم اللوحة مكرر', implode(' ', $result['errors']));
        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_existing_code_is_rejected_before_import(): void
    {
        $this->makeVehicle('VEH-EXISTING', 'PLATE-040');

        $path = $this->makeWorkbook([
            ['VEH-EXISTING', 'PLATE-041', 'نسخة', null, null, null, null, null, 'active', null],
        ]);

        $analysis = app(VehicleExcelImportService::class)->analyze($path, 'vehicles.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('رمز السيارة موجود مسبقًا', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_existing_plate_is_rejected_before_import(): void
    {
        $this->makeVehicle('VEH-050', 'PLATE-EXISTING');

        $path = $this->makeWorkbook([
            ['VEH-051', 'PLATE-EXISTING', 'سيارة أخرى', null, null, null, null, null, 'active', null],
        ]);

        $analysis = app(VehicleExcelImportService::class)->analyze($path, 'vehicles.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('رقم اللوحة موجود مسبقًا', implode(' ', $analysis['errors']));
    }

    public function test_invalid_capacity_odometer_and_status_are_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['VEH-060', 'PLATE-060', null, null, -1, 1.5, null, null, 'disabled', null],
        ]);

        $analysis = app(VehicleExcelImportService::class)->analyze($path, 'vehicles.xlsx');
        $errors = implode(' ', $analysis['errors']);

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('سعة التحميل لا يجوز أن تكون سالبة', $errors);
        $this->assertStringContainsString('عداد الكيلومترات يجب أن يكون عددًا صحيحًا', $errors);
        $this->assertStringContainsString('active أو maintenance أو inactive', $errors);
    }

    public function test_iso_dates_and_real_excel_dates_are_accepted(): void
    {
        $excelSerial = ExcelDate::PHPToExcel(new \DateTimeImmutable('2028-03-20'));
        $path = $this->makeWorkbook([
            ['VEH-070', 'PLATE-070', null, null, null, null, '2028-01-15', $excelSerial, 'inactive', null],
        ]);

        $result = app(VehicleExcelImportService::class)->import($path, 'vehicles.xlsx');

        $this->assertTrue($result['valid']);
        $vehicle = Vehicle::query()->where('code', 'VEH-070')->firstOrFail();
        $this->assertSame('2028-01-15', $vehicle->insurance_expiry_date?->format('Y-m-d'));
        $this->assertSame('2028-03-20', $vehicle->license_expiry_date?->format('Y-m-d'));
        $this->assertSame('inactive', $vehicle->status);
    }

    public function test_invalid_dates_are_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['VEH-080', 'PLATE-080', null, null, null, null, '31/12/2028', '2028-02-30', 'active', null],
        ]);

        $analysis = app(VehicleExcelImportService::class)->analyze($path, 'vehicles.xlsx');
        $errors = implode(' ', $analysis['errors']);

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('انتهاء التأمين', $errors);
        $this->assertStringContainsString('انتهاء الترخيص', $errors);
    }

    public function test_required_code_and_plate_are_enforced(): void
    {
        $path = $this->makeWorkbook([
            [null, null, 'بدون هوية', null, null, null, null, null, 'active', null],
        ]);

        $analysis = app(VehicleExcelImportService::class)->analyze($path, 'vehicles.xlsx');
        $errors = implode(' ', $analysis['errors']);

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('رمز السيارة مطلوب', $errors);
        $this->assertStringContainsString('رقم اللوحة مطلوب', $errors);
    }

    public function test_preview_preserves_real_excel_row_numbers_when_blank_rows_exist(): void
    {
        $path = $this->makeWorkbook([
            ['VEH-090', 'PLATE-090', 'الأول', null, null, null, null, null, 'active', null],
            [null, null, null, null, null, null, null, null, null, null],
            ['VEH-091', 'PLATE-091', 'الثاني', null, null, null, null, null, 'maintenance', null],
        ]);

        $analysis = app(VehicleExcelImportService::class)->analyze($path, 'vehicles.xlsx');

        $this->assertTrue($analysis['valid']);
        $this->assertSame(2, $analysis['row_count']);
        $this->assertSame(2, $analysis['rows'][0]['excel_row']);
        $this->assertSame(4, $analysis['rows'][1]['excel_row']);
    }

    public function test_vehicle_excel_preview_view_contains_vehicle_preview(): void
    {
        $source = file_get_contents(resource_path('views/filament/imports/vehicle-excel-preview.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('fd-vehicle-import-preview', $source);
        $this->assertStringContainsString('معاينة السيارات', $source);
        $this->assertStringContainsString('صف Excel', $source);
        $this->assertStringContainsString('{{ $row[\'excel_row\'] }}', $source);
        $this->assertStringContainsString('$row[\'plate_number\']', $source);
        $this->assertStringContainsString('$row[\'insurance_expiry_date\']', $source);
        $this->assertStringContainsString('array_slice($preview[\'rows\'], 0, 10)', $source);
    }

    public function test_non_xlsx_extension_is_rejected_even_if_contents_are_xlsx(): void
    {
        $path = $this->makeWorkbook([
            ['VEH-100', 'PLATE-100', 'اختبار', null, null, null, null, null, 'active', null],
        ]);

        $analysis = app(VehicleExcelImportService::class)->analyze($path, 'vehicles.xls');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('.xlsx', implode(' ', $analysis['errors']));
    }

    private function makeVehicle(string $code, string $plateNumber): Vehicle
    {
        return Vehicle::query()->create([
            'code' => $code,
            'plate_number' => $plateNumber,
            'name' => 'سيارة '.$code,
            'vehicle_type' => null,
            'capacity' => null,
            'status' => 'active',
            'current_odometer' => null,
            'insurance_expiry_date' => null,
            'license_expiry_date' => null,
            'notes' => null,
        ]);
    }

    /** @param list<list<mixed>> $rows */
    private function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(VehicleExcelImportService::HEADERS, null, 'A1');
        $sheet->getStyle('A2:B1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                if ($value === null) {
                    continue;
                }

                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'vehicles-xlsx-');
        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        (new XlsxWriter($spreadsheet))->save($xlsxPath);

        $this->beforeApplicationDestroyed(static function () use ($xlsxPath): void {
            @unlink($xlsxPath);
        });

        return $xlsxPath;
    }
}
