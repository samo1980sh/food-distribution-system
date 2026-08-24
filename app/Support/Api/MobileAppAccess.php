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
        if ($user->hasRole(User::ROLE_SALES_REPRESENTATIVE)) {
            return User::ROLE_SALES_REPRESENTATIVE;
        }

        return $user->hasRole(User::ROLE_DRIVER)
            ? User::ROLE_DRIVER
            : null;
    }

    /** @return array{role: ?string, unified: bool, legacy: bool} */
    public static function fieldWorkspace(User $user): array
    {
        $role = self::activeFieldRole($user);

        return [
            'role' => $role,
            'unified' => $role === User::ROLE_SALES_REPRESENTATIVE,
            'legacy' => $role === User::ROLE_DRIVER,
        ];
    }

    public static function usesUnifiedRepresentativeWorkspace(User $user): bool
    {
        return self::activeFieldRole($user) === User::ROLE_SALES_REPRESENTATIVE;
    }

    public static function usesLegacyDriverWorkspace(User $user): bool
    {
        return self::activeFieldRole($user) === User::ROLE_DRIVER;
    }

    private function __construct() {}
}
