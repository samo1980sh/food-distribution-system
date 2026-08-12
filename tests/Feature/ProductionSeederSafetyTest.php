<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\UserRole;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = false;

    public function test_default_database_seeder_only_installs_system_roles_and_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            count(UserRole::cases()),
            Role::query()->where('guard_name', 'web')->count(),
        );

        $this->assertSame(
            count(PermissionName::cases()),
            Permission::query()->where('guard_name', 'web')->count(),
        );

        foreach ([
            'areas',
            'units',
            'product_categories',
            'vehicles',
            'employees',
            'warehouses',
            'distribution_routes',
            'customers',
            'products',
        ] as $table) {
            $this->assertSame(
                0,
                DB::table($table)->count(),
                "Default DatabaseSeeder must not create business/demo rows in [{$table}].",
            );
        }
    }

    public function test_default_database_seeder_can_be_run_repeatedly_without_creating_business_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            count(UserRole::cases()),
            Role::query()->where('guard_name', 'web')->count(),
        );

        $this->assertSame(
            count(PermissionName::cases()),
            Permission::query()->where('guard_name', 'web')->count(),
        );

        $this->assertSame(0, DB::table('customers')->count());
        $this->assertSame(0, DB::table('products')->count());
        $this->assertSame(0, DB::table('distribution_routes')->count());
    }
}
