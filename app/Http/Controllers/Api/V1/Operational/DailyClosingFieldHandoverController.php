<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OpenDailyClosingFieldRequest;
use App\Http\Requests\Api\V1\Operational\SubmitDailyClosingCashRequest;
use App\Http\Requests\Api\V1\Operational\SubmitDailyClosingInventoryRequest;
use App\Http\Resources\Api\V1\Operational\DailyClosingResource;
use App\Models\DailyClosing;
use App\Services\Distribution\DailyClosingFieldHandoverService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyClosingFieldHandoverController extends Controller
{
    use HandlesOperationalWriteResponses;

    private const RELATIONS = [
        'vehicle.warehouse',
        'route',
        'warehouse.vehicle',
        'driver',
        'salesRepresentative',
        'inventorySubmitter',
        'cashSubmitter',
        'items.product.category',
        'items.product.unit',
    ];

    public function openToday(
        OpenDailyClosingFieldRequest $request,
        DailyClosingFieldHandoverService $service,
    ): JsonResponse {
        return $this->handleOperationalWrite(function () use ($request, $service): JsonResponse {
            $closing = $service->openToday(
                $request->user(),
                $request->validated('route_id'),
            );

            return $this->recordResponse(
                $request,
                $closing,
                $closing->wasRecentlyCreated
                    ? 'تم فتح مسودة إغلاق اليوم وحساب ملخصها.'
                    : 'تم تحميل إغلاق اليوم الحالي.',
                $closing->wasRecentlyCreated ? 201 : 200,
            );
        });
    }

    public function submitInventory(
        SubmitDailyClosingInventoryRequest $request,
        DailyClosing $dailyClosing,
        DailyClosingFieldHandoverService $service,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->submitInventory(
                $dailyClosing,
                $request->user(),
                $request->validated(),
            ),
            'تم تسليم جرد السيارة للإدارة.',
        ));
    }

    public function submitCash(
        SubmitDailyClosingCashRequest $request,
        DailyClosing $dailyClosing,
        DailyClosingFieldHandoverService $service,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->submitCash(
                $dailyClosing,
                $request->user(),
                $request->validated(),
            ),
            'تم تسليم النقد للإدارة.',
        ));
    }

    private function recordResponse(
        Request $request,
        DailyClosing $closing,
        string $message,
        int $status = 200,
    ): JsonResponse {
        $closing->loadMissing(self::RELATIONS);

        return ApiResponse::success(
            DailyClosingResource::make($closing)->resolve($request),
            $message,
            $status,
        );
    }
}
