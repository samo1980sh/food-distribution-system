<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Services\Imports\Excel\ProductExcelImportService;
use App\Services\Imports\Excel\ProductExcelTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class ProductExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_template_is_a_real_xlsx_with_expected_headers_instructions_and_validations(): void
    {
        $this->makeCategory('CAT-ACTIVE', 'active');
        $this->makeCategory('CAT-INACTIVE', 'inactive');
        $this->makeUnit('UNIT-ACTIVE', 'active');
        $this->makeUnit('UNIT-INACTIVE', 'inactive');

        $spreadsheet = app(ProductExcelTemplateService::class)->makeSpreadsheet();
        $sheet = $spreadsheet->getSheet(0);
        $references = $spreadsheet->getSheetByName('القوائم المرجعية');

        $this->assertSame('المنتجات', $sheet->getTitle());
        $this->assertSame(ProductExcelImportService::HEADERS, $sheet->rangeToArray('A1:L1', null, true, true, false)[0]);
        $this->assertNotNull($spreadsheet->getSheetByName('تعليمات'));
        $this->assertNotNull($references);
        $this->assertTrue($sheet->getRightToLeft());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('A2')->getNumberFormat()->getFormatCode());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('B2')->getNumberFormat()->getFormatCode());
        $this->assertSame('CAT-ACTIVE', $references->getCell('A2')->getValue());
        $this->assertSame('UNIT-ACTIVE', $references->getCell('D2')->getValue());
        $this->assertNotContains('CAT-INACTIVE', $references->rangeToArray('A2:A20', null, true, true, false));
        $this->assertNotContains('UNIT-INACTIVE', $references->rangeToArray('D2:D20', null, true, true, false));
        $this->assertTrue($sheet->dataValidationExists('D2'));
        $this->assertTrue($sheet->dataValidationExists('E2'));
        $this->assertTrue($sheet->dataValidationExists('F2'));
        $this->assertTrue($sheet->dataValidationExists('I2'));
        $this->assertTrue($sheet->dataValidationExists('J2'));
        $this->assertTrue($sheet->dataValidationExists('K2'));
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('D2')->getType());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('E2')->getType());
        $this->assertSame('=ACTIVE_CATEGORY_CODES', $sheet->getDataValidation('D2')->getFormula1());
        $this->assertSame('=ACTIVE_UNIT_CODES', $sheet->getDataValidation('E2')->getFormula1());
        $this->assertSame(DataValidation::TYPE_DECIMAL, $sheet->getDataValidation('F2')->getType());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('J2')->getType());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('K2')->getType());
    }

    public function test_valid_products_resolve_active_category_and_unit_and_import_all_values(): void
    {
        $category = $this->makeCategory('CAT-ACTIVE', 'active');
        $unit = $this->makeUnit('UNIT-ACTIVE', 'active');

        $path = $this->makeWorkbook([
            ['PRD-001', '0012345678901', 'منتج تجريبي', 'CAT-ACTIVE', 'UNIT-ACTIVE', 12.50, 20, 18, 3.250, 1, 'active', 'اختبار'],
        ]);

        $service = app(ProductExcelImportService::class);
        $analysis = $service->analyze($path, 'products.xlsx');

        $this->assertTrue($analysis['valid']);
        $this->assertSame(1, $analysis['row_count']);
        $this->assertSame(1, $analysis['valid_rows']);
        $this->assertSame([], $analysis['errors']);

        $result = $service->import($path, 'products.xlsx');

        $this->assertTrue($result['valid']);
        $this->assertSame(1, $result['imported_count']);

        $product = Product::query()->where('sku', 'PRD-001')->firstOrFail();
        $this->assertSame('0012345678901', $product->barcode);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($unit->id, $product->unit_id);
        $this->assertSame('12.50', $product->purchase_price);
        $this->assertSame('20.00', $product->sale_price);
        $this->assertSame('18.00', $product->wholesale_price);
        $this->assertSame('3.250', $product->min_stock);
        $this->assertTrue($product->has_expiry);
        $this->assertSame('active', $product->status);
    }

    public function test_optional_relations_and_defaults_are_applied(): void
    {
        $path = $this->makeWorkbook([
            ['PRD-DEFAULT', null, 'منتج افتراضي', null, null, null, null, null, null, null, null, null],
        ]);

        $result = app(ProductExcelImportService::class)->import($path, 'products.xlsx');

        $this->assertTrue($result['valid']);
        $product = Product::query()->where('sku', 'PRD-DEFAULT')->firstOrFail();
        $this->assertNull($product->category_id);
        $this->assertNull($product->unit_id);
        $this->assertSame('0.00', $product->purchase_price);
        $this->assertSame('0.00', $product->sale_price);
        $this->assertSame('0.00', $product->wholesale_price);
        $this->assertSame('0.000', $product->min_stock);
        $this->assertTrue($product->has_expiry);
        $this->assertSame('active', $product->status);
    }

    public function test_missing_category_prevents_entire_import(): void
    {
        $path = $this->makeWorkbook([
            ['PRD-010', null, 'سليم', null, null, 0, 0, 0, 0, 1, 'active', null],
            ['PRD-011', null, 'تصنيف مفقود', 'CAT-MISSING', null, 0, 0, 0, 0, 1, 'active', null],
        ]);

        $result = app(ProductExcelImportService::class)->import($path, 'products.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertDatabaseMissing('products', ['sku' => 'PRD-010']);
        $this->assertDatabaseMissing('products', ['sku' => 'PRD-011']);
        $this->assertStringContainsString('الصف 3', implode(' ', $result['errors']));
        $this->assertStringContainsString('CAT-MISSING', implode(' ', $result['errors']));
    }

    public function test_inactive_category_is_rejected(): void
    {
        $this->makeCategory('CAT-INACTIVE', 'inactive');

        $path = $this->makeWorkbook([
            ['PRD-020', null, 'منتج', 'CAT-INACTIVE', null, 0, 0, 0, 0, 1, 'active', null],
        ]);

        $analysis = app(ProductExcelImportService::class)->analyze($path, 'products.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('غير فعال', implode(' ', $analysis['errors']));
    }

    public function test_missing_unit_prevents_entire_import(): void
    {
        $path = $this->makeWorkbook([
            ['PRD-030', null, 'منتج', null, 'UNIT-MISSING', 0, 0, 0, 0, 1, 'active', null],
        ]);

        $analysis = app(ProductExcelImportService::class)->analyze($path, 'products.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('UNIT-MISSING', implode(' ', $analysis['errors']));
        $this->assertStringContainsString('غير موجود', implode(' ', $analysis['errors']));
    }

    public function test_inactive_unit_is_rejected(): void
    {
        $this->makeUnit('UNIT-INACTIVE', 'inactive');

        $path = $this->makeWorkbook([
            ['PRD-031', null, 'منتج', null, 'UNIT-INACTIVE', 0, 0, 0, 0, 1, 'active', null],
        ]);

        $analysis = app(ProductExcelImportService::class)->analyze($path, 'products.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('غير فعالة', implode(' ', $analysis['errors']));
    }

    public function test_duplicate_sku_inside_workbook_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['PRD-DUP', null, 'الأول', null, null, 0, 0, 0, 0, 1, 'active', null],
            ['PRD-DUP', null, 'الثاني', null, null, 0, 0, 0, 0, 1, 'active', null],
        ]);

        $analysis = app(ProductExcelImportService::class)->analyze($path, 'products.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('مكرر داخل ملف Excel', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('products', 0);
    }

    public function test_duplicate_barcode_inside_workbook_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['PRD-040', '009900', 'الأول', null, null, 0, 0, 0, 0, 1, 'active', null],
            ['PRD-041', '009900', 'الثاني', null, null, 0, 0, 0, 0, 1, 'active', null],
        ]);

        $analysis = app(ProductExcelImportService::class)->analyze($path, 'products.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('الباركود مكرر', implode(' ', $analysis['errors']));
    }

    public function test_existing_sku_is_rejected_before_import(): void
    {
        $this->makeProduct('PRD-EXISTING', 'BAR-EXISTING');

        $path = $this->makeWorkbook([
            ['PRD-EXISTING', 'BAR-NEW', 'نسخة أخرى', null, null, 0, 0, 0, 0, 1, 'active', null],
        ]);

        $analysis = app(ProductExcelImportService::class)->analyze($path, 'products.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('SKU / رمز المنتج موجود مسبقًا', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('products', 1);
    }

    public function test_existing_barcode_is_rejected_before_import(): void
    {
        $this->makeProduct('PRD-050', 'BAR-050');

        $path = $this->makeWorkbook([
            ['PRD-051', 'BAR-050', 'منتج آخر', null, null, 0, 0, 0, 0, 1, 'active', null],
        ]);

        $analysis = app(ProductExcelImportService::class)->analyze($path, 'products.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('الباركود موجود مسبقًا', implode(' ', $analysis['errors']));
    }

    public function test_invalid_numeric_boolean_and_status_values_are_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['PRD-060', null, 'منتج', null, null, -1, 'abc', 0, -0.5, 2, 'disabled', null],
        ]);

        $analysis = app(ProductExcelImportService::class)->analyze($path, 'products.xlsx');
        $errors = implode(' ', $analysis['errors']);

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('لا يجوز أن يكون سالبًا', $errors);
        $this->assertStringContainsString('سعر البيع يجب أن يكون رقمًا', $errors);
        $this->assertStringContainsString('has_expiry يجب أن تكون 1 أو 0', $errors);
        $this->assertStringContainsString('active أو inactive', $errors);
    }

    public function test_false_expiry_value_is_imported_as_false(): void
    {
        $path = $this->makeWorkbook([
            ['PRD-070', null, 'بدون صلاحية', null, null, 0, 0, 0, 0, 0, 'inactive', null],
        ]);

        $result = app(ProductExcelImportService::class)->import($path, 'products.xlsx');

        $this->assertTrue($result['valid']);
        $product = Product::query()->where('sku', 'PRD-070')->firstOrFail();
        $this->assertFalse($product->has_expiry);
        $this->assertSame('inactive', $product->status);
    }

    public function test_preview_preserves_real_excel_row_numbers_when_blank_rows_exist(): void
    {
        $path = $this->makeWorkbook([
            ['PRD-080', null, 'الأول', null, null, 0, 0, 0, 0, 1, 'active', null],
            [null, null, null, null, null, null, null, null, null, null, null, null],
            ['PRD-081', null, 'الثاني', null, null, 0, 0, 0, 0, 1, 'inactive', null],
        ]);

        $analysis = app(ProductExcelImportService::class)->analyze($path, 'products.xlsx');

        $this->assertTrue($analysis['valid']);
        $this->assertSame(2, $analysis['row_count']);
        $this->assertSame(2, $analysis['rows'][0]['excel_row']);
        $this->assertSame(4, $analysis['rows'][1]['excel_row']);
    }

    public function test_product_excel_preview_view_contains_business_reference_preview(): void
    {
        $source = file_get_contents(resource_path('views/filament/imports/product-excel-preview.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('fd-product-import-preview', $source);
        $this->assertStringContainsString('معاينة المنتجات', $source);
        $this->assertStringContainsString('صف Excel', $source);
        $this->assertStringContainsString('{{ $row[\'excel_row\'] }}', $source);
        $this->assertStringContainsString('$row[\'category_code\']', $source);
        $this->assertStringContainsString('$row[\'unit_code\']', $source);
        $this->assertStringContainsString('array_slice($preview[\'rows\'], 0, 10)', $source);
    }

    public function test_non_xlsx_extension_is_rejected_even_if_contents_are_xlsx(): void
    {
        $path = $this->makeWorkbook([
            ['PRD-090', null, 'اختبار', null, null, 0, 0, 0, 0, 1, 'active', null],
        ]);

        $analysis = app(ProductExcelImportService::class)->analyze($path, 'products.xls');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('.xlsx', implode(' ', $analysis['errors']));
    }

    private function makeCategory(string $code, string $status): ProductCategory
    {
        return ProductCategory::query()->create([
            'code' => $code,
            'name_ar' => 'تصنيف '.$code,
            'status' => $status,
            'sort_order' => 0,
        ]);
    }

    private function makeUnit(string $code, string $status): Unit
    {
        return Unit::query()->create([
            'code' => $code,
            'name_ar' => 'وحدة '.$code,
            'symbol' => null,
            'status' => $status,
            'notes' => null,
        ]);
    }

    private function makeProduct(string $sku, ?string $barcode): Product
    {
        return Product::query()->create([
            'sku' => $sku,
            'barcode' => $barcode,
            'name_ar' => 'منتج '.$sku,
            'category_id' => null,
            'unit_id' => null,
            'purchase_price' => 0,
            'sale_price' => 0,
            'wholesale_price' => 0,
            'min_stock' => 0,
            'has_expiry' => true,
            'status' => 'active',
            'notes' => null,
        ]);
    }

    /** @param list<list<mixed>> $rows */
    private function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(ProductExcelImportService::HEADERS, null, 'A1');
        $sheet->getStyle('A2:B1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->fromArray($rows, null, 'A2', true);

        $path = tempnam(sys_get_temp_dir(), 'products-xlsx-');
        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        (new XlsxWriter($spreadsheet))->save($xlsxPath);

        $this->beforeApplicationDestroyed(static function () use ($xlsxPath): void {
            @unlink($xlsxPath);
        });

        return $xlsxPath;
    }
}
