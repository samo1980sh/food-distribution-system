<?php

namespace App\Providers;

use App\Observers\MobileSyncChangeObserver;
use App\Services\Api\MobileSyncCascadeService;
use App\Services\Api\MobileSyncChangeRecorder;
use App\Services\Api\MobileSyncContextService;
use App\Services\Api\MobileSyncScopeService;
use App\Support\Api\MobileSyncEntityRegistry;
use Illuminate\Support\ServiceProvider;

class MobileOfflineSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(MobileSyncContextService::class);
        $this->app->scoped(MobileSyncScopeService::class);
        $this->app->scoped(MobileSyncChangeRecorder::class);
        $this->app->scoped(MobileSyncCascadeService::class);
    }

    public function boot(): void
    {
        foreach (MobileSyncEntityRegistry::models() as $model) {
            $model::observe(MobileSyncChangeObserver::class);
        }
    }
}
