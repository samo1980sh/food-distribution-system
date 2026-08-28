<?php

namespace App\Http\Resources\Api\V1\Operational;

use App\Enums\PermissionName;
use Illuminate\Http\Request;

class ProductResource extends OperationalResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $canSeeCost = $request->user()?->can(PermissionName::REPORT_PROFIT->value) === true;

        $unitLabel = $this->relationLoaded('unit')
            ? trim((string) ($this->unit?->symbol ?: $this->unit?->name_ar))
            : '';

        if ($unitLabel === '') {
            $unitLabel = 'وحدة غير محددة';
        }

        return [
            'id' => (int) $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name_ar,
            'category' => $this->whenLoaded(
                'category',
                fn () => $this->category
                    ? [
                        'id' => (int) $this->category->id,
                        'name' => $this->category->name_ar,
                    ]
                    : null,
            ),
            'unit' => $this->whenLoaded(
                'unit',
                fn () => $this->unit
                    ? [
                        'id' => (int) $this->unit->id,
                        'name' => $this->unit->name_ar,
                        'symbol' => $this->unit->symbol,
                    ]
                    : null,
            ),
            'quantity_unit_label' => $unitLabel,
            'sale_price' => $this->decimal($this->sale_price),
            'wholesale_price' => $this->decimal($this->wholesale_price),
            'purchase_price' => $canSeeCost
                ? $this->decimal($this->purchase_price)
                : null,
            'min_stock' => $this->decimal($this->min_stock, 3),
            'has_expiry' => (bool) $this->has_expiry,
            'status' => $this->status,
        ];
    }
}
