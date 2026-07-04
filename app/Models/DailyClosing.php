<?php

namespace App\Models;

use App\Services\Distribution\DailyClosingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class DailyClosing extends Model
{
    protected $fillable = [
        'closing_number',
        'closing_date',
        'vehicle_id',
        'route_id',
        'warehouse_id',
        'sales_representative_id',
        'status',
        'total_loaded_quantity',
        'total_sold_quantity',
        'total_returned_quantity',
        'total_sales_amount',
        'total_returns_amount',
        'total_collections_amount',
        'expected_cash_amount',
        'actual_cash_amount',
        'cash_difference',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'total_loaded_quantity' => 'decimal:3',
        'total_sold_quantity' => 'decimal:3',
        'total_returned_quantity' => 'decimal:3',
        'total_sales_amount' => 'decimal:2',
        'total_returns_amount' => 'decimal:2',
        'total_collections_amount' => 'decimal:2',
        'expected_cash_amount' => 'decimal:2',
        'actual_cash_amount' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DailyClosing $closing): void {
            if (blank($closing->closing_number)) {
                $closing->closing_number = app(DailyClosingService::class)->generateClosingNumber();
            }

            if (blank($closing->closing_date)) {
                $closing->closing_date = now()->toDateString();
            }

            if (blank($closing->status)) {
                $closing->status = 'draft';
            }

            if (blank($closing->created_by)) {
                $closing->created_by = Auth::id();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(DailyClosingItem::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DistributionRoute::class, 'route_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function salesRepresentative(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_representative_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}