<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\StockBalance;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VehicleStockPrintController extends Controller
{
    public function __invoke(Vehicle $vehicle): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->canManageInventory() === true,
            403,
        );

        $warehouse = Warehouse::query()
            ->where('type', 'vehicle')
            ->where('vehicle_id', $vehicle->id)
            ->first();

        $balances = StockBalance::query()
            ->with('product')
            ->when(
                $warehouse,
                fn ($query) => $query->where('warehouse_id', $warehouse->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('quantity', '!=', 0)
            ->orderBy('product_id')
            ->orderBy('expiry_date')
            ->get();

        $totals = [
            'rows_count' => $balances->count(),
            'products_count' => $balances->pluck('product_id')->unique()->count(),
            'quantity' => (float) $balances->sum('quantity'),
            'inventory_value' => (float) $balances->sum(
                fn (StockBalance $balance): float =>
                    (float) $balance->quantity
                    * (float) $balance->average_unit_cost,
            ),
        ];

        return view('reports.vehicle-stock.print', [
            'vehicle' => $vehicle,
            'warehouse' => $warehouse,
            'balances' => $balances,
            'totals' => $totals,
            'generatedBy' => Auth::user()?->name,
        ]);
    }
}
