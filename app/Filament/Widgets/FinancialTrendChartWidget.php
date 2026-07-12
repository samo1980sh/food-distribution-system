<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\ExecutiveDashboardService;
use Filament\Widgets\ChartWidget;

class FinancialTrendChartWidget extends ChartWidget
{
    protected ?string $heading =
        'الحركة المالية خلال آخر 14 يومًا';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    protected ?string $maxHeight = '360px';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->canManageSalesAndCollections()
            === true;
    }

    public function getDescription(): ?string
    {
        return 'المبيعات والمقبوضات والمرتجعات والمصاريف المعتمدة يوميًا.';
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
                    'backgroundColor' =>
                        'rgba(15, 118, 110, 0.15)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => 'المقبوضات',
                    'data' => $trend['collections'],
                    'borderColor' => 'rgb(22, 163, 74)',
                    'backgroundColor' =>
                        'rgba(22, 163, 74, 0.14)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => 'المرتجعات',
                    'data' => $trend['returns'],
                    'borderColor' => 'rgb(220, 38, 38)',
                    'backgroundColor' =>
                        'rgba(220, 38, 38, 0.12)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => 'المصاريف',
                    'data' => $trend['expenses'],
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' =>
                        'rgba(245, 158, 11, 0.12)',
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
