<?php

namespace App\Services\Reports;

use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Services\Authorization\AccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class RoutePerformanceReportService
{
    private static array $cache = [];

    public static function forgetCache(): void
    {
        self::$cache = [];
    }

    public function normalizeSettings(array $settings = []): array
    {
        $from = $this->date(
            $settings['from'] ?? null,
            today()->startOfMonth()->toDateString(),
        );

        $until = $this->date(
            $settings['until'] ?? null,
            today()->toDateString(),
        );

        if ($from > $until) {
            [$from, $until] = [$until, $from];
        }

        $metric = in_array(
            $settings['ranking_metric'] ?? null,
            array_keys(self::rankingMetricOptions()),
            true,
        ) ? (string) $settings['ranking_metric'] : 'net_contribution';

        $scope = in_array(
            $settings['scope'] ?? null,
            array_keys(self::scopeOptions()),
            true,
        ) ? (string) $settings['scope'] : 'all';

        $requestedLimit = (string) ($settings['limit'] ?? 'all');

        $limit = in_array(
            $requestedLimit,
            array_keys(self::limitOptions()),
            true,
        ) ? $requestedLimit : 'all';

        $status = in_array(
            $settings['status'] ?? null,
            array_keys(self::statusOptions()),
            true,
        ) ? (string) $settings['status'] : 'active';

        $minimumContribution = $settings['minimum_contribution'] ?? null;

        return [
            'from' => $from,
            'until' => $until,
            'ranking_metric' => $metric,
            'scope' => $scope,
            'limit' => $limit,
            'status' => $status,
            'route_id' => $this->id($settings['route_id'] ?? null),
            'area_id' => $this->id($settings['area_id'] ?? null),
            'vehicle_id' => $this->id($settings['vehicle_id'] ?? null),
            'driver_id' => $this->id($settings['driver_id'] ?? null),
            'sales_representative_id' => $this->id(
                $settings['sales_representative_id'] ?? null,
            ),
            'minimum_net_sales' => max(
                (float) ($settings['minimum_net_sales'] ?? 0),
                0,
            ),
            'minimum_contribution' => is_numeric($minimumContribution)
                ? (float) $minimumContribution
                : null,
            'search' => trim((string) ($settings['search'] ?? '')),
        ];
    }

    public function rankings(array $settings = []): Collection
    {
        $settings = $this->normalizeSettings($settings);
        $key = sha1(json_encode([
            'scope' => app(AccessScopeService::class)->cacheKey(),
            'settings' => $settings,
        ]) ?: '');

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $query = DistributionRoute::query()->with([
            'area',
            'vehicle',
            'driver',
            'salesRepresentative',
            'customers:id,route_id,status',
        ]);

        $this->applyFilters($query, $settings);
        $routes = $query->orderBy('id')->get();

        if ($routes->isEmpty()) {
            return self::$cache[$key] = collect();
        }

        $ids = $routes->pluck('id');

        $invoices = SalesInvoice::query()
            ->whereIn('route_id', $ids)
            ->where('status', 'confirmed')
            ->whereBetween('invoice_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->with('items')
            ->get()
            ->groupBy('route_id');

        $returns = SalesReturn::query()
            ->whereIn('route_id', $ids)
            ->where('status', 'confirmed')
            ->whereBetween('return_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->with('items')
            ->get()
            ->groupBy('route_id');

        $payments = CustomerPayment::query()
            ->whereIn('route_id', $ids)
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->get()
            ->groupBy('route_id');

        $expenses = VehicleExpense::query()
            ->whereIn('route_id', $ids)
            ->where('status', 'approved')
            ->whereBetween('expense_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->get()
            ->groupBy('route_id');

        $loads = VehicleLoad::query()
            ->whereIn('route_id', $ids)
            ->where('status', 'approved')
            ->whereBetween('load_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->get()
            ->groupBy('route_id');

        $closings = DailyClosing::query()
            ->whereIn('route_id', $ids)
            ->where('status', 'confirmed')
            ->whereBetween('closing_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->get()
            ->groupBy('route_id');

        $rows = $routes
            ->map(fn (DistributionRoute $route): array => $this->summary(
                route: $route,
                invoices: $invoices->get($route->id, collect()),
                returns: $returns->get($route->id, collect()),
                payments: $payments->get($route->id, collect()),
                expenses: $expenses->get($route->id, collect()),
                loads: $loads->get($route->id, collect()),
                closings: $closings->get($route->id, collect()),
            ))
            ->filter(function (array $row) use ($settings): bool {
                if (
                    $settings['scope'] === 'with_activity'
                    && ! $row['has_activity']
                ) {
                    return false;
                }

                if (
                    $settings['scope'] === 'without_activity'
                    && $row['has_activity']
                ) {
                    return false;
                }

                if ($row['net_sales'] < $settings['minimum_net_sales']) {
                    return false;
                }

                return $settings['minimum_contribution'] === null
                    || $row['net_contribution']
                        >= $settings['minimum_contribution'];
            })
            ->sort(function (array $a, array $b) use ($settings): int {
                $activity = (int) $b['has_activity']
                    <=> (int) $a['has_activity'];

                if ($activity !== 0) {
                    return $activity;
                }

                $metric = $settings['ranking_metric'];
                $metricOrder = (float) $b[$metric] <=> (float) $a[$metric];

                if ($metricOrder !== 0) {
                    return $metricOrder;
                }

                return (float) $b['net_sales'] <=> (float) $a['net_sales'];
            })
            ->values();

        if ($settings['limit'] !== 'all') {
            $rows = $rows->take((int) $settings['limit']);
        }

        return self::$cache[$key] = $rows
            ->values()
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            });
    }

    public function routeIds(array $settings = []): array
    {
        return $this->rankings($settings)
            ->pluck('route_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function summaryForRoute(
        int $routeId,
        array $settings = [],
    ): array {
        $row = $this->rankings($settings)
            ->firstWhere('route_id', $routeId);

        if (is_array($row)) {
            return $row;
        }

        return $this->detailForRoute($routeId, $settings)['summary'];
    }

    public function detailForRoute(
        int $routeId,
        array $settings = [],
    ): array {
        $settings = $this->normalizeSettings($settings);

        $route = DistributionRoute::query()->with([
            'area',
            'vehicle',
            'driver',
            'salesRepresentative',
            'customers:id,route_id,status',
        ])->findOrFail($routeId);

        $invoices = SalesInvoice::query()
            ->where('route_id', $routeId)
            ->where('status', 'confirmed')
            ->whereBetween('invoice_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->with(['items', 'customer:id,code,name'])
            ->orderBy('invoice_date')
            ->get();

        $returns = SalesReturn::query()
            ->where('route_id', $routeId)
            ->where('status', 'confirmed')
            ->whereBetween('return_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->with([
                'items',
                'customer:id,code,name',
                'salesInvoice:id,invoice_number',
            ])
            ->orderBy('return_date')
            ->get();

        $payments = CustomerPayment::query()
            ->where('route_id', $routeId)
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->with([
                'customer:id,code,name',
                'salesInvoice:id,invoice_number',
            ])
            ->orderBy('payment_date')
            ->get();

        $expenses = VehicleExpense::query()
            ->where('route_id', $routeId)
            ->where('status', 'approved')
            ->whereBetween('expense_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->orderBy('expense_date')
            ->get();

        $loads = VehicleLoad::query()
            ->where('route_id', $routeId)
            ->where('status', 'approved')
            ->whereBetween('load_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->orderBy('load_date')
            ->get();

        $closings = DailyClosing::query()
            ->where('route_id', $routeId)
            ->where('status', 'confirmed')
            ->whereBetween('closing_date', [
                $settings['from'],
                $settings['until'],
            ])
            ->orderBy('closing_date')
            ->get();

        return [
            'settings' => $settings,
            'summary' => $this->summary(
                $route,
                $invoices,
                $returns,
                $payments,
                $expenses,
                $loads,
                $closings,
            ),
            'invoices' => $invoices->map(function ($invoice): array {
                $cost = $this->cost($invoice->items);

                return [
                    'number' => $invoice->invoice_number,
                    'date' => $invoice->invoice_date?->toDateString(),
                    'customer' => $invoice->customer?->name,
                    'payment_type' => $invoice->payment_type,
                    'quantity' => (float) $invoice->items->sum('quantity'),
                    'total' => (float) $invoice->total_amount,
                    'cash' => (float) $invoice->invoice_cash_amount,
                    'cost' => $cost,
                    'profit' => (float) $invoice->total_amount - $cost,
                ];
            })->all(),
            'returns' => $returns->map(function ($return): array {
                $cost = $this->cost($return->items);

                return [
                    'number' => $return->return_number,
                    'date' => $return->return_date?->toDateString(),
                    'customer' => $return->customer?->name,
                    'invoice' => $return->salesInvoice?->invoice_number,
                    'reason' => $return->return_reason,
                    'quantity' => (float) $return->items->sum('quantity'),
                    'total' => (float) $return->total_amount,
                    'cost' => $cost,
                ];
            })->all(),
            'payments' => $payments->map(fn ($payment): array => [
                'number' => $payment->payment_number,
                'date' => $payment->payment_date?->toDateString(),
                'customer' => $payment->customer?->name,
                'invoice' => $payment->salesInvoice?->invoice_number,
                'method' => $payment->payment_method,
                'amount' => (float) $payment->amount,
            ])->all(),
            'expenses' => $expenses->map(fn ($expense): array => [
                'number' => $expense->expense_number,
                'date' => $expense->expense_date?->toDateString(),
                'type' => $expense->expense_type,
                'method' => $expense->payment_method,
                'amount' => (float) $expense->amount,
            ])->all(),
            'loads' => $loads->map(fn ($load): array => [
                'number' => $load->load_number,
                'date' => $load->load_date?->toDateString(),
                'quantity' => (float) $load->total_quantity,
                'cost' => (float) $load->total_cost,
            ])->all(),
            'closings' => $closings->map(fn ($closing): array => [
                'number' => $closing->closing_number,
                'date' => $closing->closing_date?->toDateString(),
                'expected' => (float) $closing->expected_cash_amount,
                'actual' => (float) $closing->actual_cash_amount,
                'difference' => (float) $closing->cash_difference,
            ])->all(),
        ];
    }

    public function totals(Collection $rows): array
    {
        $assigned = (int) $rows->sum('assigned_active_customers');
        $served = (int) $rows->sum('served_customers');
        $grossSales = (float) $rows->sum('gross_sales');
        $netSales = (float) $rows->sum('net_sales');
        $collections = (float) $rows->sum('total_collections');
        $contribution = (float) $rows->sum('net_contribution');

        return [
            'routes_count' => $rows->count(),
            'routes_with_activity' => $rows->where('has_activity', true)->count(),
            'assigned_active_customers' => $assigned,
            'served_customers' => $served,
            'service_coverage_percent' => $assigned > 0
                ? (float) (($served / $assigned) * 100)
                : null,
            'invoice_count' => (int) $rows->sum('invoice_count'),
            'return_count' => (int) $rows->sum('return_count'),
            'gross_sales' => $grossSales,
            'returns_amount' => (float) $rows->sum('returns_amount'),
            'net_sales' => $netSales,
            'net_quantity' => (float) $rows->sum('net_quantity'),
            'gross_profit' => (float) $rows->sum('gross_profit'),
            'vehicle_expenses' => (float) $rows->sum('vehicle_expenses'),
            'net_contribution' => $contribution,
            'contribution_margin_percent' => abs($netSales) > 0.0001
                ? ($contribution / $netSales) * 100
                : null,
            'total_collections' => $collections,
            'collection_coverage_percent' => $netSales > 0
                ? ($collections / $netSales) * 100
                : null,
            'loaded_quantity' => (float) $rows->sum('loaded_quantity'),
            'loaded_cost' => (float) $rows->sum('loaded_cost'),
            'cash_difference' => (float) $rows->sum('cash_difference'),
            'return_rate_percent' => $grossSales > 0
                ? ((float) $rows->sum('returns_amount') / $grossSales) * 100
                : null,
        ];
    }

    public function unassignedSummary(array $settings = []): array
    {
        $settings = $this->normalizeSettings($settings);

        $definitions = [
            'invoice' => [
                SalesInvoice::query()->whereNull('route_id')
                    ->where('status', 'confirmed')
                    ->whereBetween('invoice_date', [
                        $settings['from'],
                        $settings['until'],
                    ]),
                'total_amount',
            ],
            'return' => [
                SalesReturn::query()->whereNull('route_id')
                    ->where('status', 'confirmed')
                    ->whereBetween('return_date', [
                        $settings['from'],
                        $settings['until'],
                    ]),
                'total_amount',
            ],
            'payment' => [
                CustomerPayment::query()->whereNull('route_id')
                    ->where('status', 'confirmed')
                    ->whereBetween('payment_date', [
                        $settings['from'],
                        $settings['until'],
                    ]),
                'amount',
            ],
            'expense' => [
                VehicleExpense::query()->whereNull('route_id')
                    ->where('status', 'approved')
                    ->whereBetween('expense_date', [
                        $settings['from'],
                        $settings['until'],
                    ]),
                'amount',
            ],
            'load' => [
                VehicleLoad::query()->whereNull('route_id')
                    ->where('status', 'approved')
                    ->whereBetween('load_date', [
                        $settings['from'],
                        $settings['until'],
                    ]),
                'total_quantity',
            ],
            'closing' => [
                DailyClosing::query()->whereNull('route_id')
                    ->where('status', 'confirmed')
                    ->whereBetween('closing_date', [
                        $settings['from'],
                        $settings['until'],
                    ]),
                'cash_difference',
            ],
        ];

        $result = [];

        foreach ($definitions as $name => [$query, $column]) {
            $result[$name.'_count'] = (clone $query)->count();
            $result[$name.'_amount'] = (float) (clone $query)->sum($column);
        }

        return $result;
    }

    public static function rankingMetricOptions(): array
    {
        return [
            'net_contribution' => 'صافي المساهمة',
            'net_sales' => 'صافي المبيعات',
            'gross_profit' => 'الربح قبل المصاريف',
            'total_collections' => 'إجمالي المقبوضات',
            'served_customers' => 'عدد العملاء المخدومين',
            'invoice_count' => 'عدد الفواتير',
        ];
    }

    public static function rankingMetricLabel(string $metric): string
    {
        return self::rankingMetricOptions()[$metric] ?? $metric;
    }

    public static function scopeOptions(): array
    {
        return [
            'all' => 'جميع الخطوط',
            'with_activity' => 'الخطوط ذات النشاط فقط',
            'without_activity' => 'الخطوط دون نشاط فقط',
        ];
    }

    public static function limitOptions(): array
    {
        return [
            '10' => 'أفضل 10 خطوط',
            '20' => 'أفضل 20 خطًا',
            '50' => 'أفضل 50 خطًا',
            'all' => 'جميع الخطوط',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'active' => 'نشط',
            'inactive' => 'غير نشط',
            'all' => 'جميع الحالات',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status] ?? ($status ?: '-');
    }

    public static function paymentTypeLabel(?string $type): string
    {
        return [
            'cash' => 'نقدي',
            'credit' => 'آجل',
            'partial' => 'دفعة جزئية',
        ][$type] ?? ($type ?: '-');
    }

    public static function paymentMethodLabel(?string $method): string
    {
        return [
            'cash' => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'cheque' => 'شيك',
            'other' => 'أخرى',
        ][$method] ?? ($method ?: '-');
    }

    private function summary(
        DistributionRoute $route,
        Collection $invoices,
        Collection $returns,
        Collection $payments,
        Collection $expenses,
        Collection $loads,
        Collection $closings,
    ): array {
        $invoiceItems = $invoices->flatMap->items;
        $returnItems = $returns->flatMap->items;

        $grossSales = (float) $invoices->sum('total_amount');
        $returnsAmount = (float) $returns->sum('total_amount');
        $netSales = $grossSales - $returnsAmount;

        $soldQuantity = (float) $invoiceItems->sum('quantity');
        $returnedQuantity = (float) $returnItems->sum('quantity');

        $invoiceCost = $this->cost($invoiceItems);
        $returnCost = $this->cost($returnItems);
        $netCost = $invoiceCost - $returnCost;

        $grossProfit = $netSales - $netCost;
        $vehicleExpenses = (float) $expenses->sum('amount');
        $netContribution = $grossProfit - $vehicleExpenses;

        $invoiceCash = (float) $invoices->sum('invoice_cash_amount');
        $customerCollections = (float) $payments->sum('amount');
        $totalCollections = $invoiceCash + $customerCollections;

        $assigned = $route->customers->where('status', 'active')->count();
        $served = $invoices->pluck('customer_id')->filter()->unique()->count();

        $documents = $invoices->count()
            + $returns->count()
            + $payments->count()
            + $expenses->count()
            + $loads->count()
            + $closings->count();

        return [
            'route_id' => $route->id,
            'route' => [
                'code' => $route->code,
                'name' => $route->name,
                'status' => $route->status,
                'area' => $route->area?->name_ar,
                'vehicle' => $route->vehicle?->plate_number,
                'driver' => $route->driver?->name,
                'sales_representative' =>
                    $route->salesRepresentative?->name,
            ],
            'rank' => null,
            'has_activity' => $documents > 0,
            'assigned_active_customers' => $assigned,
            'served_customers' => $served,
            'service_coverage_percent' => $assigned > 0
                ? (float) (($served / $assigned) * 100)
                : null,
            'invoice_count' => $invoices->count(),
            'return_count' => $returns->count(),
            'payment_count' => $payments->count(),
            'expense_count' => $expenses->count(),
            'load_count' => $loads->count(),
            'closing_count' => $closings->count(),
            'gross_sales' => $grossSales,
            'returns_amount' => $returnsAmount,
            'net_sales' => $netSales,
            'net_quantity' => $soldQuantity - $returnedQuantity,
            'net_cost' => $netCost,
            'gross_profit' => $grossProfit,
            'vehicle_expenses' => $vehicleExpenses,
            'net_contribution' => $netContribution,
            'invoice_cash' => $invoiceCash,
            'customer_collections' => $customerCollections,
            'total_collections' => $totalCollections,
            'loaded_quantity' => (float) $loads->sum('total_quantity'),
            'loaded_cost' => (float) $loads->sum('total_cost'),
            'cash_difference' => (float) $closings->sum('cash_difference'),
            'average_invoice' => $invoices->count() > 0
                ? $grossSales / $invoices->count()
                : 0.0,
            'return_rate_percent' => $grossSales > 0
                ? ($returnsAmount / $grossSales) * 100
                : null,
            'collection_coverage_percent' => $netSales > 0
                ? ($totalCollections / $netSales) * 100
                : null,
            'contribution_margin_percent' => abs($netSales) > 0.0001
                ? ($netContribution / $netSales) * 100
                : null,
        ];
    }

    private function cost(Collection $items): float
    {
        return (float) $items->sum(
            fn ($item): float => (float) $item->total_cost > 0
                ? (float) $item->total_cost
                : (float) $item->quantity * (float) $item->unit_cost,
        );
    }

    private function applyFilters(Builder $query, array $settings): void
    {
        $query
            ->when(
                $settings['status'] !== 'all',
                fn (Builder $query): Builder =>
                    $query->where('status', $settings['status']),
            )
            ->when(
                $settings['route_id'],
                fn (Builder $query, int $id): Builder =>
                    $query->whereKey($id),
            )
            ->when(
                $settings['area_id'],
                fn (Builder $query, int $id): Builder =>
                    $query->where('area_id', $id),
            )
            ->when(
                $settings['vehicle_id'],
                fn (Builder $query, int $id): Builder =>
                    $query->where('vehicle_id', $id),
            )
            ->when(
                $settings['driver_id'],
                fn (Builder $query, int $id): Builder =>
                    $query->where('driver_id', $id),
            )
            ->when(
                $settings['sales_representative_id'],
                fn (Builder $query, int $id): Builder =>
                    $query->where('sales_representative_id', $id),
            )
            ->when(
                $settings['search'] !== '',
                function (Builder $query) use ($settings): Builder {
                    $value = '%'.$settings['search'].'%';

                    return $query->where(function (Builder $query) use ($value): void {
                        $query
                            ->where('code', 'like', $value)
                            ->orWhere('name', 'like', $value)
                            ->orWhereHas(
                                'area',
                                fn (Builder $query): Builder =>
                                    $query->where('name_ar', 'like', $value),
                            )
                            ->orWhereHas(
                                'vehicle',
                                fn (Builder $query): Builder =>
                                    $query->where('plate_number', 'like', $value),
                            )
                            ->orWhereHas(
                                'driver',
                                fn (Builder $query): Builder =>
                                    $query->where('name', 'like', $value),
                            )
                            ->orWhereHas(
                                'salesRepresentative',
                                fn (Builder $query): Builder =>
                                    $query->where('name', 'like', $value),
                            );
                    });
                },
            );
    }

    private function date(mixed $value, string $default): string
    {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return $default;
        }
    }

    private function id(mixed $value): ?int
    {
        if (! is_scalar($value) || ! ctype_digit((string) $value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
