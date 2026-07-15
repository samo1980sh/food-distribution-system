<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\DistributionRoute;
use App\Services\Reports\RoutePerformanceReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RoutePerformancePrintController extends Controller
{
    public function __invoke(
        Request $request,
        DistributionRoute $distributionRoute,
    ): View|RedirectResponse {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->can(PermissionName::REPORT_ROUTE_PERFORMANCE->value) === true,
            403,
        );

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
        ]);

        $report = app(RoutePerformanceReportService::class)
            ->detailForRoute(
                routeId: $distributionRoute->id,
                settings: [
                    'from' => $validated['from'] ?? null,
                    'until' => $validated['until'] ?? null,
                    'status' => 'all',
                    'limit' => 'all',
                ],
            );

        return view('reports.route-performance.print', [
            'report' => $report,
            'generatedBy' => Auth::user()?->name,
        ]);
    }
}
