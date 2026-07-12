<?php

namespace App\Services\Dashboard;

use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Services\Reports\OverdueCustomerReportService;
use App\Services\Reports\ProfitReportQuery;
use App\Services\Reports\RoutePerformanceReportService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExecutiveDashboardService
{
    private static array $summaryCache = [];

    private static array $trendCache = [];

    public static function forgetCache(): void
    {
        self::$summaryCache = [];
        self::$trendCache = [];
    }

    public function summary(?string $asOf = null): array
    {
        $date = filled($asOf)
            ? Carbon::parse($asOf)
            : today();

        $today = $date->toDateString();
        $monthFrom = $date->copy()->startOfMonth()->toDateString();
        $cacheKey = $monthFrom.'|'.$today;

        if (array_key_exists($cacheKey, self::$summaryCache)) {
            return self::$summaryCache[$cacheKey];
        }

        $todayInvoices = SalesInvoice::query()
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', $today);

        $monthInvoices = SalesInvoice::query()
            ->where('status', 'confirmed')
            ->whereBetween('invoice_date', [$monthFrom, $today]);

        $todayReturns = SalesReturn::query()
            ->where('status', 'confirmed')
            ->whereDate('return_date', $today);

        $monthReturns = SalesReturn::query()
            ->where('status', 'confirmed')
            ->whereBetween('return_date', [$monthFrom, $today]);

        $todayPayments = CustomerPayment::query()
            ->where('status', 'confirmed')
            ->whereDate('payment_date', $today);

        $monthPayments = CustomerPayment::query()
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [$monthFrom, $today]);

        $monthExpenses = VehicleExpense::query()
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$monthFrom, $today]);

        $todaySales = (float) (clone $todayInvoices)->sum(
            'total_amount',
        );

        $monthSales = (float) (clone $monthInvoices)->sum(
            'total_amount',
        );

        $todayReturnAmount = (float) (clone $todayReturns)->sum(
            'total_amount',
        );

        $monthReturnAmount = (float) (clone $monthReturns)->sum(
            'total_amount',
        );

        $todayInvoiceCash = (float) (clone $todayInvoices)->sum(
            'invoice_cash_amount',
        );

        $monthInvoiceCash = (float) (clone $monthInvoices)->sum(
            'invoice_cash_amount',
        );

        $todayPaymentsAmount = (float) (clone $todayPayments)->sum(
            'amount',
        );

        $monthPaymentsAmount = (float) (clone $monthPayments)->sum(
            'amount',
        );

        $monthExpenseAmount = (float) (clone $monthExpenses)->sum(
            'amount',
        );

        $monthProfit = (float) app(ProfitReportQuery::class)
            ->build()
            ->whereBetween('entry_date', [$monthFrom, $today])
            ->sum('profit_amount');

        $overdue = app(OverdueCustomerReportService::class)
            ->filteredSummaries(
                creditDays:
                    OverdueCustomerReportService::DEFAULT_CREDIT_DAYS,
                asOf: $today,
                criteria: ['scope' => 'overdue'],
            );

        $todayClosings = DailyClosing::query()
            ->whereDate('closing_date', $today);

        $todayConfirmedClosings = (clone $todayClosings)
            ->where('status', 'confirmed')
            ->count();

        $todayClosingCount = (clone $todayClosings)->count();

        $activityWarehouses = $this->activityWarehouseIds(
            from: $today,
            until: $today,
        );

        $closedWarehouses = DailyClosing::query()
            ->whereDate('closing_date', $today)
            ->where('status', 'confirmed')
            ->whereIn('warehouse_id', $activityWarehouses)
            ->pluck('warehouse_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique();

        $missingClosingWarehouses = $activityWarehouses
            ->diff($closedWarehouses)
            ->values();

        return self::$summaryCache[$cacheKey] = [
            'as_of' => $today,
            'month_from' => $monthFrom,

            'active_vehicles' => Vehicle::query()
                ->where('status', 'active')
                ->count(),

            'today_sales' => $todaySales,
            'today_returns' => $todayReturnAmount,
            'today_net_sales' => $todaySales - $todayReturnAmount,
            'today_invoice_count' => (clone $todayInvoices)->count(),
            'today_collections' =>
                $todayInvoiceCash + $todayPaymentsAmount,

            'month_sales' => $monthSales,
            'month_returns' => $monthReturnAmount,
            'month_net_sales' => $monthSales - $monthReturnAmount,
            'month_invoice_count' => (clone $monthInvoices)->count(),
            'month_invoice_cash' => $monthInvoiceCash,
            'month_customer_payments' => $monthPaymentsAmount,
            'month_total_collections' =>
                $monthInvoiceCash + $monthPaymentsAmount,
            'month_expenses' => $monthExpenseAmount,
            'month_approximate_profit' => $monthProfit,
            'month_net_contribution' =>
                $monthProfit - $monthExpenseAmount,

            'overdue_customers_count' => $overdue->count(),
            'overdue_amount' => (float) $overdue->sum(
                'overdue_amount',
            ),

            'today_closing_count' => $todayClosingCount,
            'today_confirmed_closings' => $todayConfirmedClosings,
            'today_activity_warehouses' =>
                $activityWarehouses->count(),
            'today_missing_closing_warehouses' =>
                $missingClosingWarehouses->count(),
        ];
    }

    public function trend(
        int $days = 14,
        ?string $asOf = null,
    ): array {
        $days = min(max($days, 7), 31);

        $until = filled($asOf)
            ? Carbon::parse($asOf)->startOfDay()
            : today();

        $from = $until->copy()->subDays($days - 1);
        $cacheKey = $from->toDateString()
            .'|'.$until->toDateString()
            .'|'.$days;

        if (array_key_exists($cacheKey, self::$trendCache)) {
            return self::$trendCache[$cacheKey];
        }

        $sales = $this->dailyTotals(
            table: 'sales_invoices',
            dateColumn: 'invoice_date',
            amountColumn: 'total_amount',
            from: $from->toDateString(),
            until: $until->toDateString(),
            status: 'confirmed',
        );

        $invoiceCash = $this->dailyTotals(
            table: 'sales_invoices',
            dateColumn: 'invoice_date',
            amountColumn: 'invoice_cash_amount',
            from: $from->toDateString(),
            until: $until->toDateString(),
            status: 'confirmed',
        );

        $returns = $this->dailyTotals(
            table: 'sales_returns',
            dateColumn: 'return_date',
            amountColumn: 'total_amount',
            from: $from->toDateString(),
            until: $until->toDateString(),
            status: 'confirmed',
        );

        $payments = $this->dailyTotals(
            table: 'customer_payments',
            dateColumn: 'payment_date',
            amountColumn: 'amount',
            from: $from->toDateString(),
            until: $until->toDateString(),
            status: 'confirmed',
        );

        $expenses = $this->dailyTotals(
            table: 'vehicle_expenses',
            dateColumn: 'expense_date',
            amountColumn: 'amount',
            from: $from->toDateString(),
            until: $until->toDateString(),
            status: 'approved',
        );

        $labels = [];
        $dates = [];
        $salesValues = [];
        $returnValues = [];
        $collectionValues = [];
        $expenseValues = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->copy()->addDays($offset);
            $key = $date->toDateString();

            $labels[] = $date->format('d/m');
            $dates[] = $key;
            $salesValues[] = (float) ($sales[$key] ?? 0);
            $returnValues[] = (float) ($returns[$key] ?? 0);
            $collectionValues[] =
                (float) ($invoiceCash[$key] ?? 0)
                + (float) ($payments[$key] ?? 0);
            $expenseValues[] = (float) ($expenses[$key] ?? 0);
        }

        return self::$trendCache[$cacheKey] = [
            'from' => $from->toDateString(),
            'until' => $until->toDateString(),
            'labels' => $labels,
            'dates' => $dates,
            'sales' => $salesValues,
            'returns' => $returnValues,
            'collections' => $collectionValues,
            'expenses' => $expenseValues,
        ];
    }

    public function alerts(
        ?User $user = null,
        ?string $asOf = null,
    ): array {
        $user ??= auth()->user();

        if (! $user) {
            return [];
        }

        $date = filled($asOf)
            ? Carbon::parse($asOf)
            : today();

        $today = $date->toDateString();
        $monthFrom = $date->copy()->startOfMonth()->toDateString();
        $alerts = [];

        if ($user->canManageSalesAndCollections()) {
            $overdue = app(OverdueCustomerReportService::class)
                ->filteredSummaries(
                    creditDays:
                        OverdueCustomerReportService::DEFAULT_CREDIT_DAYS,
                    asOf: $today,
                    criteria: ['scope' => 'overdue'],
                );

            if ($overdue->isNotEmpty()) {
                $alerts[] = [
                    'level' => 'danger',
                    'title' => 'عملاء متأخرون بالسداد',
                    'value' => number_format($overdue->count())
                        .' عميل',
                    'description' => 'إجمالي المتأخر: '
                        .$this->formatMoney(
                            (float) $overdue->sum('overdue_amount')
                        ),
                    'icon' => 'heroicon-o-user-group',
                    'url' => route(
                        'filament.admin.resources.overdue-customer-reports.index'
                    ),
                ];
            }

            $unassigned = app(RoutePerformanceReportService::class)
                ->unassignedSummary([
                    'from' => $monthFrom,
                    'until' => $today,
                    'status' => 'all',
                ]);

            $unassignedCount =
                (int) $unassigned['invoice_count']
                + (int) $unassigned['return_count']
                + (int) $unassigned['payment_count']
                + (int) $unassigned['expense_count']
                + (int) $unassigned['load_count']
                + (int) $unassigned['closing_count'];

            if ($unassignedCount > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'title' => 'مستندات غير مربوطة بخط',
                    'value' => number_format($unassignedCount)
                        .' مستند',
                    'description' => 'راجع جودة ربط الحركة بخطوط التوزيع.',
                    'icon' => 'heroicon-o-link-slash',
                    'url' => route(
                        'filament.admin.resources.route-performance-reports.index'
                    ),
                ];
            }
        }

        if ($user->canManageInventory()) {
            $expiryRisk = DB::table('stock_balances')
                ->join(
                    'products',
                    'products.id',
                    '=',
                    'stock_balances.product_id',
                )
                ->where('stock_balances.quantity', '>', 0)
                ->where('products.has_expiry', true)
                ->where(function (Builder $query) use ($date): void {
                    $query
                        ->whereNull('stock_balances.expiry_date')
                        ->orWhereDate(
                            'stock_balances.expiry_date',
                            '<=',
                            $date->copy()->addDays(30)->toDateString(),
                        );
                })
                ->count();

            if ($expiryRisk > 0) {
                $alerts[] = [
                    'level' => 'danger',
                    'title' => 'أرصدة معرضة لخطر الصلاحية',
                    'value' => number_format($expiryRisk).' رصيد',
                    'description' => 'منتهية أو خلال 30 يومًا أو بلا تاريخ صلاحية.',
                    'icon' => 'heroicon-o-clock',
                    'url' => route(
                        'filament.admin.resources.expiry-risk-reports.index'
                    ),
                ];
            }

            $stockTotals = DB::table('stock_balances')
                ->select('product_id')
                ->selectRaw('SUM(quantity) as total_quantity')
                ->groupBy('product_id');

            $lowStock = DB::table('products')
                ->leftJoinSub(
                    $stockTotals,
                    'stock_totals',
                    'stock_totals.product_id',
                    '=',
                    'products.id',
                )
                ->where('products.status', 'active')
                ->where('products.min_stock', '>', 0)
                ->whereRaw(
                    'COALESCE(stock_totals.total_quantity, 0) <= products.min_stock'
                )
                ->count();

            if ($lowStock > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'title' => 'مواد عند الحد الأدنى',
                    'value' => number_format($lowStock).' مادة',
                    'description' => 'الرصيد الكلي أقل من الحد الأدنى أو يساويه.',
                    'icon' => 'heroicon-o-archive-box-arrow-down',
                    'url' => route(
                        'filament.admin.resources.stock-balances.index'
                    ),
                ];
            }
        }

        if ($user->canManageDistribution()) {
            $pendingExpenses = VehicleExpense::query()
                ->where('status', 'pending');

            $pendingCount = (clone $pendingExpenses)->count();

            if ($pendingCount > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'title' => 'مصاريف سيارات بانتظار الاعتماد',
                    'value' => number_format($pendingCount).' مصروف',
                    'description' => 'القيمة: '
                        .$this->formatMoney(
                            (float) (clone $pendingExpenses)->sum('amount')
                        ),
                    'icon' => 'heroicon-o-receipt-refund',
                    'url' => route(
                        'filament.admin.resources.vehicle-expenses.index'
                    ),
                ];
            }
        }

        if ($user->canManageDailyClosings()) {
            $summary = $this->summary($today);

            if ($summary['today_missing_closing_warehouses'] > 0) {
                $alerts[] = [
                    'level' => 'danger',
                    'title' => 'حركة يومية دون إغلاق',
                    'value' => number_format(
                        $summary['today_missing_closing_warehouses']
                    ).' مستودع',
                    'description' => 'توجد حركة اليوم ولم يُعتمد إغلاقها.',
                    'icon' => 'heroicon-o-lock-open',
                    'url' => route(
                        'filament.admin.resources.daily-closings.index'
                    ),
                ];
            }

            $cashDifferences = DailyClosing::query()
                ->whereDate('closing_date', $today)
                ->where('status', 'confirmed')
                ->whereRaw('ABS(cash_difference) > 0.0001');

            $cashDifferenceCount = (clone $cashDifferences)->count();

            if ($cashDifferenceCount > 0) {
                $alerts[] = [
                    'level' => 'danger',
                    'title' => 'فروقات صندوق اليوم',
                    'value' => number_format($cashDifferenceCount)
                        .' إغلاق',
                    'description' => 'صافي الفروقات: '
                        .$this->formatMoney(
                            (float) (clone $cashDifferences)->sum(
                                'cash_difference'
                            )
                        ),
                    'icon' => 'heroicon-o-scale',
                    'url' => route(
                        'filament.admin.resources.daily-closing-reports.index'
                    ),
                ];
            }
        }

        return $alerts;
    }

    public function quickLinks(?User $user = null): array
    {
        $user ??= auth()->user();

        if (! $user) {
            return [];
        }

        $links = [];

        if ($user->canManageSalesAndCollections()) {
            $links[] = [
                'label' => 'تقرير المبيعات',
                'icon' => 'heroicon-o-chart-bar',
                'url' => route(
                    'filament.admin.resources.sales-reports.index'
                ),
            ];

            $links[] = [
                'label' => 'العملاء المتأخرون',
                'icon' => 'heroicon-o-user-minus',
                'url' => route(
                    'filament.admin.resources.overdue-customer-reports.index'
                ),
            ];

            $links[] = [
                'label' => 'أداء الخطوط',
                'icon' => 'heroicon-o-map',
                'url' => route(
                    'filament.admin.resources.route-performance-reports.index'
                ),
            ];
        }

        if ($user->canManageInventory()) {
            $links[] = [
                'label' => 'أرصدة المخزون',
                'icon' => 'heroicon-o-archive-box',
                'url' => route(
                    'filament.admin.resources.stock-balances.index'
                ),
            ];
        }

        if ($user->canManageDailyClosings()) {
            $links[] = [
                'label' => 'الإغلاقات اليومية',
                'icon' => 'heroicon-o-clipboard-document-check',
                'url' => route(
                    'filament.admin.resources.daily-closings.index'
                ),
            ];
        }

        return $links;
    }

    private function activityWarehouseIds(
        string $from,
        string $until,
    ): Collection {
        $ids = collect();

        $definitions = [
            [
                'model' => SalesInvoice::class,
                'date' => 'invoice_date',
                'status' => 'confirmed',
            ],
            [
                'model' => SalesReturn::class,
                'date' => 'return_date',
                'status' => 'confirmed',
            ],
            [
                'model' => CustomerPayment::class,
                'date' => 'payment_date',
                'status' => 'confirmed',
            ],
            [
                'model' => VehicleExpense::class,
                'date' => 'expense_date',
                'status' => 'approved',
            ],
        ];

        foreach ($definitions as $definition) {
            $model = $definition['model'];

            $ids = $ids->concat(
                $model::query()
                    ->where('status', $definition['status'])
                    ->whereBetween(
                        $definition['date'],
                        [$from, $until],
                    )
                    ->whereNotNull('warehouse_id')
                    ->pluck('warehouse_id')
            );
        }

        return $ids
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function dailyTotals(
        string $table,
        string $dateColumn,
        string $amountColumn,
        string $from,
        string $until,
        string $status,
    ): array {
        return DB::table($table)
            ->select($dateColumn)
            ->selectRaw(
                "SUM({$amountColumn}) as aggregate"
            )
            ->where('status', $status)
            ->whereBetween($dateColumn, [$from, $until])
            ->groupBy($dateColumn)
            ->pluck('aggregate', $dateColumn)
            ->map(
                fn ($value): float => (float) $value
            )
            ->all();
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2).' ل.س';
    }
}
