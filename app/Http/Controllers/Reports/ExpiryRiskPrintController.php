<?php

namespace App\Http\Controllers\Reports;

use App\Filament\Resources\ExpiryRiskReports\Tables\ExpiryRiskReportsTable;
use App\Http\Controllers\Controller;
use App\Models\StockBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ExpiryRiskPrintController extends Controller
{
    public function __invoke(StockBalance $stockBalance): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->canManageInventory() === true,
            403,
        );

        abort_unless(
            (float) $stockBalance->quantity > 0
            && $stockBalance->product?->has_expiry === true,
            404,
        );

        $stockBalance->load([
            'warehouse.vehicle',
            'product.category',
            'product.unit',
        ]);

        return view('reports.expiry-risk.print', [
            'balance' => $stockBalance,
            'status' => ExpiryRiskReportsTable::expiryStatus(
                $stockBalance->expiry_date,
            ),
            'daysRemaining' => ExpiryRiskReportsTable::daysRemaining(
                $stockBalance->expiry_date,
            ),
            'inventoryValue' => ExpiryRiskReportsTable::inventoryValue(
                $stockBalance,
            ),
            'generatedBy' => Auth::user()?->name,
        ]);
    }
}
