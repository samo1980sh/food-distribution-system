<?php

namespace App\Services\Distribution;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\SalesInvoice;
use App\Models\SalesJourney;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Services\Authorization\AccessScopeService;
use App\Support\Api\MobileAppAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class FieldTodayReadService
{
    public function __construct(
        private readonly FieldRouteAssignmentResolver $routeResolver,
        private readonly AccessScopeService $accessScopeService,
        private readonly SaleableVehicleStockService $saleableStock,
    ) {}

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

        $contexts = [User::ROLE_SALES_REPRESENTATIVE => null];

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
        $readiness = $this->readiness($route, $role);
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
            'schedule_status' => $resolution['schedule_status'],
            'scheduled_today' => $resolution['scheduled_today'],
            'operational_today' => $resolution['operational_today'],
            'exceptional_operation' => $resolution['exceptional_operation'],
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
    private function readiness(?DistributionRoute $route, string $role): array
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
        return $this->salesSummary($user, $route, $date);
    }

    /** @return array<string, mixed> */
    private function salesSummary(
        User $user,
        DistributionRoute $route,
        string $date,
    ): array {
        $representativeId = (int) $route->sales_representative_id;

        $journey = SalesJourney::withoutGlobalScopes()
            ->with(['visits.customer.area', 'visits.customer.route'])
            ->withCount([
                'visits',
                'visits as pending_visits_count' => fn (Builder $query) => $query->where('status', 'pending'),
                'visits as in_progress_visits_count' => fn (Builder $query) => $query->where('status', 'in_progress'),
                'visits as completed_visits_count' => fn (Builder $query) => $query->where('status', 'completed'),
            ])
            ->whereDate('journey_date', $date)
            ->where('route_id', $route->getKey())
            ->where('sales_representative_id', $representativeId)
            ->first();

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
        $vehicleOperations = $this->representativeVehicleOperations(
            $user,
            $route,
            $date,
            $representativeId,
        );

        return [
            'journey' => $journey === null ? null : [
                'id' => (int) $journey->id,
                'journey_number' => $journey->journey_number,
                'status' => $journey->status,
                'started_at' => $journey->started_at?->toIso8601String(),
                'finished_at' => $journey->finished_at?->toIso8601String(),
                'start_odometer' => $journey->start_odometer === null ? null : (int) $journey->start_odometer,
                'end_odometer' => $journey->end_odometer === null ? null : (int) $journey->end_odometer,
                'distance_km' => $journey->distance_km === null ? null : (int) $journey->distance_km,
                'visits' => [
                    'total' => (int) $journey->visits_count,
                    'pending' => (int) $journey->pending_visits_count,
                    'in_progress' => (int) $journey->in_progress_visits_count,
                    'completed' => (int) $journey->completed_visits_count,
                ],
            ],
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
            'load_custody' => $vehicleOperations['load_custody'],
            'stock' => $vehicleOperations['stock'],
            'expenses' => $vehicleOperations['expenses'],
        ];
    }

    /** @return array<string, mixed> */
    private function representativeVehicleOperations(
        User $user,
        DistributionRoute $route,
        string $date,
        int $representativeId,
    ): array {
        $loads = VehicleLoad::withoutGlobalScopes()
            ->withCount([
                'items',
                'items as different_items_count' => fn (Builder $query): Builder => $query
                    ->whereNotNull('received_quantity')
                    ->whereColumn('received_quantity', '!=', 'quantity'),
            ])
            ->whereDate('load_date', $date)
            ->where('route_id', $route->getKey())
            ->where('status', 'approved')
            ->where(function (Builder $query) use ($representativeId): void {
                $query->where('sales_representative_id', $representativeId)
                    ->orWhereNull('sales_representative_id');
            });
        $loads = $this->scoped($loads, $user)
            ->orderByRaw("CASE handover_status WHEN 'pending' THEN 0 WHEN 'discrepancy' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->get();

        $warehouseId = $route->vehicle?->warehouse?->getKey();
        $stock = null;

        if ($warehouseId !== null) {
            $balances = $this->saleableStock->query($warehouseId, $date);
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
            ->where('sales_representative_id', $representativeId);
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
        $role = MobileAppAccess::activeFieldRole($user);

        return $role === null ? [] : [$role];
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
