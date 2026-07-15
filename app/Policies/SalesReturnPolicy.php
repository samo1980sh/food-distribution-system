<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SalesReturnPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::SALES_RETURNS_VIEW;
    protected const CREATE = PermissionName::SALES_RETURNS_CREATE;
    protected const UPDATE = PermissionName::SALES_RETURNS_UPDATE;
    protected const DELETE = PermissionName::SALES_RETURNS_DELETE;

    public function update(User $user, Model $record): bool
    {
        return $record instanceof SalesReturn
            && $record->isDraft()
            && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $record instanceof SalesReturn
            && $record->isDraft()
            && parent::delete($user, $record);
    }

    public function confirm(User $user, SalesReturn $record): bool
    {
        return $record->isDraft()
            && $this->allowsMutation($user, $record, PermissionName::SALES_RETURNS_CONFIRM);
    }

    public function cancel(User $user, SalesReturn $record): bool
    {
        return $record->isConfirmed()
            && $this->allowsMutation($user, $record, PermissionName::SALES_RETURNS_CANCEL);
    }

    public function print(User $user, SalesReturn $record): bool
    {
        return $this->allowsRecord($user, $record, PermissionName::SALES_RETURNS_PRINT);
    }
}
