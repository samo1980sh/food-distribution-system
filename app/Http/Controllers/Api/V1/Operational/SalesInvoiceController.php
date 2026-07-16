<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\SalesInvoiceResource;
use App\Models\SalesInvoice;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SalesInvoiceController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = SalesInvoice::query()->with(['customer.area', 'customer.route', 'vehicle.warehouse', 'route', 'warehouse.vehicle', 'salesRepresentative']);
        $this->applySearch($query, $request, ['invoice_number', 'notes']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));
        $this->applyDateRange($query, $request, 'invoice_date');
        $this->applyIdFilters($query, $request, ['customer_id', 'route_id', 'vehicle_id', 'warehouse_id']);

        $paginator = $query
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            SalesInvoiceResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل فواتير المبيعات.',
        );
    }

    public function show(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        Gate::authorize('view', $salesInvoice);
        $salesInvoice->loadMissing(['customer.area', 'customer.route', 'vehicle.warehouse', 'route', 'warehouse.vehicle', 'salesRepresentative', 'items.product.category', 'items.product.unit']);

        return ApiResponse::success(
            SalesInvoiceResource::make($salesInvoice)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
