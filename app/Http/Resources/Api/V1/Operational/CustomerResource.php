<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class CustomerResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'owner_name' => $this->owner_name,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'customer_type' => $this->customer_type,
            'address' => $this->address,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'credit_limit' => $this->decimal($this->credit_limit),
            'credit_days' => (int) $this->credit_days,
            'payment_type' => $this->payment_type,
            'status' => $this->status,
            'notes' => $this->notes,
            'area' => $this->whenLoaded('area', fn () => $this->area
                ? AreaResource::make($this->area)->resolve($request)
                : null),
            'route' => $this->whenLoaded('route', fn () => $this->route ? [
                'id' => (int) $this->route->id,
                'code' => $this->route->code,
                'name' => $this->route->name,
            ] : null),
        ];
    }
}
