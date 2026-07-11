<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table): void {
            $table->decimal('average_unit_cost', 18, 6)
                ->default(0)
                ->after('quantity');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('unit_cost', 18, 6)
                ->default(0)
                ->change();
        });

        Schema::table('vehicle_load_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 18, 6)
                ->default(0)
                ->change();
        });

        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 18, 6)
                ->default(0)
                ->after('unit_price');
            $table->decimal('total_cost', 18, 2)
                ->default(0)
                ->after('line_total');
        });

        Schema::table('sales_return_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 18, 6)
                ->default(0)
                ->after('unit_price');
            $table->decimal('total_cost', 18, 2)
                ->default(0)
                ->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table): void {
            $table->dropColumn(['unit_cost', 'total_cost']);
        });

        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            $table->dropColumn(['unit_cost', 'total_cost']);
        });

        Schema::table('vehicle_load_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 14, 2)
                ->default(0)
                ->change();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('unit_cost', 14, 2)
                ->default(0)
                ->change();
        });

        Schema::table('stock_balances', function (Blueprint $table): void {
            $table->dropColumn('average_unit_cost');
        });
    }
};
