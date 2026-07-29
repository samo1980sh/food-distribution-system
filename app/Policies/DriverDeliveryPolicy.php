<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\DriverDelivery;
use App\Models\User;

class DriverDeliveryPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::DRIVER_DELIVERIES_VIEW;

    public function submitOutcome(User $user, DriverDelivery $delivery): bool
    {
        return $delivery->isPending()
            && $delivery->journey?->isInProgress()
            && $this->allowsMutation($user, $delivery, PermissionName::DRIVER_DELIVERIES_SUBMIT_OUTCOME);
    }
}
