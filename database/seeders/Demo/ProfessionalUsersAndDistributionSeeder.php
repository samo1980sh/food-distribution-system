<?php

namespace Database\Seeders\Demo;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProfessionalUsersAndDistributionSeeder extends Seeder
{
    private const PASSWORD = 'Demo@2026';

    public function run(): void
    {
        $users = $this->seedUsers();
        $employees = $this->seedEmployees($users);
        $routes = $this->seedRoutes($employees);
        $this->seedCustomers($routes);
        $this->seedDirectScopes($users, $routes);
    }

    /** @return array<string, User> */
    private function seedUsers(): array
    {
        $definitions = [
            'admin' => ['name' => 'مدير النظام التجريبي', 'email' => 'admin@demo.local', 'roles' => [UserRole::SUPER_ADMIN]],
            'manager' => ['name' => 'مدير التوزيع', 'email' => 'manager@demo.local', 'roles' => [UserRole::MANAGER]],
            'supervisor' => ['name' => 'مشرف عمليات دمشق', 'email' => 'supervisor@demo.local', 'roles' => [UserRole::SUPERVISOR]],
            'warehouse' => ['name' => 'أمين المستودع الرئيسي', 'email' => 'warehouse@demo.local', 'roles' => [UserRole::WAREHOUSE_KEEPER]],
            'accountant' => ['name' => 'محاسب المبيعات', 'email' => 'accountant@demo.local', 'roles' => [UserRole::ACCOUNTANT]],
            'sales' => ['name' => 'رامي مندوب دمشق', 'email' => 'sales@demo.local', 'roles' => [UserRole::SALES_REPRESENTATIVE]],
            'driver' => ['name' => 'سامر سائق دمشق', 'email' => 'driver@demo.local', 'roles' => [UserRole::DRIVER]],
            'sales_rif' => ['name' => 'هالة مندوبة الريف', 'email' => 'sales.rif@demo.local', 'roles' => [UserRole::SALES_REPRESENTATIVE]],
            'driver_rif' => ['name' => 'ياسر سائق الريف', 'email' => 'driver.rif@demo.local', 'roles' => [UserRole::DRIVER]],
            'field_team' => ['name' => 'فريق ميداني مزدوج', 'email' => 'field.team@demo.local', 'roles' => [UserRole::DRIVER, UserRole::SALES_REPRESENTATIVE]],
        ];

        $users = [];

        foreach ($definitions as $key => $definition) {
            $user = User::query()->create([
                'name' => $definition['name'],
                'email' => $definition['email'],
                'email_verified_at' => now(),
                'password' => Hash::make(self::PASSWORD),
                'status' => User::STATUS_ACTIVE,
            ]);

            $user->syncRoles(array_map(
                fn (UserRole $role): string => $role->value,
                $definition['roles'],
            ));

            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * @param array<string, User> $users
     * @return array<string, Employee>
     */
    private function seedEmployees(array $users): array
    {
        $definitions = [
            'supervisor' => ['user' => 'supervisor', 'code' => 'EMP-101', 'name' => 'نادر الخطيب', 'phone' => '0991000101', 'title' => 'مشرف عمليات دمشق', 'type' => 'supervisor'],
            'warehouse' => ['user' => 'warehouse', 'code' => 'EMP-102', 'name' => 'مازن الحمصي', 'phone' => '0991000102', 'title' => 'أمين المستودع الرئيسي', 'type' => 'warehouse_keeper'],
            'accountant' => ['user' => 'accountant', 'code' => 'EMP-103', 'name' => 'لين العبدالله', 'phone' => '0991000103', 'title' => 'محاسب مبيعات وتحصيل', 'type' => 'accountant'],
            'sales' => ['user' => 'sales', 'code' => 'EMP-201', 'name' => 'رامي منصور', 'phone' => '0992000201', 'title' => 'مندوب مبيعات دمشق', 'type' => 'sales_representative'],
            'driver' => ['user' => 'driver', 'code' => 'EMP-202', 'name' => 'سامر حمود', 'phone' => '0992000202', 'title' => 'سائق توزيع دمشق', 'type' => 'driver'],
            'sales_rif' => ['user' => 'sales_rif', 'code' => 'EMP-203', 'name' => 'هالة شحادة', 'phone' => '0992000203', 'title' => 'مندوبة مبيعات الريف', 'type' => 'sales_representative'],
            'driver_rif' => ['user' => 'driver_rif', 'code' => 'EMP-204', 'name' => 'ياسر دياب', 'phone' => '0992000204', 'title' => 'سائق توزيع الريف', 'type' => 'driver'],
            'field_team' => ['user' => 'field_team', 'code' => 'EMP-205', 'name' => 'فراس العلي', 'phone' => '0992000205', 'title' => 'سائق ومندوب ميداني', 'type' => 'sales_representative'],
        ];

        $employees = [];

        foreach ($definitions as $key => $definition) {
            $employees[$key] = Employee::query()->create([
                'user_id' => $users[$definition['user']]->id,
                'employee_code' => $definition['code'],
                'name' => $definition['name'],
                'phone' => $definition['phone'],
                'job_title' => $definition['title'],
                'type' => $definition['type'],
                'status' => 'active',
            ]);
        }

        return $employees;
    }

    /**
     * @param array<string, Employee> $employees
     * @return array<string, DistributionRoute>
     */
    private function seedRoutes(array $employees): array
    {
        $areas = Area::query()->pluck('id', 'code');
        $vehicles = Vehicle::query()->pluck('id', 'code');

        $definitions = [
            'central' => [
                'code' => 'RT-DAM-C',
                'name' => 'خط دمشق المركزي',
                'area' => 'DAM-C',
                'vehicle' => 'VH-101',
                'driver' => 'driver',
                'sales' => 'sales',
                'days' => ['saturday', 'monday', 'wednesday'],
                'status' => 'active',
                'notes' => 'خط عالي النشاط ويضم كبار العملاء.',
            ],
            'south' => [
                'code' => 'RT-DAM-S',
                'name' => 'خط دمشق الجنوبي',
                'area' => 'DAM-S',
                'vehicle' => 'VH-102',
                'driver' => 'field_team',
                'sales' => 'field_team',
                'days' => ['sunday', 'tuesday', 'thursday'],
                'status' => 'active',
                'notes' => 'خط تجريبي لحساب يجمع دور السائق والمندوب.',
            ],
            'rif' => [
                'code' => 'RT-RIF-E',
                'name' => 'خط الريف الشرقي',
                'area' => 'RIF-E',
                'vehicle' => 'VH-103',
                'driver' => 'driver_rif',
                'sales' => 'sales_rif',
                'days' => ['saturday', 'tuesday', 'thursday'],
                'status' => 'active',
                'notes' => 'خط واسع جغرافياً مع مبيعات آجلة وتحصيل دوري.',
            ],
            'homs' => [
                'code' => 'RT-HOMS-C',
                'name' => 'خط حمص المركزي',
                'area' => 'HOMS-C',
                'vehicle' => null,
                'driver' => null,
                'sales' => null,
                'days' => ['monday', 'thursday'],
                'status' => 'active',
                'notes' => 'خط دون نشاط في الفترة الحالية لاختبار تقارير الأداء.',
            ],
            'reserve' => [
                'code' => 'RT-RESERVE',
                'name' => 'خط احتياط موسمي',
                'area' => 'HOMS-C',
                'vehicle' => 'VH-104',
                'driver' => null,
                'sales' => null,
                'days' => null,
                'status' => 'inactive',
                'notes' => 'خط غير فعّال مرتبط بسيارة الصيانة.',
            ],
        ];

        $routes = [];

        foreach ($definitions as $key => $definition) {
            $routes[$key] = DistributionRoute::query()->create([
                'area_id' => $areas[$definition['area']],
                'vehicle_id' => $definition['vehicle'] ? $vehicles[$definition['vehicle']] : null,
                'driver_id' => $definition['driver'] ? $employees[$definition['driver']]->id : null,
                'sales_representative_id' => $definition['sales'] ? $employees[$definition['sales']]->id : null,
                'code' => $definition['code'],
                'name' => $definition['name'],
                'visit_days' => $definition['days'],
                'status' => $definition['status'],
                'notes' => $definition['notes'],
            ]);
        }

        return $routes;
    }

    /** @param array<string, DistributionRoute> $routes */
    private function seedCustomers(array $routes): void
    {
        $definitions = [
            ['code' => 'CUS-001', 'name' => 'سوبر ماركت الياسمين', 'owner' => 'سامر الخطيب', 'type' => 'supermarket', 'route' => 'central', 'address' => 'المالكي - دمشق', 'lat' => 33.5212000, 'lng' => 36.2861000, 'payment' => 'weekly', 'limit' => 9000000, 'days' => 14],
            ['code' => 'CUS-002', 'name' => 'ماركت المدينة', 'owner' => 'زياد المصري', 'type' => 'supermarket', 'route' => 'central', 'address' => 'أبو رمانة - دمشق', 'lat' => 33.5147000, 'lng' => 36.2925000, 'payment' => 'monthly', 'limit' => 12000000, 'days' => 30],
            ['code' => 'CUS-003', 'name' => 'بقالية الندى', 'owner' => 'مروان سعيد', 'type' => 'grocery', 'route' => 'central', 'address' => 'المزة - دمشق', 'lat' => 33.5058000, 'lng' => 36.2549000, 'payment' => 'cash', 'limit' => 0, 'days' => 1],
            ['code' => 'CUS-004', 'name' => 'ميني ماركت البركة', 'owner' => 'أحمد الحسن', 'type' => 'mini_market', 'route' => 'central', 'address' => 'كفرسوسة - دمشق', 'lat' => 33.5016000, 'lng' => 36.2762000, 'payment' => 'weekly', 'limit' => 5000000, 'days' => 10],
            ['code' => 'CUS-005', 'name' => 'سوبر ماركت الشام', 'owner' => 'باسل العلي', 'type' => 'supermarket', 'route' => 'central', 'address' => 'المهاجرين - دمشق', 'lat' => 33.5234000, 'lng' => 36.2769000, 'payment' => 'partial', 'limit' => 7000000, 'days' => 21],

            ['code' => 'CUS-006', 'name' => 'ماركت الجنوب', 'owner' => 'فادي النجار', 'type' => 'supermarket', 'route' => 'south', 'address' => 'الميدان - دمشق', 'lat' => 33.4929000, 'lng' => 36.3011000, 'payment' => 'weekly', 'limit' => 7500000, 'days' => 14],
            ['code' => 'CUS-007', 'name' => 'بقالية الخير', 'owner' => 'وليد منصور', 'type' => 'grocery', 'route' => 'south', 'address' => 'الزاهرة - دمشق', 'lat' => 33.4895000, 'lng' => 36.3096000, 'payment' => 'cash', 'limit' => 0, 'days' => 1],
            ['code' => 'CUS-008', 'name' => 'ميني ماركت الروضة', 'owner' => 'معتز حيدر', 'type' => 'mini_market', 'route' => 'south', 'address' => 'دف الشوك - دمشق', 'lat' => 33.4744000, 'lng' => 36.3277000, 'payment' => 'partial', 'limit' => 4500000, 'days' => 10],
            ['code' => 'CUS-009', 'name' => 'سوق العائلة', 'owner' => 'نزار درويش', 'type' => 'supermarket', 'route' => 'south', 'address' => 'القدم - دمشق', 'lat' => 33.4767000, 'lng' => 36.2856000, 'payment' => 'monthly', 'limit' => 9000000, 'days' => 30],
            ['code' => 'CUS-010', 'name' => 'بقالية الوفاء', 'owner' => 'طارق يوسف', 'type' => 'grocery', 'route' => 'south', 'address' => 'نهر عيشة - دمشق', 'lat' => 33.4821000, 'lng' => 36.2869000, 'payment' => 'weekly', 'limit' => 3000000, 'days' => 7],

            ['code' => 'CUS-011', 'name' => 'سوبر ماركت جرمانا', 'owner' => 'نبيل سليمان', 'type' => 'supermarket', 'route' => 'rif', 'address' => 'جرمانا - الساحة', 'lat' => 33.4864000, 'lng' => 36.3481000, 'payment' => 'monthly', 'limit' => 11000000, 'days' => 30],
            ['code' => 'CUS-012', 'name' => 'ماركت النخيل', 'owner' => 'حسام قاسم', 'type' => 'supermarket', 'route' => 'rif', 'address' => 'صحنايا - الشارع العام', 'lat' => 33.4268000, 'lng' => 36.2973000, 'payment' => 'weekly', 'limit' => 6500000, 'days' => 14],
            ['code' => 'CUS-013', 'name' => 'بقالية السهل', 'owner' => 'خليل مراد', 'type' => 'grocery', 'route' => 'rif', 'address' => 'مليحة - ريف دمشق', 'lat' => 33.4828000, 'lng' => 36.3756000, 'payment' => 'cash', 'limit' => 0, 'days' => 1],
            ['code' => 'CUS-014', 'name' => 'ميني ماركت الشرق', 'owner' => 'عماد الأحمد', 'type' => 'mini_market', 'route' => 'rif', 'address' => 'كشكول - ريف دمشق', 'lat' => 33.5002000, 'lng' => 36.3639000, 'payment' => 'partial', 'limit' => 5500000, 'days' => 10],
            ['code' => 'CUS-015', 'name' => 'مركز توفير الأسرة', 'owner' => 'غسان حمدان', 'type' => 'supermarket', 'route' => 'rif', 'address' => 'دوما - السوق التجاري', 'lat' => 33.5709000, 'lng' => 36.4031000, 'payment' => 'monthly', 'limit' => 14000000, 'days' => 30],

            ['code' => 'CUS-016', 'name' => 'ماركت العاصي', 'owner' => 'جمال البيطار', 'type' => 'supermarket', 'route' => 'homs', 'address' => 'حمص - الحضارة', 'lat' => 34.7281000, 'lng' => 36.7118000, 'payment' => 'weekly', 'limit' => 5000000, 'days' => 14],
            ['code' => 'CUS-017', 'name' => 'بقالية القلعة', 'owner' => 'إياد العبد', 'type' => 'grocery', 'route' => 'homs', 'address' => 'حمص - باب السباع', 'lat' => 34.7236000, 'lng' => 36.7184000, 'payment' => 'cash', 'limit' => 0, 'days' => 1],
            ['code' => 'CUS-018', 'name' => 'ميني ماركت النور', 'owner' => 'رامز خير', 'type' => 'mini_market', 'route' => 'homs', 'address' => 'حمص - الإنشاءات', 'lat' => 34.7119000, 'lng' => 36.7003000, 'payment' => 'monthly', 'limit' => 6000000, 'days' => 30],
            ['code' => 'CUS-019', 'name' => 'عميل متوقف مؤقتاً', 'owner' => 'محمود زيدان', 'type' => 'grocery', 'route' => 'central', 'address' => 'دمشق', 'lat' => null, 'lng' => null, 'payment' => 'cash', 'limit' => 0, 'days' => 1, 'status' => 'inactive'],
            ['code' => 'CUS-020', 'name' => 'متجر تجريبي جديد', 'owner' => 'سليم خضور', 'type' => 'mini_market', 'route' => 'south', 'address' => 'دمشق الجنوبية', 'lat' => 33.4800000, 'lng' => 36.3150000, 'payment' => 'weekly', 'limit' => 3500000, 'days' => 14],
        ];

        foreach ($definitions as $index => $definition) {
            $route = $routes[$definition['route']];

            Customer::query()->create([
                'code' => $definition['code'],
                'name' => $definition['name'],
                'owner_name' => $definition['owner'],
                'phone' => '011'.str_pad((string) (5000000 + $index), 7, '0', STR_PAD_LEFT),
                'mobile' => '0993'.str_pad((string) (100000 + $index), 6, '0', STR_PAD_LEFT),
                'customer_type' => $definition['type'],
                'area_id' => $route->area_id,
                'route_id' => $route->id,
                'address' => $definition['address'],
                'latitude' => $definition['lat'],
                'longitude' => $definition['lng'],
                'credit_limit' => $definition['limit'],
                'credit_days' => $definition['days'],
                'payment_type' => $definition['payment'],
                'status' => $definition['status'] ?? 'active',
                'notes' => $definition['code'] === 'CUS-020' ? 'عميل جديد دون تاريخ مبيعات لاختبار التغطية.' : null,
            ]);
        }
    }

    /**
     * @param array<string, User> $users
     * @param array<string, DistributionRoute> $routes
     */
    private function seedDirectScopes(array $users, array $routes): void
    {
        $supervisor = $users['supervisor'];
        $warehouseKeeper = $users['warehouse'];

        $supervisor->accessAreas()->sync([
            $routes['central']->area_id,
            $routes['south']->area_id,
            $routes['rif']->area_id,
        ]);
        $supervisor->accessRoutes()->sync([
            $routes['central']->id,
            $routes['south']->id,
            $routes['rif']->id,
        ]);
        $supervisor->accessVehicles()->sync(array_filter([
            $routes['central']->vehicle_id,
            $routes['south']->vehicle_id,
            $routes['rif']->vehicle_id,
        ]));
        $supervisor->accessWarehouses()->sync(
            Warehouse::query()
                ->whereIn('code', [
                    'WH-MAIN',
                    'WH-COLD',
                    'WH-VH-101',
                    'WH-VH-102',
                    'WH-VH-103',
                ])
                ->pluck('id')
                ->all(),
        );

        $warehouseKeeper->accessWarehouses()->sync(
            Warehouse::query()
                ->whereIn('code', [
                    'WH-MAIN',
                    'WH-COLD',
                    'WH-RESERVE',
                    'WH-VH-101',
                    'WH-VH-102',
                    'WH-VH-103',
                ])
                ->pluck('id')
                ->all(),
        );
    }
}
