<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class ProductCategoryResource extends OperationalResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'parent_id' => $this->parent_id === null ? null : (int) $this->parent_id,
            'code' => $this->code,
            'name' => $this->name_ar,
            'status' => $this->status,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
