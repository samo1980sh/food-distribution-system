<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\DailyClosingResource;
use App\Models\DailyClosing;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DailyClosingController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = DailyClosing::query()->with(['vehicle.warehouse', 'route', 'warehouse.vehicle', 'salesRepresentative']);
        $this->applySearch($query, $request, ['closing_number', 'notes']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));
        $this->applyDateRange($query, $request, 'closing_date');
        $this->applyIdFilters($query, $request, ['route_id', 'vehicle_id', 'warehouse_id']);

        $paginator = $query
            ->orderByDesc('closing_date')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            DailyClosingResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل الإغلاقات اليومية.',
        );
    }

    public function show(Request $request, DailyClosing $dailyClosing): JsonResponse
    {
        Gate::authorize('view', $dailyClosing);
        $dailyClosing->loadMissing(['vehicle.warehouse', 'route', 'warehouse.vehicle', 'salesRepresentative', 'items.product.category', 'items.product.unit']);

        return ApiResponse::success(
            DailyClosingResource::make($dailyClosing)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
