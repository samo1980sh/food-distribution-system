<?php

namespace App\Support\Filament;

use Illuminate\Database\Eloquent\Model;

final class AdminOperationalDriverGuard
{
    /** @param array<string, mixed> $data */
    public static function sanitize(array $data, ?Model $record = null): array
    {
        $data['driver_id'] = self::historicalDriverId($record);

        return $data;
    }

    private static function historicalDriverId(?Model $record): ?int
    {
        $driverId = $record?->getAttribute('driver_id');

        return filled($driverId) ? (int) $driverId : null;
    }

    private function __construct() {}
}
