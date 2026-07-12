<?php

namespace App\Filament\Resources\TopCustomerReports\Pages;

use App\Filament\Resources\TopCustomerReports\TopCustomerReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageTopCustomerReports extends ManageRecords
{
    protected static string $resource = TopCustomerReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير العملاء الأكثر شراءً';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'الترتيب الافتراضي حسب صافي المبيعات بعد طرح المرتجعات المعتمدة.';
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
                        'reports.top-customers.print-filtered',
                        ['state' => $this->encodePrintState()],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool =>
                        auth()->user()?->canManageSalesAndCollections()
                            === true
                ),
        ];
    }

    private function encodePrintState(): string
    {
        $json = json_encode([
            'filters' => $this->tableFilters ?? [],
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
