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

    public function create(User $user): bool
    {
        return parent::create($user)
            || $this->createOffice($user);
    }

    public function createOffice(User $user): bool
    {
        return $this->allows($user, PermissionName::DAILY_CLOSINGS_CREATE_OFFICE);
    }

    public function openField(User $user): bool
    {
        return $this->allows($user, PermissionName::DAILY_CLOSINGS_OPEN_FIELD);
    }

    public function update(User $user, Model $record): bool
    {
        return $record instanceof DailyClosing
            && $record->isDraft()
            && ! $record->isFieldWorkflow()
            && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $record instanceof DailyClosing
            && $record->isDraft()
            && parent::delete($user, $record);
    }

    public function submitInventory(User $user, DailyClosing $record): bool
    {
        $employeeId = $user->employee()->value('id');

        return $employeeId !== null
            && $record->isFieldWorkflow()
            && $record->isDraft()
            && (int) $record->driver_id === (int) $employeeId
            && $this->allowsMutation(
                $user,
                $record,
                PermissionName::DAILY_CLOSINGS_SUBMIT_INVENTORY,
            );
    }

    public function submitCash(User $user, DailyClosing $record): bool
    {
        $employeeId = $user->employee()->value('id');

        return $employeeId !== null
            && $record->isFieldWorkflow()
            && $record->isDraft()
            && (int) $record->sales_representative_id === (int) $employeeId
            && $this->allowsMutation(
                $user,
                $record,
                PermissionName::DAILY_CLOSINGS_SUBMIT_CASH,
            );
    }

    public function refreshTotals(User $user, DailyClosing $record): bool
    {
        return $record->isDraft()
            && $this->allowsMutation($user, $record, PermissionName::DAILY_CLOSINGS_REFRESH_TOTALS);
    }

    public function confirm(User $user, DailyClosing $record): bool
    {
        return $record->isDraft()
            && (! $record->isFieldWorkflow() || $record->fieldHandoverComplete())
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
