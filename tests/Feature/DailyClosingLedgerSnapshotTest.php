<?php

namespace Tests\Feature;

use App\Models\DailyClosing;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Distribution\DailyClosingService;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class DailyClosingLedgerSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    public function test_closing_reconstructs_opening_and_snapshots_the_inventory_ledger(): void
    {
        $suffix = uniqid();
        $date = '2026-07-10';
        $warehouse = Warehouse::query()->create([
            'code' => 'LED-W-'.$suffix,
            'name' => 'مستودع دفتر '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'LED-P-'.$suffix,
            'name_ar' => 'منتج دفتر '.$suffix,
            'purchase_price' => 5,
            'sale_price' => 10,
            'status' => 'active',
        ]);
        $inventory = app(InventoryMovementService::class);

        $inventory->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 20,
            unitCost: 5,
            movementType: 'opening_balance',
            movementDate: '2026-07-09',
        );
        $inventory->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 10,
            unitCost: 5,
            movementType: 'manual_in',
            movementDate: $date,
        );
        $inventory->removeStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 4,
            movementType: 'manual_out',
            movementDate: $date,
        );
        $inventory->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 5,
            unitCost: 5,
            movementType: 'manual_in',
            movementDate: '2026-07-11',
        );

        $closing = DailyClosing::query()->create([
            'closing_number' => 'DCL-LED-'.$suffix,
            'closing_date' => $date,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'actual_cash_amount' => 0,
        ]);

        $closing = app(DailyClosingService::class)->refreshTotals($closing);
        $item = $closing->items()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame('20.000', $item->opening_quantity);
        $this->assertSame('10.000', $item->movement_in_quantity);
        $this->assertSame('4.000', $item->movement_out_quantity);
        $this->assertSame('26.000', $item->expected_quantity);
        $this->assertSame('20.000', $closing->total_opening_quantity);
        $this->assertSame('10.000', $closing->total_movement_in_quantity);
        $this->assertSame('4.000', $closing->total_movement_out_quantity);
        $this->assertSame('26.000', $closing->total_expected_quantity);

        $item->update(['actual_quantity' => 25]);
        $closing = app(DailyClosingService::class)->confirm($closing);
        $item = $closing->items()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame('confirmed', $closing->status);
        $this->assertNotNull($closing->snapshot_at);
        $this->assertSame('25.000', $item->actual_quantity);
        $this->assertSame('-1.000', $item->difference_quantity);

        try {
            $inventory->addStock(
                warehouse: $warehouse,
                product: $product,
                quantity: 1,
                unitCost: 5,
                movementType: 'manual_in',
                movementDate: $date,
            );
            $this->fail('Expected closed-day inventory guard failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('إغلاق يومي', $exception->getMessage());
        }
    }
}
