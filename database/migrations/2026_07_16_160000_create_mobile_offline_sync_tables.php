<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $entities = [
        'areas' => 'areas',
        'routes' => 'distribution_routes',
        'vehicles' => 'vehicles',
        'warehouses' => 'warehouses',
        'employees' => 'employees',
        'product_categories' => 'product_categories',
        'units' => 'units',
        'products' => 'products',
        'customers' => 'customers',
        'stock_balances' => 'stock_balances',
        'vehicle_loads' => 'vehicle_loads',
        'sales_invoices' => 'sales_invoices',
        'customer_payments' => 'customer_payments',
        'sales_returns' => 'sales_returns',
        'vehicle_expenses' => 'vehicle_expenses',
        'daily_closings' => 'daily_closings',
    ];

    public function up(): void
    {
        Schema::create('mobile_sync_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 50);
            $table->unsignedBigInteger('record_id');
            $table->string('operation', 20);
            $table->json('scope_snapshot')->nullable();
            $table->timestamp('changed_at');

            $table->index(['entity', 'record_id'], 'mobile_sync_changes_record_index');
            $table->index(['changed_at', 'id'], 'mobile_sync_changes_time_index');
        });

        Schema::create('mobile_sync_checkpoints', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('pruned_through_cursor')->default(0);
            $table->timestamp('last_compacted_at')->nullable();
            $table->timestamps();
        });

        DB::table('mobile_sync_checkpoints')->insert([
            'id' => 1,
            'pruned_through_cursor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('mobile_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 100);
            $table->char('context_key', 64);
            $table->unsignedBigInteger('last_pull_cursor')->default(0);
            $table->timestamp('last_pull_at')->nullable();
            $table->timestamp('last_full_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id'], 'mobile_sync_states_user_device_unique');
            $table->index(['last_pull_cursor', 'last_pull_at'], 'mobile_sync_states_cursor_index');
        });

        $this->backfillExistingRecords();
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_sync_states');
        Schema::dropIfExists('mobile_sync_checkpoints');
        Schema::dropIfExists('mobile_sync_changes');
    }

    private function backfillExistingRecords(): void
    {
        foreach ($this->entities as $entity => $table) {
            DB::table($table)
                ->select(['id', 'updated_at'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($entity): void {
                    $changes = [];

                    foreach ($rows as $row) {
                        $changes[] = [
                            'entity' => $entity,
                            'record_id' => (int) $row->id,
                            'operation' => 'upsert',
                            'scope_snapshot' => null,
                            'changed_at' => $row->updated_at ?? now(),
                        ];
                    }

                    if ($changes !== []) {
                        DB::table('mobile_sync_changes')->insert($changes);
                    }
                }, 'id');
        }
    }
};
