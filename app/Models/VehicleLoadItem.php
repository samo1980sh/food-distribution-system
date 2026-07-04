<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLoadItem extends Model
{
    protected $fillable = [
        'vehicle_load_id',
        'product_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function vehicleLoad(): BelongsTo
    {
        return $this->belongsTo(VehicleLoad::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}