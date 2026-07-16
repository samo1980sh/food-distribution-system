<?php

namespace App\Http\Resources\Api\V1\Operational;

use Carbon\CarbonInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

abstract class OperationalResource extends JsonResource
{
    protected function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof CarbonInterface ? $value : Carbon::parse($value))
            ->toDateString();
    }

    protected function dateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof CarbonInterface ? $value : Carbon::parse($value))
            ->toIso8601String();
    }

    protected function decimal(mixed $value, int $scale = 2): string
    {
        return number_format((float) ($value ?? 0), $scale, '.', '');
    }
}
