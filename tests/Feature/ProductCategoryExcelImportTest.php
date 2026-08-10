<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Services\Imports\Excel\ProductCategoryExcelImportService;
use App\Services\Imports\Excel\ProductCategoryExcelTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class ProductCategoryExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_category_template_is_a_real_xlsx_with_expected_headers_and_instructions(): void
    {
        $spreadsheet = app(ProductCategoryExcelTemplateService::class)->makeSpreadsheet();

        $this->assertSame('التصنيفات', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame(ProductCategoryExcelImportService::HEADERS, $spreadsheet->getSheet(0)->rangeToArray('A1:F1', null, true, true, false)[0]);
        $this->assertNotNull($spreadsheet->getSheetByName('تعليمات'));
        $this->assertTrue($spreadsheet->getSheet(0)->getRightToLeft());
        $this->assertTrue($spreadsheet->getSheet(0)->dataValidationExists('D2'));
        $this->assertTrue($spreadsheet->getSheet(0)->dataValidationExists('E2'));
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_WHOLE, $spreadsheet->getSheet(0)->getDataValidation('D2')->getType());
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST, $spreadsheet->getSheet(0)->getDataValidation('E2')->getType());
    }

    public function test_valid_categories_resolve_existing_and_same_workbook_parents_even_when_child_comes_first(): void
    {
        $existingParent = ProductCategory::query()->create([
            'code' => 'ROOT-EXISTING',
            'name_ar' => 'أب موجود',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $path = $this->makeWorkbook([
            ['CHILD-FIRST', 'ابن قبل الأب', 'PARENT-LATER', 20, 'active', null],
            ['PARENT-LATER', 'أب داخل الملف', null, 10, 'active', null],
            ['CHILD-EXISTING', 'ابن لأب موجود', 'ROOT-EXISTING', 30, 'inactive', 'اختبار'],
        ]);

        $service = app(ProductCategoryExcelImportService::class);
        $analysis = $service->analyze($path, 'product-categories.xlsx');

        $this->assertTrue($analysis['valid']);
        $this->assertSame(3, $analysis['row_count']);
        $this->assertSame(3, $analysis['valid_rows']);
        $this->assertSame([], $analysis['errors']);

        $result = $service->import($path, 'product-categories.xlsx');

        $this->assertTrue($result['valid']);
        $this->assertSame(3, $result['imported_count']);

        $parentLater = ProductCategory::query()->where('code', 'PARENT-LATER')->firstOrFail();
        $childFirst = ProductCategory::query()->where('code', 'CHILD-FIRST')->firstOrFail();
        $childExisting = ProductCategory::query()->where('code', 'CHILD-EXISTING')->firstOrFail();

        $this->assertSame($parentLater->id, $childFirst->parent_id);
        $this->assertSame($existingParent->id, $childExisting->parent_id);
        $this->assertSame('inactive', $childExisting->status);
        $this->assertSame(30, $childExisting->sort_order);
    }

    public function test_missing_parent_prevents_entire_import(): void
    {
        $path = $this->makeWorkbook([
            ['CAT-010', 'تصنيف سليم', null, 0, 'active', null],
            ['CAT-011', 'تصنيف بأب مفقود', 'MISSING-PARENT', 0, 'active', null],
        ]);

        $result = app(ProductCategoryExcelImportService::class)->import($path, 'product-categories.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertDatabaseMissing('product_categories', ['code' => 'CAT-010']);
        $this->assertDatabaseMissing('product_categories', ['code' => 'CAT-011']);
        $this->assertStringContainsString('الصف 3', implode(' ', $result['errors']));
        $this->assertStringContainsString('غير موجود', implode(' ', $result['errors']));
    }

    public function test_inactive_existing_parent_is_rejected(): void
    {
        ProductCategory::query()->create([
            'code' => 'PARENT-INACTIVE',
            'name_ar' => 'أب غير فعال',
            'status' => 'inactive',
            'sort_order' => 0,
        ]);

        $path = $this->makeWorkbook([
            ['CAT-020', 'ابن', 'PARENT-INACTIVE', 0, 'active', null],
        ]);

        $analysis = app(ProductCategoryExcelImportService::class)->analyze($path, 'product-categories.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('غير فعال', implode(' ', $analysis['errors']));
    }

    public function test_inactive_parent_inside_workbook_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['PARENT-INACTIVE-WB', 'أب غير فعال', null, 0, 'inactive', null],
            ['CAT-021', 'ابن', 'PARENT-INACTIVE-WB', 0, 'active', null],
        ]);

        $analysis = app(ProductCategoryExcelImportService::class)->analyze($path, 'product-categories.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('داخل الملف لكنه غير فعال', implode(' ', $analysis['errors']));
    }

    public function test_parent_cycle_inside_workbook_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['CAT-A', 'تصنيف أ', 'CAT-B', 0, 'active', null],
            ['CAT-B', 'تصنيف ب', 'CAT-A', 0, 'active', null],
        ]);

        $analysis = app(ProductCategoryExcelImportService::class)->analyze($path, 'product-categories.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('تسلسل دائري', implode(' ', $analysis['errors']));
    }

    public function test_self_parent_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['CAT-SELF', 'تصنيف ذاتي', 'CAT-SELF', 0, 'active', null],
        ]);

        $analysis = app(ProductCategoryExcelImportService::class)->analyze($path, 'product-categories.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('أبًا لنفسه', implode(' ', $analysis['errors']));
    }

    public function test_duplicate_code_inside_workbook_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['CAT-030', 'الأول', null, 0, 'active', null],
            ['CAT-030', 'الثاني', null, 1, 'active', null],
        ]);

        $analysis = app(ProductCategoryExcelImportService::class)->analyze($path, 'product-categories.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('مكرر داخل ملف Excel', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('product_categories', 0);
    }

    public function test_existing_category_code_is_rejected_before_import(): void
    {
        ProductCategory::query()->create([
            'code' => 'CAT-040',
            'name_ar' => 'موجود',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $path = $this->makeWorkbook([
            ['CAT-040', 'نسخة أخرى', null, 0, 'active', null],
        ]);

        $analysis = app(ProductCategoryExcelImportService::class)->analyze($path, 'product-categories.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('موجود مسبقًا', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('product_categories', 1);
    }

    public function test_sort_order_and_status_defaults_are_applied(): void
    {
        $path = $this->makeWorkbook([
            ['CAT-050', 'افتراضي', null, null, null, null],
        ]);

        $result = app(ProductCategoryExcelImportService::class)->import($path, 'product-categories.xlsx');

        $this->assertTrue($result['valid']);
        $this->assertDatabaseHas('product_categories', [
            'code' => 'CAT-050',
            'sort_order' => 0,
            'status' => 'active',
        ]);
    }

    public function test_preview_preserves_real_excel_row_numbers_when_blank_rows_exist(): void
    {
        $path = $this->makeWorkbook([
            ['CAT-060', 'الأولى', null, 0, 'active', null],
            [null, null, null, null, null, null],
            ['CAT-061', 'الثانية', null, 2, 'inactive', null],
        ]);

        $analysis = app(ProductCategoryExcelImportService::class)->analyze($path, 'product-categories.xlsx');

        $this->assertTrue($analysis['valid']);
        $this->assertSame(2, $analysis['row_count']);
        $this->assertSame(2, $analysis['rows'][0]['excel_row']);
        $this->assertSame(4, $analysis['rows'][1]['excel_row']);
    }

    public function test_product_category_excel_preview_view_contains_hierarchy_preview(): void
    {
        $source = file_get_contents(resource_path('views/filament/imports/product-category-excel-preview.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('معاينة البيانات', $source);
        $this->assertStringContainsString('رمز الأب', $source);
        $this->assertStringContainsString('صف Excel', $source);
        $this->assertStringContainsString('{{ $row[\'excel_row\'] }}', $source);
        $this->assertStringContainsString('array_slice($preview[\'rows\'], 0, 10)', $source);
    }

    public function test_non_xlsx_extension_is_rejected_even_if_contents_are_xlsx(): void
    {
        $path = $this->makeWorkbook([
            ['CAT-070', 'اختبار', null, 0, 'active', null],
        ]);

        $analysis = app(ProductCategoryExcelImportService::class)->analyze($path, 'product-categories.xls');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('.xlsx', implode(' ', $analysis['errors']));
    }

    /** @param list<list<mixed>> $rows */
    private function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(ProductCategoryExcelImportService::HEADERS, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'product-categories-xlsx-');
        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        (new XlsxWriter($spreadsheet))->save($xlsxPath);

        $this->beforeApplicationDestroyed(static function () use ($xlsxPath): void {
            @unlink($xlsxPath);
        });

        return $xlsxPath;
    }
}
