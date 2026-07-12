<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\ExecutiveDashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DistributionOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user
            && (
                $user->canManageSalesAndCollections()
                || $user->canManageDailyClosings()
            );
    }

    protected function getStats(): array
    {
        $summary = app(ExecutiveDashboardService::class)->summary();

        return [
            Stat::make(
                'مبيعات اليوم',
                $this->money($summary['today_sales']),
            )
                ->description(
                    number_format($summary['today_invoice_count'])
                    .' فاتورة معتمدة'
                )
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('primary')
                ->url(route(
                    'filament.admin.resources.sales-reports.index'
                )),

            Stat::make(
                'صافي مبيعات الشهر',
                $this->money($summary['month_net_sales']),
            )
                ->description(
                    'مرتجعات: '
                    .$this->money($summary['month_returns'])
                )
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color(
                    $summary['month_net_sales'] >= 0
                        ? 'success'
                        : 'danger'
                )
                ->url(route(
                    'filament.admin.resources.sales-reports.index'
                )),

            Stat::make(
                'مقبوضات الشهر',
                $this->money(
                    $summary['month_total_collections']
                ),
            )
                ->description(
                    'نقد الفواتير والتحصيلات المعتمدة'
                )
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url(route(
                    'filament.admin.resources.customer-payment-reports.index'
                )),

            Stat::make(
                'صافي مساهمة الشهر',
                $this->money(
                    $summary['month_net_contribution']
                ),
            )
                ->description(
                    'الربح بعد مصاريف السيارات'
                )
                ->descriptionIcon('heroicon-m-calculator')
                ->color(
                    $summary['month_net_contribution'] >= 0
                        ? 'success'
                        : 'danger'
                )
                ->url(route(
                    'filament.admin.resources.profit-reports.index'
                )),

            Stat::make(
                'الربح التقريبي',
                $this->money(
                    $summary['month_approximate_profit']
                ),
            )
                ->description(
                    'قبل مصاريف السيارات المعتمدة'
                )
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color(
                    $summary['month_approximate_profit'] >= 0
                        ? 'info'
                        : 'danger'
                )
                ->url(route(
                    'filament.admin.resources.profit-reports.index'
                )),

            Stat::make(
                'مصاريف الشهر',
                $this->money($summary['month_expenses']),
            )
                ->description('مصاريف سيارات معتمدة')
                ->descriptionIcon('heroicon-m-receipt-refund')
                ->color(
                    $summary['month_expenses'] > 0
                        ? 'warning'
                        : 'gray'
                )
                ->url(route(
                    'filament.admin.resources.vehicle-expense-reports.index'
                )),

            Stat::make(
                'العملاء المتأخرون',
                number_format(
                    $summary['overdue_customers_count']
                ),
            )
                ->description(
                    'القيمة: '
                    .$this->money($summary['overdue_amount'])
                )
                ->descriptionIcon('heroicon-m-user-minus')
                ->color(
                    $summary['overdue_customers_count'] > 0
                        ? 'danger'
                        : 'success'
                )
                ->url(route(
                    'filament.admin.resources.overdue-customer-reports.index'
                )),

            Stat::make(
                'إغلاقات اليوم',
                number_format(
                    $summary['today_confirmed_closings']
                ),
            )
                ->description(
                    $summary['today_missing_closing_warehouses'] > 0
                        ? number_format(
                            $summary[
                                'today_missing_closing_warehouses'
                            ]
                        ).' مستودع لم يُغلق'
                        : 'لا توجد حركة مفتوحة دون إغلاق'
                )
                ->descriptionIcon(
                    $summary['today_missing_closing_warehouses'] > 0
                        ? 'heroicon-m-lock-open'
                        : 'heroicon-m-lock-closed'
                )
                ->color(
                    $summary['today_missing_closing_warehouses'] > 0
                        ? 'danger'
                        : 'success'
                )
                ->url(route(
                    'filament.admin.resources.daily-closing-reports.index'
                )),
        ];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2).' ل.س';
    }
}
