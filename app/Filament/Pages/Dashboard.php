<?php

namespace App\Filament\Pages;

use App\Enums\PermissionName;

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
        return 'متابعة تنفيذية للمبيعات والمقبوضات والربحية والتنبيهات التشغيلية.';
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }
}