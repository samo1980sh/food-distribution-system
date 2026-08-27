<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Authorization\AccessScopeService;

class PurchaseOrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isActive() ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::STOCK_MOVEMENTS_VIEW->value);
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->viewAny($user) && $this->warehouseAllowed($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::STOCK_MOVEMENTS_CREATE->value);
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $this->create($user)
            && $order->isDraft()
            && $this->warehouseAllowed($user, $order);
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function warehouseAllowed(User $user, PurchaseOrder $order): bool
    {
        $scope = app(AccessScopeService::class)->for($user);

        return $scope->unrestricted
            || in_array((int) $order->warehouse_id, $scope->warehouseIds, true);
    }
}
