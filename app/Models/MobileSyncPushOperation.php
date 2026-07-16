<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileSyncPushOperation extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'batch_id',
        'operation_id',
        'request_hash',
        'entity',
        'action',
        'status',
        'http_status',
        'record_id',
        'client_reference',
        'base_version',
        'response_payload',
        'processed_at',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'record_id' => 'integer',
        'response_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
