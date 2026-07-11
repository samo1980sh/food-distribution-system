<?php

namespace App\Filament\Resources\SalesReturnReports\Pages;

use App\Filament\Resources\SalesReturnReports\SalesReturnReportResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageSalesReturnReports extends ManageRecords
{
    protected static string $resource = SalesReturnReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير مرتجعات البيع';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'تحليل مرتجعات العملاء حسب الفترة والحالة والسبب والفاتورة والمستودع والمندوب.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}