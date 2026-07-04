<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['code' => 'DAM', 'name_ar' => 'دمشق', 'city' => 'دمشق'],
            ['code' => 'RIF-DAM', 'name_ar' => 'ريف دمشق', 'city' => 'ريف دمشق'],
            ['code' => 'ALEPPO', 'name_ar' => 'حلب', 'city' => 'حلب'],
            ['code' => 'HOMS', 'name_ar' => 'حمص', 'city' => 'حمص'],
        ];

        foreach ($areas as $area) {
            Area::query()->updateOrCreate(
                ['code' => $area['code']],
                $area + ['status' => 'active']
            );
        }

        $units = [
            ['code' => 'PCS', 'name_ar' => 'قطعة', 'symbol' => 'قطعة'],
            ['code' => 'BOX', 'name_ar' => 'كرتونة', 'symbol' => 'كرتونة'],
            ['code' => 'PACK', 'name_ar' => 'باكيت', 'symbol' => 'باكيت'],
            ['code' => 'KG', 'name_ar' => 'كيلو', 'symbol' => 'كغ'],
            ['code' => 'L', 'name_ar' => 'ليتر', 'symbol' => 'ل'],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(
                ['code' => $unit['code']],
                $unit + ['status' => 'active']
            );
        }

        $categories = [
            ['code' => 'DAIRY', 'name_ar' => 'ألبان وأجبان', 'sort_order' => 10],
            ['code' => 'CANNED', 'name_ar' => 'معلبات', 'sort_order' => 20],
            ['code' => 'DRINKS', 'name_ar' => 'مشروبات وعصائر', 'sort_order' => 30],
            ['code' => 'DRY', 'name_ar' => 'مواد جافة', 'sort_order' => 40],
            ['code' => 'SWEETS', 'name_ar' => 'حلويات وسكاكر', 'sort_order' => 50],
        ];

        foreach ($categories as $category) {
            ProductCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                $category + ['status' => 'active']
            );
        }

        $vehicles = [
            [
                'code' => 'VH-001',
                'plate_number' => 'دمشق 123456',
                'name' => 'سيارة توزيع دمشق',
                'vehicle_type' => 'فان مبرد',
                'capacity' => 1500,
                'status' => 'active',
                'current_odometer' => 85000,
            ],
            [
                'code' => 'VH-002',
                'plate_number' => 'ريف دمشق 224455',
                'name' => 'سيارة توزيع الريف',
                'vehicle_type' => 'فان عادي',
                'capacity' => 1200,
                'status' => 'active',
                'current_odometer' => 62000,
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::query()->updateOrCreate(
                ['code' => $vehicle['code']],
                $vehicle
            );
        }

        $employees = [
            [
                'employee_code' => 'EMP-001',
                'name' => 'أحمد السائق',
                'phone' => '0999000001',
                'job_title' => 'سائق توزيع',
                'type' => 'driver',
            ],
            [
                'employee_code' => 'EMP-002',
                'name' => 'محمود المندوب',
                'phone' => '0999000002',
                'job_title' => 'مندوب مبيعات',
                'type' => 'sales_representative',
            ],
            [
                'employee_code' => 'EMP-003',
                'name' => 'خالد أمين المستودع',
                'phone' => '0999000003',
                'job_title' => 'أمين مستودع',
                'type' => 'warehouse_keeper',
            ],
        ];

        foreach ($employees as $employee) {
            Employee::query()->updateOrCreate(
                ['employee_code' => $employee['employee_code']],
                $employee + ['status' => 'active']
            );
        }

        $mainWarehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'WH-MAIN'],
            [
                'name' => 'المستودع الرئيسي',
                'type' => 'main',
                'address' => 'المستودع الرئيسي للشركة',
                'status' => 'active',
            ]
        );

        foreach (Vehicle::query()->get() as $vehicle) {
            Warehouse::query()->updateOrCreate(
                ['code' => 'WH-' . $vehicle->code],
                [
                    'vehicle_id' => $vehicle->id,
                    'name' => 'مخزون سيارة ' . $vehicle->plate_number,
                    'type' => 'vehicle',
                    'status' => 'active',
                ]
            );
        }

        $damascus = Area::query()->where('code', 'DAM')->first();
        $rifDamascus = Area::query()->where('code', 'RIF-DAM')->first();

        $vehicleOne = Vehicle::query()->where('code', 'VH-001')->first();
        $vehicleTwo = Vehicle::query()->where('code', 'VH-002')->first();

        $driver = Employee::query()->where('employee_code', 'EMP-001')->first();
        $salesRep = Employee::query()->where('employee_code', 'EMP-002')->first();

        $routes = [
            [
                'code' => 'RT-MAZZEH',
                'name' => 'خط المزة',
                'area_id' => $damascus?->id,
                'vehicle_id' => $vehicleOne?->id,
                'driver_id' => $driver?->id,
                'sales_representative_id' => $salesRep?->id,
                'visit_days' => ['saturday', 'monday', 'wednesday'],
            ],
            [
                'code' => 'RT-JARAMANA',
                'name' => 'خط جرمانا',
                'area_id' => $rifDamascus?->id,
                'vehicle_id' => $vehicleTwo?->id,
                'driver_id' => $driver?->id,
                'sales_representative_id' => $salesRep?->id,
                'visit_days' => ['sunday', 'tuesday', 'thursday'],
            ],
        ];

        foreach ($routes as $route) {
            DistributionRoute::query()->updateOrCreate(
                ['code' => $route['code']],
                $route + ['status' => 'active']
            );
        }

        $mazzehRoute = DistributionRoute::query()->where('code', 'RT-MAZZEH')->first();
        $jaramanaRoute = DistributionRoute::query()->where('code', 'RT-JARAMANA')->first();

        $customers = [
            [
                'code' => 'CUS-001',
                'name' => 'سوبر ماركت الربيع',
                'owner_name' => 'أبو سامر',
                'phone' => '0110000001',
                'mobile' => '0999111111',
                'customer_type' => 'supermarket',
                'area_id' => $damascus?->id,
                'route_id' => $mazzehRoute?->id,
                'address' => 'المزة - دمشق',
                'payment_type' => 'weekly',
                'credit_limit' => 3000000,
            ],
            [
                'code' => 'CUS-002',
                'name' => 'بقالية النور',
                'owner_name' => 'أبو يزن',
                'phone' => '0110000002',
                'mobile' => '0999222222',
                'customer_type' => 'grocery',
                'area_id' => $rifDamascus?->id,
                'route_id' => $jaramanaRoute?->id,
                'address' => 'جرمانا - ريف دمشق',
                'payment_type' => 'cash',
                'credit_limit' => 0,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(
                ['code' => $customer['code']],
                $customer + ['status' => 'active']
            );
        }

        $dairy = ProductCategory::query()->where('code', 'DAIRY')->first();
        $drinks = ProductCategory::query()->where('code', 'DRINKS')->first();
        $canned = ProductCategory::query()->where('code', 'CANNED')->first();

        $box = Unit::query()->where('code', 'BOX')->first();
        $pcs = Unit::query()->where('code', 'PCS')->first();

        $products = [
            [
                'sku' => 'PRD-001',
                'barcode' => '621000000001',
                'name_ar' => 'لبن زبادي 1 كغ',
                'category_id' => $dairy?->id,
                'unit_id' => $pcs?->id,
                'purchase_price' => 7000,
                'sale_price' => 8500,
                'wholesale_price' => 8000,
                'min_stock' => 50,
                'has_expiry' => true,
            ],
            [
                'sku' => 'PRD-002',
                'barcode' => '621000000002',
                'name_ar' => 'عصير برتقال كرتونة',
                'category_id' => $drinks?->id,
                'unit_id' => $box?->id,
                'purchase_price' => 45000,
                'sale_price' => 52000,
                'wholesale_price' => 50000,
                'min_stock' => 20,
                'has_expiry' => true,
            ],
            [
                'sku' => 'PRD-003',
                'barcode' => '621000000003',
                'name_ar' => 'علبة ذرة معلبة',
                'category_id' => $canned?->id,
                'unit_id' => $pcs?->id,
                'purchase_price' => 6000,
                'sale_price' => 7500,
                'wholesale_price' => 7000,
                'min_stock' => 100,
                'has_expiry' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                $product + ['status' => 'active']
            );
        }
    }
}