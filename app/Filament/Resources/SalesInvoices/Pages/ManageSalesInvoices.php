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
                ->visible(fn (): bool => SalesInvoiceResource::canCreate())
                ->label('إضافة فاتورة بيع')
                ->modalHeading('إضافة فاتورة بيع')
                ->slideOver(),
        ];
    }
}