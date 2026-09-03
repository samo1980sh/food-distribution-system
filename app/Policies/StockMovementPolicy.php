<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Authorization\AccessScopeService;

class StockMovementPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::STOCK_MOVEMENTS_VIEW;

    public function createTransfer(User $user): bool
    {
        return $user->can(PermissionName::INVENTORY_TRANSFERS_CREATE->value);
    }

    public function createAdjustment(User $user, ?StockMovement $movement = null): bool
    {
        if (! $user->can(PermissionName::INVENTORY_ADJUSTMENTS_CREATE->value)) {
            return false;
        }

        return $movement === null
            || app(AccessScopeService::class)->allows($user, $movement);
    }
}
