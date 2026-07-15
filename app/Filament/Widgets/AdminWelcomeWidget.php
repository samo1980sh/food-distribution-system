<?php

namespace App\Filament\Widgets;

use App\Enums\PermissionName;
use Filament\Widgets\Widget;

class AdminWelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.admin-welcome-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::DASHBOARD_VIEW->value) === true;
    }
}