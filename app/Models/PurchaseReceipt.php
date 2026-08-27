<?php

namespace App\Models;

use App\Services\Support\DocumentNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class PurchaseReceipt extends Model
{
    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'warehouse_id',
        'receipt_date',
        'status',
        'total_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseReceipt $receipt): void {
            if (blank($receipt->receipt_number)) {
                $receipt->receipt_number = app(DocumentNumberService::class)
                    ->next('purchase_receipt', 'PRC');
            }

            if (blank($receipt->receipt_date)) {
                $receipt->receipt_date = now()->toDateString();
            }

            if (blank($receipt->status)) {
                $receipt->status = 'posted';
            }

            if (blank($receipt->created_by)) {
                $receipt->created_by = Auth::id();
            }
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
