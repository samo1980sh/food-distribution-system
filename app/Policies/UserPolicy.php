<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::USERS_VIEW->value);
    }

    public function view(User $user, User $record): bool
    {
        return $this->viewAny($user)
            && (! $record->isSuperAdmin() || $user->isSuperAdmin());
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::USERS_CREATE->value)
            && $user->can(PermissionName::ROLES_ASSIGN->value);
    }

    public function update(User $user, User $record): bool
    {
        return $user->can(PermissionName::USERS_UPDATE->value)
            && $user->can(PermissionName::ROLES_ASSIGN->value)
            && (! $record->isSuperAdmin() || $user->isSuperAdmin());
    }

    public function delete(User $user, User $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, User $record): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
