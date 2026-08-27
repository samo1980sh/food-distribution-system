<?php

namespace App\Services\Inventory;

use App\Models\User;
use App\Services\Authorization\AccessScopeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseStockAlertService
{
    /**
     * @return array{out_of_stock: int, low_stock: int, healthy: int, total: int}
     */
    public function mainWarehouseSummary(): array
    {
        $levels = $this->mainWarehouseLevels();

        $outOfStock = $levels->filter(
            fn (object $row): bool => (float) $row->quantity <= 0,
        )->count();

        $lowStock = $levels->filter(
            fn (object $row): bool => (float) $row->quantity > 0
                && (float) $row->min_stock > 0
                && (float) $row->quantity <= (float) $row->min_stock,
        )->count();

        return [
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'healthy' => max($levels->count() - $outOfStock - $lowStock, 0),
            'total' => $levels->count(),
        ];
    }

    /** @return Collection<int, object> */
    public function mainWarehouseLevels(): Collection
    {
        $query = DB::table('warehouses')
            ->crossJoin('products')
            ->leftJoin('stock_balances', function ($join): void {
                $join->on('stock_balances.warehouse_id', '=', 'warehouses.id')
                    ->on('stock_balances.product_id', '=', 'products.id');
            })
            ->where('warehouses.status', 'active')
            ->where('warehouses.type', 'main')
            ->where('products.status', 'active')
            ->groupBy([
                'warehouses.id',
                'warehouses.name',
                'products.id',
                'products.sku',
                'products.name_ar',
                'products.min_stock',
            ])
            ->select([
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'products.id as product_id',
                'products.sku',
                'products.name_ar',
                'products.min_stock',
            ])
            ->selectRaw('COALESCE(SUM(stock_balances.quantity), 0) as quantity');

        $user = Auth::user();

        if ($user instanceof User) {
            $scope = app(AccessScopeService::class)->for($user);

            if (! $scope->unrestricted) {
                if ($scope->warehouseIds === []) {
                    return collect();
                }

                $query->whereIn('warehouses.id', $scope->warehouseIds);
            }
        }

        return $query
            ->orderBy('warehouses.name')
            ->orderBy('products.name_ar')
            ->get();
    }
}
