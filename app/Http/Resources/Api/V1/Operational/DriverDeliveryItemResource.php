<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class DriverDeliveryItemResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'sales_invoice_item_id' => (int) $this->sales_invoice_item_id,
            'product' => $this->whenLoaded('product', fn () => ProductResource::make($this->product)->resolve($request)),
            'batch_number' => $this->batch_number,
            'expiry_date' => $this->date($this->expiry_date),
            'expected_quantity' => $this->decimal($this->expected_quantity, 3),
            'delivered_quantity' => $this->decimal($this->delivered_quantity, 3),
            'returned_quantity' => $this->decimal($this->returned_quantity, 3),
            'notes' => $this->notes,
        ];
    }
}
