<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            return ApiResponse::error(
                'الخدمة غير جاهزة حاليًا.',
                'service_unavailable',
                503,
            );
        }

        return ApiResponse::success([
            'status' => 'ok',
            'version' => (string) config('mobile_api.version', 'v1'),
            'time' => now()->toIso8601String(),
        ], 'الخدمة تعمل بصورة طبيعية.');
    }
}
