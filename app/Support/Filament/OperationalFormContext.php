<?php

namespace App\Support\Filament;

use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\SalesInvoice;
use App\Models\Warehouse;

final class OperationalFormContext
{
    /** @return array{route_id: ?int, vehicle_id: ?int, warehouse_id: ?int, sales_representative_id: ?int} */
    public static function forCustomer(mixed $customerId): array
    {
        $customer = self::customer($customerId);
        $route = self::route($customer?->route_id);

        return [
            'route_id' => self::id($route?->id),
            'vehicle_id' => self::id($route?->vehicle_id),
            'warehouse_id' => self::vehicleWarehouseId($route?->vehicle_id),
            'sales_representative_id' => self::id($route?->sales_representative_id),
        ];
    }

    /** @return array{vehicle_id: ?int, warehouse_id: ?int, driver_id: ?int, sales_representative_id: ?int} */
    public static function forRoute(mixed $routeId): array
    {
        $route = self::route($routeId);

        return [
            'vehicle_id' => self::id($route?->vehicle_id),
            'warehouse_id' => self::vehicleWarehouseId($route?->vehicle_id),
            'driver_id' => self::id($route?->driver_id),
            'sales_representative_id' => self::id($route?->sales_representative_id),
        ];
    }

    /** @return array{customer_id: ?int, vehicle_id: ?int, route_id: ?int, warehouse_id: ?int, sales_representative_id: ?int} */
    public static function forInvoice(mixed $invoiceId): array
    {
        $invoice = self::invoice($invoiceId);

        return [
            'customer_id' => self::id($invoice?->customer_id),
            'vehicle_id' => self::id($invoice?->vehicle_id),
            'route_id' => self::id($invoice?->route_id),
            'warehouse_id' => self::id($invoice?->warehouse_id),
            'sales_representative_id' => self::id($invoice?->sales_representative_id),
        ];
    }

    public static function vehicleWarehouseId(mixed $vehicleId): ?int
    {
        $vehicleId = self::id($vehicleId);

        if ($vehicleId === null) {
            return null;
        }

        return self::id(
            Warehouse::query()
                ->where('type', 'vehicle')
                ->where('vehicle_id', $vehicleId)
                ->value('id'),
        );
    }

    private static function customer(mixed $id): ?Customer
    {
        $id = self::id($id);

        return $id === null
            ? null
            : Customer::query()->find($id);
    }

    private static function route(mixed $id): ?DistributionRoute
    {
        $id = self::id($id);

        return $id === null
            ? null
            : DistributionRoute::query()->find($id);
    }

    private static function invoice(mixed $id): ?SalesInvoice
    {
        $id = self::id($id);

        return $id === null
            ? null
            : SalesInvoice::query()->find($id);
    }

    private static function id(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function __construct()
    {
    }
}
