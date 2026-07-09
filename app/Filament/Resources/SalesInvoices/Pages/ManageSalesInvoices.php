<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSalesInvoices extends ManageRecords
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->hasAnyRole([\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_MANAGER, \App\Models\User::ROLE_SUPERVISOR]) === true)
                ->label('إضافة فاتورة بيع')
                ->modalHeading('إضافة فاتورة بيع')
                ->slideOver(),
        ];
    }
}