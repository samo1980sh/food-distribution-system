<?php

namespace App\Policies;

use App\Enums\PermissionName;

class EmployeePolicy extends ProtectedMasterDataPolicy
{
    protected const VIEW_ANY = PermissionName::EMPLOYEES_VIEW;
    protected const CREATE = PermissionName::EMPLOYEES_CREATE;
    protected const UPDATE = PermissionName::EMPLOYEES_UPDATE;
    protected const DELETE = PermissionName::EMPLOYEES_DELETE;
}
