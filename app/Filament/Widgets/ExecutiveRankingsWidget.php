<?php

namespace App\Filament\Widgets;

use App\Enums\PermissionName;
use App\Services\Dashboard\ExecutiveDashboardService;
use Filament\Widgets\Widget;

class ExecutiveRankingsWidget extends Widget
{
    protected string $view =
        'filament.widgets.executive-rankings-widget';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::DASHBOARD_FINANCIAL->value) === true;
    }

    public function getViewData(): array
    {
        return app(ExecutiveDashboardService::class)
            ->executiveRankings();
    }
}
