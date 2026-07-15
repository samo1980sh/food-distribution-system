<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_area_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'area_id']);
        });

        Schema::create('user_route_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distribution_route_id')
                ->constrained('distribution_routes')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'distribution_route_id'], 'user_route_scopes_unique');
        });

        Schema::create('user_vehicle_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'vehicle_id']);
        });

        Schema::create('user_warehouse_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'warehouse_id']);
        });

        $this->preserveExistingRestrictedAccess();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_warehouse_scopes');
        Schema::dropIfExists('user_vehicle_scopes');
        Schema::dropIfExists('user_route_scopes');
        Schema::dropIfExists('user_area_scopes');
    }

    private function preserveExistingRestrictedAccess(): void
    {
        $now = now();

        $supervisorIds = $this->userIdsForRole(UserRole::SUPERVISOR->value);

        foreach ($supervisorIds as $userId) {
            DB::table('areas')->orderBy('id')->pluck('id')->each(
                fn (int|string $areaId) => DB::table('user_area_scopes')->insertOrIgnore([
                    'user_id' => $userId,
                    'area_id' => $areaId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );

            DB::table('warehouses')->orderBy('id')->pluck('id')->each(
                fn (int|string $warehouseId) => DB::table('user_warehouse_scopes')->insertOrIgnore([
                    'user_id' => $userId,
                    'warehouse_id' => $warehouseId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        }

        foreach ($this->userIdsForRole(UserRole::WAREHOUSE_KEEPER->value) as $userId) {
            DB::table('warehouses')->orderBy('id')->pluck('id')->each(
                fn (int|string $warehouseId) => DB::table('user_warehouse_scopes')->insertOrIgnore([
                    'user_id' => $userId,
                    'warehouse_id' => $warehouseId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        }
    }

    /** @return list<int> */
    private function userIdsForRole(string $roleName): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $roleName)
            ->where('roles.guard_name', 'web')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->pluck('model_has_roles.model_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }
};
