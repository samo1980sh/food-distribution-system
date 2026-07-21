<?php

namespace App\Http\Resources\Api\V1\Operational;

use App\Enums\PermissionName;
use Illuminate\Http\Request;

class VehicleLoadResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canSeeCost = $user?->can(PermissionName::REPORT_PROFIT->value) === true;

        return [
            'id' => (int) $this->id,
            'load_number' => $this->load_number,
            'load_date' => $this->date($this->load_date),
            'status' => $this->status,
            'handover_status' => $this->handover_status ?? 'pending',
            'handover_notes' => $this->handover_notes,
            'total_quantity' => $this->decimal($this->total_quantity, 3),
            'total_cost' => $canSeeCost ? $this->decimal($this->total_cost) : null,
            'notes' => $this->notes,
            'vehicle' => $this->whenLoaded(
                'vehicle',
                fn () => $this->vehicle
                    ? VehicleResource::make($this->vehicle)->resolve($request)
                    : null,
            ),
            'route' => $this->whenLoaded(
                'route',
                fn () => $this->route ? [
                    'id' => (int) $this->route->id,
                    'code' => $this->route->code,
                    'name' => $this->route->name,
                ] : null,
            ),
            'driver' => $this->whenLoaded(
                'driver',
                fn () => $this->driver
                    ? EmployeeSummaryResource::make($this->driver)->resolve($request)
                    : null,
            ),
            'sales_representative' => $this->whenLoaded(
                'salesRepresentative',
                fn () => $this->salesRepresentative
                    ? EmployeeSummaryResource::make($this->salesRepresentative)->resolve($request)
                    : null,
            ),
            'from_warehouse' => $this->whenLoaded(
                'fromWarehouse',
                fn () => $this->fromWarehouse
                    ? WarehouseResource::make($this->fromWarehouse)->resolve($request)
                    : null,
            ),
            'to_warehouse' => $this->whenLoaded(
                'toWarehouse',
                fn () => $this->toWarehouse
                    ? WarehouseResource::make($this->toWarehouse)->resolve($request)
                    : null,
            ),
            'items' => $this->whenLoaded(
                'items',
                fn () => VehicleLoadItemResource::collection($this->items)->resolve($request),
            ),
            'approved_at' => $this->dateTime($this->approved_at),
            'handover_at' => $this->dateTime($this->handover_at),
            'handover_by' => $this->whenLoaded(
                'handoverUser',
                fn () => $this->handoverUser ? [
                    'id' => (int) $this->handoverUser->id,
                    'name' => $this->handoverUser->name,
                ] : null,
            ),
            'actions' => [
                'can_update' => $user?->can('update', $this->resource) ?? false,
                'can_approve' => $user?->can('approve', $this->resource) ?? false,
                'can_cancel' => $user?->can('cancel', $this->resource) ?? false,
                'can_acknowledge' => $user?->can('acknowledge', $this->resource) ?? false,
            ],
        ];
    }
}
