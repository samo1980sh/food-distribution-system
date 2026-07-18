<?php

namespace App\Models;

use App\Services\Distribution\DailyClosingService;
use App\Services\Operations\OperationalContextValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

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
        'total_opening_quantity',
        'total_movement_in_quantity',
        'total_movement_out_quantity',
        'total_expected_quantity',
        'total_loaded_quantity',
        'total_sold_quantity',
        'total_returned_quantity',
        'total_sales_amount',
        'total_returns_amount',
        'total_collections_amount',
        'invoice_cash_amount',
        'cash_collections_amount',
        'bank_transfer_collections_amount',
        'cheque_collections_amount',
        'other_collections_amount',
        'non_cash_collections_amount',
        'total_vehicle_expenses_amount',
        'cash_vehicle_expenses_amount',
        'non_cash_vehicle_expenses_amount',
        'expected_cash_amount',
        'actual_cash_amount',
        'cash_difference',
        'snapshot_at',
        'notes',
        'created_by',
        'client_reference',
        'client_payload_hash',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'total_opening_quantity' => 'decimal:3',
        'total_movement_in_quantity' => 'decimal:3',
        'total_movement_out_quantity' => 'decimal:3',
        'total_expected_quantity' => 'decimal:3',
        'total_loaded_quantity' => 'decimal:3',
        'total_sold_quantity' => 'decimal:3',
        'total_returned_quantity' => 'decimal:3',
        'total_sales_amount' => 'decimal:2',
        'total_returns_amount' => 'decimal:2',
        'total_collections_amount' => 'decimal:2',
        'invoice_cash_amount' => 'decimal:2',
        'cash_collections_amount' => 'decimal:2',
        'bank_transfer_collections_amount' => 'decimal:2',
        'cheque_collections_amount' => 'decimal:2',
        'other_collections_amount' => 'decimal:2',
        'non_cash_collections_amount' => 'decimal:2',
        'total_vehicle_expenses_amount' => 'decimal:2',
        'cash_vehicle_expenses_amount' => 'decimal:2',
        'non_cash_vehicle_expenses_amount' => 'decimal:2',
        'expected_cash_amount' => 'decimal:2',
        'actual_cash_amount' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'snapshot_at' => 'datetime',
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

        static::saving(function (DailyClosing $closing): void {
            app(OperationalContextValidator::class)->validateOperationalRecord($closing);

            $closing->active_scope_key = $closing->activeScopeKey();

            if ($closing->status === 'cancelled' || blank($closing->closing_date) || blank($closing->warehouse_id)) {
                return;
            }

            $duplicateExists = DailyClosing::query()
                ->where('id', '!=', $closing->getKey() ?? 0)
                ->where('active_scope_key', $closing->active_scope_key)
                ->exists();

            if ($duplicateExists) {
                throw new RuntimeException('يوجد إغلاق يومي فعّال لنفس التاريخ والمستودع. يرجى تعديل الإغلاق الموجود أو إلغاؤه أولاً.');
            }
        });
    }

    public function activeScopeKey(): ?string
    {
        if ($this->status === 'cancelled' || blank($this->closing_date) || blank($this->warehouse_id)) {
            return null;
        }

        return Carbon::parse($this->closing_date)->toDateString().'|'.$this->warehouse_id;
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
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
