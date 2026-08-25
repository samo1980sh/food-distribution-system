<?php

namespace App\Support\Api;

final class MobileSyncRetiredLegacyRegistry
{
    /** @var array<string, list<string>> */
    private const PUSH_SIGNATURES = [
        'driver_journeys' => ['start', 'finish'],
        'driver_deliveries' => ['submit_outcome'],
    ];

    /** @return list<string> */
    public static function entities(): array
    {
        return array_keys(self::PUSH_SIGNATURES);
    }

    /** @return list<string> */
    public static function actions(): array
    {
        return array_values(array_unique(array_merge(
            ...array_values(self::PUSH_SIGNATURES),
        )));
    }

    public static function supports(string $entity, string $action): bool
    {
        return in_array($action, self::PUSH_SIGNATURES[$entity] ?? [], true);
    }
}
