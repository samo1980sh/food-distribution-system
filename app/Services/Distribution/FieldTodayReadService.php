<?php

namespace App\Services\Distribution;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Services\Authorization\AccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class FieldTodayReadService
{
    public function __construct(
        private readonly FieldRouteAssignmentResolver $routeResolver,
        private readonly AccessScopeService $accessScopeService,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(
        User $user,
        ?string $selectedRole = null,
        ?int $selectedRouteId = null,
    ): array {
        $date = today();
        $availableRoles = $this->availableRoles($user);

        if ($selectedRole !== null && ! in_array($selectedRole, $availableRoles, true)) {
            throw ValidationException::withMessages([
                'role' => 'الدور الميداني المحدد غير متاح لهذا المستخدم.',
            ]);
        }

        $contexts = [
            User::ROLE_DRIVER => null,
            User::ROLE_SALES_REPRESENTATIVE => null,
        ];

        foreach ($availableRoles as $role) {
            $routeId = $selectedRole === $role ? $selectedRouteId : null;
            $resolution = $this->routeResolver->resolveRole(
                $user,
                $role,
                $routeId,
                $date,
            );

            $contexts[$role] = $this->buildContext(
                $user,
                $role,
                $resolution,
                $date->toDateString(),
            );
        }

        return [
            'date' => $date->toDateString(),
            'server_time' => now()->toIso8601String(),
            'timezone' => (string) config('app.timezone'),
            'available_roles' => $availableRoles,
            'contexts' => $contexts,
        ];
    }

    /**
     * @param array{
     *     status: string,
     *     scheduled_today: bool,
     *     route: DistributionRoute|null,
     *     candidates: mixed,
     *     available_count: int,
     *     scheduled_count: int
     * } $resolution
     * @return array<string, mixed>
     */
    private function buildContext(
        User $user,
        string $role,
        array $resolution,
        string $date,
    ): array {
        /** @var DistributionRoute|null $route */
        $route = $resolution['route'];
        $readiness = $this->readiness($route);
        $status = $resolution['status'];

        if (
            $route instanceof DistributionRoute
            && $status === FieldRouteAssignmentResolver::STATUS_READY
            && ! $readiness['ready']
        ) {
            $status = 'incomplete_assignment';
        }

        return [
            'role' => $role,
            'status' => $status,
            'schedule_status' => $resolution['status'],
            'scheduled_today' => $resolution['scheduled_today'],
            'available_routes_count' => $resolution['available_count'],
            'scheduled_routes_count' => $resolution['scheduled_count'],
            'candidates' => $resolution['candidates'],
            'readiness' => $readiness,
            'route' => $route,
            'vehicle' => $route?->vehicle,
            'warehouse' => $route?->vehicle?->warehouse,
            'summary' => $route instanceof DistributionRoute
                ? $this->summary($user, $role, $route, $date)
                : null,
            'daily_closing' => $route instanceof DistributionRoute
                ? $this->dailyClosing($user, $route, $date)
                : null,
        ];
    }

    /** @return array{ready: bool, issues: list<string>} */
    private function readiness(?DistributionRoute $route): array
    {
        if (! $route instanceof DistributionRoute) {
            return [
                'ready' => false,
                'issues' => [],
            ];
        }

        $issues = [];
        $vehicle = $route->vehicle;
        $warehouse = $vehicle?->warehouse;

        if ($vehicle === null) {
            $issues[] = 'missing_vehicle';
        } elseif ($vehicle->status !== 'active') {
            $issues[] = 'inactive_vehicle';
        }

        if ($warehouse === null) {
            $issues[] = 'missing_vehicle_warehouse';
        } elseif ($warehouse->type !== 'vehicle' || $warehouse->status !== 'active') {
            $issues[] = 'inactive_vehicle_warehouse';
        }

        if ($route->driver === null) {
            $issues[] = 'missing_driver';
        } elseif ($route->driver->status !== 'active') {
            $issues[] = 'inactive_driver';
        }

        if ($route->salesRepresentative === null) {
            $issues[] = 'missing_sales_representative';
        } elseif ($route->salesRepresentative->status !== 'active') {
            $issues[] = 'inactive_sales_representative';
        }

        return [
            'ready' => $issues === [],
            'issues' => $issues,
        ];
    }

    /** @return array<string, mixed> */
    private function summary(
        User $user,
        string $role,
        DistributionRoute $route,
        string $date,
    ): array {
        return $role === User::ROLE_DRIVER
            ? $this->driverSummary($user, $route, $date)
            : $this->salesSummary($user, $route, $date);
    }

    /** @return array<string, mixed> */
    private function salesSummary(
        User $user,
        DistributionRoute $route,
        string $date,
    ): array {
        $representativeId = (int) $route->sales_representative_id;

        $customers = Customer::withoutGlobalScopes()
            ->where('route_id', $route->getKey())
            ->where('status', 'active');
        $customers = $this->scoped($customers, $user);

        $invoices = SalesInvoice::withoutGlobalScopes()
            ->whereDate('invoice_date', $date)
            ->where('route_id', $route->getKey())
            ->where('sales_representative_id', $representativeId);
        $invoices = $this->scoped($invoices, $user);

        $payments = CustomerPayment::withoutGlobalScopes()
            ->whereDate('payment_date', $date)
            ->where('route_id', $route->getKey())
            ->where('sales_representative_id', $representativeId);
        $payments = $this->scoped($payments, $user);

        $returns = SalesReturn::withoutGlobalScopes()
            ->whereDate('return_date', $date)
            ->where('route_id', $route->getKey())
            ->where('sales_representative_id', $representativeId);
        $returns = $this->scoped($returns, $user);

        return [
            'assigned_customers' => (clone $customers)->count(),
            'invoices' => [
                'total' => (clone $invoices)->count(),
                'draft' => (clone $invoices)->where('status', 'draft')->count(),
                'confirmed' => (clone $invoices)->where('status', 'confirmed')->count(),
                'cancelled' => (clone $invoices)->where('status', 'cancelled')->count(),
                'confirmed_amount' => $this->decimal(
                    (clone $invoices)->where('status', 'confirmed')->sum('total_amount'),
                ),
            ],
            'payments' => [
                'total' => (clone $payments)->count(),
                'draft' => (clone $payments)->where('status', 'draft')->count(),
                'confirmed' => (clone $payments)->where('status', 'confirmed')->count(),
                'cancelled' => (clone $payments)->where('status', 'cancelled')->count(),
                'confirmed_amount' => $this->decimal(
                    (clone $payments)->where('status', 'confirmed')->sum('amount'),
                ),
            ],
            'returns' => [
                'total' => (clone $returns)->count(),
                'draft' => (clone $returns)->where('status', 'draft')->count(),
                'confirmed' => (clone $returns)->where('status', 'confirmed')->count(),
                'cancelled' => (clone $returns)->where('status', 'cancelled')->count(),
                'confirmed_amount' => $this->decimal(
                    (clone $returns)->where('status', 'confirmed')->sum('total_amount'),
                ),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function driverSummary(
        User $user,
        DistributionRoute $route,
        string $date,
    ): array {
        $driverId = (int) $route->driver_id;

        $loads = VehicleLoad::withoutGlobalScopes()
            ->withCount([
                'items',
                'items as different_items_count' => fn (Builder $query): Builder => $query
                    ->whereNotNull('received_quantity')
                    ->whereColumn('received_quantity', '!=', 'quantity'),
            ])
            ->whereDate('load_date', $date)
            ->where('route_id', $route->getKey())
            ->where('driver_id', $driverId)
            ->where('status', 'approved');
        $loads = $this->scoped($loads, $user);
        $loads = $loads
            ->orderByRaw("CASE handover_status WHEN 'pending' THEN 0 WHEN 'discrepancy' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->get();

        $warehouseId = $route->vehicle?->warehouse?->getKey();
        $stock = null;

        if ($warehouseId !== null) {
            $balances = StockBalance::withoutGlobalScopes()
                ->where('warehouse_id', $warehouseId)
                ->where('quantity', '>', 0);
            $balances = $this->scoped($balances, $user);

            $stock = [
                'batches_count' => (clone $balances)->count(),
                'products_count' => (clone $balances)->distinct()->count('product_id'),
                'total_quantity' => $this->decimal((clone $balances)->sum('quantity'), 3),
            ];
        }

        $expenses = VehicleExpense::withoutGlobalScopes()
            ->whereDate('expense_date', $date)
            ->where('route_id', $route->getKey())
            ->where('driver_id', $driverId);
        $expenses = $this->scoped($expenses, $user);

        $handoverStatuses = $loads->pluck('handover_status');
        $loadCustodyStatus = match (true) {
            $loads->isEmpty() => 'none',
            $handoverStatuses->contains('discrepancy') => 'discrepancy',
            $handoverStatuses->every(fn (mixed $status): bool => $status === 'received') => 'received',
            default => 'pending',
        };

        $primaryLoad = $loads->first();

        return [
            'load_custody' => [
                'status' => $loadCustodyStatus,
                'loads_count' => $loads->count(),
                'pending_count' => $handoverStatuses->filter(fn (mixed $status): bool => $status === 'pending')->count(),
                'received_count' => $handoverStatuses->filter(fn (mixed $status): bool => $status === 'received')->count(),
                'discrepancy_count' => $handoverStatuses->filter(fn (mixed $status): bool => $status === 'discrepancy')->count(),
                'primary_load' => $primaryLoad === null ? null : [
                    'id' => (int) $primaryLoad->getKey(),
                    'load_number' => $primaryLoad->load_number,
                    'status' => $primaryLoad->status,
                    'handover_status' => $primaryLoad->handover_status,
                    'items_count' => (int) $primaryLoad->items_count,
                    'total_quantity' => $this->decimal($primaryLoad->total_quantity, 3),
                    'different_items_count' => (int) $primaryLoad->different_items_count,
                ],
            ],
            'stock' => $stock,
            'expenses' => [
                'total' => (clone $expenses)->count(),
                'pending' => (clone $expenses)->where('status', 'pending')->count(),
                'approved' => (clone $expenses)->where('status', 'approved')->count(),
                'rejected' => (clone $expenses)->where('status', 'rejected')->count(),
                'total_amount' => $this->decimal((clone $expenses)->sum('amount')),
                'approved_amount' => $this->decimal(
                    (clone $expenses)->where('status', 'approved')->sum('amount'),
                ),
            ],
        ];
    }

    private function dailyClosing(
        User $user,
        DistributionRoute $route,
        string $date,
    ): ?DailyClosing {
        $query = DailyClosing::withoutGlobalScopes()
            ->whereDate('closing_date', $date)
            ->where('route_id', $route->getKey())
            ->where('status', '!=', 'cancelled');
        $query = $this->scoped($query, $user);

        return $query
            ->orderByDesc('id')
            ->first();
    }

    /** @return list<string> */
    private function availableRoles(User $user): array
    {
        return collect([
            User::ROLE_DRIVER,
            User::ROLE_SALES_REPRESENTATIVE,
        ])->filter(fn (string $role): bool => $user->hasRole($role))
            ->values()
            ->all();
    }

    private function scoped(Builder $query, User $user): Builder
    {
        return $this->accessScopeService->apply($query, $user);
    }

    private function decimal(mixed $value, int $scale = 2): string
    {
        return number_format((float) ($value ?? 0), $scale, '.', '');
    }
}
