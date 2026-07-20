<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Enums\OperationSource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function getHeading(): string
    {
        return 'إنشاء فاتورة إدارية استثنائية';
    }

    public function getSubheading(): ?string
    {
        return 'هذا المسار مخصص لتعطل التطبيق أو البيع المكتبي الاستثنائي. سجّل السبب بدقة، ثم احفظ الفاتورة بانتظار المراجعة والاعتماد.';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['operation_source'] = OperationSource::ADMIN_EXCEPTION;
        $data['administrative_reason'] = trim((string) ($data['administrative_reason'] ?? ''));

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
