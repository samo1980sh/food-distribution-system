<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\FieldOperationalDayOverride;
use App\Models\User;

class FieldOperationalDayOverridePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->can(PermissionName::DISTRIBUTION_ROUTES_VIEW->value);
    }

    public function view(User $user, FieldOperationalDayOverride $override): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->can(PermissionName::DISTRIBUTION_ROUTES_UPDATE->value);
    }

    public function update(User $user, FieldOperationalDayOverride $override): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, FieldOperationalDayOverride $override): bool
    {
        return false;
    }
}
