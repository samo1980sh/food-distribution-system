<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Services\Api\MobileOperationalService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperationalDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        MobileOperationalService $service,
    ): JsonResponse {
        Gate::authorize(PermissionName::DASHBOARD_VIEW->value);

        return ApiResponse::success(
            $service->dashboard($request->user()),
            'تم تحميل ملخص اليوم.',
        );
    }
}
