<?php

namespace App\Filament\Widgets;

use App\Enums\PermissionName;
use App\Services\Dashboard\ExecutiveDashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DistributionOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'المؤشرات الرئيسية';

    protected ?string $description =
        'ملخص الأداء المالي لليوم والشهر الحالي.';

    protected int|array|null $columns = [
        'md' => 2,
        'xl' => 4,
    ];

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::DASHBOARD_FINANCIAL->value) === true;
    }

    protected function getStats(): array
    {
        $summary = app(ExecutiveDashboardService::class)->summary();
        $trend = app(ExecutiveDashboardService::class)->trend(days: 14);
        $todayExpenses = (float) ($trend['expenses'][
            count($trend['expenses']) - 1
        ] ?? 0);

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
                ->icon('heroicon-o-shopping-cart')
                ->color('primary')
                ->url($this->reportUrl(
                    PermissionName::REPORT_SALES,
                    'filament.admin.resources.sales-reports.index',
                )),

            Stat::make(
                'مقبوضات اليوم',
                $this->money($summary['today_collections']),
            )
                ->description('نقد الفواتير والتحصيلات المعتمدة')
                ->descriptionIcon('heroicon-m-check-circle')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->url($this->reportUrl(
                    PermissionName::REPORT_CUSTOMER_PAYMENTS,
                    'filament.admin.resources.customer-payment-reports.index',
                )),

            Stat::make(
                'مرتجعات اليوم',
                $this->money($summary['today_returns']),
            )
                ->description('مرتجعات مبيعات مؤكدة')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color(
                    $summary['today_returns'] > 0
                        ? 'warning'
                        : 'gray'
                )
                ->url($this->reportUrl(
                    PermissionName::REPORT_SALES_RETURNS,
                    'filament.admin.resources.sales-return-reports.index',
                )),

            Stat::make(
                'مصاريف اليوم',
                $this->money($todayExpenses),
            )
                ->description('مصاريف سيارات معتمدة')
                ->descriptionIcon('heroicon-m-receipt-refund')
                ->icon('heroicon-o-credit-card')
                ->color(
                    $todayExpenses > 0 ? 'warning' : 'gray'
                )
                ->url($this->reportUrl(
                    PermissionName::REPORT_VEHICLE_EXPENSES,
                    'filament.admin.resources.vehicle-expense-reports.index',
                )),

            Stat::make(
                'صافي مبيعات الشهر',
                $this->money($summary['month_net_sales']),
            )
                ->description(
                    number_format($summary['month_invoice_count'])
                    .' فاتورة مؤكدة'
                )
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->icon('heroicon-o-chart-bar-square')
                ->color(
                    $summary['month_net_sales'] >= 0
                        ? 'success'
                        : 'danger'
                )
                ->url($this->reportUrl(
                    PermissionName::REPORT_SALES,
                    'filament.admin.resources.sales-reports.index',
                )),

            Stat::make(
                'مقبوضات الشهر',
                $this->money($summary['month_total_collections']),
            )
                ->description('الفواتير النقدية وسندات القبض')
                ->descriptionIcon('heroicon-m-banknotes')
                ->icon('heroicon-o-wallet')
                ->color('info')
                ->url($this->reportUrl(
                    PermissionName::REPORT_CUSTOMER_PAYMENTS,
                    'filament.admin.resources.customer-payment-reports.index',
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
                ->icon('heroicon-o-calculator')
                ->color(
                    $summary['month_approximate_profit'] >= 0
                        ? 'info'
                        : 'danger'
                )
                ->url($this->reportUrl(
                    PermissionName::REPORT_PROFIT,
                    'filament.admin.resources.profit-reports.index',
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
                ->icon('heroicon-o-user-group')
                ->color(
                    $summary['overdue_customers_count'] > 0
                        ? 'danger'
                        : 'success'
                )
                ->url($this->reportUrl(
                    PermissionName::REPORT_OVERDUE_CUSTOMERS,
                    'filament.admin.resources.overdue-customer-reports.index',
                )),
        ];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2).' ل.س';
    }

    private function reportUrl(
        PermissionName $permission,
        string $routeName,
    ): ?string {
        if (auth()->user()?->can($permission->value) !== true) {
            return null;
        }

        return route($routeName);
    }
}
