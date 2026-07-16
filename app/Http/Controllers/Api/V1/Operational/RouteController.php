<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\RouteResource;
use App\Models\DistributionRoute;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RouteController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = DistributionRoute::query()->with(['area', 'vehicle.warehouse', 'driver', 'salesRepresentative']);
        $this->applySearch($query, $request, ['code', 'name']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));

        $this->applyIdFilters($query, $request, ['area_id', 'vehicle_id']);

        $paginator = $query
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            RouteResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل خطوط التوزيع.',
        );
    }

    public function show(Request $request, DistributionRoute $distributionRoute): JsonResponse
    {
        Gate::authorize('view', $distributionRoute);
        $distributionRoute->loadMissing(['area', 'vehicle.warehouse', 'driver', 'salesRepresentative']);

        return ApiResponse::success(
            RouteResource::make($distributionRoute)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
