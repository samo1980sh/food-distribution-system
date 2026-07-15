<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = UserRole::tryFrom((string) $this->primaryRoleName());

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()
                ->pluck('name')
                ->sort()
                ->values()
                ->all(),
            'employee' => $this->whenLoaded(
                'employee',
                fn () => $this->employee === null
                    ? null
                    : EmployeeResource::make($this->employee)->resolve($request),
            ),
        ];
    }
}
