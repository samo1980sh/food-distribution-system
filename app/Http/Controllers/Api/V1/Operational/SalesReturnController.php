<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\SalesReturnResource;
use App\Models\SalesReturn;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SalesReturnController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = SalesReturn::query()->with(['customer.area', 'customer.route', 'salesInvoice', 'vehicle.warehouse', 'route', 'warehouse.vehicle', 'salesRepresentative']);
        $this->applySearch($query, $request, ['return_number', 'return_reason', 'notes']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));
        $this->applyDateRange($query, $request, 'return_date');
        $this->applyIdFilters($query, $request, ['customer_id', 'route_id', 'vehicle_id', 'warehouse_id']);

        $paginator = $query
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            SalesReturnResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل مرتجعات المبيعات.',
        );
    }

    public function show(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        Gate::authorize('view', $salesReturn);
        $salesReturn->loadMissing(['customer.area', 'customer.route', 'salesInvoice', 'vehicle.warehouse', 'route', 'warehouse.vehicle', 'salesRepresentative', 'items.product.category', 'items.product.unit']);

        return ApiResponse::success(
            SalesReturnResource::make($salesReturn)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
