<?php

namespace App\Filament\Widgets;

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
        $user = auth()->user();

        return $user
            && (
                $user->canManageInventory()
                || $user->canManageDistribution()
                || $user->canManageDailyClosings()
            );
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
