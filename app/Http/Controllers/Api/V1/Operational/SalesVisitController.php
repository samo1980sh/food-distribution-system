<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\CompleteSalesVisitRequest;
use App\Http\Requests\Api\V1\Operational\StartSalesVisitRequest;
use App\Http\Resources\Api\V1\Operational\SalesVisitResource;
use App\Models\SalesVisit;
use App\Services\Api\MobileSyncVersionService;
use App\Services\Distribution\SalesFieldOperationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SalesVisitController extends Controller
{
    use HandlesOperationalWriteResponses;

    public function show(Request $request, SalesVisit $salesVisit, SalesFieldOperationService $service, MobileSyncVersionService $versions): JsonResponse
    {
        Gate::authorize('view', $salesVisit);
        return ApiResponse::success($this->resourceData($request, $service->loadVisit($salesVisit), $versions), 'تم تحميل الزيارة.');
    }

    public function start(StartSalesVisitRequest $request, SalesVisit $salesVisit, SalesFieldOperationService $service, MobileSyncVersionService $versions): JsonResponse
    {
        return $this->handleOperationalWrite(fn (): JsonResponse => ApiResponse::success(
            $this->resourceData($request, $service->startVisit($salesVisit, $request->validated()), $versions),
            'تم بدء زيارة العميل.',
        ));
    }

    public function complete(CompleteSalesVisitRequest $request, SalesVisit $salesVisit, SalesFieldOperationService $service, MobileSyncVersionService $versions): JsonResponse
    {
        return $this->handleOperationalWrite(fn (): JsonResponse => ApiResponse::success(
            $this->resourceData($request, $service->completeVisit($salesVisit, $request->validated()), $versions),
            'تم إنهاء زيارة العميل.',
        ));
    }

    private function resourceData(Request $request, SalesVisit $visit, MobileSyncVersionService $versions): array
    {
        return [
            ...SalesVisitResource::make($visit)->resolve($request),
            'sync_version' => $versions->forRecord('sales_visits', $visit),
        ];
    }
}
