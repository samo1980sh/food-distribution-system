<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('api.version')
    ->group(function (): void {
        Route::get('/health', HealthController::class)
            ->middleware('throttle:mobile-api')
            ->name('api.v1.health');

        Route::post('/auth/login', [AuthController::class, 'login'])
            ->middleware('throttle:mobile-login')
            ->name('api.v1.auth.login');

        Route::middleware([
            'auth:sanctum',
            'api.access',
            'api.token.touch',
            'throttle:mobile-api',
        ])->group(function (): void {
            Route::get('/auth/me', [AuthController::class, 'me'])
                ->name('api.v1.auth.me');

            Route::get('/auth/sessions', [AuthController::class, 'sessions'])
                ->name('api.v1.auth.sessions');

            Route::delete('/auth/sessions/{token}', [AuthController::class, 'destroySession'])
                ->whereNumber('token')
                ->name('api.v1.auth.sessions.destroy');

            Route::post('/auth/logout', [AuthController::class, 'logout'])
                ->name('api.v1.auth.logout');

            Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])
                ->name('api.v1.auth.logout-all');
        });
    });
