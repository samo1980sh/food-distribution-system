<?php

namespace App\Policies;

use App\Enums\PermissionName;

class DriverDeliveryPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::DRIVER_DELIVERIES_VIEW;
}
