<?php

namespace App\Filament\Widgets;

use App\Enums\PermissionName;
use App\Services\Dashboard\ExecutiveDashboardService;
use Filament\Widgets\ChartWidget;

class FinancialTrendChartWidget extends ChartWidget
{
    protected ?string $heading =
        'اتجاه المبيعات والتحصيل خلال آخر 14 يومًا';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::DASHBOARD_FINANCIAL->value) === true;
    }

    public function getDescription(): ?string
    {
        return 'مقارنة يومية بين قيمة المبيعات والمبالغ المقبوضة.';
    }

    protected function getData(): array
    {
        $trend = app(ExecutiveDashboardService::class)
            ->trend(days: 14);

        return [
            'datasets' => [
                [
                    'label' => 'المبيعات',
                    'data' => $trend['sales'],
                    'borderColor' => 'rgb(15, 118, 110)',
                    'backgroundColor' => 'rgba(15, 118, 110, 0.15)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => 'المقبوضات',
                    'data' => $trend['collections'],
                    'borderColor' => 'rgb(22, 163, 74)',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.14)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'rtl' => true,
                    'textDirection' => 'rtl',
                ],
                'tooltip' => [
                    'rtl' => true,
                    'textDirection' => 'rtl',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
