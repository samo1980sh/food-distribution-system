<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EffectiveAccessScopeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->resource->role?->value,
            'unrestricted' => $this->resource->unrestricted,
            'has_assignments' => $this->resource->hasAssignments(),
            'employee_id' => $this->resource->employeeId,
            'area_ids' => $this->resource->areaIds,
            'route_ids' => $this->resource->routeIds,
            'vehicle_ids' => $this->resource->vehicleIds,
            'warehouse_ids' => $this->resource->warehouseIds,
            'employee_ids' => $this->resource->employeeIds,
        ];
    }
}
