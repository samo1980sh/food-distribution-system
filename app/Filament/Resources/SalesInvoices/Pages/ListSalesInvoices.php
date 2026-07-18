<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesInvoices extends ListRecords
{
    protected static string $resource = SalesInvoiceResource::class;

    public function getHeading(): string
    {
        return 'فواتير البيع';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة دورة الفاتورة من المسودة حتى الاعتماد أو الإلغاء، مع متابعة الاستحقاق والرصيد المتبقي.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('فاتورة بيع جديدة')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => SalesInvoiceResource::canCreate()),
        ];
    }
}
