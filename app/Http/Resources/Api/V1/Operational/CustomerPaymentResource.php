<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class CustomerPaymentResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => (int) $this->id,
            'client_reference' => $this->client_reference,
            'payment_number' => $this->payment_number,
            'payment_date' => $this->date($this->payment_date),
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'amount' => $this->decimal($this->amount),
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer
                ? CustomerResource::make($this->customer)->resolve($request)
                : null),
            'sales_invoice' => $this->whenLoaded('salesInvoice', fn () => $this->salesInvoice ? [
                'id' => (int) $this->salesInvoice->id,
                'invoice_number' => $this->salesInvoice->invoice_number,
                'total_amount' => $this->decimal($this->salesInvoice->total_amount),
                'remaining_amount' => $this->decimal($this->salesInvoice->remaining_amount),
            ] : null),
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
