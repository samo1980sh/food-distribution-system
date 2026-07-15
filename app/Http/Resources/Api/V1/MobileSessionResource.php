<?php

namespace App\Http\Resources\Api\V1;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class MobileSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->getKey();

        return [
            'id' => (int) $this->getKey(),
            'device_id' => $this->getAttribute('device_id'),
            'device_name' => $this->getAttribute('device_name'),
            'platform' => $this->getAttribute('platform'),
            'app_version' => $this->getAttribute('app_version'),
            'ip_address' => $this->getAttribute('ip_address'),
            'is_current' => (int) $currentTokenId === (int) $this->getKey(),
            'last_seen_at' => $this->asIsoString($this->getAttribute('last_seen_at')),
            'last_used_at' => $this->asIsoString($this->getAttribute('last_used_at')),
            'created_at' => $this->asIsoString($this->getAttribute('created_at')),
            'expires_at' => $this->asIsoString($this->getAttribute('expires_at')),
        ];
    }

    private function asIsoString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
