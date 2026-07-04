<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'employee_code',
        'name',
        'phone',
        'job_title',
        'type',
        'status',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function driverRoutes(): HasMany
    {
        return $this->hasMany(DistributionRoute::class, 'driver_id');
    }

    public function salesRoutes(): HasMany
    {
        return $this->hasMany(DistributionRoute::class, 'sales_representative_id');
    }
}