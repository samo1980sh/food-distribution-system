<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function getHeading(): string
    {
        return 'إنشاء فاتورة بيع';
    }

    public function getSubheading(): ?string
    {
        return 'أدخل العميل والسياق التشغيلي والمواد، ثم احفظ الفاتورة كمسودة لمراجعتها قبل الاعتماد.';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
