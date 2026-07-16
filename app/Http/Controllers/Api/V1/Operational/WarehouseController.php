<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\WarehouseResource;
use App\Models\Warehouse;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WarehouseController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = Warehouse::query()->with(['vehicle']);
        $this->applySearch($query, $request, ['code', 'name', 'address']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));

        $this->applyIdFilters($query, $request, ['vehicle_id']);

        $paginator = $query
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            WarehouseResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل المستودعات.',
        );
    }

    public function show(Request $request, Warehouse $warehouse): JsonResponse
    {
        Gate::authorize('view', $warehouse);
        $warehouse->loadMissing(['vehicle']);

        return ApiResponse::success(
            WarehouseResource::make($warehouse)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
