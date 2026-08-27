<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\PurchaseReceipt;
use App\Models\User;
use App\Services\Authorization\AccessScopeService;

class PurchaseReceiptPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isActive() ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::STOCK_MOVEMENTS_VIEW->value);
    }

    public function view(User $user, PurchaseReceipt $receipt): bool
    {
        $scope = app(AccessScopeService::class)->for($user);

        return $this->viewAny($user)
            && (
                $scope->unrestricted
                || in_array((int) $receipt->warehouse_id, $scope->warehouseIds, true)
            );
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PurchaseReceipt $receipt): bool
    {
        return false;
    }

    public function delete(User $user, PurchaseReceipt $receipt): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
