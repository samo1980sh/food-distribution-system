<?php

namespace App\Models;

use App\Services\Sales\SalesReturnService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $fillable = [
        'sales_return_id',
        'product_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_price',
        'unit_cost',
        'line_total',
        'total_cost',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:6',
        'line_total' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (SalesReturnItem $item): void {
            $item->line_total = (float) $item->quantity * (float) $item->unit_price;
            $item->total_cost = (float) $item->quantity * (float) $item->unit_cost;
        });

        static::saved(function (SalesReturnItem $item): void {
            if ($item->salesReturn) {
                app(SalesReturnService::class)->recalculateTotals($item->salesReturn);
            }
        });

        static::deleted(function (SalesReturnItem $item): void {
            if ($item->salesReturn) {
                app(SalesReturnService::class)->recalculateTotals($item->salesReturn);
            }
        });
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
