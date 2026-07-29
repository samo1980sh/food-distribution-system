<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\SubmitDriverDeliveryOutcomeRequest;
use App\Http\Resources\Api\V1\Operational\DriverDeliveryResource;
use App\Models\DriverDelivery;
use App\Services\Api\MobileSyncVersionService;
use App\Services\Distribution\DriverFieldOperationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DriverDeliveryController extends Controller
{
    use HandlesOperationalWriteResponses;

    public function show(
        Request $request,
        DriverDelivery $driverDelivery,
        DriverFieldOperationService $service,
        MobileSyncVersionService $versionService,
    ): JsonResponse {
        Gate::authorize('view', $driverDelivery);

        return ApiResponse::success(
            $this->resourceData($request, $service->loadDelivery($driverDelivery), $versionService),
            'تم تحميل تفاصيل التسليم.',
        );
    }

    public function submitOutcome(
        SubmitDriverDeliveryOutcomeRequest $request,
        DriverDelivery $driverDelivery,
        DriverFieldOperationService $service,
        MobileSyncVersionService $versionService,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => ApiResponse::success(
            $this->resourceData(
                $request,
                $service->submitOutcome($driverDelivery, $request->validated()),
                $versionService,
            ),
            'تم تسجيل نتيجة التسليم.',
        ));
    }

    /** @return array<string, mixed> */
    private function resourceData(
        Request $request,
        DriverDelivery $delivery,
        MobileSyncVersionService $versionService,
    ): array {
        return [
            ...DriverDeliveryResource::make($delivery)->resolve($request),
            'sync_version' => $versionService->forRecord('driver_deliveries', $delivery),
        ];
    }
}
