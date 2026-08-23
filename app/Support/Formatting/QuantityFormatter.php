<?php

namespace App\Support\Formatting;

use App\Models\Unit;

final class QuantityFormatter
{
    public static function format(?float $quantity): string
    {
        if ($quantity === null) {
            return '-';
        }

        if (abs($quantity) < 0.0005) {
            return '0';
        }

        $formatted = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    public static function formatWithUnit(?float $quantity, ?Unit $unit = null): string
    {
        $formatted = self::format($quantity);

        if ($formatted === '-') {
            return $formatted;
        }

        $unitLabel = self::unitLabel($unit);

        return $unitLabel !== null ? $formatted.' '.$unitLabel : $formatted;
    }

    public static function formatDifference(?float $difference, ?Unit $unit = null): string
    {
        if ($difference === null) {
            return '-';
        }

        if (abs($difference) < 0.0005) {
            return '0'.(self::unitLabel($unit) ? ' '.self::unitLabel($unit) : '');
        }

        $formatted = self::format(abs($difference));
        $prefix = $difference > 0 ? '+' : '-';
        $unitLabel = self::unitLabel($unit);

        return $prefix.$formatted.($unitLabel ? ' '.$unitLabel : '');
    }

    private static function unitLabel(?Unit $unit): ?string
    {
        if ($unit === null) {
            return null;
        }

        return filled($unit->symbol)
            ? trim((string) $unit->symbol)
            : (filled($unit->name_ar) ? trim((string) $unit->name_ar) : (filled($unit->code) ? trim((string) $unit->code) : null));
    }

    private function __construct()
    {
    }
}
