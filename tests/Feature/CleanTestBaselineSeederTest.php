<?php

namespace Tests\Feature;

use Database\Seeders\CleanTestBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CleanTestBaselineSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_baseline_keeps_master_data_and_removes_operational_workflow(): void
    {
        $this->seed(CleanTestBaselineSeeder::class);

        $this->assertDatabaseCount('users', 10);
        $this->assertDatabaseCount('employees', 8);
        $this->assertDatabaseCount('customers', 20);
        $this->assertDatabaseCount('products', 15);
        $this->assertDatabaseCount('distribution_routes', 5);
        $this->assertDatabaseCount('vehicles', 4);
        $this->assertDatabaseCount('warehouses', 7);

        foreach ([
            'vehicle_load_items',
            'vehicle_loads',
            'sales_invoice_items',
            'sales_invoices',
            'customer_payments',
            'sales_return_items',
            'sales_returns',
            'vehicle_expenses',
            'daily_closing_items',
            'daily_closings',
            'sales_visits',
            'sales_journeys',
            'driver_delivery_items',
            'driver_deliveries',
            'driver_journeys',
        ] as $table) {
            if (Schema::hasTable($table)) {
                $this->assertSame(
                    0,
                    DB::table($table)->count(),
                    "Expected {$table} to be empty in the clean test baseline.",
                );
            }
        }

        $this->assertDatabaseCount('stock_balances', 15);
        $this->assertDatabaseCount('stock_movements', 15);

        $this->assertSame(
            15,
            DB::table('stock_movements')
                ->where('movement_type', 'opening_balance')
                ->count(),
        );

        $vehicleWarehouseIds = DB::table('warehouses')
            ->where('type', 'vehicle')
            ->pluck('id');

        $this->assertSame(
            0,
            DB::table('stock_balances')
                ->whereIn('warehouse_id', $vehicleWarehouseIds)
                ->where('quantity', '>', 0)
                ->count(),
            'Vehicle warehouses must start empty so vehicle loading can be tested manually.',
        );

        foreach ([
            'mobile_sync_push_operations',
            'mobile_sync_push_batches',
            'mobile_sync_checkpoints',
            'mobile_sync_states',
            'mobile_sync_changes',
            'personal_access_tokens',
        ] as $table) {
            if (Schema::hasTable($table)) {
                $this->assertSame(
                    0,
                    DB::table($table)->count(),
                    "Expected {$table} to be empty in the clean test baseline.",
                );
            }
        }
    }
}
