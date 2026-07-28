<?php

namespace App\Http\Resources\Api\V1\Operational;

use App\Enums\PermissionName;
use Illuminate\Http\Request;

class VehicleLoadItemResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        $canSeeCost = $request->user()?->can(PermissionName::REPORT_PROFIT->value) === true;

        return [
            'id' => (int) $this->id,
            'product' => $this->whenLoaded(
                'product',
                fn () => $this->product
                    ? ProductResource::make($this->product)->resolve($request)
                    : null,
            ),
            'product_id' => $this->product_id === null ? null : (int) $this->product_id,
            'product_sku' => $this->whenLoaded('product', fn () => $this->product?->sku),
            'product_name' => $this->whenLoaded('product', fn () => $this->product?->name_ar),
            'unit_label' => $this->whenLoaded(
                'product',
                fn () => $this->product?->unit?->symbol
                    ?: $this->product?->unit?->name_ar,
            ),
            'batch_number' => $this->batch_number,
            'expiry_date' => $this->date($this->expiry_date),
            'quantity' => $this->decimal($this->quantity, 3),
            'received_quantity' => $this->received_quantity === null
                ? null
                : $this->decimal($this->received_quantity, 3),
            'handover_note' => $this->handover_note,
            'unit_cost' => $canSeeCost ? $this->decimal($this->unit_cost, 6) : null,
            'total_cost' => $canSeeCost ? $this->decimal($this->total_cost) : null,
        ];
    }
}
