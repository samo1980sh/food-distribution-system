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
        return 'مراجعة الفواتير الواردة من تطبيق المندوب والتحقق من جاهزيتها للاعتماد، مع إبقاء الإدخال الإداري للحالات الاستثنائية فقط.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('فاتورة إدارية استثنائية')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => SalesInvoiceResource::canCreate()),
        ];
    }
}
