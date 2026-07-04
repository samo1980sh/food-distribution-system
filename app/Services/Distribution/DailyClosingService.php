<?php

namespace App\Services\Distribution;

use App\Models\DailyClosing;
use App\Services\Support\DocumentNumberService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DailyClosingService
{
    public function refreshTotals(DailyClosing $closing): DailyClosing
    {
        return DB::transaction(function () use ($closing): DailyClosing {
            $closing->loadMissing('warehouse');
            $this->validateClosingScope($closing);

            $date = $closing->closing_date->toDateString();
            $warehouseId = $closing->warehouse_id;
            $vehicleId = $closing->vehicle_id;
            $routeId = $closing->route_id;
            $salesRepresentativeId = $closing->sales_representative_id;

            $existingActuals = $closing->items()
                ->get()
                ->keyBy('product_id')
                ->map(fn ($item) => $item->actual_quantity)
                ->all();

            $loaded = DB::table('vehicle_load_items')
                ->join('vehicle_loads', 'vehicle_load_items.vehicle_load_id', '=', 'vehicle_loads.id')
                ->where('vehicle_loads.status', 'approved')
                ->whereDate('vehicle_loads.load_date', $date)
                ->where('vehicle_loads.to_warehouse_id', $warehouseId)
                ->when($vehicleId, fn (Builder $query) => $query->where('vehicle_loads.vehicle_id', $vehicleId))
                ->when($routeId, fn (Builder $query) => $query->where('vehicle_loads.route_id', $routeId))
                ->selectRaw('vehicle_load_items.product_id, SUM(vehicle_load_items.quantity) as quantity')
                ->groupBy('vehicle_load_items.product_id')
                ->pluck('quantity', 'product_id');

            $sold = DB::table('sales_invoice_items')
                ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
                ->where('sales_invoices.status', 'confirmed')
                ->whereDate('sales_invoices.invoice_date', $date)
                ->where('sales_invoices.warehouse_id', $warehouseId)
                ->when($vehicleId, fn (Builder $query) => $query->where('sales_invoices.vehicle_id', $vehicleId))
                ->when($routeId, fn (Builder $query) => $query->where('sales_invoices.route_id', $routeId))
                ->when($salesRepresentativeId, fn (Builder $query) => $query->where('sales_invoices.sales_representative_id', $salesRepresentativeId))
                ->selectRaw('sales_invoice_items.product_id, SUM(sales_invoice_items.quantity) as quantity')
                ->groupBy('sales_invoice_items.product_id')
                ->pluck('quantity', 'product_id');

            $returned = DB::table('sales_return_items')
                ->join('sales_returns', 'sales_return_items.sales_return_id', '=', 'sales_returns.id')
                ->where('sales_returns.status', 'confirmed')
                ->whereDate('sales_returns.return_date', $date)
                ->where('sales_returns.warehouse_id', $warehouseId)
                ->when($vehicleId, fn (Builder $query) => $query->where('sales_returns.vehicle_id', $vehicleId))
                ->when($routeId, fn (Builder $query) => $query->where('sales_returns.route_id', $routeId))
                ->when($salesRepresentativeId, fn (Builder $query) => $query->where('sales_returns.sales_representative_id', $salesRepresentativeId))
                ->selectRaw('sales_return_items.product_id, SUM(sales_return_items.quantity) as quantity')
                ->groupBy('sales_return_items.product_id')
                ->pluck('quantity', 'product_id');

            $productIds = collect($loaded->keys())
                ->merge($sold->keys())
                ->merge($returned->keys())
                ->unique()
                ->values();

            $closing->items()->delete();

            foreach ($productIds as $productId) {
                $loadedQuantity = (float) ($loaded[$productId] ?? 0);
                $soldQuantity = (float) ($sold[$productId] ?? 0);
                $returnedQuantity = (float) ($returned[$productId] ?? 0);
                $expectedQuantity = $loadedQuantity - $soldQuantity + $returnedQuantity;
                $actualQuantity = $existingActuals[$productId] ?? null;

                $closing->items()->create([
                    'product_id' => $productId,
                    'loaded_quantity' => $loadedQuantity,
                    'sold_quantity' => $soldQuantity,
                    'returned_quantity' => $returnedQuantity,
                    'expected_quantity' => $expectedQuantity,
                    'actual_quantity' => $actualQuantity,
                    'difference_quantity' => $actualQuantity === null ? 0 : ((float) $actualQuantity - $expectedQuantity),
                ]);
            }

            $totalSales = (float) $this->scopedInvoicesQuery($date, $warehouseId, $vehicleId, $routeId, $salesRepresentativeId)
                ->sum('total_amount');

            $invoiceCash = (float) $this->scopedInvoicesQuery($date, $warehouseId, $vehicleId, $routeId, $salesRepresentativeId)
                ->sum('invoice_cash_amount');

            $totalReturns = (float) DB::table('sales_returns')
                ->where('status', 'confirmed')
                ->whereDate('return_date', $date)
                ->where('warehouse_id', $warehouseId)
                ->when($vehicleId, fn (Builder $query) => $query->where('vehicle_id', $vehicleId))
                ->when($routeId, fn (Builder $query) => $query->where('route_id', $routeId))
                ->when($salesRepresentativeId, fn (Builder $query) => $query->where('sales_representative_id', $salesRepresentativeId))
                ->sum('total_amount');

            $collectionsByMethod = $this->scopedPaymentsQuery($date, $warehouseId, $vehicleId, $routeId, $salesRepresentativeId)
                ->selectRaw('payment_method, SUM(amount) as amount')
                ->groupBy('payment_method')
                ->pluck('amount', 'payment_method')
                ->map(fn ($amount): float => (float) $amount);

            $cashCollections = (float) ($collectionsByMethod['cash'] ?? 0);
            $bankTransferCollections = (float) ($collectionsByMethod['bank_transfer'] ?? 0);
            $chequeCollections = (float) ($collectionsByMethod['cheque'] ?? 0);
            $otherCollections = (float) ($collectionsByMethod['other'] ?? 0);
            $totalCollections = $cashCollections + $bankTransferCollections + $chequeCollections + $otherCollections;
            $nonCashCollections = $bankTransferCollections + $chequeCollections + $otherCollections;

            $expectedCash = $invoiceCash + $cashCollections;
            $actualCash = (float) $closing->actual_cash_amount;

            $closing->forceFill([
                'total_loaded_quantity' => $loaded->sum(),
                'total_sold_quantity' => $sold->sum(),
                'total_returned_quantity' => $returned->sum(),
                'total_sales_amount' => $totalSales,
                'total_returns_amount' => $totalReturns,
                'total_collections_amount' => $totalCollections,
                'invoice_cash_amount' => $invoiceCash,
                'cash_collections_amount' => $cashCollections,
                'bank_transfer_collections_amount' => $bankTransferCollections,
                'cheque_collections_amount' => $chequeCollections,
                'other_collections_amount' => $otherCollections,
                'non_cash_collections_amount' => $nonCashCollections,
                'expected_cash_amount' => $expectedCash,
                'cash_difference' => $actualCash - $expectedCash,
            ])->save();

            return $closing->refresh();
        });
    }

    public function confirm(DailyClosing $closing): DailyClosing
    {
        return DB::transaction(function () use ($closing): DailyClosing {
            if (! $closing->isDraft()) {
                throw new RuntimeException('لا يمكن اعتماد إغلاق يوم ليس بحالة مسودة.');
            }

            $this->refreshTotals($closing);

            $closing->forceFill([
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ])->save();

            return $closing;
        });
    }

    public function cancel(DailyClosing $closing): DailyClosing
    {
        return DB::transaction(function () use ($closing): DailyClosing {
            if (! $closing->isConfirmed()) {
                throw new RuntimeException('لا يمكن إلغاء إغلاق يوم غير معتمد.');
            }

            $closing->forceFill([
                'status' => 'cancelled',
            ])->save();

            return $closing;
        });
    }

    public function generateClosingNumber(): string
    {
        return app(DocumentNumberService::class)->next('daily_closing', 'DCL');
    }

    private function validateClosingScope(DailyClosing $closing): void
    {
        if (! $closing->vehicle_id) {
            return;
        }

        if ($closing->warehouse?->type !== 'vehicle') {
            throw new RuntimeException('إغلاق السيارة يجب أن يرتبط بمستودع سيارة.');
        }

        if ((int) $closing->warehouse?->vehicle_id !== (int) $closing->vehicle_id) {
            throw new RuntimeException('مستودع الإغلاق لا يتبع السيارة المحددة.');
        }
    }

    private function scopedInvoicesQuery(
        string $date,
        int $warehouseId,
        ?int $vehicleId,
        ?int $routeId,
        ?int $salesRepresentativeId,
    ): Builder {
        return DB::table('sales_invoices')
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', $date)
            ->where('warehouse_id', $warehouseId)
            ->when($vehicleId, fn (Builder $query) => $query->where('vehicle_id', $vehicleId))
            ->when($routeId, fn (Builder $query) => $query->where('route_id', $routeId))
            ->when($salesRepresentativeId, fn (Builder $query) => $query->where('sales_representative_id', $salesRepresentativeId));
    }

    private function scopedPaymentsQuery(
        string $date,
        int $warehouseId,
        ?int $vehicleId,
        ?int $routeId,
        ?int $salesRepresentativeId,
    ): Builder {
        return DB::table('customer_payments')
            ->where('status', 'confirmed')
            ->whereDate('payment_date', $date)
            ->where('warehouse_id', $warehouseId)
            ->when($vehicleId, fn (Builder $query) => $query->where('vehicle_id', $vehicleId))
            ->when($routeId, fn (Builder $query) => $query->where('route_id', $routeId))
            ->when($salesRepresentativeId, fn (Builder $query) => $query->where('sales_representative_id', $salesRepresentativeId));
    }
}
