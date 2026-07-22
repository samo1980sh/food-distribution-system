<?php

namespace App\Services\Authorization;

use App\Enums\UserRole;
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
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Support\Authorization\EffectiveAccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;

class AccessScopeService
{
    /** @var array<int, EffectiveAccessScope> */
    private array $cache = [];

    public function apply(Builder $query, ?User $user = null): Builder
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return $query;
        }

        if (! $user->isActive()) {
            return $this->deny($query);
        }

        $scope = $this->for($user);

        if ($scope->unrestricted) {
            return $query;
        }

        return match ($query->getModel()::class) {
            Area::class => $this->scopeSingleColumn($query, 'id', $scope->areaIds),
            DistributionRoute::class => $this->scopeSingleColumn($query, 'id', $scope->routeIds),
            Vehicle::class => $this->scopeSingleColumn($query, 'id', $scope->vehicleIds),
            Warehouse::class => $this->scopeSingleColumn($query, 'id', $scope->warehouseIds),
            Employee::class => $this->scopeSingleColumn($query, 'id', $scope->employeeIds),
            Customer::class => $this->scopeCustomers($query, $scope),
            StockBalance::class => $this->scopeSingleColumn($query, 'warehouse_id', $scope->warehouseIds),
            StockMovement::class => $this->scopeStockMovements($query, $scope),
            VehicleLoad::class => $this->scopeVehicleLoads($query, $scope),
            SalesInvoice::class,
            SalesReturn::class,
            CustomerPayment::class,
            VehicleExpense::class,
            DailyClosing::class,
            ProfitReportEntry::class => $this->scopeOperationalRecords($query, $scope),
            default => $query,
        };
    }

    public function applyToTable(
        QueryBuilder $query,
        string $table,
        ?User $user = null,
    ): QueryBuilder {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return $query;
        }

        if (! $user->isActive()) {
            return $query->whereRaw('1 = 0');
        }

        $scope = $this->for($user);

        if ($scope->unrestricted) {
            return $query;
        }

        if ($table === 'stock_balances') {
            return $scope->warehouseIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn($table.'.warehouse_id', $scope->warehouseIds);
        }

        if ($table === 'vehicle_loads' && $scope->role === UserRole::WAREHOUSE_KEEPER) {
            if ($scope->warehouseIds === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (QueryBuilder $query) use ($scope, $table): void {
                $query
                    ->whereIn($table.'.from_warehouse_id', $scope->warehouseIds)
                    ->orWhereIn($table.'.to_warehouse_id', $scope->warehouseIds);
            });
        }

        $columns = $this->tableVisibilityColumns($table, $scope);

        $columns = array_filter($columns, fn (array $ids): bool => $ids !== []);
        $usesCustomerVisibility = $scope->role !== UserRole::WAREHOUSE_KEEPER
            && in_array($table, [
                'sales_invoices',
                'sales_returns',
                'customer_payments',
            ], true)
            && ($scope->routeIds !== [] || $scope->areaIds !== []);

        if ($columns === [] && ! $usesCustomerVisibility) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (QueryBuilder $query) use (
            $columns,
            $scope,
            $table,
            $usesCustomerVisibility,
        ): void {
            $first = true;

            foreach ($columns as $column => $ids) {
                $method = $first ? 'whereIn' : 'orWhereIn';
                $query->{$method}($table.'.'.$column, $ids);
                $first = false;
            }

            if ($usesCustomerVisibility) {
                $customerIds = Customer::withoutGlobalScopes()
                    ->select('id');
                $this->scopeCustomers($customerIds, $scope);
                $method = $first ? 'whereIn' : 'orWhereIn';
                $query->{$method}($table.'.customer_id', $customerIds);
            }
        });
    }

    public function cacheKey(?User $user = null): string
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return 'guest';
        }

        $scope = $this->for($user);

        return hash('sha256', json_encode([
            'user' => (int) $user->getKey(),
            'role' => $scope->role?->value,
            'roles' => $this->roleValues($user),
            'unrestricted' => $scope->unrestricted,
            'areas' => $scope->areaIds,
            'routes' => $scope->routeIds,
            'vehicles' => $scope->vehicleIds,
            'warehouses' => $scope->warehouseIds,
            'employees' => $scope->employeeIds,
        ], JSON_UNESCAPED_SLASHES) ?: '');
    }

    public function allows(User $user, Model $record): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($this->for($user)->unrestricted) {
            return true;
        }

        if (! $record->exists || $record->getKey() === null) {
            return $this->allowsAttributes($user, $record);
        }

        return $this->apply(
            $record->newQueryWithoutScopes()->whereKey($record->getKey()),
            $user,
        )->exists();
    }

    public function allowsAttributes(User $user, Model $record): bool
    {
        $scope = $this->for($user);

        if ($scope->unrestricted) {
            return true;
        }

        return match ($record::class) {
            StockMovement::class => $this->allPresentIdsAllowed(
                [
                    $record->getAttribute('from_warehouse_id'),
                    $record->getAttribute('to_warehouse_id'),
                ],
                $scope->warehouseIds,
            ),
            VehicleLoad::class => $this->allowsVehicleLoadAttributes($scope, $record),
            SalesInvoice::class,
            SalesReturn::class,
            CustomerPayment::class,
            VehicleExpense::class,
            DailyClosing::class => $this->allowsOperationalAttributes($scope, $record),
            default => true,
        };
    }

    public function for(User $user): EffectiveAccessScope
    {
        $key = (int) $user->getKey();

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $roles = $this->rolesFor($user);
        $role = $roles[0] ?? null;

        if ($this->hasAnyRole($roles, [
            UserRole::SUPER_ADMIN,
            UserRole::MANAGER,
            UserRole::ACCOUNTANT,
        ])) {
            return $this->cache[$key] = new EffectiveAccessScope(
                role: $role,
                unrestricted: true,
            );
        }

        if ($role === null) {
            return $this->cache[$key] = new EffectiveAccessScope(
                role: null,
                unrestricted: false,
            );
        }

        $directAreaIds = $this->relationIds(
            $user->accessAreas()->getQuery()->withoutGlobalScopes(),
            'areas.id',
        );
        $directRouteIds = $this->relationIds(
            $user->accessRoutes()->getQuery()->withoutGlobalScopes(),
            'distribution_routes.id',
        );
        $directVehicleIds = $this->relationIds(
            $user->accessVehicles()->getQuery()->withoutGlobalScopes(),
            'vehicles.id',
        );
        $directWarehouseIds = $this->relationIds(
            $user->accessWarehouses()->getQuery()->withoutGlobalScopes(),
            'warehouses.id',
        );

        $employee = Employee::withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->first(['id']);

        $employeeId = $employee?->getKey();

        if (in_array(UserRole::SUPERVISOR, $roles, true)) {
            $initialVehicleIds = $this->ids(array_merge(
                $directVehicleIds,
                Warehouse::withoutGlobalScopes()
                    ->whereIn('id', $directWarehouseIds)
                    ->whereNotNull('vehicle_id')
                    ->pluck('vehicle_id')
                    ->all(),
            ));

            $routeIds = $this->ids(array_merge(
                $directRouteIds,
                DistributionRoute::withoutGlobalScopes()
                    ->whereIn('area_id', $directAreaIds)
                    ->pluck('id')
                    ->all(),
                DistributionRoute::withoutGlobalScopes()
                    ->whereIn('vehicle_id', $initialVehicleIds)
                    ->pluck('id')
                    ->all(),
            ));

            $areaIds = $this->ids(array_merge(
                $directAreaIds,
                DistributionRoute::withoutGlobalScopes()
                    ->whereIn('id', $routeIds)
                    ->pluck('area_id')
                    ->all(),
            ));

            $vehicleIds = $this->ids(array_merge(
                $initialVehicleIds,
                DistributionRoute::withoutGlobalScopes()
                    ->whereIn('id', $routeIds)
                    ->whereNotNull('vehicle_id')
                    ->pluck('vehicle_id')
                    ->all(),
            ));

            $warehouseIds = $this->ids(array_merge(
                $directWarehouseIds,
                Warehouse::withoutGlobalScopes()
                    ->whereIn('vehicle_id', $vehicleIds)
                    ->pluck('id')
                    ->all(),
                $this->operationalWarehouseIds($routeIds),
            ));

            $employeeIds = $this->routeEmployeeIds($routeIds, $employeeId);

            return $this->cache[$key] = new EffectiveAccessScope(
                role: $role,
                unrestricted: false,
                areaIds: $areaIds,
                routeIds: $routeIds,
                vehicleIds: $vehicleIds,
                warehouseIds: $warehouseIds,
                employeeIds: $employeeIds,
                employeeId: $employeeId,
            );
        }

        if (in_array(UserRole::WAREHOUSE_KEEPER, $roles, true)) {
            $warehouseIds = $directWarehouseIds;
            $vehicleIds = $this->ids(Warehouse::withoutGlobalScopes()
                ->whereIn('id', $warehouseIds)
                ->whereNotNull('vehicle_id')
                ->pluck('vehicle_id')
                ->all());
            $routeIds = $this->ids(DistributionRoute::withoutGlobalScopes()
                ->whereIn('vehicle_id', $vehicleIds)
                ->pluck('id')
                ->all());
            $areaIds = $this->ids(DistributionRoute::withoutGlobalScopes()
                ->whereIn('id', $routeIds)
                ->pluck('area_id')
                ->all());

            return $this->cache[$key] = new EffectiveAccessScope(
                role: $role,
                unrestricted: false,
                areaIds: $areaIds,
                routeIds: $routeIds,
                vehicleIds: $vehicleIds,
                warehouseIds: $warehouseIds,
                employeeIds: $this->routeEmployeeIds($routeIds, $employeeId),
                employeeId: $employeeId,
            );
        }

        $isDriver = in_array(UserRole::DRIVER, $roles, true);
        $isSalesRepresentative = in_array(
            UserRole::SALES_REPRESENTATIVE,
            $roles,
            true,
        );

        if ($isDriver || $isSalesRepresentative) {
            if ($employeeId === null) {
                return $this->cache[$key] = new EffectiveAccessScope(
                    role: $role,
                    unrestricted: false,
                );
            }

            $routeIds = $this->ids(DistributionRoute::withoutGlobalScopes()
                ->where(function (Builder $query) use (
                    $employeeId,
                    $isDriver,
                    $isSalesRepresentative,
                ): void {
                    if ($isDriver) {
                        $query->where('driver_id', $employeeId);
                    }

                    if ($isSalesRepresentative) {
                        $method = $isDriver ? 'orWhere' : 'where';
                        $query->{$method}('sales_representative_id', $employeeId);
                    }
                })
                ->pluck('id')
                ->all());
            $areaIds = $this->ids(DistributionRoute::withoutGlobalScopes()
                ->whereIn('id', $routeIds)
                ->pluck('area_id')
                ->all());
            $vehicleIds = $this->ids(DistributionRoute::withoutGlobalScopes()
                ->whereIn('id', $routeIds)
                ->whereNotNull('vehicle_id')
                ->pluck('vehicle_id')
                ->all());
            $warehouseIds = $this->ids(array_merge(
                Warehouse::withoutGlobalScopes()
                    ->whereIn('vehicle_id', $vehicleIds)
                    ->pluck('id')
                    ->all(),
                $this->operationalWarehouseIds($routeIds),
            ));

            return $this->cache[$key] = new EffectiveAccessScope(
                role: $role,
                unrestricted: false,
                areaIds: $areaIds,
                routeIds: $routeIds,
                vehicleIds: $vehicleIds,
                warehouseIds: $warehouseIds,
                employeeIds: $this->routeEmployeeIds($routeIds, $employeeId),
                employeeId: $employeeId,
            );
        }

        return $this->cache[$key] = new EffectiveAccessScope(
            role: $role,
            unrestricted: false,
        );
    }

    public function forget(?User $user = null): void
    {
        if ($user === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[(int) $user->getKey()]);
    }

    private function scopeCustomers(Builder $query, EffectiveAccessScope $scope): Builder
    {
        if ($scope->routeIds === [] && $scope->areaIds === []) {
            return $this->deny($query);
        }

        return $query->where(function (Builder $query) use ($scope): void {
            if ($scope->routeIds !== []) {
                $query->whereIn($query->getModel()->qualifyColumn('route_id'), $scope->routeIds);
            }

            if ($scope->areaIds !== []) {
                $method = $scope->routeIds === [] ? 'whereIn' : 'orWhereIn';
                $query->{$method}($query->getModel()->qualifyColumn('area_id'), $scope->areaIds);
            }
        });
    }

    private function scopeStockMovements(Builder $query, EffectiveAccessScope $scope): Builder
    {
        if ($scope->warehouseIds === []) {
            return $this->deny($query);
        }

        return $query->where(function (Builder $query) use ($scope): void {
            $query
                ->whereIn($query->getModel()->qualifyColumn('from_warehouse_id'), $scope->warehouseIds)
                ->orWhereIn($query->getModel()->qualifyColumn('to_warehouse_id'), $scope->warehouseIds);
        });
    }

    private function scopeVehicleLoads(Builder $query, EffectiveAccessScope $scope): Builder
    {
        if ($scope->role === UserRole::WAREHOUSE_KEEPER) {
            if ($scope->warehouseIds === []) {
                return $this->deny($query);
            }

            return $query->where(function (Builder $query) use ($scope): void {
                $query
                    ->whereIn($query->getModel()->qualifyColumn('from_warehouse_id'), $scope->warehouseIds)
                    ->orWhereIn($query->getModel()->qualifyColumn('to_warehouse_id'), $scope->warehouseIds);
            });
        }

        return $this->scopeOperationalRecords($query, $scope);
    }

    private function scopeOperationalRecords(Builder $query, EffectiveAccessScope $scope): Builder
    {
        $table = $query->getModel()->getTable();
        $conditions = [];

        foreach ($this->modelVisibilityColumns($scope) as $column => $ids) {
            if ($ids !== [] && $this->modelHasColumn($query->getModel(), $column)) {
                $conditions[$column] = $ids;
            }
        }

        $usesCustomerVisibility = $scope->role !== UserRole::WAREHOUSE_KEEPER
            && $this->modelHasColumn($query->getModel(), 'customer_id')
            && ($scope->routeIds !== [] || $scope->areaIds !== []);

        if ($conditions === [] && ! $usesCustomerVisibility) {
            return $this->deny($query);
        }

        return $query->where(function (Builder $query) use (
            $conditions,
            $scope,
            $table,
            $usesCustomerVisibility,
        ): void {
            $first = true;

            foreach ($conditions as $column => $ids) {
                $method = $first ? 'whereIn' : 'orWhereIn';
                $query->{$method}($table.'.'.$column, $ids);
                $first = false;
            }

            if ($usesCustomerVisibility) {
                $method = $first ? 'whereHas' : 'orWhereHas';
                $query->{$method}('customer', function (Builder $customerQuery) use ($scope): void {
                    $this->scopeCustomers($customerQuery, $scope);
                });
            }
        });
    }

    /**
     * Visibility dimensions are intentionally role-aware.
     *
     * A warehouse assignment allows inventory work and validates operational
     * payloads, but for supervisors, drivers, and representatives it must not
     * widen sales visibility. Multiple routes commonly share one main
     * warehouse, so sales-like records are scoped by route, vehicle, team, and
     * customer instead.
     *
     * @return array<string, list<int>>
     */
    private function modelVisibilityColumns(EffectiveAccessScope $scope): array
    {
        if ($scope->role === UserRole::WAREHOUSE_KEEPER) {
            return [
                'warehouse_id' => $scope->warehouseIds,
                'from_warehouse_id' => $scope->warehouseIds,
                'to_warehouse_id' => $scope->warehouseIds,
            ];
        }

        return [
            'route_id' => $scope->routeIds,
            'vehicle_id' => $scope->vehicleIds,
            'sales_representative_id' => $scope->employeeIds,
            'driver_id' => $scope->employeeIds,
        ];
    }

    /** @return array<string, list<int>> */
    private function tableVisibilityColumns(
        string $table,
        EffectiveAccessScope $scope,
    ): array {
        if ($scope->role === UserRole::WAREHOUSE_KEEPER) {
            return match ($table) {
                'sales_invoices', 'sales_returns', 'customer_payments',
                'vehicle_expenses', 'daily_closings' => [
                    'warehouse_id' => $scope->warehouseIds,
                ],
                'vehicle_loads' => [
                    'from_warehouse_id' => $scope->warehouseIds,
                    'to_warehouse_id' => $scope->warehouseIds,
                ],
                default => [],
            };
        }

        return match ($table) {
            'sales_invoices', 'sales_returns', 'customer_payments' => [
                'route_id' => $scope->routeIds,
                'vehicle_id' => $scope->vehicleIds,
                'sales_representative_id' => $scope->employeeIds,
            ],
            'daily_closings' => [
                'route_id' => $scope->routeIds,
                'vehicle_id' => $scope->vehicleIds,
                'sales_representative_id' => $scope->employeeIds,
                'driver_id' => $scope->employeeIds,
            ],
            'vehicle_expenses', 'vehicle_loads' => [
                'route_id' => $scope->routeIds,
                'vehicle_id' => $scope->vehicleIds,
                'sales_representative_id' => $scope->employeeIds,
                'driver_id' => $scope->employeeIds,
            ],
            default => [],
        };
    }

    private function scopeSingleColumn(Builder $query, string $column, array $ids): Builder
    {
        if ($ids === []) {
            return $this->deny($query);
        }

        return $query->whereIn($query->getModel()->qualifyColumn($column), $ids);
    }

    private function allowsVehicleLoadAttributes(EffectiveAccessScope $scope, Model $record): bool
    {
        if ($scope->role === UserRole::WAREHOUSE_KEEPER) {
            return $this->allPresentIdsAllowed([
                $record->getAttribute('from_warehouse_id'),
                $record->getAttribute('to_warehouse_id'),
            ], $scope->warehouseIds);
        }

        return $this->allowsOperationalAttributes($scope, $record);
    }

    private function allowsOperationalAttributes(EffectiveAccessScope $scope, Model $record): bool
    {
        $checks = [
            'route_id' => $scope->routeIds,
            'vehicle_id' => $scope->vehicleIds,
            'warehouse_id' => $scope->warehouseIds,
            'from_warehouse_id' => $scope->warehouseIds,
            'to_warehouse_id' => $scope->warehouseIds,
            'sales_representative_id' => $scope->employeeIds,
            'driver_id' => $scope->employeeIds,
        ];

        foreach ($checks as $column => $allowedIds) {
            $value = $record->getAttribute($column);

            if ($value !== null && ! in_array((int) $value, $allowedIds, true)) {
                return false;
            }
        }

        $customerId = $record->getAttribute('customer_id');

        if ($customerId !== null) {
            $customerAllowed = $this->scopeCustomers(
                Customer::withoutGlobalScopes()->whereKey((int) $customerId),
                $scope,
            )->exists();

            if (! $customerAllowed) {
                return false;
            }
        }

        return true;
    }

    private function allPresentIdsAllowed(array $values, array $allowedIds): bool
    {
        foreach ($values as $value) {
            if ($value !== null && ! in_array((int) $value, $allowedIds, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<int> */
    private function operationalWarehouseIds(array $routeIds): array
    {
        if ($routeIds === []) {
            return [];
        }

        $ids = [];

        foreach ([
            SalesInvoice::class,
            SalesReturn::class,
            CustomerPayment::class,
            VehicleExpense::class,
            DailyClosing::class,
        ] as $model) {
            $ids = array_merge(
                $ids,
                $model::withoutGlobalScopes()
                    ->whereIn('route_id', $routeIds)
                    ->whereNotNull('warehouse_id')
                    ->pluck('warehouse_id')
                    ->all(),
            );
        }

        $loads = VehicleLoad::withoutGlobalScopes()
            ->whereIn('route_id', $routeIds)
            ->get(['from_warehouse_id', 'to_warehouse_id']);

        return $this->ids(array_merge(
            $ids,
            $loads->pluck('from_warehouse_id')->all(),
            $loads->pluck('to_warehouse_id')->all(),
        ));
    }

    private function routeEmployeeIds(array $routeIds, ?int $employeeId): array
    {
        $ids = [];

        if ($routeIds !== []) {
            $routes = DistributionRoute::withoutGlobalScopes()
                ->whereIn('id', $routeIds)
                ->get(['driver_id', 'sales_representative_id']);

            $ids = array_merge(
                $routes->pluck('driver_id')->all(),
                $routes->pluck('sales_representative_id')->all(),
            );
        }

        if ($employeeId !== null) {
            $ids[] = $employeeId;
        }

        return $this->ids($ids);
    }

    private function modelHasColumn(Model $model, string $column): bool
    {
        if ($model instanceof ProfitReportEntry) {
            return in_array($column, [
                'customer_id',
                'warehouse_id',
                'vehicle_id',
                'route_id',
                'sales_representative_id',
            ], true);
        }

        return in_array($column, $model->getFillable(), true)
            || array_key_exists($column, $model->getAttributes());
    }

    /** @return list<int> */
    private function relationIds(Builder $query, string $column): array
    {
        return $this->ids($query->pluck($column)->all());
    }

    private function ids(array $ids): array
    {
        $ids = array_map('intval', array_filter($ids, fn ($id): bool => $id !== null));
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /** @return list<UserRole> */
    private function rolesFor(User $user): array
    {
        return array_values(array_filter(
            UserRole::cases(),
            fn (UserRole $role): bool => $user->hasRole($role->value),
        ));
    }

    /** @return list<string> */
    private function roleValues(User $user): array
    {
        return array_map(
            fn (UserRole $role): string => $role->value,
            $this->rolesFor($user),
        );
    }

    /**
     * @param list<UserRole> $roles
     * @param list<UserRole> $expected
     */
    private function hasAnyRole(array $roles, array $expected): bool
    {
        foreach ($expected as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    private function deny(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
