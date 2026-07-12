<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Reports\OverdueCustomerReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OverdueCustomerPrintController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
    ): View|RedirectResponse {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->canManageSalesAndCollections() === true,
            403,
        );

        $validated = $request->validate([
            'credit_days' => [
                'nullable',
                'integer',
                'min:1',
                'max:365',
            ],
            'as_of' => [
                'nullable',
                'date',
            ],
        ]);

        $creditDays = (int) (
            $validated['credit_days']
            ?? OverdueCustomerReportService::DEFAULT_CREDIT_DAYS
        );

        $asOf = (string) (
            $validated['as_of']
            ?? today()->toDateString()
        );

        $detail = app(OverdueCustomerReportService::class)
            ->detailForCustomer(
                customerId: $customer->id,
                creditDays: $creditDays,
                asOf: $asOf,
            );

        abort_unless(
            (float) $detail['current_balance'] > 0,
            404,
        );

        return view('reports.overdue-customers.print', [
            'report' => $detail,
            'generatedBy' => Auth::user()?->name,
        ]);
    }
}
