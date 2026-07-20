<?php

namespace App\Http\Resources\Api\V1\Operational;

use App\Enums\OperationSource;
use Illuminate\Http\Request;

class SalesInvoiceResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $source = OperationSource::fromState($this->operation_source);

        return [
            'id' => (int) $this->id,
            'client_reference' => $this->client_reference,
            'operation_source' => $source->value,
            'operation_source_label' => $source->label(),
            'administrative_reason' => $this->administrative_reason,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->date($this->invoice_date),
            'due_date' => $this->date($this->due_date),
            'status' => $this->status,
            'payment_type' => $this->payment_type,
            'subtotal' => $this->decimal($this->subtotal),
            'discount_amount' => $this->decimal($this->discount_amount),
            'tax_amount' => $this->decimal($this->tax_amount),
            'total_amount' => $this->decimal($this->total_amount),
            'paid_amount' => $this->decimal($this->paid_amount),
            'invoice_cash_amount' => $this->decimal($this->invoice_cash_amount),
            'remaining_amount' => $this->decimal($this->remaining_amount),
            'credit_control' => [
                'limit_snapshot' => $this->decimal($this->credit_limit_snapshot),
                'exposure_before' => $this->decimal($this->credit_exposure_before),
                'exposure_after' => $this->decimal($this->credit_exposure_after),
                'override_requested' => (bool) $this->credit_limit_override_requested,
                'overridden' => (bool) $this->credit_limit_overridden,
                'override_reason' => $this->credit_limit_override_reason,
                'overridden_at' => $this->dateTime($this->credit_limit_overridden_at),
            ],
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer
                ? CustomerResource::make($this->customer)->resolve($request)
                : null),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle
                ? VehicleResource::make($this->vehicle)->resolve($request)
                : null),
            'route' => $this->whenLoaded('route', fn () => $this->route ? [
                'id' => (int) $this->route->id,
                'code' => $this->route->code,
                'name' => $this->route->name,
            ] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse
                ? WarehouseResource::make($this->warehouse)->resolve($request)
                : null),
            'sales_representative' => $this->whenLoaded('salesRepresentative', fn () => $this->salesRepresentative
                ? EmployeeSummaryResource::make($this->salesRepresentative)->resolve($request)
                : null),
            'items' => $this->whenLoaded('items', fn () => SalesInvoiceItemResource::collection($this->items)->resolve($request)),
            'confirmed_at' => $this->dateTime($this->confirmed_at),
            'actions' => [
                'can_update' => $user?->can('update', $this->resource) ?? false,
                'can_delete' => $user?->can('delete', $this->resource) ?? false,
                'can_confirm' => $user?->can('confirm', $this->resource) ?? false,
                'can_cancel' => $user?->can('cancel', $this->resource) ?? false,
            ],
        ];
    }
}
