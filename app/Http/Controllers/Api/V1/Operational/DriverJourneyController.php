<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\FinishDriverJourneyRequest;
use App\Http\Requests\Api\V1\Operational\OpenDriverJourneyRequest;
use App\Http\Requests\Api\V1\Operational\StartDriverJourneyRequest;
use App\Http\Resources\Api\V1\Operational\DriverJourneyResource;
use App\Models\DriverJourney;
use App\Services\Api\MobileSyncVersionService;
use App\Services\Distribution\DriverFieldOperationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DriverJourneyController extends Controller
{
    use HandlesOperationalWriteResponses;

    public function openToday(
        OpenDriverJourneyRequest $request,
        DriverFieldOperationService $service,
        MobileSyncVersionService $versionService,
    ): JsonResponse {
        return $this->handleOperationalWrite(function () use ($request, $service, $versionService): JsonResponse {
            $journey = $service->openToday(
                $request->user(),
                $request->validated('route_id'),
            );

            return ApiResponse::success(
                $this->resourceData($request, $journey, $versionService),
                $journey->wasRecentlyCreated
                    ? 'تم فتح رحلة السائق لليوم.'
                    : 'تم تحميل رحلة السائق لليوم.',
                $journey->wasRecentlyCreated ? 201 : 200,
            );
        });
    }

    public function show(
        Request $request,
        DriverJourney $driverJourney,
        DriverFieldOperationService $service,
        MobileSyncVersionService $versionService,
    ): JsonResponse {
        Gate::authorize('view', $driverJourney);

        return ApiResponse::success(
            $this->resourceData($request, $service->loadJourney($driverJourney), $versionService),
            'تم تحميل تفاصيل رحلة السائق.',
        );
    }

    public function start(
        StartDriverJourneyRequest $request,
        DriverJourney $driverJourney,
        DriverFieldOperationService $service,
        MobileSyncVersionService $versionService,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => ApiResponse::success(
            $this->resourceData(
                $request,
                $service->start($driverJourney, $request->validated()),
                $versionService,
            ),
            'تم بدء رحلة السائق.',
        ));
    }

    public function finish(
        FinishDriverJourneyRequest $request,
        DriverJourney $driverJourney,
        DriverFieldOperationService $service,
        MobileSyncVersionService $versionService,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => ApiResponse::success(
            $this->resourceData(
                $request,
                $service->finish($driverJourney, $request->validated()),
                $versionService,
            ),
            'تم إنهاء رحلة السائق.',
        ));
    }

    /** @return array<string, mixed> */
    private function resourceData(
        Request $request,
        DriverJourney $journey,
        MobileSyncVersionService $versionService,
    ): array {
        return [
            ...DriverJourneyResource::make($journey)->resolve($request),
            'sync_version' => $versionService->forRecord('driver_journeys', $journey),
        ];
    }
}
