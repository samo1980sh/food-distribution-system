<?php

namespace App\Filament\Resources\ProfitReports\Pages;

use App\Filament\Resources\ProfitReports\ProfitReportResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageProfitReports extends ManageRecords
{
    protected static string $resource = ProfitReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير الأرباح التقريبية';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'صافي المبيعات وتكلفة البضاعة ومجمل الربح بعد احتساب فواتير البيع ومرتجعات البيع المعتمدة.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
