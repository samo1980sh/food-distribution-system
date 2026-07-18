<?php

namespace App\Policies;

use App\Enums\PermissionName;

class WarehousePolicy extends ProtectedMasterDataPolicy
{
    protected const VIEW_ANY = PermissionName::WAREHOUSES_VIEW;
    protected const CREATE = PermissionName::WAREHOUSES_CREATE;
    protected const UPDATE = PermissionName::WAREHOUSES_UPDATE;
    protected const DELETE = PermissionName::WAREHOUSES_DELETE;
}
