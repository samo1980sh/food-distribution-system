<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class DistributionRouteResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'visit_days' => $this->visit_days ?? [],
            'status' => $this->status,
            'area' => $this->whenLoaded('area', fn () => $this->reference(
                $this->area,
                ['id', 'code', 'name' => 'name_ar'],
            )),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->reference(
                $this->vehicle,
                ['id', 'code', 'plate_number', 'name'],
            )),
            'driver' => $this->whenLoaded('driver', fn () => $this->reference(
                $this->driver,
                ['id', 'employee_code', 'name', 'phone'],
            )),
            'sales_representative' => $this->whenLoaded(
                'salesRepresentative',
                fn () => $this->reference(
                    $this->salesRepresentative,
                    ['id', 'employee_code', 'name', 'phone'],
                ),
            ),
            'updated_at' => $this->dateTimeValue($this->updated_at),
        ];
    }
}
