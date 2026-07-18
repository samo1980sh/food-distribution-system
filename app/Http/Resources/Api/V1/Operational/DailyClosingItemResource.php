<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class DailyClosingItemResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'product' => $this->whenLoaded('product', fn () => $this->product
                ? ProductResource::make($this->product)->resolve($request)
                : null),
            'opening_quantity' => $this->decimal($this->opening_quantity, 3),
            'movement_in_quantity' => $this->decimal($this->movement_in_quantity, 3),
            'movement_out_quantity' => $this->decimal($this->movement_out_quantity, 3),
            'loaded_quantity' => $this->decimal($this->loaded_quantity, 3),
            'sold_quantity' => $this->decimal($this->sold_quantity, 3),
            'returned_quantity' => $this->decimal($this->returned_quantity, 3),
            'expected_quantity' => $this->decimal($this->expected_quantity, 3),
            'actual_quantity' => $this->actual_quantity === null
                ? null
                : $this->decimal($this->actual_quantity, 3),
            'difference_quantity' => $this->decimal($this->difference_quantity, 3),
            'notes' => $this->notes,
        ];
    }
}
