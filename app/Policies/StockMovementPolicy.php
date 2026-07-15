<?php

namespace App\Policies;

use App\Enums\PermissionName;

class StockMovementPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::STOCK_MOVEMENTS_VIEW;
    protected const CREATE = PermissionName::STOCK_MOVEMENTS_CREATE;
}
