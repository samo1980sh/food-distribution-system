<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\VehicleLoad;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InitializeInventoryCosts extends Command
{
    protected $signature = 'inventory:initialize-costs {--apply : Persist the calculated opening costs}';

    protected $description = 'Initialize weighted-average inventory costs for existing balances and document items.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $run = fn (): array => $this->initializeCosts($apply);
        $summary = $apply ? DB::transaction($run) : $run();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Balances needing initialization', $summary['balances_total']],
                ['Balances costed from stock movements', $summary['balances_from_movements']],
                ['Balances costed from product purchase price', $summary['balances_from_products']],
                ['Balances still at zero cost', $summary['balances_without_cost']],
                ['Sales invoice items initialized', $summary['invoice_items']],
                ['Sales return items initialized', $summary['return_items']],
                ['Vehicle load items initialized', $summary['vehicle_load_items']],
            ],
        );

        $this->line($apply
            ? 'Inventory opening costs initialized.'
            : 'Dry run only. Review product purchase prices, then re-run with --apply.'
        );

        if ($summary['balances_without_cost'] > 0) {
            $this->warn('Some balances remain at zero cost because no positive movement or product purchase price was available.');
        }

        return self::SUCCESS;
    }

    private function initializeCosts(bool $apply): array
    {
        $summary = [
            'balances_total' => 0,
            'balances_from_movements' => 0,
            'balances_from_products' => 0,
            'balances_without_cost' => 0,
            'invoice_items' => 0,
            'return_items' => 0,
            'vehicle_load_items' => 0,
        ];

        StockBalance::query()
            ->with('product:id,purchase_price')
            ->where('quantity', '!=', 0)
            ->where('average_unit_cost', '<=', 0)
            ->orderBy('id')
            ->each(function (StockBalance $balance) use ($apply, &$summary): void {
                $summary['balances_total']++;

                $movementCost = $this->latestIncomingMovementCost($balance);
                $unitCost = $movementCost
                    ?? (float) $balance->product?->purchase_price;

                if ($movementCost !== null) {
                    $summary['balances_from_movements']++;
                } elseif ($unitCost > 0) {
                    $summary['balances_from_products']++;
                } else {
                    $summary['balances_without_cost']++;
                }

                if ($apply && $unitCost > 0) {
                    $balance->forceFill([
                        'average_unit_cost' => round($unitCost, 6),
                    ])->save();
                }
            });

        $productCosts = Product::query()
            ->pluck('purchase_price', 'id')
            ->map(fn ($cost): float => (float) $cost);

        $invoiceIds = [];
        $returnIds = [];
        $vehicleLoadIds = [];

        DB::table('sales_invoice_items')
            ->where('unit_cost', '<=', 0)
            ->orderBy('id')
            ->each(function ($item) use ($apply, $productCosts, &$summary, &$invoiceIds): void {
                $unitCost = $this->referenceMovementCost(
                    SalesInvoice::class,
                    (int) $item->sales_invoice_id,
                    $item,
                    'sales_invoice',
                ) ?? (float) ($productCosts[$item->product_id] ?? 0);

                if ($unitCost <= 0) {
                    return;
                }

                $summary['invoice_items']++;

                if ($apply) {
                    $invoiceIds[] = (int) $item->sales_invoice_id;

                    DB::table('sales_invoice_items')
                        ->where('id', $item->id)
                        ->update([
                            'unit_cost' => round($unitCost, 6),
                            'total_cost' => round((float) $item->quantity * $unitCost, 2),
                            'updated_at' => now(),
                        ]);
                }
            });

        DB::table('sales_return_items')
            ->join('sales_returns', 'sales_return_items.sales_return_id', '=', 'sales_returns.id')
            ->where('sales_return_items.unit_cost', '<=', 0)
            ->orderBy('sales_return_items.id')
            ->select([
                'sales_return_items.*',
                'sales_returns.sales_invoice_id',
            ])
            ->each(function ($item) use ($apply, $productCosts, &$summary, &$returnIds): void {
                $unitCost = $item->sales_invoice_id
                    ? $this->matchingInvoiceItemCost(
                        (int) $item->sales_invoice_id,
                        $item,
                    )
                    : null;

                $unitCost ??= $this->referenceMovementCost(
                    SalesReturn::class,
                    (int) $item->sales_return_id,
                    $item,
                    'sales_return',
                );

                $unitCost ??= (float) ($productCosts[$item->product_id] ?? 0);

                if ($unitCost <= 0) {
                    return;
                }

                $summary['return_items']++;

                if ($apply) {
                    $returnIds[] = (int) $item->sales_return_id;

                    DB::table('sales_return_items')
                        ->where('id', $item->id)
                        ->update([
                            'unit_cost' => round($unitCost, 6),
                            'total_cost' => round((float) $item->quantity * $unitCost, 2),
                            'updated_at' => now(),
                        ]);
                }
            });

        DB::table('vehicle_load_items')
            ->where('unit_cost', '<=', 0)
            ->orderBy('id')
            ->each(function ($item) use ($apply, $productCosts, &$summary, &$vehicleLoadIds): void {
                $unitCost = $this->referenceMovementCost(
                    VehicleLoad::class,
                    (int) $item->vehicle_load_id,
                    $item,
                    'vehicle_load_transfer',
                ) ?? (float) ($productCosts[$item->product_id] ?? 0);

                if ($unitCost <= 0) {
                    return;
                }

                $summary['vehicle_load_items']++;
                $vehicleLoadIds[] = (int) $item->vehicle_load_id;

                if ($apply) {
                    DB::table('vehicle_load_items')
                        ->where('id', $item->id)
                        ->update([
                            'unit_cost' => round($unitCost, 6),
                            'total_cost' => round((float) $item->quantity * $unitCost, 2),
                            'updated_at' => now(),
                        ]);
                }
            });

        if ($apply) {
            SalesInvoice::withoutGlobalScopes()
                ->whereIn('id', array_unique($invoiceIds))
                ->get()
                ->each
                ->touch();

            SalesReturn::withoutGlobalScopes()
                ->whereIn('id', array_unique($returnIds))
                ->get()
                ->each
                ->touch();

            VehicleLoad::withoutGlobalScopes()
                ->whereIn('id', array_unique($vehicleLoadIds))
                ->get()
                ->each(function (VehicleLoad $vehicleLoad): void {
                    $vehicleLoad->forceFill([
                        'total_cost' => DB::table('vehicle_load_items')
                            ->where('vehicle_load_id', $vehicleLoad->id)
                            ->sum('total_cost'),
                    ])->save();
                });
        }

        return $summary;
    }

    private function latestIncomingMovementCost(
        StockBalance $balance,
    ): ?float {
        $cost = $this->matchingItemQuery(
            StockMovement::query()
                ->where('to_warehouse_id', $balance->warehouse_id)
                ->where('product_id', $balance->product_id)
                ->where('unit_cost', '>', 0),
            $balance,
        )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('unit_cost');

        return $cost !== null ? (float) $cost : null;
    }

    private function referenceMovementCost(
        string $referenceType,
        int $referenceId,
        object $item,
        string $movementType,
    ): ?float {
        $cost = $this->matchingItemQuery(
            StockMovement::query()
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('movement_type', $movementType)
                ->where('product_id', $item->product_id)
                ->where('unit_cost', '>', 0),
            $item,
        )
            ->orderByDesc('id')
            ->value('unit_cost');

        return $cost !== null ? (float) $cost : null;
    }

    private function matchingInvoiceItemCost(
        int $invoiceId,
        object $returnItem,
    ): ?float {
        $items = $this->matchingItemQuery(
            DB::table('sales_invoice_items')
                ->where('sales_invoice_id', $invoiceId)
                ->where('product_id', $returnItem->product_id)
                ->where('unit_cost', '>', 0),
            $returnItem,
        )->get(['quantity', 'unit_cost', 'total_cost']);

        $quantity = (float) $items->sum('quantity');

        if ($quantity <= 0) {
            return null;
        }

        $totalCost = (float) $items->sum(
            fn ($item): float => (float) $item->total_cost > 0
                ? (float) $item->total_cost
                : (float) $item->quantity * (float) $item->unit_cost,
        );

        return $totalCost > 0
            ? round($totalCost / $quantity, 6)
            : null;
    }

    private function matchingItemQuery(
        Builder|\Illuminate\Database\Eloquent\Builder $query,
        object $item,
    ): Builder|\Illuminate\Database\Eloquent\Builder {
        $query->where('batch_number', $item->batch_number);

        if ($item->expiry_date) {
            $query->whereDate('expiry_date', $item->expiry_date);
        } else {
            $query->whereNull('expiry_date');
        }

        return $query;
    }
}
