<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    protected $fillable = [
        'code',
        'plate_number',
        'name',
        'vehicle_type',
        'capacity',
        'status',
        'current_odometer',
        'insurance_expiry_date',
        'license_expiry_date',
        'notes',
    ];

    protected $casts = [
        'capacity' => 'decimal:3',
        'current_odometer' => 'integer',
        'insurance_expiry_date' => 'date',
        'license_expiry_date' => 'date',
    ];

    public function routes(): HasMany
    {
        return $this->hasMany(DistributionRoute::class);
    }

    public function warehouse(): HasOne
    {
        return $this->hasOne(Warehouse::class);
    }

    public function vehicleLoads(): HasMany
    {
        return $this->hasMany(VehicleLoad::class);
    }
    public function vehicleExpenses(): HasMany
    {
        return $this->hasMany(VehicleExpense::class);
    }
}
