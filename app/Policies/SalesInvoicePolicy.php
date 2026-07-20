<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SalesInvoicePolicy extends PermissionPolicy
{
    protected const VIEW_ANY = PermissionName::SALES_INVOICES_VIEW;
    protected const CREATE = PermissionName::SALES_INVOICES_CREATE;
    protected const UPDATE = PermissionName::SALES_INVOICES_UPDATE;
    protected const DELETE = PermissionName::SALES_INVOICES_DELETE;

    public function create(User $user): bool
    {
        return parent::create($user)
            || $this->createAdminException($user);
    }

    public function createAdminException(User $user): bool
    {
        return $this->allows($user, PermissionName::SALES_INVOICES_CREATE_ADMIN_EXCEPTION);
    }

    public function update(User $user, Model $record): bool
    {
        return $record instanceof SalesInvoice
            && $record->isDraft()
            && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $record instanceof SalesInvoice
            && $record->isDraft()
            && parent::delete($user, $record);
    }

    public function confirm(User $user, SalesInvoice $record): bool
    {
        return $record->isDraft()
            && $this->allowsMutation($user, $record, PermissionName::SALES_INVOICES_CONFIRM);
    }

    public function cancel(User $user, SalesInvoice $record): bool
    {
        return $record->isConfirmed()
            && $this->allowsMutation($user, $record, PermissionName::SALES_INVOICES_CANCEL);
    }

    public function print(User $user, SalesInvoice $record): bool
    {
        return $this->allowsRecord($user, $record, PermissionName::SALES_INVOICES_PRINT);
    }
}
