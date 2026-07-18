<?php

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Filament\Resources\CustomerPayments\Actions\CustomerPaymentActions;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerPayment extends ViewRecord
{
    protected static string $resource = CustomerPaymentResource::class;

    public function getHeading(): string
    {
        return 'تحصيل '.$this->record->payment_number;
    }

    public function getSubheading(): ?string
    {
        return 'عرض موحد للعميل والفاتورة وطريقة الدفع والسياق التشغيلي والأثر على الرصيد.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('تعديل المسودة')
                ->modalHeading('تعديل تحصيل عميل')
                ->slideOver()
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) === true),
            CustomerPaymentActions::confirm(),
            CustomerPaymentActions::cancel(),
            CustomerPaymentActions::print(),
        ];
    }
}
