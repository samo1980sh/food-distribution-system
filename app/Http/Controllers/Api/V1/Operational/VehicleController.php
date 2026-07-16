<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\VehicleResource;
use App\Models\Vehicle;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VehicleController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = Vehicle::query()->with(['warehouse']);
        $this->applySearch($query, $request, ['code', 'plate_number', 'name']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));



        $paginator = $query
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            VehicleResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل السيارات.',
        );
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('view', $vehicle);
        $vehicle->loadMissing(['warehouse']);

        return ApiResponse::success(
            VehicleResource::make($vehicle)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
