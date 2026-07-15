<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Reports\TopCustomerReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TopCustomerPrintController extends Controller
{
    public function __invoke(
        Request $request,
        Customer $customer,
    ): View|RedirectResponse {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->can(PermissionName::REPORT_TOP_CUSTOMERS->value) === true,
            403,
        );

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
        ]);

        $detail = app(TopCustomerReportService::class)
            ->detailForCustomer(
                customerId: $customer->id,
                settings: [
                    'from' => $validated['from'] ?? null,
                    'until' => $validated['until'] ?? null,
                    'limit' => 'all',
                    'customer_id' => $customer->id,
                ],
            );

        abort_unless(
            (float) $detail['summary']['net_sales'] > 0,
            404,
        );

        return view('reports.top-customers.print', [
            'report' => $detail,
            'generatedBy' => Auth::user()?->name,
        ]);
    }
}
