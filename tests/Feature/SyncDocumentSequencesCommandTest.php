<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncDocumentSequencesCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dry_run_does_not_update_document_sequences(): void
    {
        $this->createStockMovementWithNumber('STM-20991231-00009');

        DB::table('document_sequences')->insert([
            'document_type' => 'stock_movement',
            'sequence_date' => '2099-12-31',
            'last_number' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('documents:sync-sequences')
            ->expectsOutputToContain('Dry run only')
            ->assertExitCode(0);

        $this->assertSame(2, (int) DB::table('document_sequences')
            ->where('document_type', 'stock_movement')
            ->where('sequence_date', '2099-12-31')
            ->value('last_number'));
    }

    public function test_apply_updates_document_sequences_to_highest_existing_number(): void
    {
        $this->createStockMovementWithNumber('STM-20991231-00009');

        DB::table('document_sequences')->insert([
            'document_type' => 'stock_movement',
            'sequence_date' => '2099-12-31',
            'last_number' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('documents:sync-sequences --apply')
            ->expectsOutputToContain('document_sequences synchronized')
            ->assertExitCode(0);

        $this->assertSame(9, (int) DB::table('document_sequences')
            ->where('document_type', 'stock_movement')
            ->where('sequence_date', '2099-12-31')
            ->value('last_number'));
    }

    private function createStockMovementWithNumber(string $movementNumber): void
    {
        $suffix = uniqid();

        $warehouse = Warehouse::query()->create([
            'code' => 'W-SYNC-'.$suffix,
            'name' => 'Sync Warehouse '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'sku' => 'P-SYNC-'.$suffix,
            'name_ar' => 'Sync Product '.$suffix,
            'sale_price' => 10,
            'status' => 'active',
        ]);

        DB::table('stock_movements')->insert([
            'movement_number' => $movementNumber,
            'movement_type' => 'opening_balance',
            'to_warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_cost' => 0,
            'total_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}