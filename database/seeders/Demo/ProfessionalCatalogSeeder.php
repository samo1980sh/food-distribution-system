<?php

namespace Database\Seeders\Demo;

use App\Models\Area;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class ProfessionalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAreas();
        $this->seedUnits();
        $this->seedCategories();
        $this->seedVehiclesAndWarehouses();
        $this->seedProducts();
    }

    private function seedAreas(): void
    {
        foreach ([
            ['code' => 'DAM-C', 'name_ar' => 'دمشق المركز', 'city' => 'دمشق', 'notes' => 'المنطقة المركزية عالية الكثافة.'],
            ['code' => 'DAM-S', 'name_ar' => 'دمشق الجنوبية', 'city' => 'دمشق', 'notes' => 'منطقة تجمع محال وسوبرماركت متوسطة وكبيرة.'],
            ['code' => 'RIF-E', 'name_ar' => 'ريف دمشق الشرقي', 'city' => 'ريف دمشق', 'notes' => 'خطوط متباعدة تحتاج تخطيط تحميل وتحصيل دقيق.'],
            ['code' => 'HOMS-C', 'name_ar' => 'حمص المركز', 'city' => 'حمص', 'notes' => 'منطقة تجريبية لقياس الخطوط قليلة النشاط.'],
        ] as $area) {
            Area::query()->create($area + ['status' => 'active']);
        }
    }

    private function seedUnits(): void
    {
        foreach ([
            ['code' => 'PCS', 'name_ar' => 'قطعة', 'symbol' => 'قطعة'],
            ['code' => 'BOX', 'name_ar' => 'كرتونة', 'symbol' => 'كرتونة'],
            ['code' => 'PACK', 'name_ar' => 'باكيت', 'symbol' => 'باكيت'],
            ['code' => 'KG', 'name_ar' => 'كيلوغرام', 'symbol' => 'كغ'],
            ['code' => 'L', 'name_ar' => 'ليتر', 'symbol' => 'ل'],
        ] as $unit) {
            Unit::query()->create($unit + ['status' => 'active']);
        }
    }

    private function seedCategories(): void
    {
        foreach ([
            ['code' => 'DAIRY', 'name_ar' => 'ألبان وأجبان', 'sort_order' => 10],
            ['code' => 'BEVERAGES', 'name_ar' => 'مشروبات وعصائر', 'sort_order' => 20],
            ['code' => 'CANNED', 'name_ar' => 'معلبات', 'sort_order' => 30],
            ['code' => 'DRY', 'name_ar' => 'مواد جافة وأساسية', 'sort_order' => 40],
            ['code' => 'SNACKS', 'name_ar' => 'سكاكر وتسالي', 'sort_order' => 50],
            ['code' => 'HOUSEHOLD', 'name_ar' => 'منظفات وورقيات', 'sort_order' => 60],
        ] as $category) {
            ProductCategory::query()->create($category + ['status' => 'active']);
        }
    }

    private function seedVehiclesAndWarehouses(): void
    {
        $vehicles = [
            [
                'code' => 'VH-101',
                'plate_number' => 'دمشق 345621',
                'name' => 'فان دمشق المركزي',
                'vehicle_type' => 'فان مبرد',
                'capacity' => 1800,
                'current_odometer' => 86400,
                'insurance_expiry_date' => today()->addMonths(8),
                'license_expiry_date' => today()->addMonths(5),
                'status' => 'active',
            ],
            [
                'code' => 'VH-102',
                'plate_number' => 'دمشق 778214',
                'name' => 'فان دمشق الجنوبي',
                'vehicle_type' => 'فان توزيع',
                'capacity' => 1600,
                'current_odometer' => 71250,
                'insurance_expiry_date' => today()->addMonths(6),
                'license_expiry_date' => today()->addMonths(4),
                'status' => 'active',
            ],
            [
                'code' => 'VH-103',
                'plate_number' => 'ريف دمشق 449182',
                'name' => 'شاحنة الريف الشرقي',
                'vehicle_type' => 'شاحنة خفيفة',
                'capacity' => 2600,
                'current_odometer' => 103800,
                'insurance_expiry_date' => today()->addMonths(10),
                'license_expiry_date' => today()->addMonths(7),
                'status' => 'active',
            ],
            [
                'code' => 'VH-104',
                'plate_number' => 'حمص 118903',
                'name' => 'سيارة احتياط حمص',
                'vehicle_type' => 'فان توزيع',
                'capacity' => 1400,
                'current_odometer' => 129400,
                'insurance_expiry_date' => today()->addMonths(2),
                'license_expiry_date' => today()->addMonth(),
                'status' => 'maintenance',
                'notes' => 'سيارة احتياط متوقفة للصيانة الدورية.',
            ],
        ];

        foreach ($vehicles as $attributes) {
            Vehicle::query()->create($attributes);
        }

        Warehouse::query()->create([
            'code' => 'WH-MAIN',
            'name' => 'المستودع الرئيسي',
            'type' => 'main',
            'address' => 'المنطقة الصناعية - دمشق',
            'status' => 'active',
            'notes' => 'مستودع الاستلام والتوزيع الرئيسي.',
        ]);

        Warehouse::query()->create([
            'code' => 'WH-COLD',
            'name' => 'المستودع المبرد',
            'type' => 'branch',
            'address' => 'المنطقة الصناعية - قسم التبريد',
            'status' => 'active',
            'notes' => 'مخصص للألبان والمواد الحساسة للحرارة.',
        ]);

        Warehouse::query()->create([
            'code' => 'WH-RESERVE',
            'name' => 'مستودع الاحتياط',
            'type' => 'branch',
            'address' => 'ريف دمشق - مستودع احتياطي',
            'status' => 'active',
            'notes' => 'مخزون أمان ومخزون موسمي.',
        ]);

        foreach (Vehicle::query()->orderBy('id')->get() as $vehicle) {
            Warehouse::query()->create([
                'vehicle_id' => $vehicle->id,
                'code' => 'WH-'.$vehicle->code,
                'name' => 'مخزون '.$vehicle->name,
                'type' => 'vehicle',
                'address' => 'مستودع افتراضي لمخزون السيارة',
                'status' => $vehicle->status === 'active' ? 'active' : 'inactive',
            ]);
        }
    }

    private function seedProducts(): void
    {
        $categories = ProductCategory::query()->pluck('id', 'code');
        $units = Unit::query()->pluck('id', 'code');

        $products = [
            ['sku' => 'FD-001', 'barcode' => '621100000001', 'name_ar' => 'لبن زبادي 1 كغ', 'category' => 'DAIRY', 'unit' => 'PCS', 'purchase' => 7200, 'sale' => 9000, 'wholesale' => 8500, 'min_stock' => 80, 'expiry' => true],
            ['sku' => 'FD-002', 'barcode' => '621100000002', 'name_ar' => 'لبنة كاملة الدسم 500 غ', 'category' => 'DAIRY', 'unit' => 'PCS', 'purchase' => 12500, 'sale' => 15500, 'wholesale' => 14800, 'min_stock' => 60, 'expiry' => true],
            ['sku' => 'FD-003', 'barcode' => '621100000003', 'name_ar' => 'جبنة بيضاء 500 غ', 'category' => 'DAIRY', 'unit' => 'PCS', 'purchase' => 18000, 'sale' => 22500, 'wholesale' => 21500, 'min_stock' => 50, 'expiry' => true],
            ['sku' => 'FD-004', 'barcode' => '621100000004', 'name_ar' => 'عصير برتقال 24 عبوة', 'category' => 'BEVERAGES', 'unit' => 'BOX', 'purchase' => 42000, 'sale' => 51000, 'wholesale' => 49000, 'min_stock' => 30, 'expiry' => true],
            ['sku' => 'FD-005', 'barcode' => '621100000005', 'name_ar' => 'مياه معدنية 12 عبوة', 'category' => 'BEVERAGES', 'unit' => 'PACK', 'purchase' => 11000, 'sale' => 14000, 'wholesale' => 13200, 'min_stock' => 80, 'expiry' => true],
            ['sku' => 'FD-006', 'barcode' => '621100000006', 'name_ar' => 'مشروب غازي كرتونة', 'category' => 'BEVERAGES', 'unit' => 'BOX', 'purchase' => 36000, 'sale' => 44000, 'wholesale' => 42500, 'min_stock' => 35, 'expiry' => true],
            ['sku' => 'FD-007', 'barcode' => '621100000007', 'name_ar' => 'تونا قطع 160 غ', 'category' => 'CANNED', 'unit' => 'PCS', 'purchase' => 6500, 'sale' => 8200, 'wholesale' => 7800, 'min_stock' => 100, 'expiry' => true],
            ['sku' => 'FD-008', 'barcode' => '621100000008', 'name_ar' => 'ذرة حلوة معلبة', 'category' => 'CANNED', 'unit' => 'PCS', 'purchase' => 5800, 'sale' => 7400, 'wholesale' => 7000, 'min_stock' => 90, 'expiry' => true],
            ['sku' => 'FD-009', 'barcode' => '621100000009', 'name_ar' => 'أرز حبة طويلة 1 كغ', 'category' => 'DRY', 'unit' => 'KG', 'purchase' => 9500, 'sale' => 11800, 'wholesale' => 11200, 'min_stock' => 120, 'expiry' => true],
            ['sku' => 'FD-010', 'barcode' => '621100000010', 'name_ar' => 'سكر أبيض 1 كغ', 'category' => 'DRY', 'unit' => 'KG', 'purchase' => 7200, 'sale' => 9000, 'wholesale' => 8600, 'min_stock' => 120, 'expiry' => true],
            ['sku' => 'FD-011', 'barcode' => '621100000011', 'name_ar' => 'معكرونة 500 غ', 'category' => 'DRY', 'unit' => 'PACK', 'purchase' => 4500, 'sale' => 6000, 'wholesale' => 5700, 'min_stock' => 150, 'expiry' => true],
            ['sku' => 'FD-012', 'barcode' => '621100000012', 'name_ar' => 'بسكويت شاي عائلي', 'category' => 'SNACKS', 'unit' => 'PACK', 'purchase' => 6000, 'sale' => 8000, 'wholesale' => 7600, 'min_stock' => 100, 'expiry' => true],
            ['sku' => 'FD-013', 'barcode' => '621100000013', 'name_ar' => 'شيبس مشكل كرتونة', 'category' => 'SNACKS', 'unit' => 'BOX', 'purchase' => 30000, 'sale' => 39000, 'wholesale' => 37200, 'min_stock' => 40, 'expiry' => true],
            ['sku' => 'FD-014', 'barcode' => '621100000014', 'name_ar' => 'سائل جلي 1 ليتر', 'category' => 'HOUSEHOLD', 'unit' => 'L', 'purchase' => 13000, 'sale' => 17000, 'wholesale' => 16200, 'min_stock' => 70, 'expiry' => false],
            ['sku' => 'FD-015', 'barcode' => '621100000015', 'name_ar' => 'مناديل ورقية عائلية', 'category' => 'HOUSEHOLD', 'unit' => 'PACK', 'purchase' => 8000, 'sale' => 10500, 'wholesale' => 10000, 'min_stock' => 80, 'expiry' => false],
        ];

        foreach ($products as $product) {
            Product::query()->create([
                'sku' => $product['sku'],
                'barcode' => $product['barcode'],
                'name_ar' => $product['name_ar'],
                'category_id' => $categories[$product['category']],
                'unit_id' => $units[$product['unit']],
                'purchase_price' => $product['purchase'],
                'sale_price' => $product['sale'],
                'wholesale_price' => $product['wholesale'],
                'min_stock' => $product['min_stock'],
                'has_expiry' => $product['expiry'],
                'status' => 'active',
            ]);
        }
    }
}
