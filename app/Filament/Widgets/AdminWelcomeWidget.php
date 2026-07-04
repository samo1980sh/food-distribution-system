<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class AdminWelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.admin-welcome-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;
}