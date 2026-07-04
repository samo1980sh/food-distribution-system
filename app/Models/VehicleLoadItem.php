<?php

namespace App\Models;

use App\Services\Distribution\VehicleLoadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLoadItem extends Model
{
    protected $fillable = [
        'vehicle_load_id',
        'product_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (VehicleLoadItem $item): void {
            $item->total_cost = (float) $item->quantity * (float) $item->unit_cost;
        });

        static::saved(function (VehicleLoadItem $item): void {
            if ($item->vehicleLoad) {
                app(VehicleLoadService::class)->recalculateTotals($item->vehicleLoad);
            }
        });

        static::deleted(function (VehicleLoadItem $item): void {
            if ($item->vehicleLoad) {
                app(VehicleLoadService::class)->recalculateTotals($item->vehicleLoad);
            }
        });
    }

    public function vehicleLoad(): BelongsTo
    {
        return $this->belongsTo(VehicleLoad::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}