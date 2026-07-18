<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\Actions\SalesInvoiceActions;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function getHeading(): string
    {
        return 'فاتورة '.$this->record->invoice_number;
    }

    public function getSubheading(): ?string
    {
        return 'عرض موحد للسياق التشغيلي والمواد والأرصدة وسجل الاعتماد.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('تعديل المسودة')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) === true),
            SalesInvoiceActions::confirm(),
            SalesInvoiceActions::cancel(),
            SalesInvoiceActions::print(),
        ];
    }
}
