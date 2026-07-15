<?php

namespace App\Filament\Widgets;

use App\Enums\PermissionName;
use App\Services\Dashboard\ExecutiveDashboardService;
use Filament\Widgets\Widget;

class OperationalAlertsWidget extends Widget
{
    protected string $view =
        'filament.widgets.operational-alerts-widget';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::DASHBOARD_VIEW->value) === true;
    }

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    public function getViewData(): array
    {
        $service = app(ExecutiveDashboardService::class);

        return [
            'alerts' => $service->alerts(),
            'quickLinks' => $service->quickLinks(),
        ];
    }
}
