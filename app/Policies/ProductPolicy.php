<?php

namespace App\Policies;

use App\Enums\PermissionName;

class ProductPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::PRODUCTS_VIEW;
    protected const CREATE = PermissionName::PRODUCTS_CREATE;
    protected const UPDATE = PermissionName::PRODUCTS_UPDATE;
    protected const DELETE = PermissionName::PRODUCTS_DELETE;
}
