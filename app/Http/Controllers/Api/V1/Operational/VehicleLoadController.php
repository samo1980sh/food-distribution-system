<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\VehicleLoadResource;
use App\Models\VehicleLoad;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VehicleLoadController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = VehicleLoad::query()->with(['vehicle.warehouse', 'route', 'driver', 'salesRepresentative', 'fromWarehouse.vehicle', 'toWarehouse.vehicle']);
        $this->applySearch($query, $request, ['load_number', 'notes']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));
        $this->applyDateRange($query, $request, 'load_date');
        $this->applyIdFilters($query, $request, ['route_id', 'vehicle_id']);

        $paginator = $query
            ->orderByDesc('load_date')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            VehicleLoadResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل أوامر التحميل.',
        );
    }

    public function show(Request $request, VehicleLoad $vehicleLoad): JsonResponse
    {
        Gate::authorize('view', $vehicleLoad);
        $vehicleLoad->loadMissing(['vehicle.warehouse', 'route', 'driver', 'salesRepresentative', 'fromWarehouse.vehicle', 'toWarehouse.vehicle', 'items.product.category', 'items.product.unit']);

        return ApiResponse::success(
            VehicleLoadResource::make($vehicleLoad)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
