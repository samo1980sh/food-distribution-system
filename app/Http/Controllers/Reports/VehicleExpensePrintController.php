<?php

namespace App\Http\Controllers\Reports;

use App\Models\VehicleExpense;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class VehicleExpensePrintController
{
    public function __invoke(
        VehicleExpense $vehicleExpense,
    ): View|RedirectResponse {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        Gate::authorize('print', $vehicleExpense);

        abort_unless($vehicleExpense->isApproved(), 404);

        $vehicleExpense->load([
            'vehicle',
            'warehouse',
            'route',
            'driver',
            'salesRepresentative',
            'createdBy',
            'approvedBy',
        ]);

        return view('reports.vehicle-expenses.print', [
            'expense' => $vehicleExpense,
            'generatedBy' => Auth::user()?->name,
        ]);
    }
}
