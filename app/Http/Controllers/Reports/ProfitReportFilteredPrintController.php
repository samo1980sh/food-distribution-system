<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\Reports\ProfitReportQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class ProfitReportFilteredPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->can(PermissionName::REPORT_PROFIT->value) === true,
            403,
        );

        $state = $this->decodeState(
            (string) $request->query('state', ''),
        );

        $filters = is_array($state['filters'] ?? null)
            ? $state['filters']
            : [];

        $search = trim((string) ($state['search'] ?? ''));

        $dateState = is_array($filters['entry_date'] ?? null)
            ? $filters['entry_date']
            : [];

        $from = $this->normalizeDate($dateState['from'] ?? null);
        $until = $this->normalizeDate($dateState['until'] ?? null);

        $entryType = $this->filterValue($filters, 'entry_type');

        if (! in_array($entryType, ['invoice', 'return'], true)) {
            $entryType = null;
        }

        $customerId = $this->normalizeId(
            $this->filterValue($filters, 'customer_id'),
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

        $query = app(ProfitReportQuery::class)
            ->build()
            ->with([
                'customer',
                'warehouse',
                'vehicle',
                'route',
                'salesRepresentative',
            ])
            ->when(
                $from,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('entry_date', '>=', $date),
            )
            ->when(
                $until,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('entry_date', '<=', $date),
            )
            ->when(
                $entryType,
                fn (Builder $query, string $value): Builder => $query
                    ->where('entry_type', $value),
            )
            ->when(
                $customerId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('customer_id', $id),
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
                                ->where('document_number', 'like', $value)
                                ->orWhereHas(
                                    'customer',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value)
                                        ->orWhere('code', 'like', $value),
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
            );

        $salesAmount = (float) (clone $query)->sum('sales_amount');
        $costAmount = (float) (clone $query)->sum('cost_amount');
        $profitAmount = (float) (clone $query)->sum('profit_amount');

        $totals = [
            'count' => (clone $query)->count(),
            'invoice_count' => (clone $query)
                ->where('entry_type', 'invoice')
                ->count(),
            'return_count' => (clone $query)
                ->where('entry_type', 'return')
                ->count(),
            'quantity' => (float) (clone $query)->sum('quantity'),
            'sales_amount' => $salesAmount,
            'cost_amount' => $costAmount,
            'profit_amount' => $profitAmount,
            'margin_percent' => abs($salesAmount) < 0.0001
                ? 0
                : ($profitAmount / $salesAmount) * 100,
        ];

        $entries = $query
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        $entryTypeLabels = [
            'invoice' => 'فاتورة بيع',
            'return' => 'مرتجع بيع',
        ];

        $period = match (true) {
            filled($from) && filled($until) => $from.' — '.$until,
            filled($from) => 'من '.$from,
            filled($until) => 'حتى '.$until,
            default => null,
        };

        $filterSummary = array_filter([
            'الفترة' => $period,
            'نوع الحركة' => $entryType
                ? ($entryTypeLabels[$entryType] ?? $entryType)
                : null,
            'العميل' => $customerId
                ? Customer::query()->find($customerId)?->name
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

        return view('reports.profit.filtered-print', [
            'entries' => $entries,
            'totals' => $totals,
            'filterSummary' => $filterSummary,
            'entryTypeLabels' => $entryTypeLabels,
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
