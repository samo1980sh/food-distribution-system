<?php

namespace App\Services\Authorization;

use App\Enums\UserRole;
use App\Models\User;

class UserScopeAssignmentService
{
    public function normalize(User $user): void
    {
        $role = UserRole::tryFrom((string) $user->primaryRoleName());

        if ($role === UserRole::SUPERVISOR) {
            app(AccessScopeService::class)->forget($user);

            return;
        }

        if ($role === UserRole::WAREHOUSE_KEEPER) {
            $user->accessAreas()->sync([]);
            $user->accessRoutes()->sync([]);
            $user->accessVehicles()->sync([]);
            app(AccessScopeService::class)->forget($user);

            return;
        }

        $user->accessAreas()->sync([]);
        $user->accessRoutes()->sync([]);
        $user->accessVehicles()->sync([]);
        $user->accessWarehouses()->sync([]);
        app(AccessScopeService::class)->forget($user);
    }
}
