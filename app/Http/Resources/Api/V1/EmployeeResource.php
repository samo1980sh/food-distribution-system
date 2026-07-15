<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'employee_code' => $this->employee_code,
            'name' => $this->name,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'type' => $this->type,
            'status' => $this->status,
        ];
    }
}
