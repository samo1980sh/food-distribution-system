<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\CustomerPaymentResource;
use App\Models\CustomerPayment;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerPaymentController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = CustomerPayment::query()->with(['customer.area', 'customer.route', 'salesInvoice', 'vehicle.warehouse', 'route', 'warehouse.vehicle', 'salesRepresentative']);
        $this->applySearch($query, $request, ['payment_number', 'reference_number', 'notes']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));
        $this->applyDateRange($query, $request, 'payment_date');
        $this->applyIdFilters($query, $request, ['customer_id', 'route_id', 'vehicle_id', 'warehouse_id']);

        $paginator = $query
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            CustomerPaymentResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل تحصيلات العملاء.',
        );
    }

    public function show(Request $request, CustomerPayment $customerPayment): JsonResponse
    {
        Gate::authorize('view', $customerPayment);
        $customerPayment->loadMissing(['customer.area', 'customer.route', 'salesInvoice', 'vehicle.warehouse', 'route', 'warehouse.vehicle', 'salesRepresentative']);

        return ApiResponse::success(
            CustomerPaymentResource::make($customerPayment)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
