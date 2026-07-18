<?php

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomerPayments extends ManageRecords
{
    protected static string $resource = CustomerPaymentResource::class;

    public function getHeading(): string
    {
        return 'تحصيلات العملاء';
    }

    public function getSubheading(): ?string
    {
        return 'تنفيذ التحصيلات بسرعة من المودال الجانبي، مع إبقاء صفحة تفاصيل كاملة للمراجعة والطباعة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('تحصيل عميل جديد')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة تحصيل عميل')
                ->modalDescription('حدد العميل والفاتورة والمبلغ وطريقة الدفع، ثم احفظ السند كمسودة لمراجعته قبل الاعتماد.')
                ->slideOver()
                ->visible(fn (): bool => CustomerPaymentResource::canCreate()),
        ];
    }
}
