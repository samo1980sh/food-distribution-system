<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MasterDataDeletionGuard
{
    /**
     * Application-level preflight checks. Database RESTRICT foreign keys remain the final protection layer.
     * Access-scope pivot tables are intentionally not blockers because their foreign keys are cascade-on-delete.
     *
     * @var array<class-string<Model>, list<array{table: string, column: string, label: string}>>
     */
    private const BLOCKERS = [
        Area::class => [
            ['table' => 'distribution_routes', 'column' => 'area_id', 'label' => 'مسارات توزيع مرتبطة'],
            ['table' => 'customers', 'column' => 'area_id', 'label' => 'عملاء مرتبطين'],
            ['table' => 'sales_visits', 'column' => 'area_id', 'label' => 'زيارات مبيعات تاريخية'],
        ],
        Unit::class => [
            ['table' => 'products', 'column' => 'unit_id', 'label' => 'منتجات مرتبطة بوحدة القياس'],
        ],
        ProductCategory::class => [
            ['table' => 'product_categories', 'column' => 'parent_id', 'label' => 'تصنيفات فرعية مرتبطة'],
            ['table' => 'products', 'column' => 'category_id', 'label' => 'منتجات مرتبطة بالتصنيف'],
        ],
        Product::class => [
            ['table' => 'stock_balances', 'column' => 'product_id', 'label' => 'أرصدة مخزون مرتبطة'],
            ['table' => 'stock_movements', 'column' => 'product_id', 'label' => 'حركات مخزون تاريخية'],
            ['table' => 'vehicle_load_items', 'column' => 'product_id', 'label' => 'بنود تحميل سيارات تاريخية'],
            ['table' => 'sales_invoice_items', 'column' => 'product_id', 'label' => 'بنود فواتير مبيعات تاريخية'],
            ['table' => 'sales_return_items', 'column' => 'product_id', 'label' => 'بنود مرتجعات تاريخية'],
            ['table' => 'daily_closing_items', 'column' => 'product_id', 'label' => 'بنود إقفال يومي تاريخية'],
            ['table' => 'driver_delivery_items', 'column' => 'product_id', 'label' => 'بنود تسليم ميداني تاريخية'],
        ],
        Vehicle::class => [
            ['table' => 'warehouses', 'column' => 'vehicle_id', 'label' => 'مستودع سيارة مرتبط'],
            ['table' => 'distribution_routes', 'column' => 'vehicle_id', 'label' => 'مسارات توزيع مرتبطة'],
            ['table' => 'vehicle_loads', 'column' => 'vehicle_id', 'label' => 'عمليات تحميل سيارات تاريخية'],
            ['table' => 'sales_invoices', 'column' => 'vehicle_id', 'label' => 'فواتير مبيعات تاريخية'],
            ['table' => 'customer_payments', 'column' => 'vehicle_id', 'label' => 'دفعات عملاء تاريخية'],
            ['table' => 'sales_returns', 'column' => 'vehicle_id', 'label' => 'مرتجعات مبيعات تاريخية'],
            ['table' => 'daily_closings', 'column' => 'vehicle_id', 'label' => 'إقفالات يومية تاريخية'],
            ['table' => 'vehicle_expenses', 'column' => 'vehicle_id', 'label' => 'مصاريف مركبة تاريخية'],
            ['table' => 'driver_journeys', 'column' => 'vehicle_id', 'label' => 'رحلات سائق تاريخية'],
            ['table' => 'driver_deliveries', 'column' => 'vehicle_id', 'label' => 'تسليمات سائق تاريخية'],
            ['table' => 'sales_journeys', 'column' => 'vehicle_id', 'label' => 'جولات مندوب مبيعات تاريخية'],
            ['table' => 'sales_visits', 'column' => 'vehicle_id', 'label' => 'زيارات مبيعات تاريخية'],
        ],
        Warehouse::class => [
            ['table' => 'stock_balances', 'column' => 'warehouse_id', 'label' => 'أرصدة مخزون مرتبطة'],
            ['table' => 'stock_movements', 'column' => 'from_warehouse_id', 'label' => 'حركات مخزون صادرة تاريخية'],
            ['table' => 'stock_movements', 'column' => 'to_warehouse_id', 'label' => 'حركات مخزون واردة تاريخية'],
            ['table' => 'vehicle_loads', 'column' => 'from_warehouse_id', 'label' => 'عمليات تحميل صادرة تاريخية'],
            ['table' => 'vehicle_loads', 'column' => 'to_warehouse_id', 'label' => 'عمليات تحميل واردة تاريخية'],
            ['table' => 'sales_invoices', 'column' => 'warehouse_id', 'label' => 'فواتير مبيعات تاريخية'],
            ['table' => 'customer_payments', 'column' => 'warehouse_id', 'label' => 'دفعات عملاء تاريخية'],
            ['table' => 'sales_returns', 'column' => 'warehouse_id', 'label' => 'مرتجعات مبيعات تاريخية'],
            ['table' => 'daily_closings', 'column' => 'warehouse_id', 'label' => 'إقفالات يومية تاريخية'],
            ['table' => 'vehicle_expenses', 'column' => 'warehouse_id', 'label' => 'مصاريف مركبات تاريخية'],
            ['table' => 'driver_journeys', 'column' => 'warehouse_id', 'label' => 'رحلات سائق تاريخية'],
            ['table' => 'driver_deliveries', 'column' => 'warehouse_id', 'label' => 'تسليمات سائق تاريخية'],
            ['table' => 'sales_journeys', 'column' => 'warehouse_id', 'label' => 'جولات مندوب مبيعات تاريخية'],
            ['table' => 'sales_visits', 'column' => 'warehouse_id', 'label' => 'زيارات مبيعات تاريخية'],
        ],
        Employee::class => [
            ['table' => 'distribution_routes', 'column' => 'driver_id', 'label' => 'مسارات كسائق'],
            ['table' => 'distribution_routes', 'column' => 'sales_representative_id', 'label' => 'مسارات كمندوب مبيعات'],
            ['table' => 'vehicle_loads', 'column' => 'driver_id', 'label' => 'عمليات تحميل كسائق'],
            ['table' => 'vehicle_loads', 'column' => 'sales_representative_id', 'label' => 'عمليات تحميل كمندوب مبيعات'],
            ['table' => 'sales_invoices', 'column' => 'sales_representative_id', 'label' => 'فواتير مبيعات تاريخية'],
            ['table' => 'customer_payments', 'column' => 'sales_representative_id', 'label' => 'دفعات عملاء تاريخية'],
            ['table' => 'sales_returns', 'column' => 'sales_representative_id', 'label' => 'مرتجعات مبيعات تاريخية'],
            ['table' => 'daily_closings', 'column' => 'sales_representative_id', 'label' => 'إقفالات يومية تاريخية'],
            ['table' => 'vehicle_expenses', 'column' => 'driver_id', 'label' => 'مصاريف مركبات كسائق'],
            ['table' => 'vehicle_expenses', 'column' => 'sales_representative_id', 'label' => 'مصاريف مركبات كمندوب مبيعات'],
            ['table' => 'driver_journeys', 'column' => 'driver_id', 'label' => 'رحلات سائق تاريخية'],
            ['table' => 'driver_journeys', 'column' => 'sales_representative_id', 'label' => 'رحلات ميدانية تاريخية'],
            ['table' => 'driver_deliveries', 'column' => 'driver_id', 'label' => 'تسليمات سائق تاريخية'],
            ['table' => 'driver_deliveries', 'column' => 'sales_representative_id', 'label' => 'تسليمات ميدانية تاريخية'],
            ['table' => 'sales_journeys', 'column' => 'sales_representative_id', 'label' => 'جولات مندوب مبيعات تاريخية'],
            ['table' => 'sales_journeys', 'column' => 'driver_id', 'label' => 'جولات مبيعات مرتبطة كسائق'],
            ['table' => 'sales_visits', 'column' => 'sales_representative_id', 'label' => 'زيارات مبيعات تاريخية'],
        ],
        DistributionRoute::class => [
            ['table' => 'customers', 'column' => 'route_id', 'label' => 'عملاء مرتبطين بالمسار'],
            ['table' => 'vehicle_loads', 'column' => 'route_id', 'label' => 'عمليات تحميل تاريخية'],
            ['table' => 'sales_invoices', 'column' => 'route_id', 'label' => 'فواتير مبيعات تاريخية'],
            ['table' => 'customer_payments', 'column' => 'route_id', 'label' => 'دفعات عملاء تاريخية'],
            ['table' => 'sales_returns', 'column' => 'route_id', 'label' => 'مرتجعات مبيعات تاريخية'],
            ['table' => 'daily_closings', 'column' => 'route_id', 'label' => 'إقفالات يومية تاريخية'],
            ['table' => 'vehicle_expenses', 'column' => 'route_id', 'label' => 'مصاريف مركبات تاريخية'],
            ['table' => 'driver_journeys', 'column' => 'route_id', 'label' => 'رحلات سائق تاريخية'],
            ['table' => 'driver_deliveries', 'column' => 'route_id', 'label' => 'تسليمات سائق تاريخية'],
            ['table' => 'sales_journeys', 'column' => 'route_id', 'label' => 'جولات مندوب مبيعات تاريخية'],
            ['table' => 'sales_visits', 'column' => 'route_id', 'label' => 'زيارات مبيعات تاريخية'],
        ],
        Customer::class => [
            ['table' => 'sales_invoices', 'column' => 'customer_id', 'label' => 'فواتير مبيعات تاريخية'],
            ['table' => 'customer_payments', 'column' => 'customer_id', 'label' => 'دفعات تاريخية'],
            ['table' => 'sales_returns', 'column' => 'customer_id', 'label' => 'مرتجعات مبيعات تاريخية'],
            ['table' => 'driver_deliveries', 'column' => 'customer_id', 'label' => 'تسليمات سائق تاريخية'],
            ['table' => 'sales_visits', 'column' => 'customer_id', 'label' => 'زيارات مبيعات تاريخية'],
        ],
    ];

    public function supports(Model $record): bool
    {
        return array_key_exists($record::class, self::BLOCKERS);
    }

    public function reason(Model $record): ?string
    {
        if (! $this->supports($record)) {
            return 'هذا النوع من السجلات غير معتمد للحذف الجماعي.';
        }

        if ($record instanceof Employee && $record->getAttribute('user_id') !== null) {
            return 'مرتبط بحساب مستخدم؛ افصل ارتباط الحساب أو عطّل الموظف بدل حذفه.';
        }

        foreach (self::BLOCKERS[$record::class] as $blocker) {
            if (! Schema::hasTable($blocker['table']) || ! Schema::hasColumn($blocker['table'], $blocker['column'])) {
                continue;
            }

            if (DB::table($blocker['table'])->where($blocker['column'], $record->getKey())->exists()) {
                return 'مرتبط بـ '.$blocker['label'].'؛ يجب إبقاؤه للحفاظ على سلامة البيانات والسجل التاريخي.';
            }
        }

        return null;
    }

    public function databaseConstraintReason(): string
    {
        return 'منعت قاعدة البيانات الحذف لوجود علاقة مرجعية محمية. لم يتم حذف السجل.';
    }

    public function recordLabel(Model $record): string
    {
        foreach (['name_ar', 'name', 'code', 'sku', 'plate_number', 'employee_code'] as $attribute) {
            $value = $record->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '#'.(string) $record->getKey();
    }
}
