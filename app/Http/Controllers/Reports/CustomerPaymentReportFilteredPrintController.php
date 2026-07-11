<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\SalesInvoice;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class CustomerPaymentReportFilteredPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->canManageSalesAndCollections() === true,
            403,
        );

        $state = $this->decodeState(
            (string) $request->query('state', ''),
        );

        $filters = is_array($state['filters'] ?? null)
            ? $state['filters']
            : [];

        $search = trim((string) ($state['search'] ?? ''));

        $dateState = is_array($filters['payment_date'] ?? null)
            ? $filters['payment_date']
            : [];

        $from = $this->normalizeDate($dateState['from'] ?? null);
        $until = $this->normalizeDate($dateState['until'] ?? null);

        $status = $this->filterValue($filters, 'status');

        if (! in_array($status, ['draft', 'confirmed', 'cancelled'], true)) {
            $status = null;
        }

        $paymentMethod = $this->filterValue($filters, 'payment_method');

        if (! in_array(
            $paymentMethod,
            ['cash', 'bank_transfer', 'cheque', 'other'],
            true,
        )) {
            $paymentMethod = null;
        }

        $customerId = $this->normalizeId(
            $this->filterValue($filters, 'customer_id'),
        );

        $invoiceId = $this->normalizeId(
            $this->filterValue($filters, 'sales_invoice_id'),
        );

        $warehouseId = $this->normalizeId(
            $this->filterValue($filters, 'warehouse_id'),
        );

        $vehicleId = $this->normalizeId(
            $this->filterValue($filters, 'vehicle_id'),
        );

        $routeId = $this->normalizeId(
            $this->filterValue($filters, 'route_id'),
        );

        $representativeId = $this->normalizeId(
            $this->filterValue($filters, 'sales_representative_id'),
        );

        $payments = CustomerPayment::query()
            ->with([
                'customer',
                'salesInvoice',
                'warehouse',
                'vehicle',
                'route',
                'salesRepresentative',
            ])
            ->when(
                $from,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('payment_date', '>=', $date),
            )
            ->when(
                $until,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('payment_date', '<=', $date),
            )
            ->when(
                $status,
                fn (Builder $query, string $value): Builder => $query
                    ->where('status', $value),
            )
            ->when(
                $paymentMethod,
                fn (Builder $query, string $value): Builder => $query
                    ->where('payment_method', $value),
            )
            ->when(
                $customerId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('customer_id', $id),
            )
            ->when(
                $invoiceId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('sales_invoice_id', $id),
            )
            ->when(
                $warehouseId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('warehouse_id', $id),
            )
            ->when(
                $vehicleId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('vehicle_id', $id),
            )
            ->when(
                $routeId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('route_id', $id),
            )
            ->when(
                $representativeId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('sales_representative_id', $id),
            )
            ->when(
                $search !== '',
                function (Builder $query) use ($search): Builder {
                    $value = '%'.$search.'%';

                    return $query->where(
                        function (Builder $query) use ($value): void {
                            $query
                                ->where('payment_number', 'like', $value)
                                ->orWhere('reference_number', 'like', $value)
                                ->orWhereHas(
                                    'customer',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value)
                                        ->orWhere('code', 'like', $value),
                                )
                                ->orWhereHas(
                                    'salesInvoice',
                                    fn (Builder $query): Builder => $query
                                        ->where('invoice_number', 'like', $value),
                                )
                                ->orWhereHas(
                                    'warehouse',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value),
                                )
                                ->orWhereHas(
                                    'vehicle',
                                    fn (Builder $query): Builder => $query
                                        ->where('plate_number', 'like', $value),
                                )
                                ->orWhereHas(
                                    'route',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value),
                                )
                                ->orWhereHas(
                                    'salesRepresentative',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value),
                                );
                        },
                    );
                },
            )
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();

        $cashAmount = (float) $payments
            ->where('payment_method', 'cash')
            ->sum('amount');

        $bankTransferAmount = (float) $payments
            ->where('payment_method', 'bank_transfer')
            ->sum('amount');

        $chequeAmount = (float) $payments
            ->where('payment_method', 'cheque')
            ->sum('amount');

        $otherAmount = (float) $payments
            ->where('payment_method', 'other')
            ->sum('amount');

        $totals = [
            'count' => $payments->count(),
            'amount' => (float) $payments->sum('amount'),
            'cash_amount' => $cashAmount,
            'bank_transfer_amount' => $bankTransferAmount,
            'cheque_amount' => $chequeAmount,
            'other_amount' => $otherAmount,
            'non_cash_amount' => $bankTransferAmount
                + $chequeAmount
                + $otherAmount,
        ];

        $statusLabels = [
            'draft' => 'مسودة',
            'confirmed' => 'معتمد',
            'cancelled' => 'ملغي',
        ];

        $paymentMethodLabels = [
            'cash' => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'cheque' => 'شيك',
            'other' => 'أخرى',
        ];

        $period = match (true) {
            filled($from) && filled($until) => $from.' — '.$until,
            filled($from) => 'من '.$from,
            filled($until) => 'حتى '.$until,
            default => null,
        };

        $filterSummary = array_filter([
            'الفترة' => $period,
            'الحالة' => $status
                ? ($statusLabels[$status] ?? $status)
                : null,
            'طريقة الدفع' => $paymentMethod
                ? ($paymentMethodLabels[$paymentMethod] ?? $paymentMethod)
                : null,
            'العميل' => $customerId
                ? Customer::query()->find($customerId)?->name
                : null,
            'الفاتورة' => $invoiceId
                ? SalesInvoice::query()->find($invoiceId)?->invoice_number
                : null,
            'المستودع' => $warehouseId
                ? Warehouse::query()->find($warehouseId)?->name
                : null,
            'السيارة' => $vehicleId
                ? Vehicle::query()->find($vehicleId)?->plate_number
                : null,
            'خط التوزيع' => $routeId
                ? DistributionRoute::query()->find($routeId)?->name
                : null,
            'المندوب' => $representativeId
                ? Employee::query()->find($representativeId)?->name
                : null,
            'البحث' => $search !== '' ? $search : null,
        ], fn (mixed $value): bool => filled($value));

        return view('reports.customer-payments.filtered-print', [
            'payments' => $payments,
            'totals' => $totals,
            'filterSummary' => $filterSummary,
            'statusLabels' => $statusLabels,
            'paymentMethodLabels' => $paymentMethodLabels,
            'generatedBy' => Auth::user()?->name,
        ]);
    }

    private function decodeState(string $encoded): array
    {
        if ($encoded === '') {
            return [];
        }

        $base64 = strtr($encoded, '-_', '+/');
        $remainder = strlen($base64) % 4;

        if ($remainder > 0) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }

        $json = base64_decode($base64, true);

        if ($json === false) {
            return [];
        }

        $state = json_decode($json, true);

        return is_array($state) ? $state : [];
    }

    private function filterValue(array $filters, string $name): ?string
    {
        $state = $filters[$name] ?? null;

        if (! is_array($state)) {
            return is_scalar($state) && filled($state)
                ? (string) $state
                : null;
        }

        $value = $state['value'] ?? null;

        return is_scalar($value) && filled($value)
            ? (string) $value
            : null;
    }

    private function normalizeId(?string $value): ?int
    {
        if ($value === null || ! ctype_digit($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}