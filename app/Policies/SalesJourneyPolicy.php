<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\SalesJourney;
use App\Models\User;

class SalesJourneyPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::SALES_JOURNEYS_VIEW;

    public function openToday(User $user): bool
    {
        return $this->allows($user, PermissionName::SALES_JOURNEYS_OPEN);
    }

    public function start(User $user, SalesJourney $journey): bool
    {
        return $journey->isReady()
            && $this->allowsMutation($user, $journey, PermissionName::SALES_JOURNEYS_START);
    }

    public function finish(User $user, SalesJourney $journey): bool
    {
        return $journey->isInProgress()
            && $this->allowsMutation($user, $journey, PermissionName::SALES_JOURNEYS_FINISH);
    }
}
