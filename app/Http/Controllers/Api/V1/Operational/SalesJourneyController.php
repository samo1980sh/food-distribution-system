<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\FinishSalesJourneyRequest;
use App\Http\Requests\Api\V1\Operational\OpenSalesJourneyRequest;
use App\Http\Requests\Api\V1\Operational\StartSalesJourneyRequest;
use App\Http\Resources\Api\V1\Operational\SalesJourneyResource;
use App\Models\SalesJourney;
use App\Services\Api\MobileSyncVersionService;
use App\Services\Distribution\SalesFieldOperationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SalesJourneyController extends Controller
{
    use HandlesOperationalWriteResponses;

    public function openToday(OpenSalesJourneyRequest $request, SalesFieldOperationService $service, MobileSyncVersionService $versions): JsonResponse
    {
        return $this->handleOperationalWrite(function () use ($request, $service, $versions): JsonResponse {
            $journey = $service->openToday($request->user(), $request->validated('route_id'));
            return ApiResponse::success(
                $this->resourceData($request, $journey, $versions),
                $journey->wasRecentlyCreated ? 'تم فتح رحلة المبيعات لليوم.' : 'تم تحميل رحلة المبيعات لليوم.',
                $journey->wasRecentlyCreated ? 201 : 200,
            );
        });
    }

    public function show(Request $request, SalesJourney $salesJourney, SalesFieldOperationService $service, MobileSyncVersionService $versions): JsonResponse
    {
        Gate::authorize('view', $salesJourney);
        return ApiResponse::success($this->resourceData($request, $service->loadJourney($salesJourney), $versions), 'تم تحميل رحلة المبيعات.');
    }

    public function start(StartSalesJourneyRequest $request, SalesJourney $salesJourney, SalesFieldOperationService $service, MobileSyncVersionService $versions): JsonResponse
    {
        return $this->handleOperationalWrite(fn (): JsonResponse => ApiResponse::success(
            $this->resourceData($request, $service->start($salesJourney, $request->validated()), $versions),
            'تم بدء رحلة المبيعات.',
        ));
    }

    public function finish(FinishSalesJourneyRequest $request, SalesJourney $salesJourney, SalesFieldOperationService $service, MobileSyncVersionService $versions): JsonResponse
    {
        return $this->handleOperationalWrite(fn (): JsonResponse => ApiResponse::success(
            $this->resourceData($request, $service->finish($salesJourney, $request->validated()), $versions),
            'تم إنهاء رحلة المبيعات.',
        ));
    }

    private function resourceData(Request $request, SalesJourney $journey, MobileSyncVersionService $versions): array
    {
        return [
            ...SalesJourneyResource::make($journey)->resolve($request),
            'sync_version' => $versions->forRecord('sales_journeys', $journey),
        ];
    }
}
