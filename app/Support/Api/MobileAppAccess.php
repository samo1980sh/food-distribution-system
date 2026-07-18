<?php

namespace App\Support\Api;

use App\Models\User;

final class MobileAppAccess
{
    public static function allows(User $user): bool
    {
        $roles = array_values(array_filter(
            (array) config('mobile_api.allowed_roles', []),
            fn (mixed $role): bool => is_string($role) && $role !== '',
        ));

        $assignedRoles = $user->getRoleNames()
            ->filter(fn (mixed $role): bool => is_string($role) && $role !== '')
            ->values()
            ->all();

        return $roles !== []
            && $assignedRoles !== []
            && array_diff($assignedRoles, $roles) === [];
    }

    private function __construct()
    {
    }
}
