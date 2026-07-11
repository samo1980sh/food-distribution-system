<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeightedAverageInventoryCostTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncStockMovementSequenceForToday();
    }

    public function test_inbound_stock_recalculates_moving_weighted_average(): void
    {
        [$sourceWarehouse, , $product] = $this->createInventoryScope();
        $inventory = app(InventoryMovementService::class);

        $inventory->addStock(
            warehouse: $sourceWarehouse,
            product: $product,
            quantity: 10,
            unitCost: 10,
        );

        $inventory->addStock(
            warehouse: $sourceWarehouse,
            product: $product,
            quantity: 10,
            unitCost: 20,
        );

        $balance = $this->balance($sourceWarehouse, $product);

        $this->assertEqualsWithDelta(20, (float) $balance->quantity, 0.0001);
        $this->assertEqualsWithDelta(15, (float) $balance->average_unit_cost, 0.000001);
    }

    public function test_outbound_stock_uses_current_average_without_changing_it(): void
    {
        [$sourceWarehouse, , $product] = $this->createInventoryScope();
        $inventory = app(InventoryMovementService::class);

        $inventory->addStock($sourceWarehouse, $product, 10, unitCost: 10);
        $inventory->addStock($sourceWarehouse, $product, 10, unitCost: 20);

        $movement = $inventory->removeStock(
            warehouse: $sourceWarehouse,
            product: $product,
            quantity: 5,
        );

        $balance = $this->balance($sourceWarehouse, $product);

        $this->assertEqualsWithDelta(15, (float) $balance->quantity, 0.0001);
        $this->assertEqualsWithDelta(15, (float) $balance->average_unit_cost, 0.000001);
        $this->assertEqualsWithDelta(15, (float) $movement->unit_cost, 0.000001);
        $this->assertEqualsWithDelta(75, (float) $movement->total_cost, 0.001);
    }

    public function test_transfer_preserves_source_average_cost_in_destination(): void
    {
        [$sourceWarehouse, $destinationWarehouse, $product] = $this->createInventoryScope();
        $inventory = app(InventoryMovementService::class);

        $inventory->addStock($sourceWarehouse, $product, 10, unitCost: 10);
        $inventory->addStock($sourceWarehouse, $product, 10, unitCost: 20);

        $movement = $inventory->transfer(
            fromWarehouse: $sourceWarehouse,
            toWarehouse: $destinationWarehouse,
            product: $product,
            quantity: 4,
        );

        $sourceBalance = $this->balance($sourceWarehouse, $product);
        $destinationBalance = $this->balance($destinationWarehouse, $product);

        $this->assertEqualsWithDelta(16, (float) $sourceBalance->quantity, 0.0001);
        $this->assertEqualsWithDelta(15, (float) $sourceBalance->average_unit_cost, 0.000001);
        $this->assertEqualsWithDelta(4, (float) $destinationBalance->quantity, 0.0001);
        $this->assertEqualsWithDelta(15, (float) $destinationBalance->average_unit_cost, 0.000001);
        $this->assertEqualsWithDelta(15, (float) $movement->unit_cost, 0.000001);
    }

    /**
     * @return array{0: Warehouse, 1: Warehouse, 2: Product}
     */
    private function createInventoryScope(): array
    {
        $suffix = uniqid();

        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'W-COST-SRC-'.$suffix,
            'name' => 'Cost Source '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);

        $destinationWarehouse = Warehouse::query()->create([
            'code' => 'W-COST-DST-'.$suffix,
            'name' => 'Cost Destination '.$suffix,
            'type' => 'branch',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'sku' => 'P-COST-'.$suffix,
            'name_ar' => 'Cost Product '.$suffix,
            'purchase_price' => 10,
            'sale_price' => 20,
            'status' => 'active',
        ]);

        return [$sourceWarehouse, $destinationWarehouse, $product];
    }

    private function balance(Warehouse $warehouse, Product $product): StockBalance
    {
        return StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('batch_key', '')
            ->where('expiry_key', '')
            ->firstOrFail();
    }

    private function syncStockMovementSequenceForToday(): void
    {
        DB::table('document_sequences')->updateOrInsert(
            [
                'document_type' => 'stock_movement',
                'sequence_date' => now()->toDateString(),
            ],
            [
                'last_number' => 950000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
