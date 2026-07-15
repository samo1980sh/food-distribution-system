<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class VehicleLoadReportFilteredPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->can(PermissionName::REPORT_VEHICLE_LOADS->value) === true,
            403,
        );

        $state = $this->decodeState(
            (string) $request->query('state', ''),
        );

        $filters = is_array($state['filters'] ?? null)
            ? $state['filters']
            : [];

        $search = trim((string) ($state['search'] ?? ''));

        $dateState = is_array($filters['load_date'] ?? null)
            ? $filters['load_date']
            : [];

        $from = $this->normalizeDate($dateState['from'] ?? null);
        $until = $this->normalizeDate($dateState['until'] ?? null);

        $status = $this->filterValue($filters, 'status');

        if (! in_array(
            $status,
            ['draft', 'approved', 'cancelled', 'closed'],
            true,
        )) {
            $status = null;
        }

        $vehicleId = $this->normalizeId(
            $this->filterValue($filters, 'vehicle_id'),
        );

        $routeId = $this->normalizeId(
            $this->filterValue($filters, 'route_id'),
        );

        $driverId = $this->normalizeId(
            $this->filterValue($filters, 'driver_id'),
        );

        $representativeId = $this->normalizeId(
            $this->filterValue($filters, 'sales_representative_id'),
        );

        $fromWarehouseId = $this->normalizeId(
            $this->filterValue($filters, 'from_warehouse_id'),
        );

        $toWarehouseId = $this->normalizeId(
            $this->filterValue($filters, 'to_warehouse_id'),
        );

        $loads = VehicleLoad::query()
            ->with([
                'vehicle',
                'route',
                'driver',
                'salesRepresentative',
                'fromWarehouse',
                'toWarehouse',
            ])
            ->withCount('items')
            ->when(
                $from,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('load_date', '>=', $date),
            )
            ->when(
                $until,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('load_date', '<=', $date),
            )
            ->when(
                $status,
                fn (Builder $query, string $value): Builder => $query
                    ->where('status', $value),
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
                $driverId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('driver_id', $id),
            )
            ->when(
                $representativeId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('sales_representative_id', $id),
            )
            ->when(
                $fromWarehouseId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('from_warehouse_id', $id),
            )
            ->when(
                $toWarehouseId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('to_warehouse_id', $id),
            )
            ->when(
                $search !== '',
                function (Builder $query) use ($search): Builder {
                    $value = '%'.$search.'%';

                    return $query->where(
                        function (Builder $query) use ($value): void {
                            $query
                                ->where('load_number', 'like', $value)
                                ->orWhere('notes', 'like', $value)
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
                                    'driver',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value),
                                )
                                ->orWhereHas(
                                    'salesRepresentative',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value),
                                )
                                ->orWhereHas(
                                    'fromWarehouse',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value),
                                )
                                ->orWhereHas(
                                    'toWarehouse',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value),
                                );
                        },
                    );
                },
            )
            ->orderByDesc('load_date')
            ->orderByDesc('id')
            ->get();

        $totals = [
            'count' => $loads->count(),
            'items_count' => (int) $loads->sum('items_count'),
            'total_quantity' => (float) $loads->sum('total_quantity'),
            'total_cost' => (float) $loads->sum('total_cost'),
        ];

        $statusLabels = [
            'draft' => 'مسودة',
            'approved' => 'معتمد',
            'cancelled' => 'ملغي',
            'closed' => 'مغلق',
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
            'السيارة' => $vehicleId
                ? Vehicle::query()->find($vehicleId)?->plate_number
                : null,
            'خط التوزيع' => $routeId
                ? DistributionRoute::query()->find($routeId)?->name
                : null,
            'السائق' => $driverId
                ? Employee::query()->find($driverId)?->name
                : null,
            'مندوب المبيعات' => $representativeId
                ? Employee::query()->find($representativeId)?->name
                : null,
            'المستودع المصدر' => $fromWarehouseId
                ? Warehouse::query()->find($fromWarehouseId)?->name
                : null,
            'مستودع السيارة' => $toWarehouseId
                ? Warehouse::query()->find($toWarehouseId)?->name
                : null,
            'البحث' => $search !== '' ? $search : null,
        ], fn (mixed $value): bool => filled($value));

        return view('reports.vehicle-loads.filtered-print', [
            'loads' => $loads,
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
