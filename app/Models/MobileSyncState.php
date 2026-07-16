<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileSyncState extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'context_key',
        'last_pull_cursor',
        'last_pull_at',
        'last_full_sync_at',
    ];

    protected $casts = [
        'last_pull_cursor' => 'integer',
        'last_pull_at' => 'datetime',
        'last_full_sync_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
