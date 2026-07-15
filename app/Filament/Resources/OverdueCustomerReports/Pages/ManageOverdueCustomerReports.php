<?php

namespace App\Filament\Resources\OverdueCustomerReports\Pages;

use App\Enums\PermissionName;
use App\Filament\Resources\OverdueCustomerReports\OverdueCustomerReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageOverdueCustomerReports extends ManageRecords
{
    protected static string $resource = OverdueCustomerReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير العملاء المتأخرين بالدفع';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'الحد الافتراضي للتأخير 30 يومًا من تاريخ الفاتورة، مع توزيع التحصيلات والمرتجعات على أقدم المديونيات.';
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
                        'reports.overdue-customers.print-filtered',
                        ['state' => $this->encodePrintState()],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool =>
                        auth()->user()?->can(PermissionName::REPORT_OVERDUE_CUSTOMERS->value) === true
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
