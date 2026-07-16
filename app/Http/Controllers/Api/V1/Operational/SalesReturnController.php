<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Requests\Api\V1\Operational\SalesReturnWriteRequest;
use App\Http\Resources\Api\V1\Operational\SalesReturnResource;
use App\Models\SalesReturn;
use App\Services\Api\MobileOperationalWriteService;
use App\Services\Sales\SalesReturnService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SalesReturnController extends Controller
{
    use BuildsOperationalQueries;
    use HandlesOperationalWriteResponses;

    private const RELATIONS = [
        'customer.area',
        'customer.route',
        'salesInvoice',
        'vehicle.warehouse',
        'route',
        'warehouse.vehicle',
        'salesRepresentative',
        'items.product.category',
        'items.product.unit',
    ];

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = SalesReturn::query()->with(array_slice(self::RELATIONS, 0, 7));
        $this->applySearch($query, $request, ['return_number', 'client_reference', 'return_reason', 'notes']);
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

        return $this->recordResponse($request, $salesReturn, 'تم تحميل تفاصيل السجل.');
    }

    public function store(
        SalesReturnWriteRequest $request,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        return $this->handleOperationalWrite(function () use ($request, $writeService): JsonResponse {
            $result = $writeService->createSalesReturn($request->validated());
            Gate::authorize('view', $result->record);

            return $this->recordResponse(
                $request,
                $result->record,
                $result->replayed ? 'تمت إعادة المرتجع المسجل سابقاً.' : 'تم إنشاء مسودة مرتجع المبيعات.',
                $result->replayed ? 200 : 201,
                ['idempotency' => ['replayed' => $result->replayed]],
            );
        });
    }

    public function update(
        SalesReturnWriteRequest $request,
        SalesReturn $salesReturn,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $writeService->updateSalesReturn($salesReturn, $request->validated()),
            'تم تحديث مسودة مرتجع المبيعات.',
        ));
    }

    public function destroy(
        SalesReturn $salesReturn,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        Gate::authorize('delete', $salesReturn);

        return $this->handleOperationalWrite(function () use ($salesReturn, $writeService): JsonResponse {
            $id = (int) $salesReturn->id;
            $writeService->deleteRecord($salesReturn);

            return ApiResponse::success(['id' => $id], 'تم حذف مسودة مرتجع المبيعات.');
        });
    }

    public function confirm(
        Request $request,
        SalesReturn $salesReturn,
        SalesReturnService $service,
    ): JsonResponse {
        Gate::authorize('confirm', $salesReturn);

        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->confirm($salesReturn),
            'تم اعتماد مرتجع المبيعات.',
        ));
    }

    public function cancel(
        Request $request,
        SalesReturn $salesReturn,
        SalesReturnService $service,
    ): JsonResponse {
        Gate::authorize('cancel', $salesReturn);

        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->cancel($salesReturn),
            'تم إلغاء مرتجع المبيعات.',
        ));
    }

    /** @param array<string, mixed> $meta */
    private function recordResponse(
        Request $request,
        SalesReturn $salesReturn,
        string $message,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $salesReturn->loadMissing(self::RELATIONS);

        return ApiResponse::success(
            SalesReturnResource::make($salesReturn)->resolve($request),
            $message,
            $status,
            $meta,
        );
    }
}
