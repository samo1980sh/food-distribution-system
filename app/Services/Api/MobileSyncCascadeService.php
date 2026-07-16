<?php

namespace App\Services\Api;

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
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Support\Api\MobileSyncEntityRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MobileSyncCascadeService
{
    public function __construct(
        private readonly MobileSyncChangeRecorder $recorder,
        private readonly MobileSyncScopeService $scopeService,
    ) {
    }

    /**
     * @return array{
     *   deletions: list<array{entity: string, record_id: int, scope: array<string, mixed>}>,
     *   refresh: array<class-string<Model>, list<int>>
     * }
     */
    public function capture(Model $model): array
    {
        $plan = [
            'deletions' => [],
            'refresh' => [],
        ];
        $visited = [$model::class.':'.(int) $model->getKey() => true];

        $this->collectDependents($model, $plan, $visited);

        foreach ($plan['refresh'] as $modelClass => $ids) {
            $plan['refresh'][$modelClass] = array_values(array_unique($ids));
        }

        return $plan;
    }

    /** @param array{deletions: list<array{entity: string, record_id: int, scope: array<string, mixed>}>, refresh: array<class-string<Model>, list<int>>} $plan */
    public function record(array $plan): void
    {
        foreach ($plan['deletions'] as $deletion) {
            $this->recorder->deletionByIdentity(
                $deletion['entity'],
                $deletion['record_id'],
                $deletion['scope'],
            );
        }

        foreach ($plan['refresh'] as $modelClass => $ids) {
            if ($ids === []) {
                continue;
            }

            $modelClass::withoutGlobalScopes()
                ->whereKey($ids)
                ->get()
                ->each(function (Model $record): void {
                    $this->recorder->upsert($record);
                });
        }
    }

    /**
     * @param array{deletions: list<array{entity: string, record_id: int, scope: array<string, mixed>}>, refresh: array<class-string<Model>, list<int>>} $plan
     * @param array<string, bool> $visited
     */
    private function collectDependents(Model $model, array &$plan, array &$visited): void
    {
        if ($model instanceof Area) {
            $this->refreshWhere($plan, Customer::class, 'area_id', $model->getKey());
            $this->cascadeQuery(
                DistributionRoute::withoutGlobalScopes()->where('area_id', $model->getKey()),
                $plan,
                $visited,
            );

            return;
        }

        if ($model instanceof DistributionRoute) {
            $this->refreshWhere($plan, Customer::class, 'route_id', $model->getKey());
            $this->refreshWhere($plan, VehicleLoad::class, 'route_id', $model->getKey());
            $this->refreshWhere($plan, SalesInvoice::class, 'route_id', $model->getKey());
            $this->refreshWhere($plan, CustomerPayment::class, 'route_id', $model->getKey());
            $this->refreshWhere($plan, SalesReturn::class, 'route_id', $model->getKey());
            $this->refreshWhere($plan, VehicleExpense::class, 'route_id', $model->getKey());
            $this->refreshWhere($plan, DailyClosing::class, 'route_id', $model->getKey());

            return;
        }

        if ($model instanceof Vehicle) {
            $this->refreshWhere($plan, DistributionRoute::class, 'vehicle_id', $model->getKey());
            $this->refreshWhere($plan, Warehouse::class, 'vehicle_id', $model->getKey());
            $this->refreshWhere($plan, SalesInvoice::class, 'vehicle_id', $model->getKey());
            $this->refreshWhere($plan, CustomerPayment::class, 'vehicle_id', $model->getKey());
            $this->refreshWhere($plan, SalesReturn::class, 'vehicle_id', $model->getKey());
            $this->refreshWhere($plan, DailyClosing::class, 'vehicle_id', $model->getKey());
            $this->cascadeQuery(
                VehicleLoad::withoutGlobalScopes()->where('vehicle_id', $model->getKey()),
                $plan,
                $visited,
            );
            $this->cascadeQuery(
                VehicleExpense::withoutGlobalScopes()->where('vehicle_id', $model->getKey()),
                $plan,
                $visited,
            );

            return;
        }

        if ($model instanceof Warehouse) {
            $this->refreshWhere($plan, CustomerPayment::class, 'warehouse_id', $model->getKey());
            $this->cascadeQuery(
                StockBalance::withoutGlobalScopes()->where('warehouse_id', $model->getKey()),
                $plan,
                $visited,
            );
            $this->cascadeQuery(
                VehicleLoad::withoutGlobalScopes()->where(function (Builder $query) use ($model): void {
                    $query
                        ->where('from_warehouse_id', $model->getKey())
                        ->orWhere('to_warehouse_id', $model->getKey());
                }),
                $plan,
                $visited,
            );
            $this->cascadeQuery(
                SalesInvoice::withoutGlobalScopes()->where('warehouse_id', $model->getKey()),
                $plan,
                $visited,
            );
            $this->cascadeQuery(
                SalesReturn::withoutGlobalScopes()->where('warehouse_id', $model->getKey()),
                $plan,
                $visited,
            );
            $this->cascadeQuery(
                VehicleExpense::withoutGlobalScopes()->where('warehouse_id', $model->getKey()),
                $plan,
                $visited,
            );
            $this->cascadeQuery(
                DailyClosing::withoutGlobalScopes()->where('warehouse_id', $model->getKey()),
                $plan,
                $visited,
            );

            return;
        }

        if ($model instanceof Employee) {
            $employeeId = $model->getKey();

            $this->refreshQuery($plan, DistributionRoute::withoutGlobalScopes()
                ->where('driver_id', $employeeId)
                ->orWhere('sales_representative_id', $employeeId));
            $this->refreshQuery($plan, VehicleLoad::withoutGlobalScopes()
                ->where('driver_id', $employeeId)
                ->orWhere('sales_representative_id', $employeeId));
            $this->refreshWhere($plan, SalesInvoice::class, 'sales_representative_id', $employeeId);
            $this->refreshWhere($plan, CustomerPayment::class, 'sales_representative_id', $employeeId);
            $this->refreshWhere($plan, SalesReturn::class, 'sales_representative_id', $employeeId);
            $this->refreshQuery($plan, VehicleExpense::withoutGlobalScopes()
                ->where('driver_id', $employeeId)
                ->orWhere('sales_representative_id', $employeeId));
            $this->refreshWhere($plan, DailyClosing::class, 'sales_representative_id', $employeeId);

            return;
        }

        if ($model instanceof ProductCategory) {
            $this->refreshWhere($plan, ProductCategory::class, 'parent_id', $model->getKey());
            $this->refreshWhere($plan, Product::class, 'category_id', $model->getKey());

            return;
        }

        if ($model instanceof Unit) {
            $this->refreshWhere($plan, Product::class, 'unit_id', $model->getKey());

            return;
        }

        if ($model instanceof Product) {
            $this->cascadeQuery(
                StockBalance::withoutGlobalScopes()->where('product_id', $model->getKey()),
                $plan,
                $visited,
            );
            $this->refreshIds(
                $plan,
                VehicleLoad::class,
                DB::table('vehicle_load_items')
                    ->where('product_id', $model->getKey())
                    ->pluck('vehicle_load_id')
                    ->all(),
            );
            $this->refreshIds(
                $plan,
                SalesInvoice::class,
                DB::table('sales_invoice_items')
                    ->where('product_id', $model->getKey())
                    ->pluck('sales_invoice_id')
                    ->all(),
            );
            $this->refreshIds(
                $plan,
                SalesReturn::class,
                DB::table('sales_return_items')
                    ->where('product_id', $model->getKey())
                    ->pluck('sales_return_id')
                    ->all(),
            );
            $this->refreshIds(
                $plan,
                DailyClosing::class,
                DB::table('daily_closing_items')
                    ->where('product_id', $model->getKey())
                    ->pluck('daily_closing_id')
                    ->all(),
            );

            return;
        }

        if ($model instanceof Customer) {
            $this->cascadeQuery(
                SalesInvoice::withoutGlobalScopes()->where('customer_id', $model->getKey()),
                $plan,
                $visited,
            );
            $this->cascadeQuery(
                CustomerPayment::withoutGlobalScopes()->where('customer_id', $model->getKey()),
                $plan,
                $visited,
            );
            $this->cascadeQuery(
                SalesReturn::withoutGlobalScopes()->where('customer_id', $model->getKey()),
                $plan,
                $visited,
            );

            return;
        }

        if ($model instanceof SalesInvoice) {
            $this->refreshWhere($plan, CustomerPayment::class, 'sales_invoice_id', $model->getKey());
            $this->refreshWhere($plan, SalesReturn::class, 'sales_invoice_id', $model->getKey());
        }
    }

    /**
     * @param Builder<Model> $query
     * @param array{deletions: list<array{entity: string, record_id: int, scope: array<string, mixed>}>, refresh: array<class-string<Model>, list<int>>} $plan
     * @param array<string, bool> $visited
     */
    private function cascadeQuery(Builder $query, array &$plan, array &$visited): void
    {
        $query->get()->each(function (Model $record) use (&$plan, &$visited): void {
            $key = $record::class.':'.(int) $record->getKey();

            if (isset($visited[$key])) {
                return;
            }

            $visited[$key] = true;
            $this->collectDependents($record, $plan, $visited);

            $entity = MobileSyncEntityRegistry::entityForModel($record);

            if ($entity === null) {
                return;
            }

            $plan['deletions'][] = [
                'entity' => $entity,
                'record_id' => (int) $record->getKey(),
                'scope' => $this->scopeService->snapshot($record),
            ];
        });
    }

    /** @param array{deletions: list<array{entity: string, record_id: int, scope: array<string, mixed>}>, refresh: array<class-string<Model>, list<int>>} $plan */
    private function refreshWhere(
        array &$plan,
        string $modelClass,
        string $column,
        mixed $value,
    ): void {
        $this->refreshQuery(
            $plan,
            $modelClass::withoutGlobalScopes()->where($column, $value),
        );
    }

    /**
     * @param array{deletions: list<array{entity: string, record_id: int, scope: array<string, mixed>}>, refresh: array<class-string<Model>, list<int>>} $plan
     * @param Builder<Model> $query
     */
    private function refreshQuery(array &$plan, Builder $query): void
    {
        $modelClass = $query->getModel()::class;
        $this->refreshIds($plan, $modelClass, $query->pluck('id')->all());
    }

    /**
     * @param array{deletions: list<array{entity: string, record_id: int, scope: array<string, mixed>}>, refresh: array<class-string<Model>, list<int>>} $plan
     * @param class-string<Model> $modelClass
     * @param array<int, mixed> $ids
     */
    private function refreshIds(array &$plan, string $modelClass, array $ids): void
    {
        foreach ($ids as $id) {
            if ($id !== null && $id !== '') {
                $plan['refresh'][$modelClass][] = (int) $id;
            }
        }
    }
}
