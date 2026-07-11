<?php

namespace App\Filament\Resources\VehicleLoadReports\Pages;

use App\Filament\Resources\VehicleLoadReports\VehicleLoadReportResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageVehicleLoadReports extends ManageRecords
{
    protected static string $resource = VehicleLoadReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير تحميلات السيارات';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'متابعة أوامر تحميل السيارات حسب الفترة والحالة والسيارة وخط التوزيع والمستودعات والموظفين.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
