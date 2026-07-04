<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleLoad extends Model
{
    protected $fillable = [
        'load_number',
        'vehicle_id',
        'route_id',
        'driver_id',
        'sales_representative_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'load_date',
        'status',
        'total_quantity',
        'total_cost',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'load_date' => 'date',
        'total_quantity' => 'decimal:3',
        'total_cost' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(VehicleLoadItem::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DistributionRoute::class, 'route_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function salesRepresentative(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_representative_id');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}