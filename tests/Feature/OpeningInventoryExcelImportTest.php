<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\User;
use App\Services\Imports\Excel\OpeningInventoryExcelImportService;
use App\Services\Imports\Excel\OpeningInventoryExcelTemplateService;
use App\Services\Inventory\InventoryMovementService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class OpeningInventoryExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_contains_current_active_warehouse_and_product_references(): void
    {
        $warehouse = $this->makeWarehouse('WH-ACTIVE', 'active');
        $this->makeWarehouse('WH-INACTIVE', 'inactive');
        $product = $this->makeProduct('PRD-ACTIVE', true, 'active', 12.50);
        $this->makeProduct('PRD-INACTIVE', true, 'inactive', 9.00);

        $spreadsheet = app(OpeningInventoryExcelTemplateService::class)->makeSpreadsheet();
        $sheet = $spreadsheet->getSheet(0);
        $references = $spreadsheet->getSheetByName('القوائم المرجعية');

        $this->assertSame('الرصيد الافتتاحي', $sheet->getTitle());
        $this->assertSame(
            OpeningInventoryExcelImportService::HEADERS,
            $sheet->rangeToArray('A1:H1', null, true, true, false)[0],
        );
        $this->assertNotNull($references);
        $this->assertNotNull($spreadsheet->getSheetByName('تعليمات'));
        $this->assertTrue($sheet->getRightToLeft());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('A2')->getNumberFormat()->getFormatCode());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('B2')->getNumberFormat()->getFormatCode());
        $this->assertSame($warehouse->code, $references->getCell('A2')->getValue());
        $this->assertSame($product->sku, $references->getCell('E2')->getValue());
        $this->assertSame(1, $references->getCell('G2')->getValue());
        $this->assertSame(12.5, $references->getCell('H2')->getValue());
        $this->assertTrue($sheet->dataValidationExists('A2'));
        $this->assertTrue($sheet->dataValidationExists('B2'));
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('A2')->getType());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getDataValidation('B2')->getType());
        $this->assertSame('=ACTIVE_WAREHOUSE_CODES', $sheet->getDataValidation('A2')->getFormula1());
        $this->assertSame('=ACTIVE_PRODUCT_SKUS', $sheet->getDataValidation('B2')->getFormula1());
    }

    public function test_valid_rows_create_opening_balance_movements_and_stock_balances(): void
    {
        $warehouse = $this->makeWarehouse('WH-MAIN');
        $expiryProduct = $this->makeProduct('PRD-EXP', true, 'active', 10);
        $plainProduct = $this->makeProduct('PRD-PLAIN', false, 'active', 20);
        $this->syncStockMovementSequenceForToday();

        $path = $this->makeWorkbook([
            ['WH-MAIN', 'PRD-EXP', 12.5, 'LOT-A', '2027-08-20', 11, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
            ['WH-MAIN', 'PRD-PLAIN', 8, null, null, 21, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
        ]);

        $result = app(OpeningInventoryExcelImportService::class)->import($path, 'opening.xlsx');

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertSame(2, $result['imported_count']);

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'opening_balance',
            'to_warehouse_id' => $warehouse->id,
            'product_id' => $expiryProduct->id,
            'batch_number' => 'LOT-A',
            'expiry_date' => '2027-08-20',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'opening_balance',
            'to_warehouse_id' => $warehouse->id,
            'product_id' => $plainProduct->id,
        ]);

        $expiryBalance = StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $expiryProduct->id)
            ->where('batch_key', 'LOT-A')
            ->where('expiry_key', '2027-08-20')
            ->firstOrFail();

        $plainBalance = StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $plainProduct->id)
            ->where('batch_key', '')
            ->where('expiry_key', '')
            ->firstOrFail();

        $this->assertEqualsWithDelta(12.5, (float) $expiryBalance->quantity, 0.0001);
        $this->assertEqualsWithDelta(11, (float) $expiryBalance->average_unit_cost, 0.000001);
        $this->assertEqualsWithDelta(8, (float) $plainBalance->quantity, 0.0001);
        $this->assertEqualsWithDelta(21, (float) $plainBalance->average_unit_cost, 0.000001);
        $this->assertSame(2, StockMovement::query()->where('movement_type', 'opening_balance')->count());
    }

    public function test_reimporting_the_same_opening_file_is_rejected_without_changing_stock(): void
    {
        $warehouse = $this->makeWarehouse('WH-MAIN');
        $product = $this->makeProduct('PRD-PLAIN', false);
        $this->syncStockMovementSequenceForToday();
        $path = $this->makeWorkbook([
            ['WH-MAIN', 'PRD-PLAIN', 10, null, null, 12, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
        ]);

        $first = app(OpeningInventoryExcelImportService::class)->import($path, 'opening.xlsx');
        $balance = StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $second = app(OpeningInventoryExcelImportService::class)->import($path, 'opening.xlsx');

        $this->assertTrue($first['valid'], implode(' | ', $first['errors']));
        $this->assertFalse($second['valid']);
        $this->assertSame(0, $second['imported_count']);
        $this->assertStringContainsString('يوجد رصيد افتتاحي سابق', implode(' | ', $second['errors']));
        $this->assertSame(1, StockMovement::query()->where('movement_type', 'opening_balance')->count());
        $this->assertEqualsWithDelta(10, (float) $balance->fresh()->quantity, 0.0001);
        $this->assertEqualsWithDelta(12, (float) $balance->fresh()->average_unit_cost, 0.000001);
    }

    public function test_duplicate_stock_identity_rows_in_one_workbook_are_rejected_atomically(): void
    {
        $this->makeWarehouse('WH-MAIN');
        $this->makeProduct('PRD-UNIQUE', false);
        $this->makeProduct('PRD-DUPLICATE', true);
        $this->syncStockMovementSequenceForToday();

        $result = app(OpeningInventoryExcelImportService::class)->import(
            $this->makeWorkbook([
                ['WH-MAIN', 'PRD-UNIQUE', 5, null, null, 8, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
                ['WH-MAIN', 'PRD-DUPLICATE', 10, 'LOT-A', '2027-08-20', 12, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
                ['WH-MAIN', 'PRD-DUPLICATE', 15, 'LOT-A', '2027-08-20', 13, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
            ]),
            'opening.xlsx',
        );

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $errors = implode(' | ', $result['errors']);
        $this->assertStringContainsString('مكررة داخل الملف', $errors);
        $this->assertStringContainsString('الصفين 3 و4', $errors);
        $this->assertStringContainsString('LOT-A', $errors);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);
    }

    public function test_expiry_policy_is_validated_from_product_master_data(): void
    {
        $this->makeWarehouse('WH-MAIN');
        $this->makeProduct('PRD-EXP', true);
        $this->makeProduct('PRD-NOEXP', false);

        $analysis = app(OpeningInventoryExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['WH-MAIN', 'PRD-EXP', 10, 'LOT-A', null, 10, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
                ['WH-MAIN', 'PRD-NOEXP', 10, null, '2027-08-20', 10, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
            ]),
            'opening.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $errors = implode(' | ', $analysis['errors']);
        $this->assertStringContainsString('يتطلب تاريخ صلاحية', $errors);
        $this->assertStringContainsString('لا يتطلب تتبع تاريخ صلاحية', $errors);
    }

    public function test_missing_inactive_and_invalid_numeric_references_block_entire_import(): void
    {
        $this->makeWarehouse('WH-MAIN');
        $this->makeWarehouse('WH-INACTIVE', 'inactive');
        $this->makeProduct('PRD-OK', false);
        $this->syncStockMovementSequenceForToday();

        $path = $this->makeWorkbook([
            ['WH-MAIN', 'PRD-OK', 10, null, null, 10, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
            ['WH-MISSING', 'PRD-OK', 10, null, null, 10, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
            ['WH-INACTIVE', 'PRD-OK', -2, null, null, -1, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
        ]);

        $result = app(OpeningInventoryExcelImportService::class)->import($path, 'opening.xlsx');

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $errors = implode(' | ', $result['errors']);
        $this->assertStringContainsString('WH-MISSING', $errors);
        $this->assertStringContainsString('WH-INACTIVE', $errors);
        $this->assertStringContainsString('أكبر من الصفر', $errors);
        $this->assertStringContainsString('لا يمكن أن تكون سالبة', $errors);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);
    }

    public function test_restricted_user_cannot_import_into_warehouse_outside_scope(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $allowed = $this->makeWarehouse('WH-ALLOWED');
        $blocked = $this->makeWarehouse('WH-BLOCKED');
        $this->makeProduct('PRD-OK', false);

        $user = User::factory()->create([
            'role' => User::ROLE_WAREHOUSE_KEEPER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->accessWarehouses()->sync([$allowed->id]);
        $this->actingAs($user);

        $analysis = app(OpeningInventoryExcelImportService::class)->analyze(
            $this->makeWorkbook([
                ['WH-ALLOWED', 'PRD-OK', 10, null, null, 10, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
                ['WH-BLOCKED', 'PRD-OK', 10, null, null, 10, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
            ]),
            'opening.xlsx',
        );

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('خارج نطاق وصول المستخدم الحالي', implode(' | ', $analysis['errors']));
    }

    public function test_multiple_lots_for_same_product_are_supported_for_fefo_setup(): void
    {
        $warehouse = $this->makeWarehouse('WH-MAIN');
        $product = $this->makeProduct('PRD-FEFO', true);
        $this->syncStockMovementSequenceForToday();

        $result = app(OpeningInventoryExcelImportService::class)->import(
            $this->makeWorkbook([
                ['WH-MAIN', 'PRD-FEFO', 20, 'LOT-EARLY', '2026-10-01', 10, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
                ['WH-MAIN', 'PRD-FEFO', 30, 'LOT-LATE', '2027-03-01', 10, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
            ]),
            'opening.xlsx',
        );

        $this->assertTrue($result['valid'], implode(' | ', $result['errors']));
        $this->assertSame(2, StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->count());
    }

    public function test_same_product_and_batch_with_distinct_expiry_dates_remains_valid(): void
    {
        $warehouse = $this->makeWarehouse('WH-MAIN');
        $product = $this->makeProduct('PRD-FEFO', true);
        $this->syncStockMovementSequenceForToday();

        $first = app(OpeningInventoryExcelImportService::class)->import(
            $this->makeWorkbook([
                ['WH-MAIN', 'PRD-FEFO', 20, 'LOT-A', '2026-10-01', 10, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
            ]),
            'opening-first.xlsx',
        );
        $laterMissedIdentity = app(OpeningInventoryExcelImportService::class)->import(
            $this->makeWorkbook([
                ['WH-MAIN', 'PRD-FEFO', 30, 'LOT-A', '2027-03-01', 10, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
            ]),
            'opening-later.xlsx',
        );

        $this->assertTrue($first['valid'], implode(' | ', $first['errors']));
        $this->assertTrue(
            $laterMissedIdentity['valid'],
            implode(' | ', $laterMissedIdentity['errors']),
        );
        $this->assertSame(1, $first['imported_count']);
        $this->assertSame(1, $laterMissedIdentity['imported_count']);
        $this->assertSame(2, StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('batch_key', 'LOT-A')
            ->count());
    }

    public function test_existing_opening_movement_blocks_another_opening_for_the_same_identity(): void
    {
        $warehouse = $this->makeWarehouse('WH-MAIN');
        $product = $this->makeProduct('PRD-EXP', true);
        $this->syncStockMovementSequenceForToday();

        app(InventoryMovementService::class)->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 7,
            batchNumber: 'LOT-A',
            expiryDate: '2027-08-20',
            unitCost: 11,
            movementType: 'opening_balance',
            notes: 'الرصيد الافتتاحي الأصلي للنظام',
        );

        $result = app(OpeningInventoryExcelImportService::class)->import(
            $this->makeWorkbook([
                ['WH-MAIN', 'PRD-EXP', 4, 'LOT-A', '2027-08-20', 15, now()->toDateString(), 'محاولة رصيد افتتاحي مكرر للنظام'],
            ]),
            'opening.xlsx',
        );

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertStringContainsString('يوجد رصيد افتتاحي سابق', implode(' | ', $result['errors']));
        $this->assertSame(1, StockMovement::query()->where('movement_type', 'opening_balance')->count());
        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_key' => 'LOT-A',
            'expiry_key' => '2027-08-20',
            'quantity' => 7,
        ]);
    }

    public function test_operational_history_blocks_retroactive_opening_and_keeps_the_import_atomic(): void
    {
        $warehouse = $this->makeWarehouse('WH-MAIN');
        $operationalProduct = $this->makeProduct('PRD-OPERATIONAL', false);
        $newProduct = $this->makeProduct('PRD-NEW', false);
        $this->syncStockMovementSequenceForToday();

        app(InventoryMovementService::class)->addStock(
            warehouse: $warehouse,
            product: $operationalProduct,
            quantity: 10,
            unitCost: 20,
            movementType: 'purchase_receipt',
            notes: 'حركة استلام تشغيلية سابقة',
        );

        $existingBalance = StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $operationalProduct->id)
            ->firstOrFail();

        $result = app(OpeningInventoryExcelImportService::class)->import(
            $this->makeWorkbook([
                ['WH-MAIN', 'PRD-NEW', 5, null, null, 8, now()->toDateString(), 'رصيد افتتاحي لمنتج جديد للنظام'],
                ['WH-MAIN', 'PRD-OPERATIONAL', 4, null, null, 30, now()->toDateString(), 'محاولة إضافة رصيد بأثر رجعي'],
            ]),
            'opening.xlsx',
        );

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['imported_count']);
        $this->assertStringContainsString(
            'حركات مخزون تشغيلية أو إدارية سابقة',
            implode(' | ', $result['errors']),
        );
        $this->assertDatabaseMissing('stock_balances', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $newProduct->id,
        ]);
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertEqualsWithDelta(10, (float) $existingBalance->fresh()->quantity, 0.0001);
        $this->assertEqualsWithDelta(20, (float) $existingBalance->fresh()->average_unit_cost, 0.000001);
    }

    public function test_preview_view_and_admin_labels_are_present(): void
    {
        $preview = file_get_contents(resource_path('views/filament/imports/opening-inventory-excel-preview.blade.php'));
        $page = file_get_contents(app_path('Filament/Resources/StockMovements/Pages/ManageStockMovements.php'));
        $form = file_get_contents(app_path('Filament/Resources/StockMovements/Schemas/StockMovementForm.php'));

        $this->assertIsString($preview);
        $this->assertStringContainsString('fd-opening-inventory-import-preview', $preview);
        $this->assertStringContainsString('معاينة الرصيد الافتتاحي', $preview);
        $this->assertStringContainsString("->label('تسوية مخزون إدارية')", $page);
        $this->assertStringContainsString("->modalHeading('إضافة تسوية مخزون إدارية')", $page);
        $this->assertStringContainsString("->label('تحميل قالب الرصيد الافتتاحي')", $page);
        $this->assertStringContainsString("->label('استيراد رصيد افتتاحي')", $page);
        $this->assertStringContainsString("->label('سبب الحركة الإدارية')", $form);
    }

    public function test_non_xlsx_extension_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['WH-X', 'PRD-X', 1, null, null, 1, now()->toDateString(), 'رصيد افتتاحي عند بدء تشغيل النظام'],
        ]);

        $analysis = app(OpeningInventoryExcelImportService::class)->analyze($path, 'opening.xls');

        $this->assertFalse($analysis['valid']);
        $this->assertStringContainsString('.xlsx', implode(' ', $analysis['errors']));
    }

    private function makeWarehouse(string $code, string $status = 'active'): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => 'Warehouse '.$code,
            'type' => 'main',
            'status' => $status,
        ]);
    }

    private function makeProduct(
        string $sku,
        bool $hasExpiry,
        string $status = 'active',
        float $purchasePrice = 10,
    ): Product {
        return Product::query()->create([
            'sku' => $sku,
            'name_ar' => 'Product '.$sku,
            'purchase_price' => $purchasePrice,
            'sale_price' => $purchasePrice + 5,
            'wholesale_price' => $purchasePrice + 2,
            'min_stock' => 0,
            'has_expiry' => $hasExpiry,
            'status' => $status,
        ]);
    }

    /** @param list<list<mixed>> $rows */
    private function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(OpeningInventoryExcelImportService::HEADERS, null, 'A1');

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $path = tempnam(sys_get_temp_dir(), 'opening-inventory-');
        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($xlsxPath);

        return $xlsxPath;
    }

    private function syncStockMovementSequenceForToday(): void
    {
        DB::table('document_sequences')->updateOrInsert(
            [
                'document_type' => 'stock_movement',
                'sequence_date' => now()->toDateString(),
            ],
            [
                'last_number' => 970000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
