<?php

namespace App\Filament\Widgets;

use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\SalesInvoice;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DistributionOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $today = now()->toDateString();

        $activeVehiclesCount = Vehicle::query()
            ->where('status', 'active')
            ->count();

        $todayConfirmedInvoicesQuery = SalesInvoice::query()
            ->whereDate('invoice_date', $today)
            ->where('status', 'confirmed');

        $todaySalesAmount = (float) (clone $todayConfirmedInvoicesQuery)->sum('total_amount');
        $todayInvoicesCount = (clone $todayConfirmedInvoicesQuery)->count();
        $todayInvoiceCashAmount = (float) (clone $todayConfirmedInvoicesQuery)->sum('invoice_cash_amount');
        $todayRemainingAmount = (float) (clone $todayConfirmedInvoicesQuery)->sum('remaining_amount');

        $todayConfirmedPaymentsQuery = CustomerPayment::query()
            ->whereDate('payment_date', $today)
            ->where('status', 'confirmed');

        $todayCollectionsAmount = (float) (clone $todayConfirmedPaymentsQuery)->sum('amount');
        $todayCollectionsCount = (clone $todayConfirmedPaymentsQuery)->count();

        $pendingVehicleExpensesQuery = VehicleExpense::query()
            ->where('status', 'pending');

        $pendingVehicleExpensesCount = (clone $pendingVehicleExpensesQuery)->count();
        $pendingVehicleExpensesAmount = (float) (clone $pendingVehicleExpensesQuery)->sum('amount');

        $todayClosingsQuery = DailyClosing::query()
            ->whereDate('closing_date', $today);

        $todayClosingsCount = (clone $todayClosingsQuery)->count();
        $todayConfirmedClosingsCount = (clone $todayClosingsQuery)
            ->where('status', 'confirmed')
            ->count();

        return [
            Stat::make('السيارات النشطة', number_format($activeVehiclesCount))
                ->description('عدد السيارات الفعالة ضمن الأسطول')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('مبيعات اليوم', $this->formatMoney($todaySalesAmount))
                ->description(number_format($todayInvoicesCount).' فاتورة معتمدة')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('primary'),

            Stat::make('نقد فواتير اليوم', $this->formatMoney($todayInvoiceCashAmount))
                ->description('متبقي: '.$this->formatMoney($todayRemainingAmount))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($todayRemainingAmount > 0 ? 'warning' : 'success'),

            Stat::make('تحصيلات اليوم', $this->formatMoney($todayCollectionsAmount))
                ->description(number_format($todayCollectionsCount).' عملية تحصيل معتمدة')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('مصاريف سيارات معلقة', number_format($pendingVehicleExpensesCount))
                ->description('القيمة: '.$this->formatMoney($pendingVehicleExpensesAmount))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($pendingVehicleExpensesCount > 0 ? 'warning' : 'gray'),

            Stat::make('إغلاقات اليوم', number_format($todayClosingsCount))
                ->description('المعتمد: '.number_format($todayConfirmedClosingsCount))
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($todayConfirmedClosingsCount > 0 ? 'success' : 'gray'),
        ];
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2).' SYP';
    }
}