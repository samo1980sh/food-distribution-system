<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class SalesJourneyResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => (int) $this->id,
            'journey_number' => $this->journey_number,
            'journey_date' => $this->date($this->journey_date),
            'status' => $this->status,
            'started_at' => $this->dateTime($this->started_at),
            'finished_at' => $this->dateTime($this->finished_at),
            'start_notes' => $this->start_notes,
            'finish_notes' => $this->finish_notes,
            'route' => $this->whenLoaded('route', fn () => RouteResource::make($this->route)->resolve($request)),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? VehicleResource::make($this->vehicle)->resolve($request) : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? WarehouseResource::make($this->warehouse)->resolve($request) : null),
            'sales_representative' => $this->whenLoaded('salesRepresentative', fn () => EmployeeSummaryResource::make($this->salesRepresentative)->resolve($request)),
            'visits' => $this->whenLoaded('visits', fn () => SalesVisitResource::collection($this->visits)->resolve($request)),
            'summary' => [
                'total' => (int) ($this->visits_count ?? $this->visits->count()),
                'pending' => (int) ($this->pending_visits_count ?? $this->visits->where('status', 'pending')->count()),
                'in_progress' => (int) ($this->in_progress_visits_count ?? $this->visits->where('status', 'in_progress')->count()),
                'completed' => (int) ($this->completed_visits_count ?? $this->visits->where('status', 'completed')->count()),
            ],
            'actions' => [
                'can_start' => $user?->can('start', $this->resource) ?? false,
                'can_finish' => $user?->can('finish', $this->resource) ?? false,
            ],
        ];
    }
}
