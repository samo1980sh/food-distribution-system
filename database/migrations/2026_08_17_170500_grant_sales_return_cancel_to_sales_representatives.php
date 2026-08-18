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

        $permission = Permission::query()->firstOrCreate([
            'name' => PermissionName::SALES_RETURNS_CANCEL->value,
            'guard_name' => 'web',
        ]);

        $role = Role::query()
            ->where('name', 'sales_representative')
            ->where('guard_name', 'web')
            ->first();

        if ($role !== null && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::query()
            ->where('name', PermissionName::SALES_RETURNS_CANCEL->value)
            ->where('guard_name', 'web')
            ->first();

        $role = Role::query()
            ->where('name', 'sales_representative')
            ->where('guard_name', 'web')
            ->first();

        if ($role !== null && $permission !== null && $role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
