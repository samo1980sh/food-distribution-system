<?php

namespace App\Filament\Widgets;

use App\Enums\PermissionName;
use App\Services\Dashboard\ExecutiveDashboardService;
use Filament\Widgets\Widget;

class RecentOperationsWidget extends Widget
{
    protected string $view =
        'filament.widgets.recent-operations-widget';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::DASHBOARD_VIEW->value) === true;
    }

    public function getViewData(): array
    {
        return [
            'activities' => app(
                ExecutiveDashboardService::class
            )->recentActivity(limit: 10),
        ];
    }
}
