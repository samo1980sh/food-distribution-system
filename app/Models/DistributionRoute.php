<?php

namespace App\Models;

use App\Services\Operations\OperationalContextValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionRoute extends Model
{
    protected $fillable = [
        'area_id',
        'vehicle_id',
        'driver_id',
        'sales_representative_id',
        'code',
        'name',
        'visit_days',
        'status',
        'notes',
    ];

    protected $casts = [
        'visit_days' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $route): void {
            app(OperationalContextValidator::class)->validateRoute($route);
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function salesRepresentative(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_representative_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'route_id');
    }
}