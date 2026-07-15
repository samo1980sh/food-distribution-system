<?php

namespace App\Filament\Resources\CustomerPaymentReports\Pages;

use App\Enums\PermissionName;
use App\Filament\Resources\CustomerPaymentReports\CustomerPaymentReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageCustomerPaymentReports extends ManageRecords
{
    protected static string $resource = CustomerPaymentReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير التحصيلات';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'تحليل تحصيلات العملاء النقدية وغير النقدية حسب الفترة والعميل والفاتورة والمندوب.';
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
                        'reports.customer-payments.print-filtered',
                        ['state' => $this->encodePrintState()],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool => auth()->user()?->can(PermissionName::REPORT_CUSTOMER_PAYMENTS->value) === true
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