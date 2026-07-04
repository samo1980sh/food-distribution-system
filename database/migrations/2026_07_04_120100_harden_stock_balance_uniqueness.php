<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table): void {
            $table->string('batch_key')->default('')->after('batch_number');
            $table->string('expiry_key')->default('')->after('expiry_date');
        });

        DB::table('stock_balances')
            ->orderBy('id')
            ->select(['id', 'warehouse_id', 'product_id', 'batch_number', 'expiry_date', 'quantity'])
            ->get()
            ->groupBy(fn ($balance): string => implode('|', [
                $balance->warehouse_id,
                $balance->product_id,
                $balance->batch_number ?? '',
                $balance->expiry_date ?? '',
            ]))
            ->each(function ($balances): void {
                $first = $balances->first();
                $quantity = $balances->sum(fn ($balance): float => (float) $balance->quantity);

                DB::table('stock_balances')
                    ->where('id', $first->id)
                    ->update([
                        'batch_key' => $first->batch_number ?? '',
                        'expiry_key' => $first->expiry_date ?? '',
                        'quantity' => $quantity,
                        'updated_at' => now(),
                    ]);

                $duplicateIds = $balances->skip(1)->pluck('id');

                if ($duplicateIds->isNotEmpty()) {
                    DB::table('stock_balances')
                        ->whereIn('id', $duplicateIds)
                        ->delete();
                }
            });

        DB::table('stock_balances')
            ->where('batch_key', '')
            ->whereNotNull('batch_number')
            ->update(['batch_key' => DB::raw('batch_number')]);

        DB::table('stock_balances')
            ->where('expiry_key', '')
            ->whereNotNull('expiry_date')
            ->update(['expiry_key' => DB::raw('expiry_date')]);

        Schema::table('stock_balances', function (Blueprint $table): void {
            $table->unique(
                ['warehouse_id', 'product_id', 'batch_key', 'expiry_key'],
                'stock_balances_unique_normalized_batch'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table): void {
            $table->dropUnique('stock_balances_unique_normalized_batch');
            $table->dropColumn(['batch_key', 'expiry_key']);
        });
    }
};
