<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Services\Reports\RoutePerformanceReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RoutePerformanceReportFilteredPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->can(PermissionName::REPORT_ROUTE_PERFORMANCE->value) === true,
            403,
        );

        $state = $this->decode((string) $request->query('state', ''));
        $filters = is_array($state['filters'] ?? null)
            ? $state['filters']
            : [];
        $data = is_array($filters['performance_settings'] ?? null)
            ? $filters['performance_settings']
            : [];

        $service = app(RoutePerformanceReportService::class);
        $settings = $service->normalizeSettings($data);
        $rankings = $service->rankings($settings);

        $filterSummary = array_filter([
            'الفترة' => $settings['from'].' — '.$settings['until'],
            'معيار الترتيب' =>
                RoutePerformanceReportService::rankingMetricLabel(
                    $settings['ranking_metric']
                ),
            'نطاق النشاط' =>
                RoutePerformanceReportService::scopeOptions()[
                    $settings['scope']
                ] ?? $settings['scope'],
            'عدد النتائج' =>
                RoutePerformanceReportService::limitOptions()[
                    $settings['limit']
                ] ?? $settings['limit'],
            'حالة الخط' =>
                RoutePerformanceReportService::statusLabel(
                    $settings['status']
                ),
            'الخط' => $settings['route_id']
                ? DistributionRoute::find($settings['route_id'])?->name
                : null,
            'المنطقة' => $settings['area_id']
                ? Area::find($settings['area_id'])?->name_ar
                : null,
            'السيارة' => $settings['vehicle_id']
                ? Vehicle::find($settings['vehicle_id'])?->plate_number
                : null,
            'السائق' => $settings['driver_id']
                ? Employee::find($settings['driver_id'])?->name
                : null,
            'المندوب' => $settings['sales_representative_id']
                ? Employee::find(
                    $settings['sales_representative_id']
                )?->name
                : null,
            'الحد الأدنى لصافي المبيعات' =>
                $settings['minimum_net_sales'] > 0
                    ? number_format(
                        $settings['minimum_net_sales'],
                        2,
                    ).' ل.س'
                    : null,
            'الحد الأدنى لصافي المساهمة' =>
                $settings['minimum_contribution'] !== null
                    ? number_format(
                        $settings['minimum_contribution'],
                        2,
                    ).' ل.س'
                    : null,
            'البحث' => $settings['search'] ?: null,
        ], fn (mixed $value): bool => filled($value));

        return view('reports.route-performance.filtered-print', [
            'rankings' => $rankings,
            'totals' => $service->totals($rankings),
            'unassigned' => $service->unassignedSummary($settings),
            'settings' => $settings,
            'filterSummary' => $filterSummary,
            'generatedBy' => Auth::user()?->name,
        ]);
    }

    private function decode(string $encoded): array
    {
        if ($encoded === '') {
            return [];
        }

        $base64 = strtr($encoded, '-_', '+/');
        $remainder = strlen($base64) % 4;

        if ($remainder > 0) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }

        $json = base64_decode($base64, true);

        if ($json === false) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
