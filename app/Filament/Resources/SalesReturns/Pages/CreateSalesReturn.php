<?php

namespace App\Filament\Resources\SalesReturns\Pages;

use App\Filament\Resources\SalesReturns\SalesReturnResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesReturn extends CreateRecord
{
    protected static string $resource = SalesReturnResource::class;

    public function getHeading(): string
    {
        return 'إنشاء مرتجع بيع';
    }

    public function getSubheading(): ?string
    {
        return 'اربط المرتجع بالفاتورة الأصلية، وحدد المواد والكميات المستلمة، ثم احفظه كمسودة للمراجعة قبل الاعتماد.';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
