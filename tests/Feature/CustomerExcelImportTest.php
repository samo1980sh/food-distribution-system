<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Services\Imports\Excel\CustomerExcelImportService;
use App\Services\Imports\Excel\CustomerExcelTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class CustomerExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_template_is_real_xlsx_with_dynamic_active_area_and_route_lists(): void
    {
        $activeArea = $this->makeArea('AREA-A', 'منطقة فعالة');
        $inactiveArea = $this->makeArea('AREA-I', 'منطقة غير فعالة', 'inactive');
        $activeRoute = $this->makeRoute('ROUTE-A', 'خط فعال', $activeArea);
        $this->makeRoute('ROUTE-I', 'خط غير فعال', $activeArea, 'inactive');
        $this->makeRoute('ROUTE-INACTIVE-AREA', 'خط منطقة غير فعالة', $inactiveArea);

        $spreadsheet = app(CustomerExcelTemplateService::class)->makeSpreadsheet();
        $sheet = $spreadsheet->getSheet(0);
        $references = $spreadsheet->getSheetByName('القوائم المرجعية');

        $this->assertSame('العملاء', $sheet->getTitle());
        $this->assertSame(
            CustomerExcelImportService::HEADERS,
            $sheet->rangeToArray('A1:P1', null, true, true, false)[0],
        );
        $this->assertNotNull($spreadsheet->getSheetByName('تعليمات'));
        $this->assertNotNull($references);
        $this->assertTrue($sheet->getRightToLeft());

        foreach (['A2', 'E2', 'F2', 'G2', 'H2'] as $cell) {
            $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle($cell)->getNumberFormat()->getFormatCode());
        }

        $areaCodes = $this->referenceValues($references, 'A2:A20');
        $routeCodes = $this->referenceValues($references, 'D2:D20');

        $this->assertContains($activeArea->code, $areaCodes);
        $this->assertNotContains($inactiveArea->code, $areaCodes);
        $this->assertContains($activeRoute->code, $routeCodes);
        $this->assertNotContains('ROUTE-I', $routeCodes);
        $this->assertNotContains('ROUTE-INACTIVE-AREA', $routeCodes);

        $this->assertNotNull($spreadsheet->getNamedRange('ACTIVE_CUSTOMER_AREA_CODES'));
        $this->assertNotNull($spreadsheet->getNamedRange('ACTIVE_CUSTOMER_ROUTE_CODES'));
        $this->assertSame('=ACTIVE_CUSTOMER_AREA_CODES', $sheet->getDataValidation('G2')->getFormula1());
        $this->assertSame('=ACTIVE_CUSTOMER_ROUTE_CODES', $sheet->getDataValidation('H2')->getFormula1());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('D2')->getType());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('N2')->getType());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('O2')->getType());
    }

    public function test_valid_customers_import_all_supported_values(): void
    {
        $area = $this->makeArea('AREA-001', 'دمشق');
        $route = $this->makeRoute('ROUTE-001', 'خط دمشق', $area);

        $path = $this->makeWorkbook([
            ['CUS-001', 'سوبر ماركت النور', 'أحمد محمد', 'supermarket', '0110000001', '0999000001', 'AREA-001', 'ROUTE-001', 'دمشق - المزة', 33.51234567, 36.29876543, 125000.50, 45, 'credit', 'active', 'عميل تجريبي'],
        ]);

        $service = app(CustomerExcelImportService::class);
        $analysis = $service->analyze($path, 'customers.xlsx');

        $this->assertTrue($analysis['valid'], implode(' | ', $analysis['errors']));
        $this->assertSame(1, $analysis['row_count']);

        $result = $service->import($path, 'customers.xlsx');

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertSame(1, $result['imported_count']);

        $customer = Customer::query()->where('code', 'CUS-001')->firstOrFail();
        $this->assertSame($area->id, $customer->area_id);
        $this->assertSame($route->id, $customer->route_id);
        $this->assertSame('سوبر ماركت النور', $customer->name);
        $this->assertSame('أحمد محمد', $customer->owner_name);
        $this->assertSame('supermarket', $customer->customer_type);
        $this->assertSame('0110000001', $customer->phone);
        $this->assertSame('0999000001', $customer->mobile);
        $this->assertSame('125000.50', $customer->credit_limit);
        $this->assertSame(45, $customer->credit_days);
        $this->assertSame('credit', $customer->payment_type);
        $this->assertSame('active', $customer->status);
        $this->assertNull($customer->created_by);
        $this->assertNull($customer->client_reference);
        $this->assertNull($customer->client_payload_hash);
    }

    public function test_route_code_infers_area_when_area_code_is_blank(): void
    {
        $area = $this->makeArea('AREA-001', 'دمشق');
        $route = $this->makeRoute('ROUTE-001', 'خط دمشق', $area);

        $path = $this->makeWorkbook([
            ['CUS-INFER', 'عميل', null, null, null, null, null, 'ROUTE-001', null, null, null, null, null, null, null, null],
        ]);

        $service = app(CustomerExcelImportService::class);
        $analysis = $service->analyze($path, 'customers.xlsx');

        $this->assertTrue($analysis['valid'], implode(' | ', $analysis['errors']));
        $this->assertSame('AREA-001', $analysis['rows'][0]['area_code']);

        $result = $service->import($path, 'customers.xlsx');
        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));

        $this->assertDatabaseHas('customers', [
            'code' => 'CUS-INFER',
            'area_id' => $area->id,
            'route_id' => $route->id,
        ]);
    }

    public function test_blank_optional_defaults_are_applied(): void
    {
        $result = app(CustomerExcelImportService::class)->import(
            $this->makeWorkbook([
                ['CUS-DEFAULT', 'عميل افتراضي', null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertDatabaseHas('customers', [
            'code' => 'CUS-DEFAULT',
            'customer_type' => 'grocery',
            'credit_limit' => 0,
            'credit_days' => 30,
            'payment_type' => 'cash',
            'status' => 'active',
            'area_id' => null,
            'route_id' => null,
        ]);
    }

    public function test_missing_or_inactive_area_is_rejected_all_or_nothing(): void
    {
        $this->makeArea('AREA-I', 'غير فعالة', 'inactive');

        $path = $this->makeWorkbook([
            ['CUS-OK', 'سليم', null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            ['CUS-MISSING', 'مفقودة', null, null, null, null, 'AREA-404', null, null, null, null, null, null, null, null, null],
            ['CUS-INACTIVE', 'غير فعالة', null, null, null, null, 'AREA-I', null, null, null, null, null, null, null, null, null],
        ]);

        $result = app(CustomerExcelImportService::class)->import($path, 'customers.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $errors = implode(' | ', $result['errors']);
        $this->assertStringContainsString('AREA-404', $errors);
        $this->assertStringContainsString('المنطقة AREA-I موجودة لكنها غير فعالة', $errors);
        $this->assertDatabaseMissing('customers', ['code' => 'CUS-OK']);
    }

    public function test_missing_or_inactive_route_is_rejected(): void
    {
        $area = $this->makeArea('AREA-001', 'دمشق');
        $this->makeRoute('ROUTE-I', 'غير فعال', $area, 'inactive');

        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['CUS-404', 'مفقود', null, null, null, null, 'AREA-001', 'ROUTE-404', null, null, null, null, null, null, null, null],
                ['CUS-I', 'غير فعال', null, null, null, null, 'AREA-001', 'ROUTE-I', null, null, null, null, null, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('ROUTE-404', $errors);
        $this->assertStringContainsString('خط التوزيع ROUTE-I موجود لكنه غير فعال', $errors);
    }

    public function test_route_under_inactive_area_is_rejected(): void
    {
        $area = $this->makeArea('AREA-I', 'غير فعالة', 'inactive');
        $this->makeRoute('ROUTE-I-AREA', 'خط', $area);

        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['CUS-BAD', 'عميل', null, null, null, null, null, 'ROUTE-I-AREA', null, null, null, null, null, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('يتبع منطقة غير فعالة', implode(' | ', $analysis['errors']));
    }

    public function test_route_must_match_explicit_area(): void
    {
        $areaA = $this->makeArea('AREA-A', 'أ');
        $areaB = $this->makeArea('AREA-B', 'ب');
        $this->makeRoute('ROUTE-A', 'خط أ', $areaA);

        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['CUS-MISMATCH', 'عميل', null, null, null, null, 'AREA-B', 'ROUTE-A', null, null, null, null, null, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('لا يتبع المنطقة AREA-B', implode(' | ', $analysis['errors']));
    }

    public function test_invalid_customer_type_payment_type_and_status_are_rejected(): void
    {
        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['CUS-BAD-TYPE', 'عميل', null, 'factory', null, null, null, null, null, null, null, null, null, null, null, null],
                ['CUS-BAD-PAYMENT', 'عميل', null, null, null, null, null, null, null, null, null, null, null, 'yearly', null, null],
                ['CUS-BAD-STATUS', 'عميل', null, null, null, null, null, null, null, null, null, null, null, null, 'paused', null],
            ]),
            'customers.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('نوع العميل غير صالح', $errors);
        $this->assertStringContainsString('طريقة الدفع غير صالحة', $errors);
        $this->assertStringContainsString('active أو inactive', $errors);
    }

    public function test_invalid_credit_policy_values_are_rejected(): void
    {
        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['CUS-LIMIT', 'عميل', null, null, null, null, null, null, null, null, null, -1, 30, null, null, null],
                ['CUS-DAYS-LOW', 'عميل', null, null, null, null, null, null, null, null, null, 0, 0, null, null, null],
                ['CUS-DAYS-HIGH', 'عميل', null, null, null, null, null, null, null, null, null, 0, 366, null, null, null],
                ['CUS-DAYS-FLOAT', 'عميل', null, null, null, null, null, null, null, null, null, 0, 30.5, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('حد الائتمان لا يمكن أن يكون سالبًا', $errors);
        $this->assertStringContainsString('مدة الائتمان يجب ألا تقل عن يوم واحد', $errors);
        $this->assertStringContainsString('مدة الائتمان يجب ألا تتجاوز 365 يومًا', $errors);
        $this->assertStringContainsString('مدة الائتمان يجب أن تكون عددًا صحيحًا', $errors);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['CUS-LAT', 'عميل', null, null, null, null, null, null, null, 'north', 36.2, null, null, null, null, null],
                ['CUS-LNG', 'عميل', null, null, null, null, null, null, null, 33.5, 'east', null, null, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('خط العرض يجب أن يكون رقمًا صالحًا', $errors);
        $this->assertStringContainsString('خط الطول يجب أن يكون رقمًا صالحًا', $errors);
    }

    public function test_duplicate_customer_code_inside_workbook_is_rejected(): void
    {
        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['CUS-DUP', 'أول', null, null, null, null, null, null, null, null, null, null, null, null, null, null],
                ['cus-dup', 'ثان', null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('مكرر داخل ملف Excel نفسه', implode(' | ', $analysis['errors']));
    }

    public function test_existing_customer_code_is_rejected_before_import(): void
    {
        Customer::query()->create([
            'code' => 'CUS-EXISTS',
            'name' => 'موجود',
            'customer_type' => 'grocery',
            'credit_limit' => 0,
            'credit_days' => 30,
            'payment_type' => 'cash',
            'status' => 'active',
        ]);

        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['CUS-EXISTS', 'جديد', null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('موجود مسبقًا في النظام', implode(' | ', $analysis['errors']));
    }

    public function test_required_code_and_name_are_enforced(): void
    {
        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                [null, 'بلا رمز', null, null, null, null, null, null, null, null, null, null, null, null, null, null],
                ['CUS-NAMELESS', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('رمز العميل مطلوب', $errors);
        $this->assertStringContainsString('اسم العميل مطلوب', $errors);
    }

    public function test_customer_without_area_or_route_is_allowed(): void
    {
        $result = app(CustomerExcelImportService::class)->import(
            $this->makeWorkbook([
                ['CUS-NO-ROUTE', 'عميل غير موزع', null, 'other', null, null, null, null, null, null, null, 0, 30, 'cash', 'active', null],
            ]),
            'customers.xlsx',
        );

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertDatabaseHas('customers', [
            'code' => 'CUS-NO-ROUTE',
            'area_id' => null,
            'route_id' => null,
        ]);
    }

    public function test_text_business_code_phone_and_mobile_keep_leading_zeroes(): void
    {
        $result = app(CustomerExcelImportService::class)->import(
            $this->makeWorkbook([
                ['000123', 'عميل أصفار', null, null, '0011002200', '0099998888', null, null, null, null, null, null, null, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $customer = Customer::query()->where('code', '000123')->firstOrFail();
        $this->assertSame('000123', $customer->code);
        $this->assertSame('0011002200', $customer->phone);
        $this->assertSame('0099998888', $customer->mobile);
    }

    public function test_preview_preserves_real_excel_row_numbers_when_blank_rows_exist(): void
    {
        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['CUS-ROW-2', 'أول', null, null, null, null, null, null, null, null, null, null, null, null, null, null],
                [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
                ['CUS-ROW-4', 'ثان', null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            ]),
            'customers.xlsx',
        );

        $this->assertTrue($analysis['valid'], implode(' | ', $analysis['errors']));
        $this->assertSame([2, 4], array_column($analysis['rows'], 'excel_row'));
    }

    public function test_customer_excel_preview_view_contains_distribution_and_credit_preview(): void
    {
        $contents = file_get_contents(resource_path('views/filament/imports/customer-excel-preview.blade.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('area_code', $contents);
        $this->assertStringContainsString('route_code', $contents);
        $this->assertStringContainsString('credit_limit', $contents);
        $this->assertStringContainsString('payment_type', $contents);
        $this->assertStringContainsString("row['excel_row']", $contents);
        $this->assertStringContainsString('fd-customer-import-preview', $contents);
    }

    public function test_import_payload_does_not_expose_internal_mobile_audit_fields(): void
    {
        $contents = file_get_contents(app_path('Services/Imports/Excel/CustomerExcelImportService.php'));

        $this->assertIsString($contents);
        $this->assertStringNotContainsString("'created_by' =>", $contents);
        $this->assertStringNotContainsString("'client_reference' =>", $contents);
        $this->assertStringNotContainsString("'client_payload_hash' =>", $contents);
        $this->assertStringNotContainsString("'operation_source' =>", $contents);
    }

    public function test_non_xlsx_extension_is_rejected_even_if_contents_are_xlsx(): void
    {
        $analysis = app(CustomerExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['CUS-001', 'عميل', null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            ]),
            'customers.csv',
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

    private function makeRoute(string $code, string $name, Area $area, string $status = 'active'): DistributionRoute
    {
        return DistributionRoute::query()->create([
            'area_id' => $area->id,
            'code' => $code,
            'name' => $name,
            'visit_days' => [],
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

    /** @param list<list<mixed>> $rows */
    private function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(CustomerExcelImportService::HEADERS, null, 'A1');

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;
            $row = array_pad($row, count(CustomerExcelImportService::HEADERS), null);

            foreach ($row as $columnIndex => $value) {
                if ($value === null) {
                    continue;
                }

                $sheet->setCellValue([$columnIndex + 1, $excelRow], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'customer-import-').'.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);

        return $path;
    }
}
