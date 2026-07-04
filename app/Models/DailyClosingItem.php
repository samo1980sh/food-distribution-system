<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosingItem extends Model
{
    protected $fillable = [
        'daily_closing_id',
        'product_id',
        'loaded_quantity',
        'sold_quantity',
        'returned_quantity',
        'expected_quantity',
        'actual_quantity',
        'difference_quantity',
        'notes',
    ];

    protected $casts = [
        'loaded_quantity' => 'decimal:3',
        'sold_quantity' => 'decimal:3',
        'returned_quantity' => 'decimal:3',
        'expected_quantity' => 'decimal:3',
        'actual_quantity' => 'decimal:3',
        'difference_quantity' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::saving(function (DailyClosingItem $item): void {
            if ($item->actual_quantity !== null) {
                $item->difference_quantity = (float) $item->actual_quantity - (float) $item->expected_quantity;
            }
        });
    }

    public function dailyClosing(): BelongsTo
    {
        return $this->belongsTo(DailyClosing::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}