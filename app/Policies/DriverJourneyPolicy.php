<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\DriverJourney;
use App\Models\User;

class DriverJourneyPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::DRIVER_JOURNEYS_VIEW;

    public function openToday(User $user): bool
    {
        return $this->allows($user, PermissionName::DRIVER_JOURNEYS_OPEN);
    }

    public function start(User $user, DriverJourney $journey): bool
    {
        return $journey->isReady()
            && $this->allowsMutation($user, $journey, PermissionName::DRIVER_JOURNEYS_START);
    }

    public function finish(User $user, DriverJourney $journey): bool
    {
        return $journey->isInProgress()
            && $this->allowsMutation($user, $journey, PermissionName::DRIVER_JOURNEYS_FINISH);
    }
}
