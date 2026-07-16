<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\DailyClosingWriteRequest;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\DailyClosingResource;
use App\Models\DailyClosing;
use App\Services\Api\MobileOperationalWriteService;
use App\Services\Distribution\DailyClosingService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DailyClosingController extends Controller
{
    use BuildsOperationalQueries;
    use HandlesOperationalWriteResponses;

    private const RELATIONS = [
        'vehicle.warehouse',
        'route',
        'warehouse.vehicle',
        'salesRepresentative',
        'items.product.category',
        'items.product.unit',
    ];

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = DailyClosing::query()->with(array_slice(self::RELATIONS, 0, 4));
        $this->applySearch($query, $request, ['closing_number', 'client_reference', 'notes']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));
        $this->applyDateRange($query, $request, 'closing_date');
        $this->applyIdFilters($query, $request, ['route_id', 'vehicle_id', 'warehouse_id']);

        $paginator = $query
            ->orderByDesc('closing_date')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            DailyClosingResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل الإغلاقات اليومية.',
        );
    }

    public function show(Request $request, DailyClosing $dailyClosing): JsonResponse
    {
        Gate::authorize('view', $dailyClosing);

        return $this->recordResponse($request, $dailyClosing, 'تم تحميل تفاصيل السجل.');
    }

    public function store(
        DailyClosingWriteRequest $request,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        return $this->handleOperationalWrite(function () use ($request, $writeService): JsonResponse {
            $result = $writeService->createDailyClosing($request->validated());
            Gate::authorize('view', $result->record);

            return $this->recordResponse(
                $request,
                $result->record,
                $result->replayed ? 'تمت إعادة الإغلاق المسجل سابقاً.' : 'تم إنشاء مسودة الإغلاق اليومي وحساب ملخصها.',
                $result->replayed ? 200 : 201,
                ['idempotency' => ['replayed' => $result->replayed]],
            );
        });
    }

    public function update(
        DailyClosingWriteRequest $request,
        DailyClosing $dailyClosing,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $writeService->updateDailyClosing($dailyClosing, $request->validated()),
            'تم تحديث مسودة الإغلاق اليومي.',
        ));
    }

    public function destroy(
        DailyClosing $dailyClosing,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        Gate::authorize('delete', $dailyClosing);

        return $this->handleOperationalWrite(function () use ($dailyClosing, $writeService): JsonResponse {
            $id = (int) $dailyClosing->id;
            $writeService->deleteRecord($dailyClosing);

            return ApiResponse::success(['id' => $id], 'تم حذف مسودة الإغلاق اليومي.');
        });
    }

    public function refreshTotals(
        Request $request,
        DailyClosing $dailyClosing,
        DailyClosingService $service,
    ): JsonResponse {
        Gate::authorize('refreshTotals', $dailyClosing);

        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->refreshTotals($dailyClosing),
            'تم تحديث مجاميع الإغلاق اليومي.',
        ));
    }

    public function confirm(
        Request $request,
        DailyClosing $dailyClosing,
        DailyClosingService $service,
    ): JsonResponse {
        Gate::authorize('confirm', $dailyClosing);

        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->confirm($dailyClosing),
            'تم اعتماد الإغلاق اليومي.',
        ));
    }

    public function cancel(
        Request $request,
        DailyClosing $dailyClosing,
        DailyClosingService $service,
    ): JsonResponse {
        Gate::authorize('cancel', $dailyClosing);

        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->cancel($dailyClosing),
            'تم إلغاء الإغلاق اليومي.',
        ));
    }

    /** @param array<string, mixed> $meta */
    private function recordResponse(
        Request $request,
        DailyClosing $closing,
        string $message,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $closing->loadMissing(self::RELATIONS);

        return ApiResponse::success(
            DailyClosingResource::make($closing)->resolve($request),
            $message,
            $status,
            $meta,
        );
    }
}
