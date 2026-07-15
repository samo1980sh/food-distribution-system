<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\DailyClosing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DailyClosingPrintController extends Controller
{
    public function __invoke(DailyClosing $dailyClosing): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        Gate::authorize('print', $dailyClosing);

        $dailyClosing->load([
            'warehouse',
            'vehicle',
            'route',
            'salesRepresentative',
            'items.product',
        ]);

        return view('reports.daily-closings.print', [
            'dailyClosing' => $dailyClosing,
        ]);
    }
}