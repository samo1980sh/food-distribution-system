<?php

namespace App\Http\Controllers\Reports;

use App\Filament\Resources\VehicleExpenseReports\Tables\VehicleExpenseReportsTable;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Throwable;

class VehicleExpenseReportFilteredPrintController
{
    public function __invoke(Request $request): View
    {
        abort_unless(
            Auth::user()?->canManageDistribution() === true,
            403,
        );

        $state = $this->decodeState((string) $request->query('state', ''));
        $filters = is_array($state['filters'] ?? null)
            ? $state['filters']
            : [];

        $search = is_string($state['search'] ?? null)
            ? trim($state['search'])
            : '';

        $dateFilter = is_array($filters['expense_date'] ?? null)
            ? $filters['expense_date']
            : [];

        $from = $this->normalizeDate($dateFilter['from'] ?? null);
        $until = $this->normalizeDate($dateFilter['until'] ?? null);

        $vehicleId = $this->normalizeId(
            $this->filterValue($filters, 'vehicle_id'),
        );

        $warehouseId = $this->normalizeId(
            $this->filterValue($filters, 'warehouse_id'),
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

        $expenseType = $this->filterValue($filters, 'expense_type');
        $paymentMethod = $this->filterValue($filters, 'payment_method');

        $query = VehicleExpense::query()
            ->where('status', 'approved')
            ->with([
                'vehicle',
                'warehouse',
                'route',
                'driver',
                'salesRepresentative',
                'approvedBy',
            ])
            ->when(
                $from,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('expense_date', '>=', $date),
            )
            ->when(
                $until,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('expense_date', '<=', $date),
            )
            ->when(
                $vehicleId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('vehicle_id', $id),
            )
            ->when(
                $warehouseId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('warehouse_id', $id),
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
                $expenseType,
                fn (Builder $query, string $value): Builder => $query
                    ->where('expense_type', $value),
            )
            ->when(
                $paymentMethod,
                fn (Builder $query, string $value): Builder => $query
                    ->where('payment_method', $value),
            )
            ->when(
                $search !== '',
                function (Builder $query) use ($search): Builder {
                    $value = '%'.$search.'%';

                    return $query->where(
                        function (Builder $query) use ($value): void {
                            $query
                                ->where('expense_number', 'like', $value)
                                ->orWhereHas(
                                    'vehicle',
                                    fn (Builder $query): Builder => $query
                                        ->where('plate_number', 'like', $value),
                                )
                                ->orWhereHas(
                                    'warehouse',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value),
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
                                    'approvedBy',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value),
                                );
                        },
                    );
                },
            );

        $totals = [
            'count' => (clone $query)->count(),
            'total_amount' => (float) (clone $query)->sum('amount'),
            'cash_amount' => (float) (clone $query)
                ->where('payment_method', 'cash')
                ->sum('amount'),
            'non_cash_amount' => (float) (clone $query)
                ->where('payment_method', '!=', 'cash')
                ->sum('amount'),
        ];

        $typeTotals = (clone $query)
            ->selectRaw('expense_type, COUNT(*) as records_count, SUM(amount) as total_amount')
            ->groupBy('expense_type')
            ->orderBy('expense_type')
            ->get();

        $expenses = $query
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $period = match (true) {
            filled($from) && filled($until) => $from.' — '.$until,
            filled($from) => 'من '.$from,
            filled($until) => 'حتى '.$until,
            default => null,
        };

        $expenseTypeLabels = VehicleExpenseReportsTable::expenseTypeOptions();
        $paymentMethodLabels = VehicleExpenseReportsTable::paymentMethodOptions();

        $filterSummary = array_filter([
            'الفترة' => $period,
            'السيارة' => $vehicleId
                ? Vehicle::query()->find($vehicleId)?->plate_number
                : null,
            'المستودع' => $warehouseId
                ? Warehouse::query()->find($warehouseId)?->name
                : null,
            'خط التوزيع' => $routeId
                ? DistributionRoute::query()->find($routeId)?->name
                : null,
            'السائق' => $driverId
                ? Employee::query()->find($driverId)?->name
                : null,
            'المندوب' => $representativeId
                ? Employee::query()->find($representativeId)?->name
                : null,
            'نوع المصروف' => $expenseType
                ? ($expenseTypeLabels[$expenseType] ?? $expenseType)
                : null,
            'طريقة الدفع' => $paymentMethod
                ? ($paymentMethodLabels[$paymentMethod] ?? $paymentMethod)
                : null,
            'البحث' => $search !== '' ? $search : null,
        ], fn (mixed $value): bool => filled($value));

        return view('reports.vehicle-expenses.filtered-print', [
            'expenses' => $expenses,
            'totals' => $totals,
            'typeTotals' => $typeTotals,
            'filterSummary' => $filterSummary,
            'expenseTypeLabels' => $expenseTypeLabels,
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
