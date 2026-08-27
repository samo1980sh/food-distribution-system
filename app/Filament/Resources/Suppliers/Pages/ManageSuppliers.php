<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSuppliers extends ManageRecords
{
    protected static string $resource = SupplierResource::class;

    public function getHeading(): string
    {
        return 'دليل الموردين';
    }

    public function getSubheading(): ?string
    {
        return 'الموردون المستخدمون في أوامر الشراء والاستلام الفعلي للمخزون.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => SupplierResource::canCreate())
                ->label('إضافة مورد')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة مورد')
                ->slideOver(),
        ];
    }
}
