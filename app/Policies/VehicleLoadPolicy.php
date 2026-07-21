<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Models\VehicleLoad;
use Illuminate\Database\Eloquent\Model;

class VehicleLoadPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::VEHICLE_LOADS_VIEW;
    protected const CREATE = PermissionName::VEHICLE_LOADS_CREATE;
    protected const UPDATE = PermissionName::VEHICLE_LOADS_UPDATE;
    protected const DELETE = PermissionName::VEHICLE_LOADS_DELETE;

    public function update(User $user, Model $record): bool
    {
        return $record instanceof VehicleLoad
            && $record->isDraft()
            && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $record instanceof VehicleLoad
            && $record->isDraft()
            && parent::delete($user, $record);
    }

    public function approve(User $user, VehicleLoad $record): bool
    {
        return $record->isDraft()
            && $this->allowsMutation($user, $record, PermissionName::VEHICLE_LOADS_APPROVE);
    }

    public function cancel(User $user, VehicleLoad $record): bool
    {
        return $record->isApproved()
            && $this->allowsMutation($user, $record, PermissionName::VEHICLE_LOADS_CANCEL);
    }

    public function acknowledge(User $user, VehicleLoad $record): bool
    {
        $employeeId = $user->employee()->value('id');

        return $employeeId !== null
            && in_array((int) $employeeId, array_filter([
                (int) $record->driver_id,
                (int) $record->sales_representative_id,
            ]), true)
            && $record->isApproved()
            && $record->isHandoverPending()
            && $this->allowsRecord($user, $record, PermissionName::VEHICLE_LOADS_VIEW);
    }

    public function print(User $user, VehicleLoad $record): bool
    {
        return $this->allowsRecord($user, $record, PermissionName::VEHICLE_LOADS_PRINT);
    }
}
