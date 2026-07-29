<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesVisit extends Model
{
    protected $fillable = [
        'sales_journey_id',
        'customer_id',
        'route_id',
        'area_id',
        'vehicle_id',
        'warehouse_id',
        'sales_representative_id',
        'planned_sequence',
        'status',
        'outcome',
        'started_at',
        'completed_at',
        'start_latitude',
        'start_longitude',
        'completion_latitude',
        'completion_longitude',
        'start_notes',
        'completion_notes',
        'started_by',
        'completed_by',
    ];

    protected $casts = [
        'planned_sequence' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'start_latitude' => 'decimal:7',
        'start_longitude' => 'decimal:7',
        'completion_latitude' => 'decimal:7',
        'completion_longitude' => 'decimal:7',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(SalesJourney::class, 'sales_journey_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DistributionRoute::class, 'route_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function salesRepresentative(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_representative_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
