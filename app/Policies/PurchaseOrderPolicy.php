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
        return $user->can(PermissionName::PURCHASE_ORDERS_VIEW->value);
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->viewAny($user) && $this->warehouseAllowed($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PURCHASE_ORDERS_CREATE->value);
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->can(PermissionName::PURCHASE_ORDERS_UPDATE->value)
            && $order->isDraft()
            && $this->warehouseAllowed($user, $order);
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $user->can(PermissionName::PURCHASE_ORDERS_APPROVE->value)
            && $order->isDraft()
            && $this->warehouseAllowed($user, $order);
    }

    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $user->can(PermissionName::PURCHASE_ORDERS_RECEIVE->value)
            && $order->canReceive()
            && $this->warehouseAllowed($user, $order);
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $user->can(PermissionName::PURCHASE_ORDERS_CANCEL->value)
            && in_array($order->status, [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_APPROVED,
            ], true)
            && ! $order->receipts()->exists()
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
