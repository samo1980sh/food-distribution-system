<?php

namespace App\Services\Api;

use App\Enums\UserRole;
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
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use App\Support\Api\MobileSyncEntityRegistry;
use Illuminate\Database\Eloquent\Model;

class MobileSyncScopeService
{
    public function __construct(
        private readonly AccessScopeService $accessScopeService,
    ) {
    }

    /** @return array<string, mixed> */
    public function snapshot(Model $model): array
    {
        $entity = MobileSyncEntityRegistry::entityForModel($model);

        if ($entity === null) {
            return [];
        }

        if ($model instanceof Product || $model instanceof ProductCategory || $model instanceof Unit) {
            return ['global' => true];
        }

        if ($model instanceof Area) {
            return ['area_id' => (int) $model->getKey()];
        }

        if ($model instanceof Customer) {
            return $this->clean([
                'area_id' => $model->area_id,
                'route_id' => $model->route_id,
            ]);
        }

        if ($model instanceof DistributionRoute) {
            return $this->clean([
                'area_id' => $model->area_id,
                'route_id' => $model->getKey(),
                'vehicle_id' => $model->vehicle_id,
                'employee_ids' => [
                    $model->driver_id,
                    $model->sales_representative_id,
                ],
            ]);
        }

        if ($model instanceof Vehicle) {
            return ['vehicle_id' => (int) $model->getKey()];
        }

        if ($model instanceof Warehouse) {
            return $this->clean([
                'warehouse_id' => $model->getKey(),
                'vehicle_id' => $model->vehicle_id,
            ]);
        }

        if ($model instanceof Employee) {
            return ['employee_id' => (int) $model->getKey()];
        }

        if ($model instanceof StockBalance) {
            return $this->clean([
                'warehouse_id' => $model->warehouse_id,
            ]);
        }

        if ($model instanceof VehicleLoad) {
            return $this->clean([
                'route_id' => $model->route_id,
                'vehicle_id' => $model->vehicle_id,
                'warehouse_ids' => [
                    $model->from_warehouse_id,
                    $model->to_warehouse_id,
                ],
                'employee_ids' => [
                    $model->driver_id,
                    $model->sales_representative_id,
                ],
            ]);
        }

        if ($model instanceof SalesInvoice
            || $model instanceof CustomerPayment
            || $model instanceof SalesReturn
            || $model instanceof VehicleExpense
            || $model instanceof DailyClosing) {
            $customer = $model->getAttribute('customer_id') === null
                ? null
                : Customer::withoutGlobalScopes()->find(
                    (int) $model->getAttribute('customer_id'),
                    ['id', 'area_id', 'route_id'],
                );

            return $this->clean([
                'route_id' => $model->getAttribute('route_id'),
                'vehicle_id' => $model->getAttribute('vehicle_id'),
                'warehouse_id' => $model->getAttribute('warehouse_id'),
                'employee_ids' => [
                    $model->getAttribute('driver_id'),
                    $model->getAttribute('sales_representative_id'),
                ],
                'customer_area_id' => $customer?->area_id,
                'customer_route_id' => $customer?->route_id,
            ]);
        }

        return [];
    }

    /** @param array<string, mixed> $snapshot */
    public function allows(User $user, string $entity, array $snapshot): bool
    {
        $scope = $this->accessScopeService->for($user);

        if ($scope->unrestricted) {
            return true;
        }

        if (($snapshot['global'] ?? false) === true) {
            return true;
        }

        return match ($entity) {
            'areas' => $this->contains($scope->areaIds, $snapshot['area_id'] ?? null),
            'routes' => $this->contains($scope->routeIds, $snapshot['route_id'] ?? null),
            'vehicles' => $this->contains($scope->vehicleIds, $snapshot['vehicle_id'] ?? null),
            'warehouses' => $this->contains($scope->warehouseIds, $snapshot['warehouse_id'] ?? null),
            'employees' => $this->contains($scope->employeeIds, $snapshot['employee_id'] ?? null),
            'product_categories', 'units', 'products' => true,
            'customers' => $this->contains($scope->routeIds, $snapshot['route_id'] ?? null)
                || $this->contains($scope->areaIds, $snapshot['area_id'] ?? null),
            'stock_balances' => $this->contains($scope->warehouseIds, $snapshot['warehouse_id'] ?? null),
            'vehicle_loads' => $scope->role === UserRole::WAREHOUSE_KEEPER
                ? $this->intersects($scope->warehouseIds, $snapshot['warehouse_ids'] ?? [])
                : $this->matchesOperationalScope($scope, $snapshot),
            'sales_invoices', 'customer_payments', 'sales_returns', 'vehicle_expenses', 'daily_closings' =>
                $scope->role === UserRole::WAREHOUSE_KEEPER
                    ? $this->contains($scope->warehouseIds, $snapshot['warehouse_id'] ?? null)
                    : $this->matchesOperationalScope($scope, $snapshot),
            default => false,
        };
    }

    /** @param array<string, mixed> $snapshot */
    private function matchesOperationalScope(object $scope, array $snapshot): bool
    {
        return $this->contains($scope->routeIds, $snapshot['route_id'] ?? null)
            || $this->contains($scope->vehicleIds, $snapshot['vehicle_id'] ?? null)
            || $this->intersects($scope->employeeIds, $snapshot['employee_ids'] ?? [])
            || $this->contains($scope->routeIds, $snapshot['customer_route_id'] ?? null)
            || $this->contains($scope->areaIds, $snapshot['customer_area_id'] ?? null);
    }

    /** @param list<int> $allowed */
    private function contains(array $allowed, mixed $value): bool
    {
        return $value !== null && in_array((int) $value, $allowed, true);
    }

    /** @param list<int> $allowed */
    private function intersects(array $allowed, mixed $values): bool
    {
        foreach ((array) $values as $value) {
            if ($this->contains($allowed, $value)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $snapshot
     *  @return array<string, mixed>
     */
    private function clean(array $snapshot): array
    {
        foreach ($snapshot as $key => $value) {
            if (is_array($value)) {
                $value = array_values(array_unique(array_map(
                    'intval',
                    array_filter($value, static fn (mixed $id): bool => $id !== null && $id !== ''),
                )));

                if ($value === []) {
                    unset($snapshot[$key]);
                } else {
                    $snapshot[$key] = $value;
                }

                continue;
            }

            if ($value === null || $value === '') {
                unset($snapshot[$key]);
            } else {
                $snapshot[$key] = (int) $value;
            }
        }

        return $snapshot;
    }
}
