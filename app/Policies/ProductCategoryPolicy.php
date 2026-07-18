<?php

namespace App\Policies;

use App\Enums\PermissionName;

class ProductCategoryPolicy extends ProtectedMasterDataPolicy
{
    protected const VIEW_ANY = PermissionName::PRODUCT_CATEGORIES_VIEW;
    protected const CREATE = PermissionName::PRODUCT_CATEGORIES_CREATE;
    protected const UPDATE = PermissionName::PRODUCT_CATEGORIES_UPDATE;
    protected const DELETE = PermissionName::PRODUCT_CATEGORIES_DELETE;
}
