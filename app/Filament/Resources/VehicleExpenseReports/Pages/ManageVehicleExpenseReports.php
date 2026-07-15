<?php

namespace App\Filament\Resources\VehicleExpenseReports\Pages;

use App\Enums\PermissionName;
use App\Filament\Resources\VehicleExpenseReports\VehicleExpenseReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageVehicleExpenseReports extends ManageRecords
{
    protected static string $resource = VehicleExpenseReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير مصاريف السيارات';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'استعراض المصاريف المعتمدة فقط مع الفلاتر والإجماليات والطباعة.';
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
                        'reports.vehicle-expenses.print-filtered',
                        ['state' => $this->encodePrintState()],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool => auth()->user()?->can(PermissionName::REPORT_VEHICLE_EXPENSES->value) === true
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
