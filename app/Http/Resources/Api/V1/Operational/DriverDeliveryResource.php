<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class DriverDeliveryResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => (int) $this->id,
            'driver_journey_id' => (int) $this->driver_journey_id,
            'status' => $this->status,
            'expected_quantity' => $this->decimal($this->expected_quantity, 3),
            'delivered_quantity' => $this->decimal($this->delivered_quantity, 3),
            'returned_quantity' => $this->decimal($this->returned_quantity, 3),
            'return_required' => (bool) $this->return_required,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'latitude' => $this->latitude === null ? null : (string) $this->latitude,
            'longitude' => $this->longitude === null ? null : (string) $this->longitude,
            'proof_note' => $this->proof_note,
            'failure_reason' => $this->failure_reason,
            'outcome_submitted_at' => $this->dateTime($this->outcome_submitted_at),
            'sales_invoice' => $this->whenLoaded('salesInvoice', fn () => [
                'id' => (int) $this->salesInvoice->id,
                'invoice_number' => $this->salesInvoice->invoice_number,
                'invoice_date' => $this->date($this->salesInvoice->invoice_date),
                'total_amount' => $this->decimal($this->salesInvoice->total_amount),
                'status' => $this->salesInvoice->status,
            ]),
            'customer' => $this->whenLoaded(
                'customer',
                fn () => $this->customer
                    ? CustomerResource::make($this->customer)->resolve($request)
                    : null,
            ),
            'sales_representative' => $this->whenLoaded(
                'salesRepresentative',
                fn () => $this->salesRepresentative
                    ? EmployeeSummaryResource::make($this->salesRepresentative)->resolve($request)
                    : null,
            ),
            'items' => $this->whenLoaded(
                'items',
                fn () => DriverDeliveryItemResource::collection($this->items)->resolve($request),
            ),
            'actions' => [
                'can_submit_outcome' => $user?->can('submitOutcome', $this->resource) ?? false,
            ],
        ];
    }
}
