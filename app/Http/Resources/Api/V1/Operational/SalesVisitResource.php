<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class SalesVisitResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => (int) $this->id,
            'sales_journey_id' => (int) $this->sales_journey_id,
            'customer_id' => (int) $this->customer_id,
            'planned_sequence' => (int) $this->planned_sequence,
            'status' => $this->status,
            'outcome' => $this->outcome,
            'started_at' => $this->dateTime($this->started_at),
            'completed_at' => $this->dateTime($this->completed_at),
            'start_location' => $this->start_latitude === null || $this->start_longitude === null ? null : [
                'latitude' => (string) $this->start_latitude,
                'longitude' => (string) $this->start_longitude,
            ],
            'completion_location' => $this->completion_latitude === null || $this->completion_longitude === null ? null : [
                'latitude' => (string) $this->completion_latitude,
                'longitude' => (string) $this->completion_longitude,
            ],
            'start_notes' => $this->start_notes,
            'completion_notes' => $this->completion_notes,
            'customer' => $this->whenLoaded('customer', fn () => CustomerResource::make($this->customer)->resolve($request)),
            'route' => $this->whenLoaded('route', fn () => RouteResource::make($this->route)->resolve($request)),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? VehicleResource::make($this->vehicle)->resolve($request) : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? WarehouseResource::make($this->warehouse)->resolve($request) : null),
            'sales_representative' => $this->whenLoaded('salesRepresentative', fn () => EmployeeSummaryResource::make($this->salesRepresentative)->resolve($request)),
            'documents' => [
                'invoices' => (int) ($this->invoices_count
                    ?? ($this->relationLoaded('invoices') ? $this->invoices->count() : 0)),
                'payments' => (int) ($this->payments_count
                    ?? ($this->relationLoaded('payments') ? $this->payments->count() : 0)),
                'returns' => (int) ($this->returns_count
                    ?? ($this->relationLoaded('returns') ? $this->returns->count() : 0)),
            ],
            'actions' => [
                'can_start' => $user?->can('start', $this->resource) ?? false,
                'can_complete' => $user?->can('complete', $this->resource) ?? false,
            ],
        ];
    }
}
