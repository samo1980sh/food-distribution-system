<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileSyncChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entity',
        'record_id',
        'operation',
        'scope_snapshot',
        'changed_at',
    ];

    protected $casts = [
        'record_id' => 'integer',
        'scope_snapshot' => 'array',
        'changed_at' => 'datetime',
    ];
}
