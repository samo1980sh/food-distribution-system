<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\MobileSessionResource;
use App\Models\User;
use App\Services\Api\MobileBootstrapService;
use App\Services\Api\MobileTokenService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const DUMMY_PASSWORD_HASH =
        '$2y$12$FyLhlfzte1cBS/hBXEX.guIpq6rgh1s2VMbTVSR/o21ZWcf0Itxqm';

    public function __construct(
        private readonly MobileTokenService $tokenService,
        private readonly MobileBootstrapService $bootstrapService,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        $passwordIsValid = Hash::check(
            $request->string('password')->toString(),
            $user?->password ?? self::DUMMY_PASSWORD_HASH,
        );

        if (! $user instanceof User || ! $passwordIsValid) {
            throw ValidationException::withMessages([
                'email' => ['بيانات تسجيل الدخول غير صحيحة.'],
            ]);
        }

        if (! $user->isActive()) {
            return ApiResponse::error(
                'الحساب غير فعّال. يرجى التواصل مع الإدارة.',
                'account_inactive',
                403,
            );
        }

        if (! $user->can(PermissionName::API_ACCESS->value)) {
            return ApiResponse::error(
                'الحساب لا يملك صلاحية استخدام واجهة التطبيق.',
                'api_access_denied',
                403,
            );
        }

        $newToken = $this->tokenService->issue(
            $user,
            $request->safe()->only([
                'device_id',
                'device_name',
                'platform',
                'app_version',
            ]),
            $request,
        );

        $user->withAccessToken($newToken->accessToken);

        return ApiResponse::success([
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $newToken->accessToken->expires_at?->toIso8601String(),
            'bootstrap' => $this->bootstrapService->build($user, $request),
        ], 'تم تسجيل الدخول بنجاح.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->bootstrapService->build($request->user(), $request),
            'تم تحميل بيانات الحساب.',
        );
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = MobileSessionResource::collection(
            $this->tokenService->sessions($request->user()),
        )->resolve($request);

        return ApiResponse::success([
            'sessions' => $sessions,
        ], 'تم تحميل جلسات التطبيق.');
    }

    public function destroySession(Request $request, int $token): JsonResponse
    {
        $currentRevoked = $this->tokenService->revokeSession(
            $request->user(),
            $token,
        );

        return ApiResponse::success([
            'current_session_revoked' => $currentRevoked,
        ], 'تم إنهاء الجلسة المطلوبة.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokenService->revokeCurrent($request->user());

        return ApiResponse::success(
            message: 'تم تسجيل الخروج من هذا الجهاز.',
        );
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $revoked = $this->tokenService->revokeAll($request->user());

        return ApiResponse::success([
            'revoked_sessions' => $revoked,
        ], 'تم تسجيل الخروج من جميع الأجهزة.');
    }
}
