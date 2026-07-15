<?php

namespace App\Policies;

use App\Enums\PermissionName;

class UnitPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::UNITS_VIEW;
    protected const CREATE = PermissionName::UNITS_CREATE;
    protected const UPDATE = PermissionName::UNITS_UPDATE;
    protected const DELETE = PermissionName::UNITS_DELETE;
}
