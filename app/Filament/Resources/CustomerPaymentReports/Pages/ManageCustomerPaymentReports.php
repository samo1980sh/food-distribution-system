<?php

namespace App\Filament\Resources\CustomerPaymentReports\Pages;

use App\Filament\Resources\CustomerPaymentReports\CustomerPaymentReportResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageCustomerPaymentReports extends ManageRecords
{
    protected static string $resource = CustomerPaymentReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير التحصيلات';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'تحليل تحصيلات العملاء النقدية وغير النقدية حسب الفترة والعميل والفاتورة والمندوب.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}