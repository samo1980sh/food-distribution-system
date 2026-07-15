<?php

namespace App\Filament\Resources\SalesReturns\Pages;

use App\Filament\Resources\SalesReturns\SalesReturnResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSalesReturns extends ManageRecords
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => SalesReturnResource::canCreate())
                ->label('إضافة مرتجع')
                ->modalHeading('إضافة مرتجع بيع')
                ->slideOver(),
        ];
    }
}