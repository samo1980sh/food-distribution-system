<?php

namespace App\Policies;

use App\Enums\PermissionName;

class AreaPolicy extends ProtectedMasterDataPolicy
{
    protected const VIEW_ANY = PermissionName::AREAS_VIEW;
    protected const CREATE = PermissionName::AREAS_CREATE;
    protected const UPDATE = PermissionName::AREAS_UPDATE;
    protected const DELETE = PermissionName::AREAS_DELETE;
}
