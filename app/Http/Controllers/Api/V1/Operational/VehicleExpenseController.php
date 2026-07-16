<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\VehicleExpenseResource;
use App\Models\VehicleExpense;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VehicleExpenseController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = VehicleExpense::query()->with(['vehicle.warehouse', 'warehouse.vehicle', 'route', 'driver', 'salesRepresentative']);
        $this->applySearch($query, $request, ['expense_number', 'expense_type', 'notes']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));
        $this->applyDateRange($query, $request, 'expense_date');
        $this->applyIdFilters($query, $request, ['route_id', 'vehicle_id', 'warehouse_id']);

        $paginator = $query
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            VehicleExpenseResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل مصاريف السيارات.',
        );
    }

    public function show(Request $request, VehicleExpense $vehicleExpense): JsonResponse
    {
        Gate::authorize('view', $vehicleExpense);
        $vehicleExpense->loadMissing(['vehicle.warehouse', 'warehouse.vehicle', 'route', 'driver', 'salesRepresentative']);

        return ApiResponse::success(
            VehicleExpenseResource::make($vehicleExpense)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
