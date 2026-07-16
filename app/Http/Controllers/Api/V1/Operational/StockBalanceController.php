<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\StockBalanceResource;
use App\Models\StockBalance;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return ApiResponse::success(
            StockBalanceResource::make($stockBalance)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
