<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SalesReturnPrintController extends Controller
{
    public function __invoke(
        SalesReturn $salesReturn,
    ): View|RedirectResponse {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->canManageSalesAndCollections() === true,
            403,
        );

        $salesReturn->load([
            'customer',
            'salesInvoice',
            'warehouse',
            'vehicle',
            'route',
            'salesRepresentative',
            'items.product',
        ]);

        return view('reports.sales-returns.print', [
            'salesReturn' => $salesReturn,
            'createdBy' => $salesReturn->created_by
                ? User::query()->find($salesReturn->created_by)?->name
                : null,
            'confirmedBy' => $salesReturn->confirmed_by
                ? User::query()->find($salesReturn->confirmed_by)?->name
                : null,
        ]);
    }
}