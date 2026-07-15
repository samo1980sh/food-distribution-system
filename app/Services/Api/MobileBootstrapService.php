<?php

namespace App\Services\Api;

use App\Http\Resources\Api\V1\EffectiveAccessScopeResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Authorization\AccessScopeService;
use Illuminate\Http\Request;

class MobileBootstrapService
{
    public function __construct(
        private readonly AccessScopeService $accessScopeService,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(User $user, Request $request): array
    {
        $user->loadMissing('employee');
        $scope = $this->accessScopeService->for($user);

        return [
            'api' => [
                'version' => (string) config('mobile_api.version', 'v1'),
                'server_time' => now()->toIso8601String(),
                'timezone' => (string) config('app.timezone'),
            ],
            'user' => UserResource::make($user)->resolve($request),
            'scope' => EffectiveAccessScopeResource::make($scope)->resolve($request),
            'features' => [
                'offline_sync' => false,
                'push_notifications' => false,
                'background_location' => false,
            ],
        ];
    }
}
