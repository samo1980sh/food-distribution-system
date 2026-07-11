<?php

namespace App\Filament\Resources\VehicleLoadReports\Pages;

use App\Filament\Resources\VehicleLoadReports\VehicleLoadReportResource;
use Filament\Actions\Action;
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
        return [
            Action::make('printFiltered')
                ->label('طباعة النتائج المفلترة')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(
                    fn (): string => route(
                        'reports.vehicle-loads.print-filtered',
                        ['state' => $this->encodePrintState()],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool => auth()->user()?->canManageDistribution() === true
                ),
        ];
    }

    private function encodePrintState(): string
    {
        $json = json_encode([
            'filters' => $this->tableFilters ?? [],
            'search' => $this->getTableSearch(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '';
        }

        return rtrim(
            strtr(base64_encode($json), '+/', '-_'),
            '=',
        );
    }
}
