<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\MobileSyncPullRequest;
use App\Models\User;
use App\Services\Api\MobileOfflineSyncService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSyncController extends Controller
{
    use HandlesOperationalWriteResponses;

    public function status(
        Request $request,
        MobileOfflineSyncService $syncService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $syncService->status($user, $request),
            'تم تحميل حالة المزامنة.',
        );
    }

    public function pull(
        MobileSyncPullRequest $request,
        MobileOfflineSyncService $syncService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return $this->handleOperationalWrite(fn (): JsonResponse => ApiResponse::success(
            $syncService->pull(
                $user,
                $request,
                $request->cursor(),
                $request->limit(),
                $request->contextKey(),
            ),
            'تم تحميل تغييرات المزامنة.',
        ));
    }
}
