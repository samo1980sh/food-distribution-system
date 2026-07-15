<?php

namespace App\Filament\Widgets;

use App\Enums\PermissionName;
use App\Services\Dashboard\ExecutiveDashboardService;
use Filament\Widgets\Widget;

class OperationsFollowUpWidget extends Widget
{
    protected string $view =
        'filament.widgets.operations-follow-up-widget';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::DASHBOARD_OPERATIONAL->value) === true;
    }

    public function getViewData(): array
    {
        return [
            'items' => app(
                ExecutiveDashboardService::class
            )->operationalFollowUp(),
        ];
    }
}
