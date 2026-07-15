<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class MobileApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('mobile-login', function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email')));
            $key = hash('sha256', $email.'|'.($request->ip() ?? 'unknown'));

            return Limit::perMinute(max(
                1,
                (int) config('mobile_api.login_rate_limit_per_minute', 5),
            ))->by($key);
        });

        RateLimiter::for('mobile-api', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier()
                ? 'user:'.$request->user()->getAuthIdentifier()
                : 'ip:'.($request->ip() ?? 'unknown');

            return Limit::perMinute(max(
                1,
                (int) config('mobile_api.rate_limit_per_minute', 120),
            ))->by($key);
        });
    }
}
