<?php

namespace App\Services\Api;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MobileTokenService
{
    public function issue(
        User $user,
        array $device,
        Request $request,
    ): NewAccessToken {
        return DB::transaction(function () use ($user, $device, $request): NewAccessToken {
            User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->mobileTokens($user)
                ->where('device_id', $device['device_id'])
                ->delete();

            $this->mobileTokens($user)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->delete();

            $this->enforceSessionLimit($user);

            $ttl = max(1, (int) config('mobile_api.token_ttl_minutes', 43200));
            $expiresAt = now()->addMinutes($ttl);
            $ability = (string) config('mobile_api.token_ability', 'api:v1');
            $name = $this->tokenName(
                $device['platform'],
                $device['device_name'],
            );

            $newToken = $user->createToken($name, [$ability], $expiresAt);

            $newToken->accessToken->forceFill([
                'device_id' => $device['device_id'],
                'device_name' => $device['device_name'],
                'platform' => $device['platform'],
                'app_version' => $device['app_version'] ?? null,
                'ip_address' => $request->ip(),
                'last_seen_at' => now(),
            ])->save();

            return $newToken;
        });
    }

    public function revokeCurrent(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function revokeAll(User $user): int
    {
        return $this->mobileTokens($user)->delete();
    }

    public function revokeSession(User $user, int $tokenId): bool
    {
        $token = $this->mobileTokens($user)->whereKey($tokenId)->first();

        if (! $token instanceof PersonalAccessToken) {
            throw new NotFoundHttpException('جلسة التطبيق المطلوبة غير موجودة.');
        }

        $currentTokenId = $user->currentAccessToken()?->getKey();
        $isCurrent = (int) $currentTokenId === (int) $token->getKey();
        $token->delete();

        return $isCurrent;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, PersonalAccessToken> */
    public function sessions(User $user): Collection
    {
        $this->mobileTokens($user)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        return $this->mobileTokens($user)
            ->latest('last_seen_at')
            ->latest('created_at')
            ->get();
    }

    private function enforceSessionLimit(User $user): void
    {
        $maxSessions = max(1, (int) config('mobile_api.max_sessions', 5));
        $tokenIds = $this->mobileTokens($user)
            ->oldest('last_seen_at')
            ->oldest('created_at')
            ->oldest('id')
            ->pluck('id');

        $excess = max(0, $tokenIds->count() - $maxSessions + 1);

        if ($excess === 0) {
            return;
        }

        $this->mobileTokens($user)
            ->whereIn('id', $tokenIds->take($excess)->all())
            ->delete();
    }

    /** @return MorphMany<PersonalAccessToken, User> */
    private function mobileTokens(User $user): MorphMany
    {
        $prefix = (string) config('mobile_api.token_name_prefix', 'mobile:');

        return $user->tokens()
            ->where('name', 'like', $prefix.'%');
    }

    private function tokenName(string $platform, string $deviceName): string
    {
        $prefix = (string) config('mobile_api.token_name_prefix', 'mobile:');

        return $prefix.$platform.':'.Str::limit($deviceName, 80, '');
    }
}
