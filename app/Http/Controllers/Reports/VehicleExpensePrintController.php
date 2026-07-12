<?php

namespace App\Http\Controllers\Reports;

use App\Models\VehicleExpense;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class VehicleExpensePrintController
{
    public function __invoke(VehicleExpense $vehicleExpense): View
    {
        abort_unless(
            Auth::user()?->canManageDistribution() === true,
            403,
        );

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
