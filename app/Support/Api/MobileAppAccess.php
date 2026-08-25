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

    public static function allowsLogin(User $user): bool
    {
        return self::allows($user)
            && self::usesUnifiedRepresentativeWorkspace($user);
    }

    public static function activeFieldRole(User $user): ?string
    {
        return $user->hasRole(User::ROLE_SALES_REPRESENTATIVE)
            ? User::ROLE_SALES_REPRESENTATIVE
            : null;
    }

    /** @return array{role: ?string, unified: bool, legacy: bool} */
    public static function fieldWorkspace(User $user): array
    {
        $role = self::activeFieldRole($user);

        return [
            'role' => $role,
            'unified' => $role === User::ROLE_SALES_REPRESENTATIVE,
            'legacy' => false,
        ];
    }

    public static function usesUnifiedRepresentativeWorkspace(User $user): bool
    {
        return self::activeFieldRole($user) === User::ROLE_SALES_REPRESENTATIVE;
    }

    private function __construct() {}
}
