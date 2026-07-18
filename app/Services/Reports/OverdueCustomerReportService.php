<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\Authorization\AccessScopeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OverdueCustomerReportService
{
    public const DEFAULT_CREDIT_DAYS = 30;

    private static array $summaryCache = [];

    public static function forgetCache(): void
    {
        self::$summaryCache = [];
    }

    public function summaries(
        int $creditDays = self::DEFAULT_CREDIT_DAYS,
        ?string $asOf = null,
    ): Collection {
        $creditDays = $this->normalizeCreditDays($creditDays);
        $asOfDate = $this->normalizeAsOf($asOf);
        $cacheKey = app(AccessScopeService::class)->cacheKey()
            .'|'.$creditDays
            .'|'.$asOfDate;

        if (array_key_exists($cacheKey, self::$summaryCache)) {
            return self::$summaryCache[$cacheKey];
        }

        $customers = Customer::query()
            ->with(['area', 'route'])
            ->whereHas(
                'salesInvoices',
                fn ($query) => $query
                    ->where('status', 'confirmed')
                    ->whereDate('invoice_date', '<=', $asOfDate),
            )
            ->orderBy('id')
            ->get();

        if ($customers->isEmpty()) {
            return self::$summaryCache[$cacheKey] = collect();
        }

        $customerIds = $customers->pluck('id');

        $invoicesByCustomer = SalesInvoice::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', '<=', $asOfDate)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->groupBy('customer_id');

        $paymentsByCustomer = CustomerPayment::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'confirmed')
            ->whereDate('payment_date', '<=', $asOfDate)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->groupBy('customer_id');

        $returnsByCustomer = SalesReturn::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'confirmed')
            ->whereDate('return_date', '<=', $asOfDate)
            ->orderBy('return_date')
            ->orderBy('id')
            ->get()
            ->groupBy('customer_id');

        $summaries = $customers
            ->map(function (Customer $customer) use (
                $invoicesByCustomer,
                $paymentsByCustomer,
                $returnsByCustomer,
                $creditDays,
                $asOfDate,
            ): array {
                return $this->buildSummary(
                    customer: $customer,
                    invoices: $invoicesByCustomer->get($customer->id, collect()),
                    payments: $paymentsByCustomer->get($customer->id, collect()),
                    returns: $returnsByCustomer->get($customer->id, collect()),
                    creditDays: $creditDays,
                    asOfDate: $asOfDate,
                );
            })
            ->keyBy('customer_id');

        return self::$summaryCache[$cacheKey] = $summaries;
    }

    public function summaryForCustomer(
        int $customerId,
        int $creditDays = self::DEFAULT_CREDIT_DAYS,
        ?string $asOf = null,
    ): array {
        $summary = $this->summaries($creditDays, $asOf)->get($customerId);

        if (is_array($summary)) {
            return $summary;
        }

        $customer = Customer::query()
            ->with(['area', 'route'])
            ->findOrFail($customerId);

        return $this->emptySummary(
            customer: $customer,
            creditDays: $this->normalizeCreditDays($creditDays),
            asOfDate: $this->normalizeAsOf($asOf),
        );
    }

    public function filteredSummaries(
        int $creditDays = self::DEFAULT_CREDIT_DAYS,
        ?string $asOf = null,
        array $criteria = [],
    ): Collection {
        $scope = (string) ($criteria['scope'] ?? 'overdue');
        $risk = filled($criteria['risk'] ?? null)
            ? (string) $criteria['risk']
            : null;

        $minimumOverdue = max(
            (float) ($criteria['minimum_overdue'] ?? 0),
            0,
        );

        $areaId = $this->normalizeId($criteria['area_id'] ?? null);
        $routeId = $this->normalizeId($criteria['route_id'] ?? null);
        $paymentType = filled($criteria['payment_type'] ?? null)
            ? (string) $criteria['payment_type']
            : null;
        $customerType = filled($criteria['customer_type'] ?? null)
            ? (string) $criteria['customer_type']
            : null;
        $status = filled($criteria['status'] ?? null)
            ? (string) $criteria['status']
            : null;
        $search = trim((string) ($criteria['search'] ?? ''));

        return $this->summaries($creditDays, $asOf)
            ->filter(function (array $summary) use (
                $scope,
                $risk,
                $minimumOverdue,
                $areaId,
                $routeId,
                $paymentType,
                $customerType,
                $status,
                $search,
            ): bool {
                if ($scope === 'all_positive') {
                    if ((float) $summary['current_balance'] <= 0) {
                        return false;
                    }
                } elseif ((float) $summary['overdue_amount'] <= 0) {
                    return false;
                }

                if (
                    $minimumOverdue > 0
                    && (float) $summary['overdue_amount'] < $minimumOverdue
                ) {
                    return false;
                }

                if ($risk !== null && $summary['risk_status'] !== $risk) {
                    return false;
                }

                if (
                    $areaId !== null
                    && (int) ($summary['customer']['area_id'] ?? 0) !== $areaId
                ) {
                    return false;
                }

                if (
                    $routeId !== null
                    && (int) ($summary['customer']['route_id'] ?? 0) !== $routeId
                ) {
                    return false;
                }

                if (
                    $paymentType !== null
                    && $summary['customer']['payment_type'] !== $paymentType
                ) {
                    return false;
                }

                if (
                    $customerType !== null
                    && $summary['customer']['customer_type'] !== $customerType
                ) {
                    return false;
                }

                if (
                    $status !== null
                    && $summary['customer']['status'] !== $status
                ) {
                    return false;
                }

                if ($search !== '' && ! $this->matchesSearch($summary, $search)) {
                    return false;
                }

                return true;
            })
            ->sortBy([
                ['risk_rank', 'desc'],
                ['days_overdue', 'desc'],
                ['overdue_amount', 'desc'],
                ['customer.name', 'asc'],
            ])
            ->values();
    }

    public function customerIds(
        int $creditDays = self::DEFAULT_CREDIT_DAYS,
        ?string $asOf = null,
        array $criteria = [],
    ): array {
        return $this->filteredSummaries(
            creditDays: $creditDays,
            asOf: $asOf,
            criteria: $criteria,
        )
            ->pluck('customer_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function totals(Collection $summaries): array
    {
        return [
            'customers_count' => $summaries->count(),
            'current_balance' => (float) $summaries->sum('current_balance'),
            'overdue_amount' => (float) $summaries->sum('overdue_amount'),
            'not_due_amount' => (float) $summaries->sum('not_due_amount'),
            'overdue_invoices_count' => (int) $summaries->sum(
                'overdue_invoices_count',
            ),
            'over_limit_count' => $summaries
                ->where('risk_status', 'over_limit')
                ->count(),
            'high_risk_count' => $summaries
                ->where('risk_status', 'high')
                ->count(),
            'maximum_days_overdue' => (int) (
                $summaries->max('days_overdue') ?? 0
            ),
        ];
    }

    public function detailForCustomer(
        int $customerId,
        int $creditDays = self::DEFAULT_CREDIT_DAYS,
        ?string $asOf = null,
    ): array {
        $creditDays = $this->normalizeCreditDays($creditDays);
        $asOfDate = $this->normalizeAsOf($asOf);
        $summary = $this->summaryForCustomer(
            customerId: $customerId,
            creditDays: $creditDays,
            asOf: $asOfDate,
        );

        $payments = CustomerPayment::query()
            ->with('salesInvoice:id,invoice_number')
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->whereDate('payment_date', '<=', $asOfDate)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomerPayment $payment): array => [
                'date' => $payment->payment_date?->toDateString(),
                'document_number' => $payment->payment_number,
                'invoice_number' => $payment->salesInvoice?->invoice_number,
                'payment_method' => $payment->payment_method,
                'amount' => (float) $payment->amount,
                'reference_number' => $payment->reference_number,
            ])
            ->all();

        $returns = SalesReturn::query()
            ->with('salesInvoice:id,invoice_number')
            ->where('customer_id', $customerId)
            ->where('status', 'confirmed')
            ->whereDate('return_date', '<=', $asOfDate)
            ->orderBy('return_date')
            ->orderBy('id')
            ->get()
            ->map(fn (SalesReturn $salesReturn): array => [
                'date' => $salesReturn->return_date?->toDateString(),
                'document_number' => $salesReturn->return_number,
                'invoice_number' => $salesReturn->salesInvoice?->invoice_number,
                'amount' => (float) $salesReturn->total_amount,
                'reason' => $salesReturn->return_reason,
            ])
            ->all();

        return $summary + [
            'payments' => $payments,
            'returns' => $returns,
        ];
    }

    public static function riskOptions(): array
    {
        return [
            'normal' => 'طبيعي',
            'warning' => 'تنبيه',
            'high' => 'مخاطر مرتفعة',
            'over_limit' => 'متجاوز للحد الائتماني',
        ];
    }

    public static function riskLabel(string $risk): string
    {
        return self::riskOptions()[$risk] ?? $risk;
    }

    public static function riskColor(string $risk): string
    {
        return match ($risk) {
            'normal' => 'success',
            'warning' => 'warning',
            'high' => 'danger',
            'over_limit' => 'danger',
            default => 'gray',
        };
    }

    public static function paymentTypeOptions(): array
    {
        return [
            'cash' => 'نقدي',
            'credit' => 'آجل',
            'partial' => 'دفعة جزئية',
        ];
    }

    public static function paymentTypeLabel(?string $paymentType): string
    {
        return self::paymentTypeOptions()[$paymentType]
            ?? ($paymentType ?: '-');
    }

    public static function paymentMethodOptions(): array
    {
        return [
            'cash' => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'cheque' => 'شيك',
            'other' => 'أخرى',
        ];
    }

    public static function paymentMethodLabel(?string $paymentMethod): string
    {
        return self::paymentMethodOptions()[$paymentMethod]
            ?? ($paymentMethod ?: '-');
    }

    private function buildSummary(
        Customer $customer,
        Collection $invoices,
        Collection $payments,
        Collection $returns,
        int $creditDays,
        string $asOfDate,
    ): array {
        $asOf = Carbon::parse($asOfDate)->startOfDay();

        $invoiceCashTotal = (float) $invoices->sum('invoice_cash_amount');
        $paymentsTotal = (float) $payments->sum('amount');
        $returnsTotal = (float) $returns->sum('total_amount');

        $availableCredits = $paymentsTotal + $returnsTotal;

        $invoiceRows = $invoices
            ->map(function (SalesInvoice $invoice) use (
                &$availableCredits,
                $creditDays,
                $asOf,
            ): array {
                $invoiceDate = $invoice->invoice_date?->copy()->startOfDay()
                    ?? $asOf->copy();

                $dueDate = $invoice->due_date?->copy()->startOfDay()
                    ?? $invoiceDate->copy()->addDays($creditDays);

                $initialOutstanding = max(
                    (float) $invoice->total_amount
                    - (float) $invoice->invoice_cash_amount,
                    0,
                );

                $allocatedCredit = min(
                    $initialOutstanding,
                    max($availableCredits, 0),
                );

                $availableCredits = max(
                    $availableCredits - $allocatedCredit,
                    0,
                );

                $remaining = max(
                    $initialOutstanding - $allocatedCredit,
                    0,
                );

                $isOverdue = $remaining > 0 && $asOf->gt($dueDate);

                $daysOverdue = $isOverdue
                    ? $dueDate->diffInDays($asOf)
                    : 0;

                return [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoiceDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'payment_type' => $invoice->payment_type,
                    'total_amount' => (float) $invoice->total_amount,
                    'invoice_cash_amount' => (float) $invoice->invoice_cash_amount,
                    'initial_outstanding' => $initialOutstanding,
                    'allocated_credits' => $allocatedCredit,
                    'remaining_amount' => $remaining,
                    'overdue_amount' => $isOverdue ? $remaining : 0.0,
                    'not_due_amount' => $isOverdue ? 0.0 : $remaining,
                    'is_overdue' => $isOverdue,
                    'days_overdue' => $daysOverdue,
                ];
            })
            ->values();

        $outstandingRows = $invoiceRows
            ->where('remaining_amount', '>', 0)
            ->values();

        $overdueRows = $invoiceRows
            ->where('overdue_amount', '>', 0)
            ->values();

        $currentBalance = (float) $outstandingRows->sum('remaining_amount');
        $overdueAmount = (float) $overdueRows->sum('overdue_amount');
        $notDueAmount = max($currentBalance - $overdueAmount, 0);

        $oldestOutstandingDate = $outstandingRows
            ->min('invoice_date');

        $oldestOverdueDate = $overdueRows
            ->min('due_date');

        $daysOverdue = (int) (
            $overdueRows->max('days_overdue') ?? 0
        );

        $creditLimit = (float) $customer->credit_limit;

        $creditUsage = $creditLimit > 0
            ? ($currentBalance / $creditLimit) * 100
            : null;

        $riskStatus = $this->riskStatus(
            currentBalance: $currentBalance,
            overdueAmount: $overdueAmount,
            daysOverdue: $daysOverdue,
            creditLimit: $creditLimit,
        );

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
                'address' => $customer->address,
                'credit_limit' => $creditLimit,
                'payment_type' => $customer->payment_type,
                'status' => $customer->status,
            ],
            'credit_days' => $creditDays,
            'as_of' => $asOfDate,
            'confirmed_invoices_count' => $invoices->count(),
            'invoice_total' => (float) $invoices->sum('total_amount'),
            'invoice_cash_total' => $invoiceCashTotal,
            'payments_total' => $paymentsTotal,
            'returns_total' => $returnsTotal,
            'unapplied_credit' => max($availableCredits, 0),
            'current_balance' => $currentBalance,
            'overdue_amount' => $overdueAmount,
            'not_due_amount' => $notDueAmount,
            'outstanding_invoices_count' => $outstandingRows->count(),
            'overdue_invoices_count' => $overdueRows->count(),
            'oldest_outstanding_date' => $oldestOutstandingDate,
            'oldest_overdue_date' => $oldestOverdueDate,
            'days_overdue' => $daysOverdue,
            'credit_usage_percent' => $creditUsage,
            'risk_status' => $riskStatus,
            'risk_rank' => $this->riskRank($riskStatus),
            'invoices' => $invoiceRows->all(),
        ];
    }

    private function emptySummary(
        Customer $customer,
        int $creditDays,
        string $asOfDate,
    ): array {
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
                'address' => $customer->address,
                'credit_limit' => (float) $customer->credit_limit,
                'payment_type' => $customer->payment_type,
                'status' => $customer->status,
            ],
            'credit_days' => $creditDays,
            'as_of' => $asOfDate,
            'confirmed_invoices_count' => 0,
            'invoice_total' => 0.0,
            'invoice_cash_total' => 0.0,
            'payments_total' => 0.0,
            'returns_total' => 0.0,
            'unapplied_credit' => 0.0,
            'current_balance' => 0.0,
            'overdue_amount' => 0.0,
            'not_due_amount' => 0.0,
            'outstanding_invoices_count' => 0,
            'overdue_invoices_count' => 0,
            'oldest_outstanding_date' => null,
            'oldest_overdue_date' => null,
            'days_overdue' => 0,
            'credit_usage_percent' => null,
            'risk_status' => 'normal',
            'risk_rank' => 0,
            'invoices' => [],
        ];
    }

    private function riskStatus(
        float $currentBalance,
        float $overdueAmount,
        int $daysOverdue,
        float $creditLimit,
    ): string {
        if (
            $creditLimit > 0
            && $currentBalance - $creditLimit > 0.0001
        ) {
            return 'over_limit';
        }

        if (
            $overdueAmount > 0
            && (
                $daysOverdue >= 60
                || (
                    $creditLimit > 0
                    && $overdueAmount >= ($creditLimit * 0.75)
                )
            )
        ) {
            return 'high';
        }

        if ($overdueAmount > 0) {
            return 'warning';
        }

        return 'normal';
    }

    private function riskRank(string $risk): int
    {
        return match ($risk) {
            'over_limit' => 4,
            'high' => 3,
            'warning' => 2,
            'normal' => 1,
            default => 0,
        };
    }

    private function matchesSearch(array $summary, string $search): bool
    {
        $haystack = implode(' ', array_filter([
            $summary['customer']['code'] ?? null,
            $summary['customer']['name'] ?? null,
            $summary['customer']['owner_name'] ?? null,
            $summary['customer']['phone'] ?? null,
            $summary['customer']['mobile'] ?? null,
            $summary['customer']['area'] ?? null,
            $summary['customer']['route'] ?? null,
            $summary['customer']['address'] ?? null,
        ], fn (mixed $value): bool => filled($value)));

        return mb_stripos($haystack, $search) !== false;
    }

    private function normalizeCreditDays(int $creditDays): int
    {
        return min(max($creditDays, 1), 365);
    }

    private function normalizeAsOf(?string $asOf): string
    {
        return filled($asOf)
            ? Carbon::parse($asOf)->toDateString()
            : today()->toDateString();
    }

    private function normalizeId(mixed $value): ?int
    {
        if (! is_scalar($value) || ! ctype_digit((string) $value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
