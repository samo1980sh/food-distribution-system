<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class FieldOperationalDayOverrideResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'operation_date' => $this->date($this->operation_date),
            'status' => $this->status,
            'reason' => $this->reason,
            'route_id' => (int) $this->route_id,
            'vehicle_id' => (int) $this->vehicle_id,
            'sales_representative_id' => (int) $this->sales_representative_id,
            'cancelled_at' => $this->dateTime($this->cancelled_at),
        ];
    }
}
