<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverDelivery extends Model
{
    protected $fillable = [
        'driver_journey_id',
        'sales_invoice_id',
        'customer_id',
        'route_id',
        'vehicle_id',
        'warehouse_id',
        'driver_id',
        'sales_representative_id',
        'status',
        'expected_quantity',
        'delivered_quantity',
        'returned_quantity',
        'return_required',
        'recipient_name',
        'recipient_phone',
        'latitude',
        'longitude',
        'proof_note',
        'failure_reason',
        'outcome_submitted_at',
        'outcome_submitted_by',
    ];

    protected $casts = [
        'expected_quantity' => 'decimal:3',
        'delivered_quantity' => 'decimal:3',
        'returned_quantity' => 'decimal:3',
        'return_required' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'outcome_submitted_at' => 'datetime',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(DriverJourney::class, 'driver_journey_id');
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DistributionRoute::class, 'route_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function salesRepresentative(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_representative_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DriverDeliveryItem::class);
    }

    public function outcomeSubmitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outcome_submitted_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['delivered', 'partial', 'failed'], true);
    }
}
