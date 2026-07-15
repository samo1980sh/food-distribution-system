<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class TouchMobileApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $this->touchWhenStale($token, $request);
        }

        return $next($request);
    }

    private function touchWhenStale(
        PersonalAccessToken $token,
        Request $request,
    ): void {
        $interval = max(
            60,
            (int) config('mobile_api.token_touch_interval_seconds', 300),
        );

        $lastSeenAt = $token->getAttribute('last_seen_at');

        if (
            $lastSeenAt !== null
            && now()->diffInSeconds(Carbon::parse($lastSeenAt)) < $interval
        ) {
            return;
        }

        $token->forceFill([
            'last_seen_at' => now(),
            'ip_address' => $request->ip(),
        ])->saveQuietly();
    }
}
