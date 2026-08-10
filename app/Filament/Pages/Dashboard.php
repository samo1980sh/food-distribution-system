<?php

namespace App\Filament\Pages;

use App\Enums\PermissionName;
use App\Filament\Widgets\DistributionOverviewWidget;
use App\Filament\Widgets\ExecutiveRankingsWidget;
use App\Filament\Widgets\FinancialTrendChartWidget;
use App\Filament\Widgets\OperationalAlertsWidget;
use App\Filament\Widgets\RecentOperationsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'لوحة التحكم';

    protected static ?string $title = 'لوحة التحكم';

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(PermissionName::DASHBOARD_VIEW->value) === true;
    }

    public function getTitle(): string|Htmlable
    {
        return 'لوحة التحكم';
    }

    public function getHeading(): string|Htmlable
    {
        return 'لوحة التحكم';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'نظرة شاملة على المبيعات والتحصيل والأداء التشغيلي';
    }

    public function getWidgets(): array
    {
        return [
            DistributionOverviewWidget::class,
            FinancialTrendChartWidget::class,
            OperationalAlertsWidget::class,
            RecentOperationsWidget::class,
            ExecutiveRankingsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }
}
