<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class DriverJourneyResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => (int) $this->id,
            'journey_number' => $this->journey_number,
            'journey_date' => $this->date($this->journey_date),
            'status' => $this->status,
            'start_odometer' => $this->start_odometer === null ? null : $this->decimal($this->start_odometer),
            'end_odometer' => $this->end_odometer === null ? null : $this->decimal($this->end_odometer),
            'started_at' => $this->dateTime($this->started_at),
            'finished_at' => $this->dateTime($this->finished_at),
            'start_notes' => $this->start_notes,
            'finish_notes' => $this->finish_notes,
            'route' => $this->whenLoaded('route', fn () => RouteResource::make($this->route)->resolve($request)),
            'vehicle' => $this->whenLoaded('vehicle', fn () => VehicleResource::make($this->vehicle)->resolve($request)),
            'warehouse' => $this->whenLoaded('warehouse', fn () => WarehouseResource::make($this->warehouse)->resolve($request)),
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
            'deliveries' => $this->whenLoaded(
                'deliveries',
                fn () => DriverDeliveryResource::collection($this->deliveries)->resolve($request),
            ),
            'summary' => [
                'total' => (int) ($this->deliveries_count ?? $this->deliveries->count()),
                'pending' => (int) ($this->pending_deliveries_count ?? $this->deliveries->where('status', 'pending')->count()),
                'delivered' => (int) ($this->delivered_deliveries_count ?? $this->deliveries->where('status', 'delivered')->count()),
                'partial' => (int) ($this->partial_deliveries_count ?? $this->deliveries->where('status', 'partial')->count()),
                'failed' => (int) ($this->failed_deliveries_count ?? $this->deliveries->where('status', 'failed')->count()),
            ],
            'actions' => [
                'can_start' => $user?->can('start', $this->resource) ?? false,
                'can_finish' => $user?->can('finish', $this->resource) ?? false,
            ],
        ];
    }
}
