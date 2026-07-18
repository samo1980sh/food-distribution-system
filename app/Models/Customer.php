<?php

namespace App\Models;

use App\Services\Operations\OperationalContextValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'code',
        'name',
        'owner_name',
        'phone',
        'mobile',
        'customer_type',
        'area_id',
        'route_id',
        'address',
        'latitude',
        'longitude',
        'credit_limit',
        'payment_type',
        'status',
        'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'credit_limit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $customer): void {
            app(OperationalContextValidator::class)->validateCustomer($customer);
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(DistributionRoute::class, 'route_id');
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }
}