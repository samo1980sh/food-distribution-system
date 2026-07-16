<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileSyncCheckpoint extends Model
{
    public const SINGLETON_ID = 1;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'pruned_through_cursor',
        'last_compacted_at',
    ];

    protected $casts = [
        'pruned_through_cursor' => 'integer',
        'last_compacted_at' => 'datetime',
    ];

    public static function singleton(): self
    {
        return self::query()->firstOrCreate(
            ['id' => self::SINGLETON_ID],
            ['pruned_through_cursor' => 0],
        );
    }
}
