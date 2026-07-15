<?php

namespace App\Policies;

use App\Enums\PermissionName;

class StockBalancePolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::STOCK_BALANCES_VIEW;
}
