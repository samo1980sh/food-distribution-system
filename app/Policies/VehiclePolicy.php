<?php

namespace App\Policies;

use App\Enums\PermissionName;

class VehiclePolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::VEHICLES_VIEW;
    protected const CREATE = PermissionName::VEHICLES_CREATE;
    protected const UPDATE = PermissionName::VEHICLES_UPDATE;
    protected const DELETE = PermissionName::VEHICLES_DELETE;
}
