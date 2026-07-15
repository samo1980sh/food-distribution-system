<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLES = [
        'super_admin',
        'manager',
        'supervisor',
        'warehouse_keeper',
        'accountant',
        'sales_representative',
        'driver',
    ];

    private const USER_MODEL = 'App\\Models\\User';

    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type']);
            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();

            $table->primary(
                ['permission_id', 'model_id', 'model_type'],
                'model_has_permissions_permission_model_type_primary',
            );
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type']);
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->primary(
                ['role_id', 'model_id', 'model_type'],
                'model_has_roles_role_model_type_primary',
            );
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->primary(
                ['permission_id', 'role_id'],
                'role_has_permissions_permission_id_role_id_primary',
            );
        });

        $now = now();

        DB::table('roles')->insert(
            array_map(
                fn (string $role): array => [
                    'name' => $role,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                self::ROLES,
            ),
        );

        if (Schema::hasColumn('users', 'role')) {
            $roleIds = DB::table('roles')
                ->where('guard_name', 'web')
                ->pluck('id', 'name');

            DB::table('users')
                ->select(['id', 'role'])
                ->orderBy('id')
                ->each(function (object $user) use ($roleIds): void {
                    $legacyRole = in_array(
                        (string) $user->role,
                        self::ROLES,
                        true,
                    ) ? (string) $user->role : 'manager';

                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $roleIds[$legacyRole],
                        'model_type' => self::USER_MODEL,
                        'model_id' => $user->id,
                    ]);
                });

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role')
                    ->default('manager');
            });
        }

        $assignedRoles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', self::USER_MODEL)
            ->select(['model_has_roles.model_id', 'roles.name'])
            ->get();

        foreach ($assignedRoles as $assignedRole) {
            DB::table('users')
                ->where('id', $assignedRole->model_id)
                ->update(['role' => $assignedRole->name]);
        }

        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
