<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\CustomerPaymentWriteRequest;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\CustomerPaymentResource;
use App\Models\CustomerPayment;
use App\Services\Api\MobileOperationalWriteService;
use App\Services\Sales\CustomerPaymentService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerPaymentController extends Controller
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
    ];

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = CustomerPayment::query()->with(self::RELATIONS);
        $this->applySearch($query, $request, ['payment_number', 'client_reference', 'reference_number', 'notes']);
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

        return $this->recordResponse($request, $customerPayment, 'تم تحميل تفاصيل السجل.');
    }

    public function store(
        CustomerPaymentWriteRequest $request,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        return $this->handleOperationalWrite(function () use ($request, $writeService): JsonResponse {
            $result = $writeService->createCustomerPayment($request->validated());
            Gate::authorize('view', $result->record);

            return $this->recordResponse(
                $request,
                $result->record,
                $result->replayed ? 'تمت إعادة التحصيل المسجل سابقاً.' : 'تم إنشاء مسودة تحصيل العميل.',
                $result->replayed ? 200 : 201,
                ['idempotency' => ['replayed' => $result->replayed]],
            );
        });
    }

    public function update(
        CustomerPaymentWriteRequest $request,
        CustomerPayment $customerPayment,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $writeService->updateCustomerPayment($customerPayment, $request->validated()),
            'تم تحديث مسودة تحصيل العميل.',
        ));
    }

    public function destroy(
        CustomerPayment $customerPayment,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        Gate::authorize('delete', $customerPayment);

        return $this->handleOperationalWrite(function () use ($customerPayment, $writeService): JsonResponse {
            $id = (int) $customerPayment->id;
            $writeService->deleteRecord($customerPayment);

            return ApiResponse::success(['id' => $id], 'تم حذف مسودة تحصيل العميل.');
        });
    }

    public function confirm(
        Request $request,
        CustomerPayment $customerPayment,
        CustomerPaymentService $service,
    ): JsonResponse {
        Gate::authorize('confirm', $customerPayment);

        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->confirm($customerPayment),
            'تم اعتماد تحصيل العميل.',
        ));
    }

    public function cancel(
        Request $request,
        CustomerPayment $customerPayment,
        CustomerPaymentService $service,
    ): JsonResponse {
        Gate::authorize('cancel', $customerPayment);

        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->cancel($customerPayment),
            'تم إلغاء تحصيل العميل.',
        ));
    }

    /** @param array<string, mixed> $meta */
    private function recordResponse(
        Request $request,
        CustomerPayment $payment,
        string $message,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payment->loadMissing(self::RELATIONS);

        return ApiResponse::success(
            CustomerPaymentResource::make($payment)->resolve($request),
            $message,
            $status,
            $meta,
        );
    }
}
