<?php

namespace App\Policies;

use App\Enums\PermissionName;

class DriverJourneyPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::DRIVER_JOURNEYS_VIEW;
}
