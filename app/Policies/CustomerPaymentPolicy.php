<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\CustomerPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerPaymentPolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::CUSTOMER_PAYMENTS_VIEW;
    protected const CREATE = PermissionName::CUSTOMER_PAYMENTS_CREATE;
    protected const UPDATE = PermissionName::CUSTOMER_PAYMENTS_UPDATE;
    protected const DELETE = PermissionName::CUSTOMER_PAYMENTS_DELETE;

    public function update(User $user, Model $record): bool
    {
        return $record instanceof CustomerPayment
            && $record->isDraft()
            && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $record instanceof CustomerPayment
            && $record->isDraft()
            && parent::delete($user, $record);
    }

    public function confirm(User $user, CustomerPayment $record): bool
    {
        return $record->isDraft()
            && $this->allows($user, PermissionName::CUSTOMER_PAYMENTS_CONFIRM);
    }

    public function cancel(User $user, CustomerPayment $record): bool
    {
        return $record->isConfirmed()
            && $this->allows($user, PermissionName::CUSTOMER_PAYMENTS_CANCEL);
    }

    public function print(User $user, CustomerPayment $record): bool
    {
        return $this->allows($user, PermissionName::CUSTOMER_PAYMENTS_PRINT);
    }
}
