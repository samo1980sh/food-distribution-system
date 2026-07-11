<?php

namespace App\Filament\Resources\DailyClosingReports\Pages;

use App\Filament\Resources\DailyClosingReports\DailyClosingReportResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageDailyClosingReports extends ManageRecords
{
    protected static string $resource = DailyClosingReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير الإغلاق اليومي';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'استعراض وتحليل إغلاقات الأيام مع الفلاتر والإجماليات المالية والتشغيلية.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}