<?php

namespace App\Services\Distribution;

use App\Models\StockBalance;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class SaleableVehicleStockService
{
    public function query(int $warehouseId, CarbonInterface|string $date): Builder
    {
        return StockBalance::withoutGlobalScopes()
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->whereHas('product', fn (Builder $query): Builder => $query->where('status', 'active'))
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $date);
            });
    }

    public function exists(int $warehouseId, CarbonInterface|string $date): bool
    {
        return $this->query($warehouseId, $date)->exists();
    }
}
