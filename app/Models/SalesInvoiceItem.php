<?php

namespace App\Models;

use App\Services\Sales\SalesInvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceItem extends Model
{
    protected $fillable = [
        'sales_invoice_id',
        'product_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_price',
        'unit_cost',
        'discount_amount',
        'line_total',
        'total_cost',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:6',
        'discount_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (SalesInvoiceItem $item): void {
            $item->line_total = ((float) $item->quantity * (float) $item->unit_price) - (float) $item->discount_amount;

            if ((float) $item->line_total < 0) {
                $item->line_total = 0;
            }

            $item->total_cost = (float) $item->quantity
                * (float) $item->unit_cost;
        });

        static::saved(function (SalesInvoiceItem $item): void {
            if ($item->salesInvoice) {
                app(SalesInvoiceService::class)->recalculateTotals($item->salesInvoice);
            }
        });

        static::deleted(function (SalesInvoiceItem $item): void {
            if ($item->salesInvoice) {
                app(SalesInvoiceService::class)->recalculateTotals($item->salesInvoice);
            }
        });
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
