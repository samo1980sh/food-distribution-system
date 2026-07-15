<?php

namespace App\Policies;

use App\Enums\PermissionName;

class CustomerPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::CUSTOMERS_VIEW;
    protected const CREATE = PermissionName::CUSTOMERS_CREATE;
    protected const UPDATE = PermissionName::CUSTOMERS_UPDATE;
    protected const DELETE = PermissionName::CUSTOMERS_DELETE;
}
