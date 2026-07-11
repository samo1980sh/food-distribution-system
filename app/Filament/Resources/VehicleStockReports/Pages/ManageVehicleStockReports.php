<?php

namespace App\Filament\Resources\VehicleStockReports\Pages;

use App\Filament\Resources\VehicleStockReports\VehicleStockReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageVehicleStockReports extends ManageRecords
{
    protected static string $resource = VehicleStockReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير مخزون السيارات';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'عرض الأرصدة الحالية داخل مستودعات السيارات حسب المنتج والتشغيلة والصلاحية.';
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
                        'reports.vehicle-stock.print-filtered',
                        ['state' => $this->encodePrintState()],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool => auth()->user()?->canManageInventory() === true
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
