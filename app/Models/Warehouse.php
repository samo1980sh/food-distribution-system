<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'vehicle_id',
        'code',
        'name',
        'type',
        'address',
        'status',
        'notes',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function incomingStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'to_warehouse_id');
    }

    public function outgoingStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'from_warehouse_id');
    }
}