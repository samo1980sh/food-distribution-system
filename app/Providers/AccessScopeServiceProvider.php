<?php

namespace App\Providers;

use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Services\Authorization\AccessScopeService;
use App\Support\Authorization\ScopedModelObserver;
use App\Support\Authorization\ScopedModelRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

class AccessScopeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AccessScopeService::class);
    }

    public function boot(): void
    {
        foreach (ScopedModelRegistry::models() as $model) {
            $model::addGlobalScope(
                'user_access_scope',
                fn (Builder $query): Builder => app(AccessScopeService::class)->apply($query),
            );
        }

        foreach ([
            StockMovement::class,
            VehicleLoad::class,
            SalesInvoice::class,
            SalesReturn::class,
            CustomerPayment::class,
            VehicleExpense::class,
            DailyClosing::class,
        ] as $model) {
            $model::observe(ScopedModelObserver::class);
        }
    }
}
