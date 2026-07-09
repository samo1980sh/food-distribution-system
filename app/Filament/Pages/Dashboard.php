<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'لوحة التحكم';

    protected static ?string $title = 'لوحة التحكم';

    protected static ?int $navigationSort = 0;

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
        return 'متابعة يومية للمبيعات، التحصيلات، مصاريف السيارات، وإغلاقات التوزيع.';
    }
}