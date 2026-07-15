<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VehicleLoad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VehicleLoadPrintController extends Controller
{
    public function __invoke(
        VehicleLoad $vehicleLoad,
    ): View|RedirectResponse {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        Gate::authorize('print', $vehicleLoad);

        $vehicleLoad->load([
            'vehicle',
            'route',
            'driver',
            'salesRepresentative',
            'fromWarehouse',
            'toWarehouse',
            'items.product',
        ]);

        return view('reports.vehicle-loads.print', [
            'vehicleLoad' => $vehicleLoad,
            'createdBy' => $vehicleLoad->created_by
                ? User::query()->find($vehicleLoad->created_by)?->name
                : null,
            'approvedBy' => $vehicleLoad->approved_by
                ? User::query()->find($vehicleLoad->approved_by)?->name
                : null,
        ]);
    }
}
