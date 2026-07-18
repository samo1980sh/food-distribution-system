<?php

namespace App\Http\Resources\Api\V1\Operational;

use Illuminate\Http\Request;

class DailyClosingResource extends OperationalResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => (int) $this->id,
            'client_reference' => $this->client_reference,
            'closing_number' => $this->closing_number,
            'closing_date' => $this->date($this->closing_date),
            'status' => $this->status,
            'total_opening_quantity' => $this->decimal($this->total_opening_quantity, 3),
            'total_movement_in_quantity' => $this->decimal($this->total_movement_in_quantity, 3),
            'total_movement_out_quantity' => $this->decimal($this->total_movement_out_quantity, 3),
            'total_expected_quantity' => $this->decimal($this->total_expected_quantity, 3),
            'total_loaded_quantity' => $this->decimal($this->total_loaded_quantity, 3),
            'total_sold_quantity' => $this->decimal($this->total_sold_quantity, 3),
            'total_returned_quantity' => $this->decimal($this->total_returned_quantity, 3),
            'total_sales_amount' => $this->decimal($this->total_sales_amount),
            'total_returns_amount' => $this->decimal($this->total_returns_amount),
            'total_collections_amount' => $this->decimal($this->total_collections_amount),
            'expected_cash_amount' => $this->decimal($this->expected_cash_amount),
            'actual_cash_amount' => $this->decimal($this->actual_cash_amount),
            'cash_difference' => $this->decimal($this->cash_difference),
            'inventory' => [
                'opening_quantity' => $this->decimal($this->total_opening_quantity, 3),
                'movement_in_quantity' => $this->decimal($this->total_movement_in_quantity, 3),
                'movement_out_quantity' => $this->decimal($this->total_movement_out_quantity, 3),
                'expected_quantity' => $this->decimal($this->total_expected_quantity, 3),
                'loaded_quantity' => $this->decimal($this->total_loaded_quantity, 3),
                'sold_quantity' => $this->decimal($this->total_sold_quantity, 3),
                'returned_quantity' => $this->decimal($this->total_returned_quantity, 3),
            ],
            'financial' => [
                'sales_amount' => $this->decimal($this->total_sales_amount),
                'returns_amount' => $this->decimal($this->total_returns_amount),
                'collections_amount' => $this->decimal($this->total_collections_amount),
                'invoice_cash_amount' => $this->decimal($this->invoice_cash_amount),
                'cash_collections_amount' => $this->decimal($this->cash_collections_amount),
                'bank_transfer_collections_amount' => $this->decimal($this->bank_transfer_collections_amount),
                'cheque_collections_amount' => $this->decimal($this->cheque_collections_amount),
                'other_collections_amount' => $this->decimal($this->other_collections_amount),
                'non_cash_collections_amount' => $this->decimal($this->non_cash_collections_amount),
                'vehicle_expenses_amount' => $this->decimal($this->total_vehicle_expenses_amount),
                'cash_vehicle_expenses_amount' => $this->decimal($this->cash_vehicle_expenses_amount),
                'non_cash_vehicle_expenses_amount' => $this->decimal($this->non_cash_vehicle_expenses_amount),
                'expected_cash_amount' => $this->decimal($this->expected_cash_amount),
                'actual_cash_amount' => $this->decimal($this->actual_cash_amount),
                'cash_difference' => $this->decimal($this->cash_difference),
            ],
            'notes' => $this->notes,
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
            'items' => $this->whenLoaded('items', fn () => DailyClosingItemResource::collection($this->items)->resolve($request)),
            'snapshot_at' => $this->dateTime($this->snapshot_at),
            'confirmed_at' => $this->dateTime($this->confirmed_at),
            'actions' => [
                'can_update' => $user?->can('update', $this->resource) ?? false,
                'can_delete' => $user?->can('delete', $this->resource) ?? false,
                'can_refresh_totals' => $user?->can('refreshTotals', $this->resource) ?? false,
                'can_confirm' => $user?->can('confirm', $this->resource) ?? false,
                'can_cancel' => $user?->can('cancel', $this->resource) ?? false,
            ],
        ];
    }
}
