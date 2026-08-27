<?php

namespace App\Models;

use App\Enums\OperationSource;
use App\Services\Distribution\SalesFieldOperationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class SalesJourney extends Model
{
    protected $fillable = [
        'journey_number',
        'journey_date',
        'route_id',
        'vehicle_id',
        'warehouse_id',
        'sales_representative_id',
        'status',
        'started_at',
        'finished_at',
        'start_odometer',
        'end_odometer',
        'distance_km',
        'start_notes',
        'finish_notes',
        'created_by',
        'operation_source',
    ];

    protected $casts = [
        'journey_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'start_odometer' => 'integer',
        'end_odometer' => 'integer',
        'distance_km' => 'integer',
        'operation_source' => OperationSource::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (SalesJourney $journey): void {
            if (blank($journey->journey_number)) {
                $journey->journey_number = app(SalesFieldOperationService::class)
                    ->generateJourneyNumber();
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
                $journey->operation_source = OperationSource::MOBILE_SALES;
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

    public function salesRepresentative(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_representative_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(SalesVisit::class);
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
