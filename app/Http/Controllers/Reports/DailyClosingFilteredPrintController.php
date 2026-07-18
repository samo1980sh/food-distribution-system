<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class DailyClosingFilteredPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->can(PermissionName::REPORT_DAILY_CLOSINGS->value) === true,
            403,
        );

        $state = $this->decodeState(
            (string) $request->query('state', ''),
        );

        $filters = is_array($state['filters'] ?? null)
            ? $state['filters']
            : [];

        $search = trim((string) ($state['search'] ?? ''));

        $dateState = is_array($filters['closing_date'] ?? null)
            ? $filters['closing_date']
            : [];

        $from = $this->normalizeDate($dateState['from'] ?? null);
        $until = $this->normalizeDate($dateState['until'] ?? null);

        $status = $this->filterValue($filters, 'status');

        if (! in_array($status, ['draft', 'confirmed', 'cancelled'], true)) {
            $status = null;
        }

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

        $closings = DailyClosing::query()
            ->with([
                'warehouse',
                'vehicle',
                'route',
                'salesRepresentative',
            ])
            ->when(
                $from,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('closing_date', '>=', $date),
            )
            ->when(
                $until,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('closing_date', '<=', $date),
            )
            ->when(
                $status,
                fn (Builder $query, string $value): Builder => $query
                    ->where('status', $value),
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
                                ->where('closing_number', 'like', $value)
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
            ->orderByDesc('closing_date')
            ->orderByDesc('id')
            ->get();

        $totals = [
            'count' => $closings->count(),
            'total_opening_quantity' => (float) $closings->sum('total_opening_quantity'),
            'total_movement_in_quantity' => (float) $closings->sum('total_movement_in_quantity'),
            'total_movement_out_quantity' => (float) $closings->sum('total_movement_out_quantity'),
            'total_expected_quantity' => (float) $closings->sum('total_expected_quantity'),
            'total_sales_amount' => (float) $closings->sum('total_sales_amount'),
            'total_returns_amount' => (float) $closings->sum('total_returns_amount'),
            'total_collections_amount' => (float) $closings->sum('total_collections_amount'),
            'total_vehicle_expenses_amount' => (float) $closings->sum('total_vehicle_expenses_amount'),
            'expected_cash_amount' => (float) $closings->sum('expected_cash_amount'),
            'actual_cash_amount' => (float) $closings->sum('actual_cash_amount'),
            'cash_difference' => (float) $closings->sum('cash_difference'),
        ];

        $totals['net_sales_amount'] = max(
            $totals['total_sales_amount']
            - $totals['total_returns_amount'],
            0,
        );

        $statusLabels = [
            'draft' => 'مسودة',
            'confirmed' => 'معتمد',
            'cancelled' => 'ملغي',
        ];

        $period = match (true) {
            filled($from) && filled($until) => $from.' — '.$until,
            filled($from) => 'من '.$from,
            filled($until) => 'حتى '.$until,
            default => null,
        };

        $filterSummary = array_filter([
            'الفترة' => $period,
            'الحالة' => $status ? ($statusLabels[$status] ?? $status) : null,
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

        return view('reports.daily-closings.filtered-print', [
            'closings' => $closings,
            'totals' => $totals,
            'filterSummary' => $filterSummary,
            'statusLabels' => $statusLabels,
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