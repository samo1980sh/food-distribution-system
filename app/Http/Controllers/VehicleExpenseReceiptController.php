<?php

namespace App\Http\Controllers;

use App\Models\VehicleExpense;
use App\Services\Support\VehicleExpenseReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleExpenseReceiptController extends Controller
{
    public function __invoke(
        VehicleExpense $vehicleExpense,
        VehicleExpenseReceiptService $receipts,
    ): StreamedResponse|RedirectResponse {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        Gate::authorize('view', $vehicleExpense);

        return $receipts->response($vehicleExpense);
    }
}
