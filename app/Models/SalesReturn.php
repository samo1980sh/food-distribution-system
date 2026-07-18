<?php

namespace App\Models;

use App\Services\Operations\OperationalContextValidator;
use App\Services\Sales\SalesReturnService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class SalesReturn extends Model
{
    protected $fillable = [
        'return_number',
        'customer_id',
        'sales_invoice_id',
        'vehicle_id',
        'route_id',
        'warehouse_id',
        'sales_representative_id',
        'return_date',
        'status',
        'return_reason',
        'subtotal',
        'discount_amount',
        'total_amount',
        'notes',
        'created_by',
        'client_reference',
        'client_payload_hash',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'return_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (SalesReturn $record): void {
            app(OperationalContextValidator::class)->validateOperationalRecord($record);
        });

        static::creating(function (SalesReturn $salesReturn): void {
            if (blank($salesReturn->return_number)) {
                $salesReturn->return_number = app(SalesReturnService::class)->generateReturnNumber();
            }

            if (blank($salesReturn->return_date)) {
                $salesReturn->return_date = now()->toDateString();
            }

            if (blank($salesReturn->status)) {
                $salesReturn->status = 'draft';
            }

            if (blank($salesReturn->created_by)) {
                $salesReturn->created_by = Auth::id();
            }
        });

        static::saved(function (SalesReturn $salesReturn): void {
            if ($salesReturn->wasChanged(['discount_amount'])) {
                app(SalesReturnService::class)->recalculateTotals($salesReturn);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
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