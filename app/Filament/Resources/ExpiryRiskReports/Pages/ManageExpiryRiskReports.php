<?php

namespace App\Filament\Resources\ExpiryRiskReports\Pages;

use App\Filament\Resources\ExpiryRiskReports\ExpiryRiskReportResource;
use App\Models\StockBalance;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ManageExpiryRiskReports extends ManageRecords
{
    protected static string $resource = ExpiryRiskReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير المواد القريبة من الانتهاء';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $missingCount = StockBalance::query()
            ->where('quantity', '>', 0)
            ->whereNull('expiry_date')
            ->whereHas(
                'product',
                fn (Builder $query): Builder => $query
                    ->where('has_expiry', true),
            )
            ->count();

        if ($missingCount > 0) {
            return "تنبيه: يوجد {$missingCount} رصيد لمنتجات تتطلب صلاحية لكن تاريخ الصلاحية غير مسجل.";
        }

        return 'متابعة الأرصدة المنتهية والقريبة من الانتهاء وقيمة المخزون المعرّضة للخطر.';
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
                        'reports.expiry-risk.print-filtered',
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
