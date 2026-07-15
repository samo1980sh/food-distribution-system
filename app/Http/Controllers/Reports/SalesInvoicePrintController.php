<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SalesInvoicePrintController extends Controller
{
    public function __invoke(SalesInvoice $salesInvoice): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        Gate::authorize('print', $salesInvoice);

        $salesInvoice->load([
            'customer',
            'warehouse',
            'vehicle',
            'route',
            'salesRepresentative',
            'items.product',
            'creator',
            'confirmer',
        ]);

        return view('reports.sales-invoices.print', [
            'salesInvoice' => $salesInvoice,
        ]);
    }
}