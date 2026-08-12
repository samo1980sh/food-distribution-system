<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Controller;
use App\Models\VehicleExpense;
use App\Services\Support\VehicleExpenseReceiptService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleExpenseReceiptController extends Controller
{
    public function __invoke(
        VehicleExpense $vehicleExpense,
        VehicleExpenseReceiptService $receipts,
    ): StreamedResponse {
        Gate::authorize('view', $vehicleExpense);

        return $receipts->response($vehicleExpense);
    }
}
