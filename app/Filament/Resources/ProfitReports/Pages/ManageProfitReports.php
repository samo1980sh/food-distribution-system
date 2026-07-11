<?php

namespace App\Filament\Resources\ProfitReports\Pages;

use App\Filament\Resources\ProfitReports\ProfitReportResource;
use Filament\Actions\Action;
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
        return [
            Action::make('printFiltered')
                ->label('طباعة النتائج المفلترة')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(
                    fn (): string => route(
                        'reports.profit.print-filtered',
                        ['state' => $this->encodePrintState()],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool => auth()->user()?->canManageSalesAndCollections() === true
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
