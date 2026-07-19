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

    protected string $view =
        'filament.resources.route-performance-reports.pages.manage-route-performance-reports';

    public string $analysisView = 'executive';

    public function getHeading(): string|Htmlable
    {
        return 'تقرير أداء خطوط التوزيع';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'يقيس صافي المبيعات والربح والمصاريف والمقبوضات وخدمة العملاء، مع إبقاء الخطوط دون نشاط في نهاية الترتيب.';
    }

    public function setAnalysisView(string $view): void
    {
        if (! array_key_exists($view, $this->getAnalysisViews())) {
            return;
        }

        $this->analysisView = $view;
    }

    public function getAnalysisViews(): array
    {
        return [
            'executive' => [
                'label' => 'النظرة التنفيذية',
                'icon' => 'heroicon-o-presentation-chart-line',
                'description' => 'خلاصة متوازنة للترتيب والنشاط والتغطية والمبيعات والمساهمة والمقبوضات وفرق الصندوق.',
            ],
            'sales' => [
                'label' => 'المبيعات والربحية',
                'icon' => 'heroicon-o-banknotes',
                'description' => 'قراءة مالية للمبيعات والمرتجعات والربح والمصاريف وصافي المساهمة وهامشها.',
            ],
            'collections' => [
                'label' => 'التحصيل والتسوية',
                'icon' => 'heroicon-o-scale',
                'description' => 'مقارنة صافي المبيعات بالمقبوضات وتغطيتها، مع فرق الصندوق وكمية التحميل.',
            ],
            'operations' => [
                'label' => 'التغطية والتشغيل',
                'icon' => 'heroicon-o-map',
                'description' => 'فريق الخط والسيارة والمنطقة وتغطية العملاء وحجم التحميل التشغيلي.',
            ],
        ];
    }

    public function getAnalysisViewDescription(): string
    {
        return $this->getAnalysisViews()[$this->analysisView]['description']
            ?? $this->getAnalysisViews()['executive']['description'];
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
