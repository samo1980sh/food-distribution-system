<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'ordered_quantity',
        'received_quantity',
        'unit_cost',
        'line_total',
        'notes',
    ];

    protected $casts = [
        'ordered_quantity' => 'decimal:3',
        'received_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:6',
        'line_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item): void {
            $item->line_total = round(
                (float) $item->ordered_quantity * (float) $item->unit_cost,
                2,
            );
        });

        static::saved(function (PurchaseOrderItem $item): void {
            self::refreshOrderTotals((int) $item->purchase_order_id);
        });

        static::deleted(function (PurchaseOrderItem $item): void {
            self::refreshOrderTotals((int) $item->purchase_order_id);
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    private static function refreshOrderTotals(int $purchaseOrderId): void
    {
        if ($purchaseOrderId <= 0) {
            return;
        }

        $total = (float) static::query()
            ->where('purchase_order_id', $purchaseOrderId)
            ->sum('line_total');

        PurchaseOrder::query()
            ->whereKey($purchaseOrderId)
            ->update([
                'subtotal' => round($total, 2),
                'total_amount' => round($total, 2),
                'updated_at' => now(),
            ]);
    }
}
