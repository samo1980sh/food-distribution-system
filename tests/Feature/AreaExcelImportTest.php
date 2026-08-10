<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Services\Imports\Excel\AreaExcelImportService;
use App\Services\Imports\Excel\AreaExcelTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class AreaExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_template_is_a_real_xlsx_with_expected_headers_and_instructions(): void
    {
        $spreadsheet = app(AreaExcelTemplateService::class)->makeSpreadsheet();

        $this->assertSame('المناطق', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame(AreaExcelImportService::HEADERS, $spreadsheet->getSheet(0)->rangeToArray('A1:E1', null, true, true, false)[0]);
        $this->assertNotNull($spreadsheet->getSheetByName('تعليمات'));
        $this->assertTrue($spreadsheet->getSheet(0)->getRightToLeft());
    }

    public function test_valid_area_xlsx_is_analyzed_and_imported_all_at_once(): void
    {
        $path = $this->makeWorkbook([
            ['A-001', 'دمشق', 'دمشق', 'active', null],
            ['A-002', 'ريف دمشق', 'ريف دمشق', 'inactive', 'اختبار'],
        ]);

        $service = app(AreaExcelImportService::class);
        $analysis = $service->analyze($path, 'areas.xlsx');

        $this->assertTrue($analysis['valid']);
        $this->assertSame(2, $analysis['row_count']);
        $this->assertSame(2, $analysis['valid_rows']);
        $this->assertSame([], $analysis['errors']);
        $this->assertSame(2, $analysis['rows'][0]['excel_row']);
        $this->assertSame(3, $analysis['rows'][1]['excel_row']);

        $result = $service->import($path, 'areas.xlsx');

        $this->assertTrue($result['valid']);
        $this->assertSame(2, $result['imported_count']);
        $this->assertDatabaseHas('areas', ['code' => 'A-001', 'name_ar' => 'دمشق', 'status' => 'active']);
        $this->assertDatabaseHas('areas', ['code' => 'A-002', 'status' => 'inactive']);
    }

    public function test_one_invalid_row_prevents_the_entire_import(): void
    {
        $path = $this->makeWorkbook([
            ['A-010', 'منطقة سليمة', null, 'active', null],
            ['A-011', 'منطقة خاطئة', null, 'broken-status', null],
        ]);

        $result = app(AreaExcelImportService::class)->import($path, 'areas.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertDatabaseMissing('areas', ['code' => 'A-010']);
        $this->assertDatabaseMissing('areas', ['code' => 'A-011']);
        $this->assertStringContainsString('الصف 3', implode(' ', $result['errors']));
    }

    public function test_duplicate_code_inside_workbook_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['A-020', 'الأولى', null, 'active', null],
            ['A-020', 'الثانية', null, 'active', null],
        ]);

        $analysis = app(AreaExcelImportService::class)->analyze($path, 'areas.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('مكرر داخل ملف Excel', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('areas', 0);
    }

    public function test_existing_area_code_is_rejected_before_import(): void
    {
        Area::query()->create([
            'code' => 'A-030',
            'name_ar' => 'موجودة',
            'status' => 'active',
        ]);

        $path = $this->makeWorkbook([
            ['A-030', 'نسخة أخرى', null, 'active', null],
        ]);

        $analysis = app(AreaExcelImportService::class)->analyze($path, 'areas.xlsx');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('موجود مسبقًا', implode(' ', $analysis['errors']));
        $this->assertDatabaseCount('areas', 1);
    }

    public function test_preview_preserves_real_excel_row_numbers_when_blank_rows_exist(): void
    {
        $path = $this->makeWorkbook([
            ['A-035', 'الأولى', null, 'active', null],
            [null, null, null, null, null],
            ['A-036', 'الثانية', null, 'inactive', null],
        ]);

        $analysis = app(AreaExcelImportService::class)->analyze($path, 'areas.xlsx');

        $this->assertTrue($analysis['valid']);
        $this->assertSame(2, $analysis['row_count']);
        $this->assertSame(2, $analysis['rows'][0]['excel_row']);
        $this->assertSame(4, $analysis['rows'][1]['excel_row']);
    }

    public function test_area_excel_preview_view_contains_compact_row_preview(): void
    {
        $source = file_get_contents(resource_path('views/filament/imports/area-excel-preview.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('معاينة البيانات', $source);
        $this->assertStringContainsString('صف Excel', $source);
        $this->assertStringContainsString('{{ $row[\'excel_row\'] }}', $source);
        $this->assertStringContainsString('array_slice($preview[\'rows\'], 0, 10)', $source);
    }

    public function test_non_xlsx_extension_is_rejected_even_if_contents_are_xlsx(): void
    {
        $path = $this->makeWorkbook([
            ['A-040', 'اختبار', null, 'active', null],
        ]);

        $analysis = app(AreaExcelImportService::class)->analyze($path, 'areas.xls');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('.xlsx', implode(' ', $analysis['errors']));
    }

    /** @param list<list<mixed>> $rows */
    private function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(AreaExcelImportService::HEADERS, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'areas-xlsx-');
        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        (new XlsxWriter($spreadsheet))->save($xlsxPath);

        $this->beforeApplicationDestroyed(static function () use ($xlsxPath): void {
            @unlink($xlsxPath);
        });

        return $xlsxPath;
    }
}
