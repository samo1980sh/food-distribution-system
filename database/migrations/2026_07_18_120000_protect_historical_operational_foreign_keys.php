<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<array{table: string, column: string, parent: string, previous: string}>
     */
    private const FOREIGN_KEYS = [
        ['table' => 'distribution_routes', 'column' => 'area_id', 'parent' => 'areas', 'previous' => 'cascade'],
        ['table' => 'distribution_routes', 'column' => 'vehicle_id', 'parent' => 'vehicles', 'previous' => 'set null'],
        ['table' => 'distribution_routes', 'column' => 'driver_id', 'parent' => 'employees', 'previous' => 'set null'],
        ['table' => 'distribution_routes', 'column' => 'sales_representative_id', 'parent' => 'employees', 'previous' => 'set null'],

        ['table' => 'customers', 'column' => 'area_id', 'parent' => 'areas', 'previous' => 'set null'],
        ['table' => 'customers', 'column' => 'route_id', 'parent' => 'distribution_routes', 'previous' => 'set null'],

        ['table' => 'products', 'column' => 'category_id', 'parent' => 'product_categories', 'previous' => 'set null'],
        ['table' => 'products', 'column' => 'unit_id', 'parent' => 'units', 'previous' => 'set null'],
        ['table' => 'warehouses', 'column' => 'vehicle_id', 'parent' => 'vehicles', 'previous' => 'set null'],

        ['table' => 'stock_balances', 'column' => 'warehouse_id', 'parent' => 'warehouses', 'previous' => 'cascade'],
        ['table' => 'stock_balances', 'column' => 'product_id', 'parent' => 'products', 'previous' => 'cascade'],

        ['table' => 'stock_movements', 'column' => 'from_warehouse_id', 'parent' => 'warehouses', 'previous' => 'set null'],
        ['table' => 'stock_movements', 'column' => 'to_warehouse_id', 'parent' => 'warehouses', 'previous' => 'set null'],
        ['table' => 'stock_movements', 'column' => 'product_id', 'parent' => 'products', 'previous' => 'cascade'],

        ['table' => 'vehicle_loads', 'column' => 'vehicle_id', 'parent' => 'vehicles', 'previous' => 'cascade'],
        ['table' => 'vehicle_loads', 'column' => 'route_id', 'parent' => 'distribution_routes', 'previous' => 'set null'],
        ['table' => 'vehicle_loads', 'column' => 'driver_id', 'parent' => 'employees', 'previous' => 'set null'],
        ['table' => 'vehicle_loads', 'column' => 'sales_representative_id', 'parent' => 'employees', 'previous' => 'set null'],
        ['table' => 'vehicle_loads', 'column' => 'from_warehouse_id', 'parent' => 'warehouses', 'previous' => 'cascade'],
        ['table' => 'vehicle_loads', 'column' => 'to_warehouse_id', 'parent' => 'warehouses', 'previous' => 'cascade'],
        ['table' => 'vehicle_load_items', 'column' => 'product_id', 'parent' => 'products', 'previous' => 'cascade'],

        ['table' => 'sales_invoices', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'cascade'],
        ['table' => 'sales_invoices', 'column' => 'vehicle_id', 'parent' => 'vehicles', 'previous' => 'set null'],
        ['table' => 'sales_invoices', 'column' => 'route_id', 'parent' => 'distribution_routes', 'previous' => 'set null'],
        ['table' => 'sales_invoices', 'column' => 'warehouse_id', 'parent' => 'warehouses', 'previous' => 'cascade'],
        ['table' => 'sales_invoices', 'column' => 'sales_representative_id', 'parent' => 'employees', 'previous' => 'set null'],
        ['table' => 'sales_invoice_items', 'column' => 'product_id', 'parent' => 'products', 'previous' => 'cascade'],

        ['table' => 'customer_payments', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'cascade'],
        ['table' => 'customer_payments', 'column' => 'sales_invoice_id', 'parent' => 'sales_invoices', 'previous' => 'set null'],
        ['table' => 'customer_payments', 'column' => 'vehicle_id', 'parent' => 'vehicles', 'previous' => 'set null'],
        ['table' => 'customer_payments', 'column' => 'route_id', 'parent' => 'distribution_routes', 'previous' => 'set null'],
        ['table' => 'customer_payments', 'column' => 'warehouse_id', 'parent' => 'warehouses', 'previous' => 'set null'],
        ['table' => 'customer_payments', 'column' => 'sales_representative_id', 'parent' => 'employees', 'previous' => 'set null'],

        ['table' => 'sales_returns', 'column' => 'customer_id', 'parent' => 'customers', 'previous' => 'cascade'],
        ['table' => 'sales_returns', 'column' => 'sales_invoice_id', 'parent' => 'sales_invoices', 'previous' => 'set null'],
        ['table' => 'sales_returns', 'column' => 'vehicle_id', 'parent' => 'vehicles', 'previous' => 'set null'],
        ['table' => 'sales_returns', 'column' => 'route_id', 'parent' => 'distribution_routes', 'previous' => 'set null'],
        ['table' => 'sales_returns', 'column' => 'warehouse_id', 'parent' => 'warehouses', 'previous' => 'cascade'],
        ['table' => 'sales_returns', 'column' => 'sales_representative_id', 'parent' => 'employees', 'previous' => 'set null'],
        ['table' => 'sales_return_items', 'column' => 'product_id', 'parent' => 'products', 'previous' => 'cascade'],

        ['table' => 'daily_closings', 'column' => 'vehicle_id', 'parent' => 'vehicles', 'previous' => 'set null'],
        ['table' => 'daily_closings', 'column' => 'route_id', 'parent' => 'distribution_routes', 'previous' => 'set null'],
        ['table' => 'daily_closings', 'column' => 'warehouse_id', 'parent' => 'warehouses', 'previous' => 'cascade'],
        ['table' => 'daily_closings', 'column' => 'sales_representative_id', 'parent' => 'employees', 'previous' => 'set null'],
        ['table' => 'daily_closing_items', 'column' => 'product_id', 'parent' => 'products', 'previous' => 'cascade'],

        ['table' => 'vehicle_expenses', 'column' => 'vehicle_id', 'parent' => 'vehicles', 'previous' => 'cascade'],
        ['table' => 'vehicle_expenses', 'column' => 'warehouse_id', 'parent' => 'warehouses', 'previous' => 'cascade'],
        ['table' => 'vehicle_expenses', 'column' => 'route_id', 'parent' => 'distribution_routes', 'previous' => 'set null'],
        ['table' => 'vehicle_expenses', 'column' => 'driver_id', 'parent' => 'employees', 'previous' => 'set null'],
        ['table' => 'vehicle_expenses', 'column' => 'sales_representative_id', 'parent' => 'employees', 'previous' => 'set null'],
    ];

    public function up(): void
    {
        foreach (self::FOREIGN_KEYS as $foreignKey) {
            $this->replaceForeignKey(
                $foreignKey['table'],
                $foreignKey['column'],
                $foreignKey['parent'],
                'restrict',
            );
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::FOREIGN_KEYS) as $foreignKey) {
            $this->replaceForeignKey(
                $foreignKey['table'],
                $foreignKey['column'],
                $foreignKey['parent'],
                $foreignKey['previous'],
            );
        }
    }

    private function replaceForeignKey(
        string $tableName,
        string $column,
        string $parentTable,
        string $onDelete,
    ): void {
        Schema::table($tableName, function (Blueprint $table) use ($column): void {
            $table->dropForeign([$column]);
        });

        Schema::table($tableName, function (Blueprint $table) use ($column, $parentTable, $onDelete): void {
            $table->foreign($column)
                ->references('id')
                ->on($parentTable)
                ->onDelete($onDelete);
        });
    }
};
