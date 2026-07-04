<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DistributionOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('طلبات اليوم', '0')
                ->description('بانتظار بناء Module الطلبات')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('سيارات قيد التوزيع', '0')
                ->description('ستظهر بعد إضافة السيارات والرحلات')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('تحصيلات اليوم', '0')
                ->description('سترتبط لاحقًا بالمحاسبة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('تنبيهات المخزون', '0')
                ->description('نقص مخزون / قرب انتهاء صلاحية')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
        ];
    }
}