<?php

namespace App\Support\Api;

use App\Enums\PermissionName;
use App\Http\Resources\Api\V1\Operational\AreaResource;
use App\Http\Resources\Api\V1\Operational\CustomerPaymentResource;
use App\Http\Resources\Api\V1\Operational\CustomerResource;
use App\Http\Resources\Api\V1\Operational\DailyClosingResource;
use App\Http\Resources\Api\V1\Operational\DriverDeliveryResource;
use App\Http\Resources\Api\V1\Operational\DriverJourneyResource;
use App\Http\Resources\Api\V1\Operational\EmployeeSummaryResource;
use App\Http\Resources\Api\V1\Operational\ProductCategoryResource;
use App\Http\Resources\Api\V1\Operational\ProductResource;
use App\Http\Resources\Api\V1\Operational\RouteResource;
use App\Http\Resources\Api\V1\Operational\SalesInvoiceResource;
use App\Http\Resources\Api\V1\Operational\SalesJourneyResource;
use App\Http\Resources\Api\V1\Operational\SalesReturnResource;
use App\Http\Resources\Api\V1\Operational\SalesVisitResource;
use App\Http\Resources\Api\V1\Operational\StockBalanceResource;
use App\Http\Resources\Api\V1\Operational\UnitResource;
use App\Http\Resources\Api\V1\Operational\VehicleExpenseResource;
use App\Http\Resources\Api\V1\Operational\VehicleLoadResource;
use App\Http\Resources\Api\V1\Operational\VehicleResource;
use App\Http\Resources\Api\V1\Operational\WarehouseResource;
use App\Models\Area;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\DriverDelivery;
use App\Models\DriverJourney;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesInvoice;
use App\Models\SalesJourney;
use App\Models\SalesReturn;
use App\Models\SalesVisit;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

final class MobileSyncEntityRegistry
{
    public const VERSION = 8;

    private const LEGACY_DRIVER_ENTITIES = [
        'driver_journeys',
        'driver_deliveries',
    ];

    /**
     * @return array<string, array{
     *   model: class-string<Model>,
     *   resource: class-string<JsonResource>,
     *   permissions: list<string>,
     *   relations: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            'areas' => [
                'model' => Area::class,
                'resource' => AreaResource::class,
                'permissions' => [
                    PermissionName::AREAS_VIEW->value,
                    PermissionName::DISTRIBUTION_ROUTES_VIEW->value,
                    PermissionName::CUSTOMERS_VIEW->value,
                ],
                'relations' => [],
            ],
            'routes' => [
                'model' => DistributionRoute::class,
                'resource' => RouteResource::class,
                'permissions' => [PermissionName::DISTRIBUTION_ROUTES_VIEW->value],
                'relations' => ['area', 'vehicle.warehouse', 'driver', 'salesRepresentative'],
            ],
            'vehicles' => [
                'model' => Vehicle::class,
                'resource' => VehicleResource::class,
                'permissions' => [PermissionName::VEHICLES_VIEW->value],
                'relations' => ['warehouse'],
            ],
            'warehouses' => [
                'model' => Warehouse::class,
                'resource' => WarehouseResource::class,
                'permissions' => [PermissionName::WAREHOUSES_VIEW->value],
                'relations' => ['vehicle'],
            ],
            'employees' => [
                'model' => Employee::class,
                'resource' => EmployeeSummaryResource::class,
                'permissions' => [
                    PermissionName::EMPLOYEES_VIEW->value,
                    PermissionName::DISTRIBUTION_ROUTES_VIEW->value,
                    PermissionName::VEHICLE_LOADS_VIEW->value,
                    PermissionName::VEHICLE_EXPENSES_VIEW->value,
                ],
                'relations' => [],
            ],
            'product_categories' => [
                'model' => ProductCategory::class,
                'resource' => ProductCategoryResource::class,
                'permissions' => [
                    PermissionName::PRODUCT_CATEGORIES_VIEW->value,
                    PermissionName::PRODUCTS_VIEW->value,
                ],
                'relations' => [],
            ],
            'units' => [
                'model' => Unit::class,
                'resource' => UnitResource::class,
                'permissions' => [
                    PermissionName::UNITS_VIEW->value,
                    PermissionName::PRODUCTS_VIEW->value,
                ],
                'relations' => [],
            ],
            'products' => [
                'model' => Product::class,
                'resource' => ProductResource::class,
                'permissions' => [PermissionName::PRODUCTS_VIEW->value],
                'relations' => ['category', 'unit'],
            ],
            'customers' => [
                'model' => Customer::class,
                'resource' => CustomerResource::class,
                'permissions' => [PermissionName::CUSTOMERS_VIEW->value],
                'relations' => ['area', 'route'],
            ],
            'stock_balances' => [
                'model' => StockBalance::class,
                'resource' => StockBalanceResource::class,
                'permissions' => [PermissionName::STOCK_BALANCES_VIEW->value],
                'relations' => ['warehouse.vehicle', 'product.category', 'product.unit'],
            ],
            'vehicle_loads' => [
                'model' => VehicleLoad::class,
                'resource' => VehicleLoadResource::class,
                'permissions' => [PermissionName::VEHICLE_LOADS_VIEW->value],
                'relations' => [
                    'vehicle.warehouse',
                    'route',
                    'driver',
                    'salesRepresentative',
                    'fromWarehouse.vehicle',
                    'toWarehouse.vehicle',
                    'handoverUser',
                    'items.product.category',
                    'items.product.unit',
                ],
            ],
            'driver_journeys' => [
                'model' => DriverJourney::class,
                'resource' => DriverJourneyResource::class,
                'permissions' => [PermissionName::DRIVER_JOURNEYS_VIEW->value],
                'relations' => [
                    'route.area',
                    'route.vehicle.warehouse',
                    'route.driver',
                    'route.salesRepresentative',
                    'vehicle.warehouse',
                    'warehouse.vehicle',
                    'driver',
                    'salesRepresentative',
                    'deliveries.salesInvoice',
                    'deliveries.customer',
                    'deliveries.items.product.category',
                    'deliveries.items.product.unit',
                ],
            ],
            'driver_deliveries' => [
                'model' => DriverDelivery::class,
                'resource' => DriverDeliveryResource::class,
                'permissions' => [PermissionName::DRIVER_DELIVERIES_VIEW->value],
                'relations' => [
                    'journey',
                    'salesInvoice',
                    'customer',
                    'salesRepresentative',
                    'items.product.category',
                    'items.product.unit',
                ],
            ],
            'sales_journeys' => [
                'model' => SalesJourney::class,
                'resource' => SalesJourneyResource::class,
                'permissions' => [PermissionName::SALES_JOURNEYS_VIEW->value],
                'relations' => [
                    'route.area',
                    'route.vehicle.warehouse',
                    'vehicle.warehouse',
                    'warehouse.vehicle',
                    'salesRepresentative',
                    'driver',
                    'visits.customer.area',
                    'visits.customer.route',
                    'visits.invoices',
                    'visits.payments',
                    'visits.returns',
                ],
            ],
            'sales_visits' => [
                'model' => SalesVisit::class,
                'resource' => SalesVisitResource::class,
                'permissions' => [PermissionName::SALES_VISITS_VIEW->value],
                'relations' => [
                    'journey',
                    'customer.area',
                    'customer.route',
                    'route.area',
                    'vehicle.warehouse',
                    'warehouse.vehicle',
                    'salesRepresentative',
                    'invoices',
                    'payments',
                    'returns',
                ],
            ],
            'sales_invoices' => [
                'model' => SalesInvoice::class,
                'resource' => SalesInvoiceResource::class,
                'permissions' => [PermissionName::SALES_INVOICES_VIEW->value],
                'relations' => [
                    'customer.area',
                    'customer.route',
                    'vehicle.warehouse',
                    'route',
                    'warehouse.vehicle',
                    'salesRepresentative',
                    'items.product.category',
                    'items.product.unit',
                ],
            ],
            'customer_payments' => [
                'model' => CustomerPayment::class,
                'resource' => CustomerPaymentResource::class,
                'permissions' => [PermissionName::CUSTOMER_PAYMENTS_VIEW->value],
                'relations' => [
                    'customer.area',
                    'customer.route',
                    'salesInvoice',
                    'vehicle.warehouse',
                    'route',
                    'warehouse.vehicle',
                    'salesRepresentative',
                ],
            ],
            'sales_returns' => [
                'model' => SalesReturn::class,
                'resource' => SalesReturnResource::class,
                'permissions' => [PermissionName::SALES_RETURNS_VIEW->value],
                'relations' => [
                    'customer.area',
                    'customer.route',
                    'salesInvoice',
                    'vehicle.warehouse',
                    'route',
                    'warehouse.vehicle',
                    'salesRepresentative',
                    'items.product.category',
                    'items.product.unit',
                ],
            ],
            'vehicle_expenses' => [
                'model' => VehicleExpense::class,
                'resource' => VehicleExpenseResource::class,
                'permissions' => [PermissionName::VEHICLE_EXPENSES_VIEW->value],
                'relations' => [
                    'vehicle.warehouse',
                    'route',
                    'warehouse.vehicle',
                    'driver',
                    'salesRepresentative',
                ],
            ],
            'daily_closings' => [
                'model' => DailyClosing::class,
                'resource' => DailyClosingResource::class,
                'permissions' => [PermissionName::DAILY_CLOSINGS_VIEW->value],
                'relations' => [
                    'vehicle.warehouse',
                    'route',
                    'warehouse.vehicle',
                    'driver',
                    'salesRepresentative',
                    'inventorySubmitter',
                    'cashSubmitter',
                    'items.product.category',
                    'items.product.unit',
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function entities(): array
    {
        return array_keys(self::definitions());
    }

    /** @return list<string> */
    public static function entitiesFor(User $user): array
    {
        if (! MobileAppAccess::usesUnifiedRepresentativeWorkspace($user)) {
            return self::entities();
        }

        return array_values(array_diff(
            self::entities(),
            self::LEGACY_DRIVER_ENTITIES,
        ));
    }

    public static function isActiveFor(User $user, string $entity): bool
    {
        return in_array($entity, self::entitiesFor($user), true);
    }

    /** @return list<class-string<Model>> */
    public static function models(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $definition): string => $definition['model'],
            self::definitions(),
        )));
    }

    /** @return array{model: class-string<Model>, resource: class-string<JsonResource>, permissions: list<string>, relations: list<string>} */
    public static function definition(string $entity): array
    {
        $definition = self::definitions()[$entity] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown mobile sync entity [{$entity}].");
        }

        return $definition;
    }

    public static function entityForModel(Model|string $model): ?string
    {
        $modelClass = is_string($model) ? $model : $model::class;

        foreach (self::definitions() as $entity => $definition) {
            if ($definition['model'] === $modelClass) {
                return $entity;
            }
        }

        return null;
    }
}
