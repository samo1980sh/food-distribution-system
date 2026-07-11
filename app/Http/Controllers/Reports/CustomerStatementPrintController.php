<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CustomerStatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerStatementPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->canManageSalesAndCollections() === true,
            403,
        );

        $validated = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],
            'from' => [
                'required',
                'date',
            ],
            'until' => [
                'required',
                'date',
                'after_or_equal:from',
            ],
        ]);

        $statement = app(CustomerStatementService::class)
            ->generate(
                customerId: (int) $validated['customer_id'],
                from: (string) $validated['from'],
                until: (string) $validated['until'],
            );

        return view('reports.customer-statements.print', [
            'customer' => $statement['customer'],
            'transactions' => $statement['transactions'],
            'totals' => $statement['totals'],
            'from' => (string) $validated['from'],
            'until' => (string) $validated['until'],
            'generatedBy' => Auth::user()?->name,
        ]);
    }
}