<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Requests\Api\V1\Operational\VehicleExpenseRejectRequest;
use App\Http\Requests\Api\V1\Operational\VehicleExpenseWriteRequest;
use App\Http\Resources\Api\V1\Operational\VehicleExpenseResource;
use App\Models\VehicleExpense;
use App\Services\Api\MobileOperationalWriteService;
use App\Services\Distribution\VehicleExpenseService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VehicleExpenseController extends Controller
{
    use BuildsOperationalQueries;
    use HandlesOperationalWriteResponses;

    private const RELATIONS = [
        'vehicle.warehouse',
        'warehouse.vehicle',
        'route',
        'driver',
        'salesRepresentative',
    ];

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = VehicleExpense::query()->with(self::RELATIONS);
        $this->applySearch($query, $request, ['expense_number', 'client_reference', 'expense_type', 'notes']);
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

        return $this->recordResponse($request, $vehicleExpense, 'تم تحميل تفاصيل السجل.');
    }

    public function store(
        VehicleExpenseWriteRequest $request,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        return $this->handleOperationalWrite(function () use ($request, $writeService): JsonResponse {
            $result = $writeService->createVehicleExpense(
                $request->validated(),
                $request->file('receipt'),
            );
            Gate::authorize('view', $result->record);

            return $this->recordResponse(
                $request,
                $result->record,
                $result->replayed ? 'تمت إعادة المصروف المسجل سابقاً.' : 'تم إنشاء مصروف السيارة قيد المراجعة.',
                $result->replayed ? 200 : 201,
                ['idempotency' => ['replayed' => $result->replayed]],
            );
        });
    }

    public function update(
        VehicleExpenseWriteRequest $request,
        VehicleExpense $vehicleExpense,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $writeService->updateVehicleExpense(
                $vehicleExpense,
                $request->validated(),
                $request->file('receipt'),
            ),
            'تم تحديث مصروف السيارة.',
        ));
    }

    public function destroy(
        VehicleExpense $vehicleExpense,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        Gate::authorize('delete', $vehicleExpense);

        return $this->handleOperationalWrite(function () use ($vehicleExpense, $writeService): JsonResponse {
            $id = (int) $vehicleExpense->id;
            $writeService->deleteRecord($vehicleExpense);

            return ApiResponse::success(['id' => $id], 'تم حذف مصروف السيارة قيد المراجعة.');
        });
    }

    public function approve(
        Request $request,
        VehicleExpense $vehicleExpense,
        VehicleExpenseService $service,
    ): JsonResponse {
        Gate::authorize('approve', $vehicleExpense);

        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->approve($vehicleExpense),
            'تم اعتماد مصروف السيارة.',
        ));
    }

    public function reject(
        VehicleExpenseRejectRequest $request,
        VehicleExpense $vehicleExpense,
        VehicleExpenseService $service,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->reject($vehicleExpense, $request->validated('reason')),
            'تم رفض مصروف السيارة.',
        ));
    }

    /** @param array<string, mixed> $meta */
    private function recordResponse(
        Request $request,
        VehicleExpense $expense,
        string $message,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $expense->loadMissing(self::RELATIONS);

        return ApiResponse::success(
            VehicleExpenseResource::make($expense)->resolve($request),
            $message,
            $status,
            $meta,
        );
    }
}
