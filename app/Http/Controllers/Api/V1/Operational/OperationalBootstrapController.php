<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Operational\RouteResource;
use App\Http\Resources\Api\V1\Operational\VehicleResource;
use App\Http\Resources\Api\V1\Operational\WarehouseResource;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Api\MobileOfflineSyncService;
use App\Services\Api\MobileSyncContextService;
use App\Support\Api\MobileSyncEntityRegistry;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalBootstrapController extends Controller
{
    public function __construct(
        private readonly MobileSyncContextService $syncContextService,
        private readonly MobileOfflineSyncService $offlineSyncService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = today()->toDateString();

        $modules = [
            'routes' => $user->can(PermissionName::DISTRIBUTION_ROUTES_VIEW->value),
            'vehicles' => $user->can(PermissionName::VEHICLES_VIEW->value),
            'warehouses' => $user->can(PermissionName::WAREHOUSES_VIEW->value),
            'products' => $user->can(PermissionName::PRODUCTS_VIEW->value),
            'customers' => $user->can(PermissionName::CUSTOMERS_VIEW->value),
            'stock_balances' => $user->can(PermissionName::STOCK_BALANCES_VIEW->value),
            'vehicle_loads' => $user->can(PermissionName::VEHICLE_LOADS_VIEW->value),
            'sales_invoices' => $user->can(PermissionName::SALES_INVOICES_VIEW->value),
            'customer_payments' => $user->can(PermissionName::CUSTOMER_PAYMENTS_VIEW->value),
            'sales_returns' => $user->can(PermissionName::SALES_RETURNS_VIEW->value),
            'vehicle_expenses' => $user->can(PermissionName::VEHICLE_EXPENSES_VIEW->value),
            'daily_closings' => $user->can(PermissionName::DAILY_CLOSINGS_VIEW->value),
        ];

        $routes = $modules['routes']
            ? DistributionRoute::query()
                ->with(['area', 'vehicle.warehouse', 'driver', 'salesRepresentative'])
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
            : collect();
        $vehicles = $modules['vehicles']
            ? Vehicle::query()->with('warehouse')->where('status', 'active')->orderBy('code')->get()
            : collect();
        $warehouses = $modules['warehouses']
            ? Warehouse::query()->with('vehicle')->where('status', 'active')->orderBy('code')->get()
            : collect();

        return ApiResponse::success([
            'date' => $today,
            'server_time' => now()->toIso8601String(),
            'modules' => $modules,
            'context' => [
                'routes' => RouteResource::collection($routes)->resolve($request),
                'vehicles' => VehicleResource::collection($vehicles)->resolve($request),
                'warehouses' => WarehouseResource::collection($warehouses)->resolve($request),
            ],
            'write' => [
                'enabled' => true,
                'idempotent_create' => true,
                'client_reference_required' => true,
                'sales_invoices' => $this->writeCapabilities($user, SalesInvoice::class, [
                    'confirm' => PermissionName::SALES_INVOICES_CONFIRM,
                    'cancel' => PermissionName::SALES_INVOICES_CANCEL,
                ]),
                'customer_payments' => $this->writeCapabilities($user, CustomerPayment::class, [
                    'confirm' => PermissionName::CUSTOMER_PAYMENTS_CONFIRM,
                    'cancel' => PermissionName::CUSTOMER_PAYMENTS_CANCEL,
                ]),
                'sales_returns' => $this->writeCapabilities($user, SalesReturn::class, [
                    'confirm' => PermissionName::SALES_RETURNS_CONFIRM,
                    'cancel' => PermissionName::SALES_RETURNS_CANCEL,
                ]),
                'vehicle_expenses' => $this->writeCapabilities($user, VehicleExpense::class, [
                    'approve' => PermissionName::VEHICLE_EXPENSES_APPROVE,
                    'reject' => PermissionName::VEHICLE_EXPENSES_REJECT,
                ]),
                'daily_closings' => $this->writeCapabilities($user, DailyClosing::class, [
                    'refresh_totals' => PermissionName::DAILY_CLOSINGS_REFRESH_TOTALS,
                    'confirm' => PermissionName::DAILY_CLOSINGS_CONFIRM,
                    'cancel' => PermissionName::DAILY_CLOSINGS_CANCEL,
                ]),
            ],
            'sync' => [
                'server_time' => now()->toIso8601String(),
                'context_key' => $this->syncContextService->key($user),
                'registry_version' => MobileSyncEntityRegistry::VERSION,
                'current_cursor' => $this->offlineSyncService->currentCursor(),
                'minimum_cursor' => $this->offlineSyncService->minimumCursor(),
                'supports_updated_since' => true,
                'supports_cursor_pull' => true,
                'supports_deleted_records' => true,
                'write_api_enabled' => true,
                'offline_queue_supported' => true,
                'push_mode' => 'batch_idempotent',
                'batch_push_supported' => true,
                'conflict_strategy' => 'server_wins_pull_then_retry',
                'max_push_operations' => (int) config('mobile_api.sync_max_push_operations', 50),
                'endpoints' => [
                    'status' => '/api/v1/operational/sync/status',
                    'pull' => '/api/v1/operational/sync/pull',
                    'push' => '/api/v1/operational/sync/push',
                ],
            ],
            'today' => [
                'customers' => $modules['customers'] ? Customer::query()->where('status', 'active')->count() : null,
                'stock_batches' => $modules['stock_balances'] ? StockBalance::query()->where('quantity', '>', 0)->count() : null,
                'vehicle_loads' => $modules['vehicle_loads'] ? VehicleLoad::query()->whereDate('load_date', $today)->count() : null,
                'sales_invoices' => $modules['sales_invoices'] ? SalesInvoice::query()->whereDate('invoice_date', $today)->count() : null,
                'sales_amount' => $modules['sales_invoices'] ? (float) SalesInvoice::query()->whereDate('invoice_date', $today)->where('status', 'confirmed')->sum('total_amount') : null,
                'customer_payments' => $modules['customer_payments'] ? CustomerPayment::query()->whereDate('payment_date', $today)->count() : null,
                'collections_amount' => $modules['customer_payments'] ? (float) CustomerPayment::query()->whereDate('payment_date', $today)->where('status', 'confirmed')->sum('amount') : null,
                'sales_returns' => $modules['sales_returns'] ? SalesReturn::query()->whereDate('return_date', $today)->count() : null,
                'vehicle_expenses' => $modules['vehicle_expenses'] ? VehicleExpense::query()->whereDate('expense_date', $today)->count() : null,
                'daily_closings' => $modules['daily_closings'] ? DailyClosing::query()->whereDate('closing_date', $today)->count() : null,
            ],
        ], 'تم تحميل البيانات التشغيلية الأساسية.');
    }

    /**
     * @param class-string $modelClass
     * @param array<string, PermissionName> $actions
     * @return array<string, bool>
     */
    private function writeCapabilities(
        User $user,
        string $modelClass,
        array $actions,
    ): array {
        [$update, $delete] = match ($modelClass) {
            SalesInvoice::class => [PermissionName::SALES_INVOICES_UPDATE, PermissionName::SALES_INVOICES_DELETE],
            CustomerPayment::class => [PermissionName::CUSTOMER_PAYMENTS_UPDATE, PermissionName::CUSTOMER_PAYMENTS_DELETE],
            SalesReturn::class => [PermissionName::SALES_RETURNS_UPDATE, PermissionName::SALES_RETURNS_DELETE],
            VehicleExpense::class => [PermissionName::VEHICLE_EXPENSES_UPDATE, PermissionName::VEHICLE_EXPENSES_DELETE],
            DailyClosing::class => [PermissionName::DAILY_CLOSINGS_UPDATE, PermissionName::DAILY_CLOSINGS_DELETE],
        };

        return [
            'create' => $user->can('create', $modelClass),
            'update' => $user->can($update->value),
            'delete' => $user->can($delete->value),
            ...collect($actions)
                ->map(fn (PermissionName $permission): bool => $user->can($permission->value))
                ->all(),
        ];
    }
}
