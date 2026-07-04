<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'city',
        'status',
        'notes',
    ];

    public function routes(): HasMany
    {
        return $this->hasMany(DistributionRoute::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}