<?php

namespace App\Filament\Resources\RoutePerformanceReports\Pages;

use App\Enums\PermissionName;
use App\Filament\Resources\RoutePerformanceReports\RoutePerformanceReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageRoutePerformanceReports extends ManageRecords
{
    protected static string $resource =
        RoutePerformanceReportResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'تقرير أداء خطوط التوزيع';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'يقيس صافي المبيعات والربح والمصاريف والمقبوضات وخدمة العملاء، مع إبقاء الخطوط دون نشاط في نهاية الترتيب.';
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
                        'reports.route-performance.print-filtered',
                        ['state' => $this->encodeState()],
                    ),
                    shouldOpenInNewTab: true,
                )
                ->visible(
                    fn (): bool =>
                        auth()->user()?->can(PermissionName::REPORT_ROUTE_PERFORMANCE->value) === true
                ),
        ];
    }

    private function encodeState(): string
    {
        $json = json_encode([
            'filters' => $this->tableFilters ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false
            ? ''
            : rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
