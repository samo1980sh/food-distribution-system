<?php

namespace App\Models;

use App\Enums\OperationSource;
use App\Services\Support\DocumentNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class DriverJourney extends Model
{
    protected $fillable = [
        'journey_number',
        'journey_date',
        'route_id',
        'vehicle_id',
        'warehouse_id',
        'driver_id',
        'sales_representative_id',
        'status',
        'start_odometer',
        'end_odometer',
        'started_at',
        'finished_at',
        'start_notes',
        'finish_notes',
        'created_by',
        'operation_source',
    ];

    protected $casts = [
        'journey_date' => 'date',
        'start_odometer' => 'decimal:2',
        'end_odometer' => 'decimal:2',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'operation_source' => OperationSource::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (DriverJourney $journey): void {
            if (blank($journey->journey_number)) {
                $journey->journey_number = app(DocumentNumberService::class)
                    ->next('driver_journey', 'JRN');
            }

            if (blank($journey->journey_date)) {
                $journey->journey_date = today()->toDateString();
            }

            if (blank($journey->status)) {
                $journey->status = 'ready';
            }

            if (blank($journey->created_by)) {
                $journey->created_by = Auth::id();
            }

            if (blank($journey->operation_source)) {
                $journey->operation_source = OperationSource::MOBILE_DRIVER;
            }
        });
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

    public function deliveries(): HasMany
    {
        return $this->hasMany(DriverDelivery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
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
