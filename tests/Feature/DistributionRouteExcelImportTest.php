<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Services\Imports\Excel\DistributionRouteExcelImportService;
use App\Services\Imports\Excel\DistributionRouteExcelTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class DistributionRouteExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_distribution_route_template_is_real_xlsx_with_dynamic_business_reference_lists(): void
    {
        $activeArea = $this->makeArea('AREA-A', 'منطقة فعالة');
        $this->makeArea('AREA-I', 'منطقة غير فعالة', 'inactive');
        $activeVehicle = $this->makeVehicle('VEH-A', 'PLATE-A');
        $this->makeVehicle('VEH-I', 'PLATE-I', 'inactive');
        $representative = $this->makeEmployee('REP-A', 'مندوب فعال', 'sales_representative');
        $this->makeEmployee('ACC-A', 'محاسب', 'accountant');

        $spreadsheet = app(DistributionRouteExcelTemplateService::class)->makeSpreadsheet();
        $sheet = $spreadsheet->getSheet(0);
        $references = $spreadsheet->getSheetByName('القوائم المرجعية');

        $this->assertSame('خطوط التوزيع', $sheet->getTitle());
        $this->assertSame(
            DistributionRouteExcelImportService::HEADERS,
            $sheet->rangeToArray('A1:H1', null, true, true, false)[0],
        );
        $this->assertNotNull($spreadsheet->getSheetByName('تعليمات'));
        $this->assertNotNull($references);
        $this->assertTrue($sheet->getRightToLeft());

        foreach (['A2', 'C2', 'D2', 'E2'] as $cell) {
            $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle($cell)->getNumberFormat()->getFormatCode());
        }

        $areaCodes = $this->referenceValues($references, 'A2:A20');
        $vehicleCodes = $this->referenceValues($references, 'D2:D20');
        $representativeCodes = $this->referenceValues($references, 'H2:H20');

        $this->assertContains($activeArea->code, $areaCodes);
        $this->assertNotContains('AREA-I', $areaCodes);
        $this->assertContains($activeVehicle->code, $vehicleCodes);
        $this->assertNotContains('VEH-I', $vehicleCodes);
        $this->assertContains($representative->employee_code, $representativeCodes);
        $this->assertNotContains('ACC-A', $representativeCodes);

        $this->assertNotNull($spreadsheet->getNamedRange('ACTIVE_AREA_CODES'));
        $this->assertNotNull($spreadsheet->getNamedRange('ACTIVE_VEHICLE_CODES'));
        $this->assertNull($spreadsheet->getNamedRange('ACTIVE_DRIVER_CODES'));
        $this->assertNotNull($spreadsheet->getNamedRange('ACTIVE_SALES_REP_CODES'));
        $this->assertSame('=ACTIVE_AREA_CODES', $sheet->getDataValidation('C2')->getFormula1());
        $this->assertSame('=ACTIVE_VEHICLE_CODES', $sheet->getDataValidation('D2')->getFormula1());
        $this->assertSame('=ACTIVE_SALES_REP_CODES', $sheet->getDataValidation('E2')->getFormula1());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('G2')->getType());
    }

    public function test_valid_distribution_routes_import_all_supported_values(): void
    {
        $area = $this->makeArea('AREA-001', 'دمشق');
        $vehicle = $this->makeVehicle('VEH-001', '001234');
        $representative = $this->makeEmployee('REP-001', 'المندوب', 'sales_representative');

        $path = $this->makeWorkbook([
            ['ROUTE-001', 'خط أول', 'AREA-001', 'VEH-001', 'REP-001', 'saturday,monday,wednesday', 'active', 'ملاحظات'],
            ['ROUTE-002', 'خط ثان', 'AREA-001', null, null, null, 'inactive', null],
        ]);

        $service = app(DistributionRouteExcelImportService::class);
        $analysis = $service->analyze($path, 'routes.xlsx');

        $this->assertTrue($analysis['valid'], implode(' | ', $analysis['errors']));
        $this->assertSame(2, $analysis['row_count']);
        $this->assertSame(['saturday', 'monday', 'wednesday'], $analysis['rows'][0]['visit_days']);

        $result = $service->import($path, 'routes.xlsx');

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertSame(2, $result['imported_count']);

        $route = DistributionRoute::query()->where('code', 'ROUTE-001')->firstOrFail();
        $this->assertSame($area->id, $route->area_id);
        $this->assertSame($vehicle->id, $route->vehicle_id);
        $this->assertSame($representative->id, $route->sales_representative_id);
        $this->assertSame(['saturday', 'monday', 'wednesday'], $route->visit_days);
        $this->assertSame('active', $route->status);

        $optionalRoute = DistributionRoute::query()->where('code', 'ROUTE-002')->firstOrFail();
        $this->assertNull($optionalRoute->vehicle_id);
        $this->assertNull($optionalRoute->sales_representative_id);
        $this->assertSame([], $optionalRoute->visit_days);
        $this->assertSame('inactive', $optionalRoute->status);
    }

    public function test_blank_status_defaults_to_active(): void
    {
        $this->makeArea('AREA-001', 'دمشق');

        $path = $this->makeWorkbook([
            ['ROUTE-DEFAULT', 'خط افتراضي', 'AREA-001', null, null, null, null, null],
        ]);

        $result = app(DistributionRouteExcelImportService::class)->import($path, 'routes.xlsx');

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertDatabaseHas('distribution_routes', [
            'code' => 'ROUTE-DEFAULT',
            'status' => 'active',
        ]);
    }

    public function test_missing_area_is_rejected_all_or_nothing(): void
    {
        $this->makeArea('AREA-001', 'دمشق');

        $path = $this->makeWorkbook([
            ['ROUTE-OK', 'خط سليم', 'AREA-001', null, null, null, 'active', null],
            ['ROUTE-BAD', 'خط خاطئ', 'AREA-404', null, null, null, 'active', null],
        ]);

        $result = app(DistributionRouteExcelImportService::class)->import($path, 'routes.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertStringContainsString('AREA-404', implode(' | ', $result['errors']));
        $this->assertDatabaseMissing('distribution_routes', ['code' => 'ROUTE-OK']);
        $this->assertDatabaseMissing('distribution_routes', ['code' => 'ROUTE-BAD']);
    }

    public function test_inactive_area_is_rejected(): void
    {
        $this->makeArea('AREA-I', 'غير فعالة', 'inactive');

        $analysis = app(DistributionRouteExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['ROUTE-001', 'خط', 'AREA-I', null, null, null, 'active', null],
            ]),
            'routes.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('غير فعالة', implode(' | ', $analysis['errors']));
    }

    public function test_missing_or_inactive_vehicle_is_rejected(): void
    {
        $this->makeArea('AREA-001', 'دمشق');
        $this->makeVehicle('VEH-I', 'PLATE-I', 'inactive');

        $analysis = app(DistributionRouteExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['ROUTE-404', 'مفقودة', 'AREA-001', 'VEH-404', null, null, 'active', null],
                ['ROUTE-I', 'غير فعالة', 'AREA-001', 'VEH-I', null, null, 'active', null],
            ]),
            'routes.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('VEH-404', $errors);
        $this->assertStringContainsString('السيارة VEH-I موجودة لكنها غير فعالة', $errors);
    }

    public function test_legacy_workbook_with_driver_code_is_rejected(): void
    {
        $this->makeArea('AREA-001', 'دمشق');

        $legacyHeaders = [
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
        $analysis = app(DistributionRouteExcelImportService::class)->analyze($this->makeWorkbook(
            [['ROUTE-LEGACY', 'خط قديم', 'AREA-001', null, 'DRV-001', null, null, 'active', null]],
            $legacyHeaders,
        ), 'routes.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('رؤوس الأعمدة غير مطابقة للقالب', implode(' | ', $analysis['errors']));
        $this->assertDatabaseMissing('distribution_routes', ['code' => 'ROUTE-LEGACY']);
    }

    public function test_missing_inactive_and_unqualified_sales_representative_are_rejected(): void
    {
        $this->makeArea('AREA-001', 'دمشق');
        $this->makeEmployee('REP-I', 'غير فعال', 'sales_representative', 'inactive');
        $this->makeEmployee('ACC-001', 'محاسب', 'accountant');

        $analysis = app(DistributionRouteExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['ROUTE-404', 'مفقود', 'AREA-001', null, 'REP-404', null, 'active', null],
                ['ROUTE-I', 'غير فعال', 'AREA-001', null, 'REP-I', null, 'active', null],
                ['ROUTE-U', 'غير مؤهل', 'AREA-001', null, 'ACC-001', null, 'active', null],
            ]),
            'routes.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('REP-404', $errors);
        $this->assertStringContainsString('مندوب المبيعات REP-I موجود لكنه غير فعال', $errors);
        $this->assertStringContainsString('ACC-001 غير مؤهل للعمل كمندوب مبيعات', $errors);
    }

    public function test_invalid_or_duplicate_visit_days_are_rejected(): void
    {
        $this->makeArea('AREA-001', 'دمشق');

        $analysis = app(DistributionRouteExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['ROUTE-BAD-DAY', 'يوم خاطئ', 'AREA-001', null, null, 'monday,holiday', 'active', null],
                ['ROUTE-DUP-DAY', 'يوم مكرر', 'AREA-001', null, null, 'friday,friday', 'active', null],
            ]),
            'routes.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('holiday غير صالح', $errors);
        $this->assertStringContainsString('friday مكرر داخل نفس الصف', $errors);
    }

    public function test_duplicate_route_code_inside_workbook_is_rejected(): void
    {
        $this->makeArea('AREA-001', 'دمشق');

        $analysis = app(DistributionRouteExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['ROUTE-DUP', 'أول', 'AREA-001', null, null, null, 'active', null],
                ['route-dup', 'ثان', 'AREA-001', null, null, null, 'active', null],
            ]),
            'routes.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('مكرر داخل ملف Excel نفسه', implode(' | ', $analysis['errors']));
    }

    public function test_existing_route_code_is_rejected_before_import(): void
    {
        $area = $this->makeArea('AREA-001', 'دمشق');
        DistributionRoute::query()->create([
            'area_id' => $area->id,
            'code' => 'ROUTE-EXISTS',
            'name' => 'موجود',
            'visit_days' => [],
            'status' => 'active',
        ]);

        $analysis = app(DistributionRouteExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['ROUTE-EXISTS', 'جديد', 'AREA-001', null, null, null, 'active', null],
            ]),
            'routes.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('موجود مسبقًا في النظام', implode(' | ', $analysis['errors']));
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->makeArea('AREA-001', 'دمشق');

        $analysis = app(DistributionRouteExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['ROUTE-BAD', 'خط', 'AREA-001', null, null, null, 'paused', null],
            ]),
            'routes.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('active أو inactive', implode(' | ', $analysis['errors']));
    }

    public function test_required_code_name_and_area_are_enforced(): void
    {
        $this->makeArea('AREA-001', 'دمشق');

        $analysis = app(DistributionRouteExcelImportService::class)->analyze(
            $this->makeWorkbook([
                [null, 'بلا رمز', 'AREA-001', null, null, null, 'active', null],
                ['ROUTE-NAMELESS', null, 'AREA-001', null, null, null, 'active', null],
                ['ROUTE-AREALESS', 'بلا منطقة', null, null, null, null, 'active', null],
            ]),
            'routes.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('رمز خط التوزيع مطلوب', $errors);
        $this->assertStringContainsString('اسم خط التوزيع مطلوب', $errors);
        $this->assertStringContainsString('رمز المنطقة مطلوب', $errors);
    }

    public function test_preview_preserves_real_excel_row_numbers_when_blank_rows_exist(): void
    {
        $this->makeArea('AREA-001', 'دمشق');

        $analysis = app(DistributionRouteExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['ROUTE-ROW-2', 'أول', 'AREA-001', null, null, null, 'active', null],
                [null, null, null, null, null, null, null, null],
                ['ROUTE-ROW-4', 'ثان', 'AREA-001', null, null, 'friday', 'active', null],
            ]),
            'routes.xlsx',
        );

        $this->assertTrue($analysis['valid'], implode(' | ', $analysis['errors']));
        $this->assertSame([2, 4], array_column($analysis['rows'], 'excel_row'));
    }

    public function test_distribution_route_excel_preview_view_contains_business_reference_preview(): void
    {
        $contents = file_get_contents(resource_path('views/filament/imports/distribution-route-excel-preview.blade.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('area_code', $contents);
        $this->assertStringContainsString('vehicle_code', $contents);
        $this->assertStringNotContainsString('driver_code', $contents);
        $this->assertStringContainsString('sales_representative_code', $contents);
        $this->assertStringContainsString("row['excel_row']", $contents);
        $this->assertStringContainsString('fd-route-import-preview', $contents);
    }

    public function test_non_xlsx_extension_is_rejected_even_if_contents_are_xlsx(): void
    {
        $this->makeArea('AREA-001', 'دمشق');

        $analysis = app(DistributionRouteExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['ROUTE-001', 'خط', 'AREA-001', null, null, null, 'active', null],
            ]),
            'routes.csv',
        );

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('.xlsx', implode(' | ', $analysis['errors']));
    }

    private function makeArea(string $code, string $name, string $status = 'active'): Area
    {
        return Area::query()->create([
            'code' => $code,
            'name_ar' => $name,
            'status' => $status,
        ]);
    }

    private function makeVehicle(string $code, string $plate, string $status = 'active'): Vehicle
    {
        return Vehicle::query()->create([
            'code' => $code,
            'plate_number' => $plate,
            'name' => $code,
            'status' => $status,
        ]);
    }

    private function makeEmployee(
        string $code,
        string $name,
        string $type,
        string $status = 'active',
        ?int $userId = null,
    ): Employee {
        return Employee::query()->create([
            'user_id' => $userId,
            'employee_code' => $code,
            'name' => $name,
            'type' => $type,
            'status' => $status,
        ]);
    }

    /** @return list<string> */
    private function referenceValues($sheet, string $range): array
    {
        return array_values(array_filter(array_map(
            static fn (array $row): mixed => $row[0] ?? null,
            $sheet->rangeToArray($range, null, true, true, false),
        )));
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @param  list<string>|null  $headers
     */
    private function makeWorkbook(array $rows, ?array $headers = null): string
    {
        $headers ??= DistributionRouteExcelImportService::HEADERS;
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;
            $row = array_pad($row, count($headers), null);

            foreach ($row as $columnIndex => $value) {
                if ($value === null) {
                    continue;
                }

                $sheet->setCellValue([$columnIndex + 1, $excelRow], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'route-import-').'.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);

        return $path;
    }
}
