<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Services\Authorization\AccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class TopCustomerReportService
{
    private static array $rankingCache = [];

    public static function forgetCache(): void
    {
        self::$rankingCache = [];
    }

    public function rankings(array $settings = []): Collection
    {
        $settings = $this->normalizeSettings($settings);
        $cacheKey = hash(
            'sha256',
            json_encode(
                [
                    'scope' => app(AccessScopeService::class)->cacheKey(),
                    'settings' => $settings,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ) ?: '',
        );

        if (array_key_exists($cacheKey, self::$rankingCache)) {
            return self::$rankingCache[$cacheKey];
        }

        $customerQuery = Customer::query()
            ->with(['area', 'route']);

        $this->applyCustomerCriteria($customerQuery, $settings);

        $candidateCustomers = $customerQuery
            ->orderBy('id')
            ->get();

        if ($candidateCustomers->isEmpty()) {
            return self::$rankingCache[$cacheKey] = collect();
        }

        $customerIds = $candidateCustomers->pluck('id');

        $invoicesByCustomer = SalesInvoice::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', '>=', $settings['from'])
            ->whereDate('invoice_date', '<=', $settings['until'])
            ->with('items')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->groupBy('customer_id');

        $returnsByCustomer = SalesReturn::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'confirmed')
            ->whereDate('return_date', '>=', $settings['from'])
            ->whereDate('return_date', '<=', $settings['until'])
            ->with('items')
            ->orderBy('return_date')
            ->orderBy('id')
            ->get()
            ->groupBy('customer_id');

        $summaries = $candidateCustomers
            ->map(function (Customer $customer) use (
                $invoicesByCustomer,
                $returnsByCustomer,
            ): array {
                return $this->buildSummary(
                    customer: $customer,
                    invoices: $invoicesByCustomer->get(
                        $customer->id,
                        collect(),
                    ),
                    returns: $returnsByCustomer->get(
                        $customer->id,
                        collect(),
                    ),
                );
            })
            ->filter(
                fn (array $summary): bool =>
                    (float) $summary['net_sales'] > 0
                    && (float) $summary['net_sales']
                        >= (float) $settings['minimum_net_sales'],
            )
            ->sort(function (array $left, array $right) use ($settings): int {
                $metric = $settings['ranking_metric'];

                $metricComparison = (float) $right[$metric]
                    <=> (float) $left[$metric];

                if ($metricComparison !== 0) {
                    return $metricComparison;
                }

                $netSalesComparison = (float) $right['net_sales']
                    <=> (float) $left['net_sales'];

                if ($netSalesComparison !== 0) {
                    return $netSalesComparison;
                }

                return strcmp(
                    (string) $left['customer']['name'],
                    (string) $right['customer']['name'],
                );
            })
            ->values();

        if ($settings['limit'] !== 'all') {
            $summaries = $summaries->take((int) $settings['limit']);
        }

        $displayedNetSales = (float) $summaries->sum('net_sales');

        $rankings = $summaries
            ->values()
            ->map(function (
                array $summary,
                int $index,
            ) use ($displayedNetSales): array {
                $summary['rank'] = $index + 1;
                $summary['net_sales_share_percent'] = $displayedNetSales > 0
                    ? ((float) $summary['net_sales'] / $displayedNetSales)
                        * 100
                    : 0.0;

                return $summary;
            });

        return self::$rankingCache[$cacheKey] = $rankings;
    }

    public function customerIds(array $settings = []): array
    {
        return $this->rankings($settings)
            ->pluck('customer_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function summaryForCustomer(
        int $customerId,
        array $settings = [],
    ): array {
        $summary = $this->rankings($settings)
            ->firstWhere('customer_id', $customerId);

        if (is_array($summary)) {
            return $summary;
        }

        return $this->detailForCustomer(
            customerId: $customerId,
            settings: $settings,
        )['summary'];
    }

    public function detailForCustomer(
        int $customerId,
        array $settings = [],
    ): array {
        $settings = $this->normalizeSettings($settings);

        $customer = Customer::query()
            ->with(['area', 'route'])
            ->findOrFail($customerId);

        $invoices = SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', '>=', $settings['from'])
            ->whereDate('invoice_date', '<=', $settings['until'])
            ->with([
                'items.product:id,sku,name_ar',
                'vehicle:id,plate_number',
                'route:id,name',
                'salesRepresentative:id,name',
            ])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $returns = SalesReturn::query()
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->whereDate('return_date', '>=', $settings['from'])
            ->whereDate('return_date', '<=', $settings['until'])
            ->with([
                'items.product:id,sku,name_ar',
                'salesInvoice:id,invoice_number',
            ])
            ->orderBy('return_date')
            ->orderBy('id')
            ->get();

        $summary = $this->buildSummary(
            customer: $customer,
            invoices: $invoices,
            returns: $returns,
        );

        return [
            'settings' => $settings,
            'summary' => $summary,
            'invoices' => $invoices
                ->map(function (SalesInvoice $invoice): array {
                    $cost = $this->invoiceCost($invoice->items);

                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'invoice_date' => $invoice->invoice_date?->toDateString(),
                        'payment_type' => $invoice->payment_type,
                        'vehicle' => $invoice->vehicle?->plate_number,
                        'route' => $invoice->route?->name,
                        'representative' => $invoice->salesRepresentative?->name,
                        'items_count' => $invoice->items->count(),
                        'quantity' => (float) $invoice->items->sum('quantity'),
                        'total_amount' => (float) $invoice->total_amount,
                        'cost_amount' => $cost,
                        'profit_amount' => (float) $invoice->total_amount - $cost,
                    ];
                })
                ->all(),
            'returns' => $returns
                ->map(function (SalesReturn $salesReturn): array {
                    $cost = $this->returnCost($salesReturn->items);

                    return [
                        'id' => $salesReturn->id,
                        'return_number' => $salesReturn->return_number,
                        'return_date' => $salesReturn->return_date?->toDateString(),
                        'invoice_number' => $salesReturn->salesInvoice?->invoice_number,
                        'reason' => $salesReturn->return_reason,
                        'items_count' => $salesReturn->items->count(),
                        'quantity' => (float) $salesReturn->items->sum('quantity'),
                        'total_amount' => (float) $salesReturn->total_amount,
                        'cost_amount' => $cost,
                        'profit_reversal' => (float) $salesReturn->total_amount
                            - $cost,
                    ];
                })
                ->all(),
        ];
    }

    public function totals(Collection $rankings): array
    {
        return [
            'customers_count' => $rankings->count(),
            'invoice_count' => (int) $rankings->sum('invoice_count'),
            'return_count' => (int) $rankings->sum('return_count'),
            'gross_sales' => (float) $rankings->sum('gross_sales'),
            'returns_amount' => (float) $rankings->sum('returns_amount'),
            'net_sales' => (float) $rankings->sum('net_sales'),
            'net_quantity' => (float) $rankings->sum('net_quantity'),
            'approximate_profit' => (float) $rankings->sum(
                'approximate_profit',
            ),
        ];
    }

    public function normalizeSettings(array $settings = []): array
    {
        $from = $this->normalizeDate(
            $settings['from'] ?? null,
            today()->startOfMonth()->toDateString(),
        );

        $until = $this->normalizeDate(
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
        )
            ? (string) $settings['ranking_metric']
            : 'net_sales';

        $rawLimit = (string) ($settings['limit'] ?? '10');

        $limit = $rawLimit === 'all'
            ? 'all'
            : (
                ctype_digit($rawLimit)
                && (int) $rawLimit >= 1
                && (int) $rawLimit <= 100
                    ? $rawLimit
                    : '10'
            );

        return [
            'from' => $from,
            'until' => $until,
            'ranking_metric' => $metric,
            'limit' => $limit,
            'customer_id' => $this->normalizeId(
                $settings['customer_id'] ?? null,
            ),
            'area_id' => $this->normalizeId(
                $settings['area_id'] ?? null,
            ),
            'route_id' => $this->normalizeId(
                $settings['route_id'] ?? null,
            ),
            'customer_type' => $this->normalizeOption(
                $settings['customer_type'] ?? null,
                array_keys(self::customerTypeOptions()),
            ),
            'payment_type' => $this->normalizeOption(
                $settings['payment_type'] ?? null,
                array_keys(self::paymentTypeOptions()),
            ),
            'status' => $this->normalizeOption(
                $settings['status'] ?? null,
                array_keys(self::statusOptions()),
            ),
            'minimum_net_sales' => max(
                (float) ($settings['minimum_net_sales'] ?? 0),
                0,
            ),
            'search' => trim((string) ($settings['search'] ?? '')),
        ];
    }

    public static function rankingMetricOptions(): array
    {
        return [
            'net_sales' => 'صافي المبيعات',
            'gross_sales' => 'إجمالي المبيعات',
            'invoice_count' => 'عدد الفواتير',
            'net_quantity' => 'صافي الكمية',
            'approximate_profit' => 'الربح التقريبي',
            'average_invoice' => 'متوسط قيمة الفاتورة',
        ];
    }

    public static function rankingMetricLabel(string $metric): string
    {
        return self::rankingMetricOptions()[$metric] ?? $metric;
    }

    public static function limitOptions(): array
    {
        return [
            '10' => 'أفضل 10 عملاء',
            '20' => 'أفضل 20 عميلًا',
            '50' => 'أفضل 50 عميلًا',
            '100' => 'أفضل 100 عميل',
            'all' => 'جميع العملاء',
        ];
    }

    public static function customerTypeOptions(): array
    {
        return [
            'grocery' => 'بقالية',
            'supermarket' => 'سوبر ماركت',
            'restaurant' => 'مطعم',
            'wholesaler' => 'تاجر جملة',
            'other' => 'أخرى',
        ];
    }

    public static function customerTypeLabel(?string $type): string
    {
        return self::customerTypeOptions()[$type] ?? ($type ?: '-');
    }

    public static function paymentTypeOptions(): array
    {
        return [
            'cash' => 'نقدي',
            'credit' => 'آجل',
            'partial' => 'دفعة جزئية',
        ];
    }

    public static function paymentTypeLabel(?string $type): string
    {
        return self::paymentTypeOptions()[$type] ?? ($type ?: '-');
    }

    public static function statusOptions(): array
    {
        return [
            'active' => 'نشط',
            'inactive' => 'غير نشط',
        ];
    }

    private function buildSummary(
        Customer $customer,
        Collection $invoices,
        Collection $returns,
    ): array {
        $grossSales = (float) $invoices->sum('total_amount');
        $returnsAmount = (float) $returns->sum('total_amount');

        $soldQuantity = (float) $invoices
            ->flatMap(fn (SalesInvoice $invoice): Collection => $invoice->items)
            ->sum('quantity');

        $returnedQuantity = (float) $returns
            ->flatMap(fn (SalesReturn $salesReturn): Collection => $salesReturn->items)
            ->sum('quantity');

        $invoiceCost = (float) $invoices->sum(
            fn (SalesInvoice $invoice): float =>
                $this->invoiceCost($invoice->items),
        );

        $returnCost = (float) $returns->sum(
            fn (SalesReturn $salesReturn): float =>
                $this->returnCost($salesReturn->items),
        );

        $netSales = $grossSales - $returnsAmount;
        $netQuantity = $soldQuantity - $returnedQuantity;
        $netCost = $invoiceCost - $returnCost;
        $approximateProfit = $netSales - $netCost;

        return [
            'customer_id' => $customer->id,
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'owner_name' => $customer->owner_name,
                'phone' => $customer->phone,
                'mobile' => $customer->mobile,
                'customer_type' => $customer->customer_type,
                'area_id' => $customer->area_id,
                'area' => $customer->area?->name_ar,
                'route_id' => $customer->route_id,
                'route' => $customer->route?->name,
                'payment_type' => $customer->payment_type,
                'status' => $customer->status,
                'address' => $customer->address,
            ],
            'invoice_count' => $invoices->count(),
            'return_count' => $returns->count(),
            'gross_sales' => $grossSales,
            'returns_amount' => $returnsAmount,
            'net_sales' => $netSales,
            'sold_quantity' => $soldQuantity,
            'returned_quantity' => $returnedQuantity,
            'net_quantity' => $netQuantity,
            'invoice_cost' => $invoiceCost,
            'return_cost' => $returnCost,
            'net_cost' => $netCost,
            'average_invoice' => $invoices->count() > 0
                ? $grossSales / $invoices->count()
                : 0.0,
            'approximate_profit' => $approximateProfit,
            'profit_margin_percent' => abs($netSales) > 0.0001
                ? ($approximateProfit / $netSales) * 100
                : null,
            'last_purchase_date' => $invoices->max(
                fn (SalesInvoice $invoice): ?string =>
                    $invoice->invoice_date?->toDateString(),
            ),
            'rank' => null,
            'net_sales_share_percent' => 0.0,
        ];
    }

    private function invoiceCost(Collection $items): float
    {
        return (float) $items->sum(
            fn (SalesInvoiceItem $item): float =>
                (float) $item->total_cost > 0
                    ? (float) $item->total_cost
                    : (float) $item->quantity
                        * (float) $item->unit_cost,
        );
    }

    private function returnCost(Collection $items): float
    {
        return (float) $items->sum(
            fn (SalesReturnItem $item): float =>
                (float) $item->total_cost > 0
                    ? (float) $item->total_cost
                    : (float) $item->quantity
                        * (float) $item->unit_cost,
        );
    }

    private function applyCustomerCriteria(
        Builder $query,
        array $settings,
    ): void {
        $query
            ->when(
                $settings['customer_id'],
                fn (Builder $query, int $id): Builder =>
                    $query->whereKey($id),
            )
            ->when(
                $settings['area_id'],
                fn (Builder $query, int $id): Builder =>
                    $query->where('area_id', $id),
            )
            ->when(
                $settings['route_id'],
                fn (Builder $query, int $id): Builder =>
                    $query->where('route_id', $id),
            )
            ->when(
                $settings['customer_type'],
                fn (Builder $query, string $type): Builder =>
                    $query->where('customer_type', $type),
            )
            ->when(
                $settings['payment_type'],
                fn (Builder $query, string $type): Builder =>
                    $query->where('payment_type', $type),
            )
            ->when(
                $settings['status'],
                fn (Builder $query, string $status): Builder =>
                    $query->where('status', $status),
            )
            ->when(
                $settings['search'] !== '',
                function (Builder $query) use ($settings): Builder {
                    $value = '%'.$settings['search'].'%';

                    return $query->where(
                        function (Builder $query) use ($value): void {
                            $query
                                ->where('code', 'like', $value)
                                ->orWhere('name', 'like', $value)
                                ->orWhere('owner_name', 'like', $value)
                                ->orWhere('phone', 'like', $value)
                                ->orWhere('mobile', 'like', $value)
                                ->orWhereHas(
                                    'area',
                                    fn (Builder $query): Builder =>
                                        $query->where('name_ar', 'like', $value),
                                )
                                ->orWhereHas(
                                    'route',
                                    fn (Builder $query): Builder =>
                                        $query->where('name', 'like', $value),
                                );
                        },
                    );
                },
            );
    }

    private function normalizeDate(
        mixed $value,
        string $default,
    ): string {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return $default;
        }
    }

    private function normalizeId(mixed $value): ?int
    {
        if (! is_scalar($value) || ! ctype_digit((string) $value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function normalizeOption(
        mixed $value,
        array $allowed,
    ): ?string {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return in_array($normalized, $allowed, true)
            ? $normalized
            : null;
    }
}
