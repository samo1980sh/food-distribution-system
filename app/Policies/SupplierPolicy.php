<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isActive() ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::STOCK_MOVEMENTS_VIEW->value);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::STOCK_MOVEMENTS_CREATE->value);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
