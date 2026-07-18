<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Api\MobileAppAccess;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                'يجب تسجيل الدخول للوصول إلى هذه الخدمة.',
                'unauthenticated',
                401,
            );
        }

        if (! $user->isActive()) {
            $user->currentAccessToken()?->delete();

            return ApiResponse::error(
                'الحساب غير فعّال أو لم يعد متاحًا.',
                'account_inactive',
                403,
            );
        }

        if (! MobileAppAccess::allows($user)) {
            $user->currentAccessToken()?->delete();

            return ApiResponse::error(
                'هذا التطبيق مخصص لحسابات السائقين ومندوبي المبيعات فقط.',
                'mobile_role_denied',
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

        $token = $user->currentAccessToken();

        if (
            ! $token instanceof PersonalAccessToken
            || ! $user->tokenCan((string) config('mobile_api.token_ability'))
        ) {
            return ApiResponse::error(
                'رمز الدخول لا يملك الصلاحية المطلوبة.',
                'token_ability_denied',
                403,
            );
        }

        return $next($request);
    }
}
