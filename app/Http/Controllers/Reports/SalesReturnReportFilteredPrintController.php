<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class SalesReturnReportFilteredPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->can(PermissionName::REPORT_SALES_RETURNS->value) === true,
            403,
        );

        $state = $this->decodeState(
            (string) $request->query('state', ''),
        );

        $filters = is_array($state['filters'] ?? null)
            ? $state['filters']
            : [];

        $search = trim((string) ($state['search'] ?? ''));

        $dateState = is_array($filters['return_date'] ?? null)
            ? $filters['return_date']
            : [];

        $from = $this->normalizeDate($dateState['from'] ?? null);
        $until = $this->normalizeDate($dateState['until'] ?? null);

        $status = $this->filterValue($filters, 'status');

        if (! in_array($status, ['draft', 'confirmed', 'cancelled'], true)) {
            $status = null;
        }

        $returnReason = $this->filterValue($filters, 'return_reason');

        if (! in_array(
            $returnReason,
            [
                'expired',
                'damaged',
                'customer_refused',
                'wrong_item',
                'other',
            ],
            true,
        )) {
            $returnReason = null;
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

        $returns = SalesReturn::query()
            ->with([
                'customer',
                'salesInvoice',
                'warehouse',
                'vehicle',
                'route',
                'salesRepresentative',
            ])
            ->withCount('items')
            ->when(
                $from,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('return_date', '>=', $date),
            )
            ->when(
                $until,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('return_date', '<=', $date),
            )
            ->when(
                $status,
                fn (Builder $query, string $value): Builder => $query
                    ->where('status', $value),
            )
            ->when(
                $returnReason,
                fn (Builder $query, string $value): Builder => $query
                    ->where('return_reason', $value),
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
                                ->where('return_number', 'like', $value)
                                ->orWhere('notes', 'like', $value)
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
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->get();

        $totals = [
            'count' => $returns->count(),
            'items_count' => (int) $returns->sum('items_count'),
            'subtotal' => (float) $returns->sum('subtotal'),
            'discount_amount' => (float) $returns->sum('discount_amount'),
            'total_amount' => (float) $returns->sum('total_amount'),
        ];

        $statusLabels = [
            'draft' => 'مسودة',
            'confirmed' => 'معتمد',
            'cancelled' => 'ملغي',
        ];

        $reasonLabels = [
            'expired' => 'منتهي الصلاحية',
            'damaged' => 'تالف',
            'customer_refused' => 'رفض العميل',
            'wrong_item' => 'مادة خاطئة',
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
            'سبب المرتجع' => $returnReason
                ? ($reasonLabels[$returnReason] ?? $returnReason)
                : null,
            'العميل' => $customerId
                ? Customer::query()->find($customerId)?->name
                : null,
            'الفاتورة الأصلية' => $invoiceId
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

        return view('reports.sales-returns.filtered-print', [
            'returns' => $returns,
            'totals' => $totals,
            'filterSummary' => $filterSummary,
            'statusLabels' => $statusLabels,
            'reasonLabels' => $reasonLabels,
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