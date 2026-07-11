<?php

namespace App\Filament\Resources\DailyClosingReports\Pages;

use App\Filament\Resources\DailyClosingReports\DailyClosingReportResource;
use Filament\Actions\Action;
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
        return [
            Action::make('printFiltered')
                ->label('طباعة النتائج المفلترة')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(
                    fn (): string => route(
                        'reports.daily-closings.print-filtered',
                        ['state' => $this->encodePrintState()],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool => auth()->user()?->canManageDailyClosings() === true
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