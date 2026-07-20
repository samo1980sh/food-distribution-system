<?php

namespace App\Filament\Resources\SalesReturns\Pages;

use App\Enums\OperationSource;
use App\Filament\Resources\SalesReturns\SalesReturnResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesReturn extends CreateRecord
{
    protected static string $resource = SalesReturnResource::class;

    public function getHeading(): string
    {
        return 'إنشاء مرتجع إداري استثنائي';
    }

    public function getSubheading(): ?string
    {
        return 'هذا المسار للحالات الاستثنائية فقط. وثّق سبب الإدخال الإداري واربط المرتجع بالفاتورة الأصلية قبل الحفظ.';
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
