<?php

namespace App\Filament\Resources\SalesReturnReports\Pages;

use App\Enums\PermissionName;
use App\Filament\Resources\SalesReturnReports\SalesReturnReportResource;
use Filament\Actions\Action;
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
        return [
            Action::make('printFiltered')
                ->label('طباعة النتائج المفلترة')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(
                    fn (): string => route(
                        'reports.sales-returns.print-filtered',
                        ['state' => $this->encodePrintState()],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool => auth()->user()?->can(PermissionName::REPORT_SALES_RETURNS->value) === true
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