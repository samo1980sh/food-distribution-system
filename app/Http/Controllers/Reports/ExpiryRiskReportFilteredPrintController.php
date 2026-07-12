<?php

namespace App\Http\Controllers\Reports;

use App\Filament\Resources\ExpiryRiskReports\Tables\ExpiryRiskReportsTable;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBalance;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class ExpiryRiskReportFilteredPrintController extends Controller
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

        $expiryState = is_array($filters['expiry_risk'] ?? null)
            ? $filters['expiry_risk']
            : [];

        $expiryData = [
            'scope' => $this->normalizeOption(
                $expiryState['scope'] ?? null,
                array_keys(ExpiryRiskReportsTable::expiryScopeOptions()),
                'risk_30',
            ),
            'status' => $this->normalizeOption(
                $expiryState['status'] ?? null,
                array_keys(ExpiryRiskReportsTable::expiryStatusOptions()),
            ),
            'from' => $this->normalizeDate($expiryState['from'] ?? null),
            'until' => $this->normalizeDate($expiryState['until'] ?? null),
        ];

        $warehouseId = $this->normalizeId(
            $this->filterValue($filters, 'warehouse_id'),
        );

        $warehouseType = $this->normalizeOption(
            $this->filterValue($filters, 'warehouse_type'),
            array_keys(ExpiryRiskReportsTable::warehouseTypeOptions()),
        );

        $vehicleId = $this->normalizeId(
            $this->filterValue($filters, 'vehicle_id'),
        );

        $productId = $this->normalizeId(
            $this->filterValue($filters, 'product_id'),
        );

        $categoryId = $this->normalizeId(
            $this->filterValue($filters, 'category_id'),
        );

        $batchState = is_array($filters['batch_number'] ?? null)
            ? $filters['batch_number']
            : [];

        $batchNumber = trim((string) ($batchState['value'] ?? ''));

        $query = StockBalance::query()
            ->with([
                'warehouse.vehicle',
                'product.category',
                'product.unit',
            ])
            ->where('quantity', '>', 0)
            ->whereHas(
                'product',
                fn (Builder $query): Builder => $query
                    ->where('has_expiry', true),
            );

        ExpiryRiskReportsTable::applyExpiryFilter($query, $expiryData);

        $query
            ->when(
                $warehouseId,
                fn (Builder $query, int $id): Builder => $query
                    ->where('warehouse_id', $id),
            )
            ->when(
                $warehouseType,
                fn (Builder $query, string $type): Builder => $query
                    ->whereHas(
                        'warehouse',
                        fn (Builder $query): Builder => $query
                            ->where('type', $type),
                    ),
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
                $categoryId,
                fn (Builder $query, int $id): Builder => $query
                    ->whereHas(
                        'product',
                        fn (Builder $query): Builder => $query
                            ->where('category_id', $id),
                    ),
            )
            ->when(
                $batchNumber !== '',
                fn (Builder $query): Builder => $query
                    ->where('batch_number', 'like', '%'.$batchNumber.'%'),
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
                                    'product.category',
                                    fn (Builder $query): Builder => $query
                                        ->where('name_ar', 'like', $value),
                                )
                                ->orWhereHas(
                                    'product.unit',
                                    fn (Builder $query): Builder => $query
                                        ->where('name_ar', 'like', $value)
                                        ->orWhere('symbol', 'like', $value),
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
            );

        $balances = $query
            ->orderByRaw('expiry_date IS NULL DESC')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();

        $expired = $balances->filter(
            fn (StockBalance $balance): bool =>
                ExpiryRiskReportsTable::expiryStatus(
                    $balance->expiry_date,
                ) === 'expired',
        );

        $near = $balances->filter(
            fn (StockBalance $balance): bool =>
                in_array(
                    ExpiryRiskReportsTable::expiryStatus(
                        $balance->expiry_date,
                    ),
                    ['today', 'critical_7', 'near_30'],
                    true,
                ),
        );

        $missing = $balances->filter(
            fn (StockBalance $balance): bool =>
                ExpiryRiskReportsTable::expiryStatus(
                    $balance->expiry_date,
                ) === 'missing',
        );

        $totals = [
            'rows_count' => $balances->count(),
            'products_count' => $balances->pluck('product_id')->unique()->count(),
            'warehouses_count' => $balances->pluck('warehouse_id')->unique()->count(),
            'quantity' => (float) $balances->sum('quantity'),
            'inventory_value' => (float) $balances->sum(
                fn (StockBalance $balance): float =>
                    ExpiryRiskReportsTable::inventoryValue($balance),
            ),
            'expired_quantity' => (float) $expired->sum('quantity'),
            'expired_value' => (float) $expired->sum(
                fn (StockBalance $balance): float =>
                    ExpiryRiskReportsTable::inventoryValue($balance),
            ),
            'near_quantity' => (float) $near->sum('quantity'),
            'near_value' => (float) $near->sum(
                fn (StockBalance $balance): float =>
                    ExpiryRiskReportsTable::inventoryValue($balance),
            ),
            'missing_count' => $missing->count(),
            'missing_quantity' => (float) $missing->sum('quantity'),
            'missing_value' => (float) $missing->sum(
                fn (StockBalance $balance): float =>
                    ExpiryRiskReportsTable::inventoryValue($balance),
            ),
        ];

        $scopeOptions = ExpiryRiskReportsTable::expiryScopeOptions();
        $statusOptions = ExpiryRiskReportsTable::expiryStatusOptions();
        $warehouseTypeOptions = ExpiryRiskReportsTable::warehouseTypeOptions();

        $customPeriod = match (true) {
            filled($expiryData['from']) && filled($expiryData['until']) =>
                $expiryData['from'].' — '.$expiryData['until'],
            filled($expiryData['from']) => 'من '.$expiryData['from'],
            filled($expiryData['until']) => 'حتى '.$expiryData['until'],
            default => null,
        };

        $filterSummary = array_filter([
            'نطاق الصلاحية' => $expiryData['status']
                ? ($statusOptions[$expiryData['status']] ?? $expiryData['status'])
                : ($customPeriod
                    ?: ($scopeOptions[$expiryData['scope']] ?? $expiryData['scope'])),
            'المستودع' => $warehouseId
                ? Warehouse::query()->find($warehouseId)?->name
                : null,
            'نوع المستودع' => $warehouseType
                ? ($warehouseTypeOptions[$warehouseType] ?? $warehouseType)
                : null,
            'السيارة' => $vehicleId
                ? Vehicle::query()->find($vehicleId)?->plate_number
                : null,
            'المنتج' => $productId
                ? Product::query()->find($productId)?->name_ar
                : null,
            'التصنيف' => $categoryId
                ? ProductCategory::query()->find($categoryId)?->name_ar
                : null,
            'رقم التشغيلة' => $batchNumber !== '' ? $batchNumber : null,
            'البحث' => $search !== '' ? $search : null,
        ], fn (mixed $value): bool => filled($value));

        return view('reports.expiry-risk.filtered-print', [
            'balances' => $balances,
            'totals' => $totals,
            'filterSummary' => $filterSummary,
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

    private function normalizeOption(
        mixed $value,
        array $allowed,
        ?string $default = null,
    ): ?string {
        if (! is_scalar($value)) {
            return $default;
        }

        $normalized = trim((string) $value);

        return in_array($normalized, $allowed, true)
            ? $normalized
            : $default;
    }
}
