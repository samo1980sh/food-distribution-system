<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class VehicleStockReportFilteredPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->canManageInventory() === true,
            403,
        );

        $state = $this->decodeState(
            (string) $request->query('state', ''),
        );

        $filters = is_array($state['filters'] ?? null)
            ? $state['filters']
            : [];

        $search = trim((string) ($state['search'] ?? ''));

        $expiryDateState = is_array($filters['expiry_date'] ?? null)
            ? $filters['expiry_date']
            : [];

        $expiryFrom = $this->normalizeDate($expiryDateState['from'] ?? null);
        $expiryUntil = $this->normalizeDate($expiryDateState['until'] ?? null);

        $vehicleId = $this->normalizeId(
            $this->filterValue($filters, 'vehicle_id'),
        );

        $productId = $this->normalizeId(
            $this->filterValue($filters, 'product_id'),
        );

        $expiryStatus = $this->filterValue($filters, 'expiry_status');

        if (! in_array(
            $expiryStatus,
            ['expired', 'within_30', 'within_60', 'without_expiry'],
            true,
        )) {
            $expiryStatus = null;
        }

        $balances = StockBalance::query()
            ->with([
                'warehouse.vehicle',
                'product',
            ])
            ->where('quantity', '!=', 0)
            ->whereHas(
                'warehouse',
                fn (Builder $query): Builder => $query
                    ->where('type', 'vehicle'),
            )
            ->when(
                $vehicleId,
                fn (Builder $query, int $id): Builder => $query
                    ->whereHas(
                        'warehouse',
                        fn (Builder $query): Builder => $query
                            ->where('vehicle_id', $id),
                    ),
            )
            ->when(
                $productId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('product_id', $id),
            )
            ->when(
                $expiryFrom,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('expiry_date', '>=', $date),
            )
            ->when(
                $expiryUntil,
                fn (Builder $query, string $date): Builder => $query
                    ->whereDate('expiry_date', '<=', $date),
            )
            ->when(
                $expiryStatus,
                fn (Builder $query, string $status): Builder =>
                    $this->applyExpiryStatus($query, $status),
            )
            ->when(
                $search !== '',
                function (Builder $query) use ($search): Builder {
                    $value = '%'.$search.'%';

                    return $query->where(
                        function (Builder $query) use ($value): void {
                            $query
                                ->where('batch_number', 'like', $value)
                                ->orWhereHas(
                                    'product',
                                    fn (Builder $query): Builder => $query
                                        ->where('sku', 'like', $value)
                                        ->orWhere('barcode', 'like', $value)
                                        ->orWhere('name_ar', 'like', $value),
                                )
                                ->orWhereHas(
                                    'warehouse',
                                    fn (Builder $query): Builder => $query
                                        ->where('name', 'like', $value)
                                        ->orWhere('code', 'like', $value),
                                )
                                ->orWhereHas(
                                    'warehouse.vehicle',
                                    fn (Builder $query): Builder => $query
                                        ->where('plate_number', 'like', $value)
                                        ->orWhere('code', 'like', $value)
                                        ->orWhere('name', 'like', $value),
                                );
                        },
                    );
                },
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $totals = [
            'rows_count' => $balances->count(),
            'vehicles_count' => $balances
                ->map(fn (StockBalance $balance) => $balance->warehouse?->vehicle_id)
                ->filter()
                ->unique()
                ->count(),
            'products_count' => $balances->pluck('product_id')->unique()->count(),
            'quantity' => (float) $balances->sum('quantity'),
            'estimated_value' => (float) $balances->sum(
                fn (StockBalance $balance): float =>
                    (float) $balance->quantity
                    * (float) ($balance->product?->purchase_price ?? 0),
            ),
        ];

        $expiryStatusLabels = [
            'expired' => 'منتهي الصلاحية',
            'within_30' => 'ينتهي خلال 30 يومًا',
            'within_60' => 'ينتهي خلال 60 يومًا',
            'without_expiry' => 'بدون تاريخ صلاحية',
        ];

        $expiryPeriod = match (true) {
            filled($expiryFrom) && filled($expiryUntil) =>
                $expiryFrom.' — '.$expiryUntil,
            filled($expiryFrom) => 'من '.$expiryFrom,
            filled($expiryUntil) => 'حتى '.$expiryUntil,
            default => null,
        };

        $filterSummary = array_filter([
            'السيارة' => $vehicleId
                ? Vehicle::query()->find($vehicleId)?->plate_number
                : null,
            'المنتج' => $productId
                ? Product::query()->find($productId)?->name_ar
                : null,
            'فترة الصلاحية' => $expiryPeriod,
            'حالة الصلاحية' => $expiryStatus
                ? ($expiryStatusLabels[$expiryStatus] ?? $expiryStatus)
                : null,
            'البحث' => $search !== '' ? $search : null,
        ], fn (mixed $value): bool => filled($value));

        return view('reports.vehicle-stock.filtered-print', [
            'balances' => $balances,
            'totals' => $totals,
            'filterSummary' => $filterSummary,
            'generatedBy' => Auth::user()?->name,
        ]);
    }

    private function applyExpiryStatus(
        Builder $query,
        string $status,
    ): Builder {
        return match ($status) {
            'expired' => $query
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', today()),
            'within_30' => $query
                ->whereBetween('expiry_date', [
                    today()->toDateString(),
                    today()->addDays(30)->toDateString(),
                ]),
            'within_60' => $query
                ->whereBetween('expiry_date', [
                    today()->toDateString(),
                    today()->addDays(60)->toDateString(),
                ]),
            'without_expiry' => $query->whereNull('expiry_date'),
            default => $query,
        };
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
