<?php

namespace App\Services\Api;

use App\Models\User;
use App\Services\Authorization\AccessScopeService;
use App\Support\Api\MobileSyncEntityRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileSyncContextService
{
    public function __construct(
        private readonly AccessScopeService $accessScopeService,
    ) {
    }

    public function key(User $user): string
    {
        $permissions = $user->getAllPermissions()
            ->pluck('name')
            ->map(static fn (mixed $permission): string => (string) $permission)
            ->sort()
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'registry_version' => MobileSyncEntityRegistry::VERSION,
            'scope' => $this->accessScopeService->cacheKey($user),
            'permissions' => $permissions,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function deviceId(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();
        $deviceId = trim((string) $token?->getAttribute('device_id'));

        if ($deviceId !== '') {
            return $deviceId;
        }

        $tokenId = $token?->getKey();

        return $tokenId === null
            ? 'user:'.(string) Auth::id()
            : 'token:'.(string) $tokenId;
    }
}
