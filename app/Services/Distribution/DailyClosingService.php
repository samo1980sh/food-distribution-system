<?php

namespace App\Services\Distribution;

use App\Models\DailyClosing;
use App\Services\Support\DocumentNumberService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DailyClosingService
{
    public function refreshTotals(DailyClosing $closing): DailyClosing
    {
        return DB::transaction(function () use ($closing): DailyClosing {
            $closing = DailyClosing::query()
                ->lockForUpdate()
                ->findOrFail($closing->getKey());

            if (! $closing->isDraft()) {
                throw new RuntimeException('لا يمكن تحديث إجماليات إغلاق يوم ليس بحالة مسودة.');
            }

            $closing->loadMissing('warehouse');
            $this->validateClosingScope($closing);

            $date = $closing->closing_date->toDateString();
            $warehouseId = (int) $closing->warehouse_id;

            $existingActuals = $closing->items()
                ->get()
                ->keyBy('product_id')
                ->map(fn ($item) => [
                    'actual_quantity' => $item->actual_quantity,
                    'notes' => $item->notes,
                ])
                ->all();

            $loaded = DB::table('vehicle_load_items')
                ->join('vehicle_loads', 'vehicle_load_items.vehicle_load_id', '=', 'vehicle_loads.id')
                ->where('vehicle_loads.status', 'approved')
                ->whereDate('vehicle_loads.load_date', $date)
                ->where('vehicle_loads.to_warehouse_id', $warehouseId)
                ->selectRaw('vehicle_load_items.product_id, SUM(vehicle_load_items.quantity) as quantity')
                ->groupBy('vehicle_load_items.product_id')
                ->pluck('quantity', 'product_id');

            $sold = DB::table('sales_invoice_items')
                ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
                ->where('sales_invoices.status', 'confirmed')
                ->whereDate('sales_invoices.invoice_date', $date)
                ->where('sales_invoices.warehouse_id', $warehouseId)
                ->selectRaw('sales_invoice_items.product_id, SUM(sales_invoice_items.quantity) as quantity')
                ->groupBy('sales_invoice_items.product_id')
                ->pluck('quantity', 'product_id');

            $returned = DB::table('sales_return_items')
                ->join('sales_returns', 'sales_return_items.sales_return_id', '=', 'sales_returns.id')
                ->where('sales_returns.status', 'confirmed')
                ->whereDate('sales_returns.return_date', $date)
                ->where('sales_returns.warehouse_id', $warehouseId)
                ->selectRaw('sales_return_items.product_id, SUM(sales_return_items.quantity) as quantity')
                ->groupBy('sales_return_items.product_id')
                ->pluck('quantity', 'product_id');

            $ledger = $this->ledgerSnapshot($date, $warehouseId);

            $productIds = collect($loaded->keys())
                ->merge($sold->keys())
                ->merge($returned->keys())
                ->merge($ledger['product_ids'])
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            $closing->items()->delete();

            foreach ($productIds as $productId) {
                $loadedQuantity = (float) ($loaded[$productId] ?? 0);
                $soldQuantity = (float) ($sold[$productId] ?? 0);
                $returnedQuantity = (float) ($returned[$productId] ?? 0);
                $openingQuantity = $this->quantity($ledger['opening'][$productId] ?? 0);
                $movementInQuantity = $this->quantity($ledger['day_in'][$productId] ?? 0);
                $movementOutQuantity = $this->quantity($ledger['day_out'][$productId] ?? 0);
                $expectedQuantity = $this->quantity(
                    $openingQuantity + $movementInQuantity - $movementOutQuantity,
                );
                $actual = $existingActuals[$productId]['actual_quantity'] ?? null;
                $notes = $existingActuals[$productId]['notes'] ?? null;

                $closing->items()->create([
                    'product_id' => $productId,
                    'opening_quantity' => $openingQuantity,
                    'movement_in_quantity' => $movementInQuantity,
                    'movement_out_quantity' => $movementOutQuantity,
                    'loaded_quantity' => $loadedQuantity,
                    'sold_quantity' => $soldQuantity,
                    'returned_quantity' => $returnedQuantity,
                    'expected_quantity' => $expectedQuantity,
                    'actual_quantity' => $actual,
                    'difference_quantity' => $actual === null
                        ? 0
                        : $this->quantity((float) $actual - $expectedQuantity),
                    'notes' => $notes,
                ]);
            }

            $totalSales = (float) $this->scopedInvoicesQuery($date, $warehouseId)
                ->sum('total_amount');

            $invoiceCash = (float) $this->scopedInvoicesQuery($date, $warehouseId)
                ->sum('invoice_cash_amount');

            $totalReturns = (float) DB::table('sales_returns')
                ->where('status', 'confirmed')
                ->whereDate('return_date', $date)
                ->where('warehouse_id', $warehouseId)
                ->sum('total_amount');

            $collectionsByMethod = $this->scopedPaymentsQuery($date, $warehouseId)
                ->selectRaw('payment_method, SUM(amount) as amount')
                ->groupBy('payment_method')
                ->pluck('amount', 'payment_method')
                ->map(fn ($amount): float => (float) $amount);

            $cashCollections = (float) ($collectionsByMethod['cash'] ?? 0);
            $bankTransferCollections = (float) ($collectionsByMethod['bank_transfer'] ?? 0);
            $chequeCollections = (float) ($collectionsByMethod['cheque'] ?? 0);
            $otherCollections = (float) ($collectionsByMethod['other'] ?? 0);
            $totalCollections = $cashCollections
                + $bankTransferCollections
                + $chequeCollections
                + $otherCollections;
            $nonCashCollections = $bankTransferCollections
                + $chequeCollections
                + $otherCollections;

            $vehicleExpensesByMethod = $this->scopedVehicleExpensesQuery($date, $warehouseId)
                ->selectRaw('payment_method, SUM(amount) as amount')
                ->groupBy('payment_method')
                ->pluck('amount', 'payment_method')
                ->map(fn ($amount): float => (float) $amount);

            $cashVehicleExpenses = (float) ($vehicleExpensesByMethod['cash'] ?? 0);
            $bankTransferVehicleExpenses = (float) ($vehicleExpensesByMethod['bank_transfer'] ?? 0);
            $chequeVehicleExpenses = (float) ($vehicleExpensesByMethod['cheque'] ?? 0);
            $otherVehicleExpenses = (float) ($vehicleExpensesByMethod['other'] ?? 0);
            $totalVehicleExpenses = $cashVehicleExpenses
                + $bankTransferVehicleExpenses
                + $chequeVehicleExpenses
                + $otherVehicleExpenses;
            $nonCashVehicleExpenses = $bankTransferVehicleExpenses
                + $chequeVehicleExpenses
                + $otherVehicleExpenses;

            $expectedCash = $invoiceCash + $cashCollections - $cashVehicleExpenses;
            $actualCash = (float) $closing->actual_cash_amount;

            $closing->forceFill([
                'total_opening_quantity' => $ledger['opening']->sum(),
                'total_movement_in_quantity' => $ledger['day_in']->sum(),
                'total_movement_out_quantity' => $ledger['day_out']->sum(),
                'total_expected_quantity' => $closing->items()->sum('expected_quantity'),
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
                'total_vehicle_expenses_amount' => $totalVehicleExpenses,
                'cash_vehicle_expenses_amount' => $cashVehicleExpenses,
                'non_cash_vehicle_expenses_amount' => $nonCashVehicleExpenses,
                'expected_cash_amount' => $expectedCash,
                'cash_difference' => $actualCash - $expectedCash,
                'snapshot_at' => null,
            ])->save();

            return $closing->refresh();
        });
    }

    public function confirm(DailyClosing $closing): DailyClosing
    {
        return DB::transaction(function () use ($closing): DailyClosing {
            $closing = DailyClosing::query()
                ->lockForUpdate()
                ->findOrFail($closing->getKey());

            if (! $closing->isDraft()) {
                throw new RuntimeException('لا يمكن اعتماد إغلاق يوم ليس بحالة مسودة.');
            }

            if ($closing->isFieldWorkflow() && ! $closing->fieldHandoverComplete()) {
                throw new RuntimeException('لا يمكن اعتماد الإغلاق الميداني قبل تسليم جرد السيارة والنقد من المسؤولين عنهما.');
            }

            $closing = $this->refreshTotals($closing);

            $missingActuals = $closing->items()
                ->whereNull('actual_quantity')
                ->exists();

            if ($missingActuals) {
                throw new RuntimeException('يجب إدخال الجرد الفعلي لجميع مواد الإغلاق قبل الاعتماد.');
            }

            if ($closing->isFieldWorkflow()) {
                $unexplainedInventoryDifference = $closing->items()
                    ->whereRaw('ABS(difference_quantity) >= 0.0005')
                    ->where(function ($query): void {
                        $query->whereNull('notes')->orWhere('notes', '');
                    })
                    ->exists();

                if ($unexplainedInventoryDifference) {
                    throw new RuntimeException('يوجد فرق جرد غير مفسر. يجب أن يعيد السائق تسليم الجرد مع توضيح الفروقات.');
                }

                if (abs((float) $closing->cash_difference) >= 0.005 && blank($closing->cash_notes)) {
                    throw new RuntimeException('يوجد فرق صندوق غير مفسر. يجب أن يعيد مندوب المبيعات تسليم النقد مع توضيح الفرق.');
                }
            }

            $closing->forceFill([
                'status' => 'confirmed',
                'snapshot_at' => now(),
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ])->save();

            return $closing->refresh();
        });
    }

    public function cancel(DailyClosing $closing): DailyClosing
    {
        return DB::transaction(function () use ($closing): DailyClosing {
            $closing = DailyClosing::query()
                ->lockForUpdate()
                ->findOrFail($closing->getKey());

            if (! $closing->isConfirmed()) {
                throw new RuntimeException('لا يمكن إلغاء إغلاق يوم غير معتمد.');
            }

            $closing->forceFill([
                'status' => 'cancelled',
            ])->save();

            return $closing->refresh();
        });
    }

    public function generateClosingNumber(): string
    {
        return app(DocumentNumberService::class)->next('daily_closing', 'DCL');
    }

    /**
     * @return array{
     *   product_ids: Collection<int, int>,
     *   opening: Collection<int, float>,
     *   day_in: Collection<int, float>,
     *   day_out: Collection<int, float>
     * }
     */
    private function ledgerSnapshot(string $date, int $warehouseId): array
    {
        $current = DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id')
            ->map(fn ($quantity): float => (float) $quantity);

        $inFromDate = $this->movementQuantityQuery(
            date: $date,
            warehouseColumn: 'to_warehouse_id',
            warehouseId: $warehouseId,
            comparator: '>=',
        );

        $outFromDate = $this->movementQuantityQuery(
            date: $date,
            warehouseColumn: 'from_warehouse_id',
            warehouseId: $warehouseId,
            comparator: '>=',
        );

        $dayIn = $this->movementQuantityQuery(
            date: $date,
            warehouseColumn: 'to_warehouse_id',
            warehouseId: $warehouseId,
            comparator: '=',
        );

        $dayOut = $this->movementQuantityQuery(
            date: $date,
            warehouseColumn: 'from_warehouse_id',
            warehouseId: $warehouseId,
            comparator: '=',
        );

        $productIds = collect($current->keys())
            ->merge($inFromDate->keys())
            ->merge($outFromDate->keys())
            ->merge($dayIn->keys())
            ->merge($dayOut->keys())
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $opening = $productIds->mapWithKeys(function (int $productId) use (
            $current,
            $inFromDate,
            $outFromDate,
        ): array {
            $quantity = (float) ($current[$productId] ?? 0)
                - (float) ($inFromDate[$productId] ?? 0)
                + (float) ($outFromDate[$productId] ?? 0);

            return [$productId => $this->quantity($quantity)];
        });

        return [
            'product_ids' => $productIds,
            'opening' => $opening,
            'day_in' => $dayIn,
            'day_out' => $dayOut,
        ];
    }

    private function movementQuantityQuery(
        string $date,
        string $warehouseColumn,
        int $warehouseId,
        string $comparator,
    ): Collection {
        $query = DB::table('stock_movements')
            ->where($warehouseColumn, $warehouseId);

        if ($comparator === '=') {
            $query->whereDate('movement_date', $date);
        } else {
            $query->whereDate('movement_date', $comparator, $date);
        }

        return $query
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id')
            ->map(fn ($quantity): float => $this->quantity((float) $quantity));
    }

    private function quantity(float $quantity): float
    {
        $rounded = round($quantity, 3);

        return abs($rounded) < 0.0005 ? 0.0 : $rounded;
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

    private function scopedVehicleExpensesQuery(
        string $date,
        int $warehouseId,
    ): Builder {
        return DB::table('vehicle_expenses')
            ->where('status', 'approved')
            ->whereDate('expense_date', $date)
            ->where('warehouse_id', $warehouseId);
    }

    private function scopedInvoicesQuery(
        string $date,
        int $warehouseId,
    ): Builder {
        return DB::table('sales_invoices')
            ->where('status', 'confirmed')
            ->whereDate('invoice_date', $date)
            ->where('warehouse_id', $warehouseId);
    }

    private function scopedPaymentsQuery(
        string $date,
        int $warehouseId,
    ): Builder {
        return DB::table('customer_payments')
            ->where('status', 'confirmed')
            ->whereDate('payment_date', $date)
            ->where('warehouse_id', $warehouseId);
    }
}
