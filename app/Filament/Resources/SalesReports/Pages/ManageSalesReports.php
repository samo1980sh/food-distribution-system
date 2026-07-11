<?php

namespace App\Filament\Resources\SalesReports\Pages;

use App\Filament\Resources\SalesReports\SalesReportResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageSalesReports extends ManageRecords
{
    protected static string $resource = SalesReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير المبيعات';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'تحليل فواتير البيع والمدفوعات والأرصدة المتبقية حسب الفترة والعميل والمندوب والمستودع.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}