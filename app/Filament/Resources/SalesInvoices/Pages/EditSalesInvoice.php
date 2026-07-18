<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\Actions\SalesInvoiceActions;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function getHeading(): string
    {
        return 'تعديل الفاتورة '.$this->record->invoice_number;
    }

    public function getSubheading(): ?string
    {
        return 'يمكن تعديل المسودة فقط. راجع المواد والقيم المالية قبل الاعتماد.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('عرض التفاصيل'),
            SalesInvoiceActions::print(),
            DeleteAction::make()
                ->label('حذف المسودة')
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->record) === true),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
