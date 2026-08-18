<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\StockBalanceResource;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\VehicleLoad;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class StockBalanceController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = StockBalance::query()->with(['warehouse.vehicle', 'product.category', 'product.unit']);
        $this->applySearch($query, $request, ['batch_number']);
        $this->applyIdFilters($query, $request, ['warehouse_id', 'product_id']);
        $query->where('quantity', '>', 0);
        $query->when($request->validated('date_from'), fn ($q, $date) => $q->whereDate('expiry_date', '>=', $date));
        $query->when($request->validated('date_to'), fn ($q, $date) => $q->whereDate('expiry_date', '<=', $date));
        $paginator = $query
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        $this->attachSourceLoads($paginator->getCollection());

        return ApiResponse::paginated(
            StockBalanceResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل أرصدة المخزون.',
        );
    }

    public function show(Request $request, StockBalance $stockBalance): JsonResponse
    {
        Gate::authorize('view', $stockBalance);
        $stockBalance->loadMissing(['warehouse.vehicle', 'product.category', 'product.unit']);
        $this->attachSourceLoads(collect([$stockBalance]));

        return ApiResponse::success(
            StockBalanceResource::make($stockBalance)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }

    /**
     * @param  Collection<int, StockBalance>  $balances
     */
    private function attachSourceLoads(Collection $balances): void
    {
        if ($balances->isEmpty()) {
            return;
        }

        $warehouseIds = $balances->pluck('warehouse_id')->filter()
            ->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $productIds = $balances->pluck('product_id')->filter()
            ->map(fn ($id): int => (int) $id)->unique()->values()->all();

        if ($warehouseIds === [] || $productIds === []) {
            return;
        }

        $sourceRows = StockMovement::query()
            ->join('vehicle_loads', function ($join): void {
                $join
                    ->on('vehicle_loads.id', '=', 'stock_movements.reference_id')
                    ->where('stock_movements.reference_type', '=', VehicleLoad::class)
                    ->where('vehicle_loads.status', '=', 'approved');
            })
            ->leftJoin(
                'warehouses as source_warehouses',
                'source_warehouses.id',
                '=',
                'vehicle_loads.from_warehouse_id',
            )
            ->where('stock_movements.movement_type', 'vehicle_load_transfer')
            ->whereIn('stock_movements.to_warehouse_id', $warehouseIds)
            ->whereIn('stock_movements.product_id', $productIds)
            ->orderByDesc('stock_movements.id')
            ->get([
                'stock_movements.to_warehouse_id',
                'stock_movements.product_id',
                'stock_movements.batch_number',
                'stock_movements.expiry_date',
                'vehicle_loads.id as source_load_id',
                'vehicle_loads.load_number as source_load_number',
                'vehicle_loads.load_date as source_load_date',
                'source_warehouses.name as source_warehouse_name',
            ]);

        $sources = [];

        foreach ($sourceRows as $row) {
            $key = $this->stockSourceKey(
                (int) $row->to_warehouse_id,
                (int) $row->product_id,
                $row->batch_number,
                $row->expiry_date,
            );

            if (! array_key_exists($key, $sources)) {
                $sources[$key] = $row;
            }
        }

        foreach ($balances as $balance) {
            $key = $this->stockSourceKey(
                (int) $balance->warehouse_id,
                (int) $balance->product_id,
                $balance->batch_number,
                $balance->expiry_date,
            );
            $source = $sources[$key] ?? null;

            $balance->setAttribute('source_load_id', $source?->source_load_id);
            $balance->setAttribute('source_load_number', $source?->source_load_number);
            $balance->setAttribute(
                'source_load_date',
                $source?->source_load_date
                    ? substr((string) $source->source_load_date, 0, 10)
                    : null,
            );
            $balance->setAttribute('source_warehouse_name', $source?->source_warehouse_name);
        }
    }

    private function stockSourceKey(
        int $warehouseId,
        int $productId,
        mixed $batchNumber,
        mixed $expiryDate,
    ): string {
        $batch = trim((string) ($batchNumber ?? ''));
        $expiry = $expiryDate instanceof \DateTimeInterface
            ? $expiryDate->format('Y-m-d')
            : substr(trim((string) ($expiryDate ?? '')), 0, 10);

        return implode('|', [$warehouseId, $productId, $batch, $expiry]);
    }

}
