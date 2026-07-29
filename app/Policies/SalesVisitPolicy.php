<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\SalesVisit;
use App\Models\User;

class SalesVisitPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::SALES_VISITS_VIEW;

    public function start(User $user, SalesVisit $visit): bool
    {
        return $visit->isPending()
            && $visit->journey?->isInProgress()
            && $this->allowsMutation($user, $visit, PermissionName::SALES_VISITS_START);
    }

    public function complete(User $user, SalesVisit $visit): bool
    {
        return $visit->isInProgress()
            && $visit->journey?->isInProgress()
            && $this->allowsMutation($user, $visit, PermissionName::SALES_VISITS_COMPLETE);
    }
}
