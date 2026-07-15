<?php

namespace App\Policies;

use App\Enums\PermissionName;

class DistributionRoutePolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::DISTRIBUTION_ROUTES_VIEW;
    protected const CREATE = PermissionName::DISTRIBUTION_ROUTES_CREATE;
    protected const UPDATE = PermissionName::DISTRIBUTION_ROUTES_UPDATE;
    protected const DELETE = PermissionName::DISTRIBUTION_ROUTES_DELETE;
}
