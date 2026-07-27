<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\FieldTodayRequest;
use App\Http\Resources\Api\V1\Operational\FieldTodayResource;
use App\Services\Distribution\FieldTodayReadService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class FieldTodayController extends Controller
{
    public function __invoke(
        FieldTodayRequest $request,
        FieldTodayReadService $service,
    ): JsonResponse {
        $data = $service->build(
            $request->user(),
            $request->validated('role'),
            $request->validated('route_id'),
        );

        return ApiResponse::success(
            FieldTodayResource::make($data)->resolve($request),
            'تم تحميل سياق العمل الميداني لليوم.',
        );
    }
}
