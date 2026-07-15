<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Policies\AreaPolicy;
use App\Policies\CustomerPaymentPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DailyClosingPolicy;
use App\Policies\DistributionRoutePolicy;
use App\Policies\EmployeePolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SalesInvoicePolicy;
use App\Policies\SalesReturnPolicy;
use App\Policies\StockBalancePolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehicleExpensePolicy;
use App\Policies\VehicleLoadPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private array $policies = [
        User::class => UserPolicy::class,
        Area::class => AreaPolicy::class,
        Employee::class => EmployeePolicy::class,
        Customer::class => CustomerPolicy::class,
        Unit::class => UnitPolicy::class,
        ProductCategory::class => ProductCategoryPolicy::class,
        Product::class => ProductPolicy::class,
        DistributionRoute::class => DistributionRoutePolicy::class,
        Vehicle::class => VehiclePolicy::class,
        Warehouse::class => WarehousePolicy::class,
        StockBalance::class => StockBalancePolicy::class,
        StockMovement::class => StockMovementPolicy::class,
        VehicleLoad::class => VehicleLoadPolicy::class,
        SalesInvoice::class => SalesInvoicePolicy::class,
        SalesReturn::class => SalesReturnPolicy::class,
        CustomerPayment::class => CustomerPaymentPolicy::class,
        VehicleExpense::class => VehicleExpensePolicy::class,
        DailyClosing::class => DailyClosingPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->isActive()) {
                return false;
            }

            return $user->isSuperAdmin() ? true : null;
        });
    }
}
