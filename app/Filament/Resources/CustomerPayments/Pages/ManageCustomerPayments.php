<?php

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomerPayments extends ManageRecords
{
    protected static string $resource = CustomerPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->canManageSalesAndCollections() === true)
                ->label('إضافة تحصيل')
                ->modalHeading('إضافة تحصيل عميل')
                ->slideOver(),
        ];
    }
}