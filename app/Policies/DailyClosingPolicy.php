<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\DailyClosing;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DailyClosingPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::DAILY_CLOSINGS_VIEW;
    protected const CREATE = PermissionName::DAILY_CLOSINGS_CREATE;
    protected const UPDATE = PermissionName::DAILY_CLOSINGS_UPDATE;
    protected const DELETE = PermissionName::DAILY_CLOSINGS_DELETE;

    public function update(User $user, Model $record): bool
    {
        return $record instanceof DailyClosing
            && $record->isDraft()
            && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $record instanceof DailyClosing
            && $record->isDraft()
            && parent::delete($user, $record);
    }

    public function refreshTotals(User $user, DailyClosing $record): bool
    {
        return $record->isDraft()
            && $this->allowsMutation($user, $record, PermissionName::DAILY_CLOSINGS_REFRESH_TOTALS);
    }

    public function confirm(User $user, DailyClosing $record): bool
    {
        return $record->isDraft()
            && $this->allowsMutation($user, $record, PermissionName::DAILY_CLOSINGS_CONFIRM);
    }

    public function cancel(User $user, DailyClosing $record): bool
    {
        return $record->isConfirmed()
            && $this->allowsMutation($user, $record, PermissionName::DAILY_CLOSINGS_CANCEL);
    }

    public function print(User $user, DailyClosing $record): bool
    {
        return $this->allowsRecord($user, $record, PermissionName::DAILY_CLOSINGS_PRINT);
    }
}
