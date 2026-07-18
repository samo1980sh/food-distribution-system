<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $fillable = [
        'movement_number',
        'movement_type',
        'movement_date',
        'reference_type',
        'reference_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'product_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_cost',
        'total_cost',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'expiry_date' => 'date',
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement): void {
            if (blank($movement->movement_date)) {
                $movement->movement_date = now()->toDateString();
            }
        });
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
