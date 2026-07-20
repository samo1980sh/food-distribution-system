<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Models\VehicleExpense;
use Illuminate\Database\Eloquent\Model;

class VehicleExpensePolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::VEHICLE_EXPENSES_VIEW;
    protected const CREATE = PermissionName::VEHICLE_EXPENSES_CREATE;
    protected const UPDATE = PermissionName::VEHICLE_EXPENSES_UPDATE;
    protected const DELETE = PermissionName::VEHICLE_EXPENSES_DELETE;

    public function create(User $user): bool
    {
        return parent::create($user)
            || $this->createAdminException($user);
    }

    public function createAdminException(User $user): bool
    {
        return $this->allows($user, PermissionName::VEHICLE_EXPENSES_CREATE_ADMIN_EXCEPTION);
    }

    public function update(User $user, Model $record): bool
    {
        return $record instanceof VehicleExpense
            && $record->isPending()
            && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $record instanceof VehicleExpense
            && $record->isPending()
            && parent::delete($user, $record);
    }

    public function approve(User $user, VehicleExpense $record): bool
    {
        return $record->isPending()
            && $this->allowsMutation($user, $record, PermissionName::VEHICLE_EXPENSES_APPROVE);
    }

    public function reject(User $user, VehicleExpense $record): bool
    {
        return $record->isPending()
            && $this->allowsMutation($user, $record, PermissionName::VEHICLE_EXPENSES_REJECT);
    }

    public function print(User $user, VehicleExpense $record): bool
    {
        return $this->allowsRecord($user, $record, PermissionName::VEHICLE_EXPENSES_PRINT);
    }
}
