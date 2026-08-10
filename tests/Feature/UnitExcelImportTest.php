<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Services\Imports\Excel\UnitExcelImportService;
use App\Services\Imports\Excel\UnitExcelTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class UnitExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_template_is_a_real_xlsx_with_expected_headers_and_instructions(): void
    {
        $spreadsheet = app(UnitExcelTemplateService::class)->makeSpreadsheet();

        $this->assertSame('الوحدات', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame(UnitExcelImportService::HEADERS, $spreadsheet->getSheet(0)->rangeToArray('A1:E1', null, true, true, false)[0]);
        $this->assertNotNull($spreadsheet->getSheetByName('تعليمات'));
        $this->assertTrue($spreadsheet->getSheet(0)->getRightToLeft());
    }

    public function test_valid_unit_xlsx_is_analyzed_and_imported_all_at_once(): void
    {
        $path = $this->makeWorkbook([
            ['KG-TEST', 'كيلوغرام اختبار', 'كغ', 'active', null],
            ['BOX-TEST', 'صندوق اختبار', 'صندوق', 'inactive', 'اختبار'],
        ]);

        $service = app(UnitExcelImportService::class);
        $analysis = $service->analyze($path, 'units.xlsx');

        $this->assertTrue($analysis['valid']);
        $this->assertSame(2, $analysis['row_count']);
        $this->assertSame(2, $analysis['valid_rows']);
        $this->assertSame([], $analysis['errors']);
        $this->assertSame(2, $analysis['rows'][0]['excel_row']);
        $this->assertSame(3, $analysis['rows'][1]['excel_row']);

        $result = $service->import($path, 'units.xlsx');

        $this->assertTrue($result['valid']);
        $this->assertSame(2, $result['imported_count']);
        $this->assertDatabaseHas('units', ['code' => 'KG-TEST', 'name_ar' => 'كيلوغرام اختبار', 'symbol' => 'كغ', 'status' => 'active']);
        $this->assertDatabaseHas('units', ['code' => 'BOX-TEST', 'status' => 'inactive']);
    }

    public function test_one_invalid_row_prevents_the_entire_import(): void
    {
        $path = $this->makeWorkbook([
            ['UNIT-010', 'وحدة سليمة', null, 'active', null],
            ['UNIT-011', 'وحدة خاطئة', null, 'broken-status', null],
        ]);

        $result = app(UnitExcelImportService::class)->import($path, 'units.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertDatabaseMissing('units', ['code' => 'UNIT-010']);
        $this->assertDatabaseMissing('units', ['code' => 'UNIT-011']);
        $this->assertStringContainsString('الصف 3', implode(' ', $result['errors']));
    }

    public function test_duplicate_code_inside_workbook_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['UNIT-020', 'الوحدة الأولى', null, 'active', null],
            ['UNIT-020', 'الوحدة الثانية', null, 'active', null],
        ]);

        $analysis = app(UnitExcelImportService::class)->analyze($path, 'units.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('مكرر داخل ملف Excel', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('units', 0);
    }

    public function test_existing_unit_code_is_rejected_before_import(): void
    {
        Unit::query()->create([
            'code' => 'UNIT-030',
            'name_ar' => 'موجودة',
            'status' => 'active',
        ]);

        $path = $this->makeWorkbook([
            ['UNIT-030', 'نسخة أخرى', null, 'active', null],
        ]);

        $analysis = app(UnitExcelImportService::class)->analyze($path, 'units.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('موجود مسبقًا', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('units', 1);
    }

    public function test_preview_preserves_real_excel_row_numbers_when_blank_rows_exist(): void
    {
        $path = $this->makeWorkbook([
            ['UNIT-035', 'الأولى', null, 'active', null],
            [null, null, null, null, null],
            ['UNIT-036', 'الثانية', 'ث', 'inactive', null],
        ]);

        $analysis = app(UnitExcelImportService::class)->analyze($path, 'units.xlsx');

        $this->assertTrue($analysis['valid']);
        $this->assertSame(2, $analysis['row_count']);
        $this->assertSame(2, $analysis['rows'][0]['excel_row']);
        $this->assertSame(4, $analysis['rows'][1]['excel_row']);
    }

    public function test_unit_excel_preview_view_contains_compact_row_preview(): void
    {
        $source = file_get_contents(resource_path('views/filament/imports/unit-excel-preview.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('معاينة البيانات', $source);
        $this->assertStringContainsString('صف Excel', $source);
        $this->assertStringContainsString('{{ $row[\'excel_row\'] }}', $source);
        $this->assertStringContainsString('array_slice($preview[\'rows\'], 0, 10)', $source);
    }

    public function test_non_xlsx_extension_is_rejected_even_if_contents_are_xlsx(): void
    {
        $path = $this->makeWorkbook([
            ['UNIT-040', 'اختبار', null, 'active', null],
        ]);

        $analysis = app(UnitExcelImportService::class)->analyze($path, 'units.xls');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('.xlsx', implode(' ', $analysis['errors']));
    }

    /** @param list<list<mixed>> $rows */
    private function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(UnitExcelImportService::HEADERS, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'units-xlsx-');
        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        (new XlsxWriter($spreadsheet))->save($xlsxPath);

        $this->beforeApplicationDestroyed(static function () use ($xlsxPath): void {
            @unlink($xlsxPath);
        });

        return $xlsxPath;
    }
}
