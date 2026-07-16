<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileSyncPushBatch extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'batch_id',
        'request_hash',
        'status',
        'operation_count',
        'applied_count',
        'replayed_count',
        'conflict_count',
        'failed_count',
        'response_payload',
        'processed_at',
    ];

    protected $casts = [
        'operation_count' => 'integer',
        'applied_count' => 'integer',
        'replayed_count' => 'integer',
        'conflict_count' => 'integer',
        'failed_count' => 'integer',
        'response_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
