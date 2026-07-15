<?php

namespace App\Support\Authorization;

use App\Models\Area;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\ProfitReportEntry;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;

final class ScopedModelRegistry
{
    /** @return list<class-string> */
    public static function models(): array
    {
        return [
            Area::class,
            DistributionRoute::class,
            Vehicle::class,
            Warehouse::class,
            Employee::class,
            Customer::class,
            StockBalance::class,
            StockMovement::class,
            VehicleLoad::class,
            SalesInvoice::class,
            SalesReturn::class,
            CustomerPayment::class,
            VehicleExpense::class,
            DailyClosing::class,
            ProfitReportEntry::class,
        ];
    }
}
