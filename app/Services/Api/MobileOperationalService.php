<?php

namespace App\Services\Api;

use App\Enums\PermissionName;
use App\Http\Resources\Api\V1\Operational\DistributionRouteResource;
use App\Http\Resources\Api\V1\Operational\VehicleResource;
use App\Http\Resources\Api\V1\Operational\WarehouseResource;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use Illuminate\Http\Request;

class MobileOperationalService
{
    public function __construct(
        private readonly MobileBootstrapService $mobileBootstrapService,
        private readonly AccessScopeService $accessScopeService,
    ) {
    }

    /** @return array<string, mixed> */
    public function bootstrap(User $user, Request $request): array
    {
        return [
            'auth' => $this->mobileBootstrapService->build($user, $request),
            'capabilities' => $this->capabilities($user),
            'assignments' => $this->assignments($user, $request),
            'today' => $this->dashboard($user),
            'sync' => [
                'server_time' => now()->toIso8601String(),
                'cursor' => now()->toIso8601String(),
                'supports_updated_since' => true,
                'supports_deleted_records' => false,
                'write_api_enabled' => false,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function dashboard(User $user): array
    {
        $today = today()->toDateString();
        $financial = $user->can(PermissionName::DASHBOARD_FINANCIAL->value);

        $invoiceQuery = SalesInvoice::query()->whereDate('invoice_date', $today);
        $paymentQuery = CustomerPayment::query()->whereDate('payment_date', $today);
        $returnQuery = SalesReturn::query()->whereDate('return_date', $today);
        $expenseQuery = VehicleExpense::query()->whereDate('expense_date', $today);
        $loadQuery = VehicleLoad::query()->whereDate('load_date', $today);
        $closingQuery = DailyClosing::query()->whereDate('closing_date', $today);

        $data = [
            'date' => $today,
            'customers' => $user->can(PermissionName::CUSTOMERS_VIEW->value)
                ? Customer::query()->where('status', 'active')->count()
                : null,
            'stock_batches' => $user->can(PermissionName::STOCK_BALANCES_VIEW->value)
                ? StockBalance::query()->where('quantity', '>', 0)->count()
                : null,
            'vehicle_loads' => $user->can(PermissionName::VEHICLE_LOADS_VIEW->value)
                ? [
                    'total' => (clone $loadQuery)->count(),
                    'approved' => (clone $loadQuery)->where('status', 'approved')->count(),
                    'total_quantity' => (float) (clone $loadQuery)
                        ->where('status', 'approved')
                        ->sum('total_quantity'),
                ]
                : null,
            'sales_invoices' => $user->can(PermissionName::SALES_INVOICES_VIEW->value)
                ? [
                    'total' => (clone $invoiceQuery)->count(),
                    'confirmed' => (clone $invoiceQuery)->where('status', 'confirmed')->count(),
                    'total_amount' => $financial
                        ? (float) (clone $invoiceQuery)
                            ->where('status', 'confirmed')
                            ->sum('total_amount')
                        : null,
                ]
                : null,
            'customer_payments' => $user->can(PermissionName::CUSTOMER_PAYMENTS_VIEW->value)
                ? [
                    'total' => (clone $paymentQuery)->count(),
                    'confirmed' => (clone $paymentQuery)->where('status', 'confirmed')->count(),
                    'amount' => $financial
                        ? (float) (clone $paymentQuery)
                            ->where('status', 'confirmed')
                            ->sum('amount')
                        : null,
                ]
                : null,
            'sales_returns' => $user->can(PermissionName::SALES_RETURNS_VIEW->value)
                ? [
                    'total' => (clone $returnQuery)->count(),
                    'confirmed' => (clone $returnQuery)->where('status', 'confirmed')->count(),
                    'total_amount' => $financial
                        ? (float) (clone $returnQuery)
                            ->where('status', 'confirmed')
                            ->sum('total_amount')
                        : null,
                ]
                : null,
            'vehicle_expenses' => $user->can(PermissionName::VEHICLE_EXPENSES_VIEW->value)
                ? [
                    'total' => (clone $expenseQuery)->count(),
                    'pending' => (clone $expenseQuery)->where('status', 'pending')->count(),
                    'amount' => $financial
                        ? (float) (clone $expenseQuery)
                            ->where('status', 'approved')
                            ->sum('amount')
                        : null,
                ]
                : null,
            'daily_closings' => $user->can(PermissionName::DAILY_CLOSINGS_VIEW->value)
                ? [
                    'total' => (clone $closingQuery)->count(),
                    'draft' => (clone $closingQuery)->where('status', 'draft')->count(),
                    'confirmed' => (clone $closingQuery)->where('status', 'confirmed')->count(),
                ]
                : null,
        ];

        return array_filter($data, static fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    private function capabilities(User $user): array
    {
        $permissions = [
            'dashboard' => PermissionName::DASHBOARD_VIEW,
            'areas' => PermissionName::AREAS_VIEW,
            'routes' => PermissionName::DISTRIBUTION_ROUTES_VIEW,
            'customers' => PermissionName::CUSTOMERS_VIEW,
            'vehicles' => PermissionName::VEHICLES_VIEW,
            'warehouses' => PermissionName::WAREHOUSES_VIEW,
            'products' => PermissionName::PRODUCTS_VIEW,
            'stock' => PermissionName::STOCK_BALANCES_VIEW,
            'vehicle_loads' => PermissionName::VEHICLE_LOADS_VIEW,
            'sales_invoices' => PermissionName::SALES_INVOICES_VIEW,
            'customer_payments' => PermissionName::CUSTOMER_PAYMENTS_VIEW,
            'sales_returns' => PermissionName::SALES_RETURNS_VIEW,
            'vehicle_expenses' => PermissionName::VEHICLE_EXPENSES_VIEW,
            'daily_closings' => PermissionName::DAILY_CLOSINGS_VIEW,
        ];

        return [
            'read' => collect($permissions)
                ->map(fn (PermissionName $permission): bool => $user->can($permission->value))
                ->all(),
            'write' => [
                'enabled' => false,
                'sales_invoices' => $user->can(PermissionName::SALES_INVOICES_CREATE->value),
                'customer_payments' => $user->can(PermissionName::CUSTOMER_PAYMENTS_CREATE->value),
                'sales_returns' => $user->can(PermissionName::SALES_RETURNS_CREATE->value),
                'vehicle_expenses' => $user->can(PermissionName::VEHICLE_EXPENSES_CREATE->value),
            ],
            'financial_fields' => $user->can(PermissionName::DASHBOARD_FINANCIAL->value),
            'cost_fields' => $user->can(PermissionName::REPORT_PROFIT->value),
        ];
    }

    /** @return array<string, mixed> */
    private function assignments(User $user, Request $request): array
    {
        $scope = $this->accessScopeService->for($user);

        $routes = $user->can(PermissionName::DISTRIBUTION_ROUTES_VIEW->value)
            ? DistributionRouteResource::collection(
                DistributionRoute::query()
                    ->with(['area', 'vehicle', 'driver', 'salesRepresentative'])
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->limit(100)
                    ->get(),
            )->resolve($request)
            : [];

        $vehicles = $user->can(PermissionName::VEHICLES_VIEW->value)
            ? VehicleResource::collection(
                Vehicle::query()
                    ->with('warehouse')
                    ->where('status', 'active')
                    ->orderBy('plate_number')
                    ->limit(100)
                    ->get(),
            )->resolve($request)
            : [];

        $warehouses = $user->can(PermissionName::WAREHOUSES_VIEW->value)
            ? WarehouseResource::collection(
                Warehouse::query()
                    ->with('vehicle')
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->limit(100)
                    ->get(),
            )->resolve($request)
            : [];

        return [
            'scope' => [
                'unrestricted' => $scope->unrestricted,
                'area_ids' => $scope->areaIds,
                'route_ids' => $scope->routeIds,
                'vehicle_ids' => $scope->vehicleIds,
                'warehouse_ids' => $scope->warehouseIds,
                'employee_ids' => $scope->employeeIds,
            ],
            'routes' => $routes,
            'vehicles' => $vehicles,
            'warehouses' => $warehouses,
        ];
    }
}
