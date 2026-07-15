<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Services\Reports\TopCustomerReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TopCustomerReportFilteredPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->can(PermissionName::REPORT_TOP_CUSTOMERS->value) === true,
            403,
        );

        $state = $this->decodeState(
            (string) $request->query('state', ''),
        );

        $filters = is_array($state['filters'] ?? null)
            ? $state['filters']
            : [];

        $filterState = is_array(
            $filters['ranking_settings'] ?? null
        )
            ? $filters['ranking_settings']
            : [];

        $service = app(TopCustomerReportService::class);
        $settings = $service->normalizeSettings($filterState);
        $rankings = $service->rankings($settings);
        $totals = $service->totals($rankings);

        $filterSummary = array_filter([
            'الفترة' => $settings['from'].' — '.$settings['until'],
            'معيار الترتيب' =>
                TopCustomerReportService::rankingMetricLabel(
                    $settings['ranking_metric'],
                ),
            'عدد النتائج' =>
                TopCustomerReportService::limitOptions()[
                    $settings['limit']
                ] ?? $settings['limit'],
            'العميل' => $settings['customer_id']
                ? Customer::query()->find($settings['customer_id'])?->name
                : null,
            'المنطقة' => $settings['area_id']
                ? Area::query()->find($settings['area_id'])?->name_ar
                : null,
            'خط التوزيع' => $settings['route_id']
                ? DistributionRoute::query()
                    ->find($settings['route_id'])?->name
                : null,
            'نوع العميل' => $settings['customer_type']
                ? TopCustomerReportService::customerTypeLabel(
                    $settings['customer_type'],
                )
                : null,
            'نمط دفع العميل' => $settings['payment_type']
                ? TopCustomerReportService::paymentTypeLabel(
                    $settings['payment_type'],
                )
                : null,
            'حالة العميل' => $settings['status']
                ? (
                    TopCustomerReportService::statusOptions()[
                        $settings['status']
                    ] ?? $settings['status']
                )
                : null,
            'الحد الأدنى للصافي' =>
                $settings['minimum_net_sales'] > 0
                    ? number_format(
                        $settings['minimum_net_sales'],
                        2,
                    ).' ل.س'
                    : null,
            'البحث' => $settings['search'] !== ''
                ? $settings['search']
                : null,
        ], fn (mixed $value): bool => filled($value));

        return view('reports.top-customers.filtered-print', [
            'rankings' => $rankings,
            'totals' => $totals,
            'settings' => $settings,
            'filterSummary' => $filterSummary,
            'generatedBy' => Auth::user()?->name,
        ]);
    }

    private function decodeState(string $encoded): array
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

        $state = json_decode($json, true);

        return is_array($state) ? $state : [];
    }
}
