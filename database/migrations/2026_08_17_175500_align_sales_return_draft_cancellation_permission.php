<?php

use App\Enums\PermissionName;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $deletePermission = Permission::query()->firstOrCreate([
            'name' => PermissionName::SALES_RETURNS_DELETE->value,
            'guard_name' => 'web',
        ]);
        $cancelPermission = Permission::query()->firstOrCreate([
            'name' => PermissionName::SALES_RETURNS_CANCEL->value,
            'guard_name' => 'web',
        ]);

        $role = Role::query()
            ->where('name', 'sales_representative')
            ->where('guard_name', 'web')
            ->first();

        if ($role !== null) {
            if (! $role->hasPermissionTo($deletePermission)) {
                $role->givePermissionTo($deletePermission);
            }
            if ($role->hasPermissionTo($cancelPermission)) {
                $role->revokePermissionTo($cancelPermission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $deletePermission = Permission::query()
            ->where('name', PermissionName::SALES_RETURNS_DELETE->value)
            ->where('guard_name', 'web')
            ->first();
        $cancelPermission = Permission::query()
            ->where('name', PermissionName::SALES_RETURNS_CANCEL->value)
            ->where('guard_name', 'web')
            ->first();

        $role = Role::query()
            ->where('name', 'sales_representative')
            ->where('guard_name', 'web')
            ->first();

        if ($role !== null) {
            if ($deletePermission !== null && $role->hasPermissionTo($deletePermission)) {
                $role->revokePermissionTo($deletePermission);
            }
            if ($cancelPermission !== null && ! $role->hasPermissionTo($cancelPermission)) {
                $role->givePermissionTo($cancelPermission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
