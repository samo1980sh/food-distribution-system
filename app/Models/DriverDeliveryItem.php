<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverDeliveryItem extends Model
{
    protected $fillable = [
        'driver_delivery_id',
        'sales_invoice_item_id',
        'product_id',
        'batch_number',
        'expiry_date',
        'expected_quantity',
        'delivered_quantity',
        'returned_quantity',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'expected_quantity' => 'decimal:3',
        'delivered_quantity' => 'decimal:3',
        'returned_quantity' => 'decimal:3',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(DriverDelivery::class, 'driver_delivery_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceItem::class, 'sales_invoice_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
