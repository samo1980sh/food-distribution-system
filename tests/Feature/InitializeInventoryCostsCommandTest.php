<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InitializeInventoryCostsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dry_run_does_not_change_existing_balance_cost(): void
    {
        $balance = $this->createZeroCostBalance();

        $this->artisan('inventory:initialize-costs')
            ->expectsOutputToContain('Dry run only')
            ->assertExitCode(0);

        $this->assertEqualsWithDelta(
            0,
            (float) $balance->refresh()->average_unit_cost,
            0.000001,
        );
    }

    public function test_apply_initializes_balance_from_product_purchase_price(): void
    {
        $balance = $this->createZeroCostBalance();

        $this->artisan('inventory:initialize-costs --apply')
            ->expectsOutputToContain('Inventory opening costs initialized')
            ->assertExitCode(0);

        $this->assertEqualsWithDelta(
            12.5,
            (float) $balance->refresh()->average_unit_cost,
            0.000001,
        );
    }

    private function createZeroCostBalance(): StockBalance
    {
        $suffix = uniqid();

        $warehouse = Warehouse::query()->create([
            'code' => 'W-INIT-COST-'.$suffix,
            'name' => 'Initialize Cost Warehouse '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'sku' => 'P-INIT-COST-'.$suffix,
            'name_ar' => 'Initialize Cost Product '.$suffix,
            'purchase_price' => 12.5,
            'status' => 'active',
        ]);

        return StockBalance::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'batch_key' => '',
            'expiry_key' => '',
            'quantity' => 5,
            'average_unit_cost' => 0,
        ]);
    }
}
