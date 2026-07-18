<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomers extends ManageRecords
{
    protected static string $resource = CustomerResource::class;

    public function getHeading(): string
    {
        return 'دليل العملاء';
    }

    public function getSubheading(): ?string
    {
        return 'إدارة بيانات العميل والتوزيع والسياسة الائتمانية من مودال جانبي سريع مع المحافظة على السجل المالي.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => CustomerResource::canCreate())
                ->label('إضافة عميل')
                ->icon('heroicon-o-plus')
                ->modalHeading('إضافة عميل')
                ->modalDescription('أدخل الهوية والموقع وخط التوزيع والسياسة الائتمانية قبل حفظ العميل.')
                ->slideOver(),
        ];
    }
}
