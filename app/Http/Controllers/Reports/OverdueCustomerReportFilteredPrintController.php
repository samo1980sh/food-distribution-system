<?php

namespace App\Http\Controllers\Reports;

use App\Enums\PermissionName;
use App\Filament\Resources\OverdueCustomerReports\Tables\OverdueCustomerReportsTable;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\DistributionRoute;
use App\Services\Reports\OverdueCustomerReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OverdueCustomerReportFilteredPrintController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        abort_unless(
            Auth::user()?->can(PermissionName::REPORT_OVERDUE_CUSTOMERS->value) === true,
            403,
        );

        $state = $this->decodeState(
            (string) $request->query('state', ''),
        );

        $filters = is_array($state['filters'] ?? null)
            ? $state['filters']
            : [];

        $search = trim((string) ($state['search'] ?? ''));

        $settingsState = is_array(
            $filters['overdue_settings'] ?? null
        )
            ? $filters['overdue_settings']
            : [];

        $settings = OverdueCustomerReportsTable::settingsFromFilterData(
            $settingsState,
        );

        $areaId = $this->normalizeId(
            $this->filterValue($filters, 'area_id'),
        );

        $routeId = $this->normalizeId(
            $this->filterValue($filters, 'route_id'),
        );

        $paymentType = $this->filterValue(
            $filters,
            'payment_type',
        );

        $customerType = $this->filterValue(
            $filters,
            'customer_type',
        );

        $status = $this->filterValue($filters, 'status');

        $criteria = [
            'scope' => $settings['scope'],
            'risk' => $settings['risk'],
            'minimum_overdue' => $settings['minimum_overdue'],
            'area_id' => $areaId,
            'route_id' => $routeId,
            'payment_type' => $paymentType,
            'customer_type' => $customerType,
            'status' => $status,
            'search' => $search,
        ];

        $service = app(OverdueCustomerReportService::class);

        $customers = $service->filteredSummaries(
            creditDays: $settings['credit_days'],
            asOf: $settings['as_of'],
            criteria: $criteria,
        );

        $totals = $service->totals($customers);

        $riskOptions = OverdueCustomerReportService::riskOptions();
        $paymentTypeOptions = OverdueCustomerReportService::paymentTypeOptions();

        $filterSummary = array_filter([
            'نطاق التقرير' => $settings['scope'] === 'all_positive'
                ? 'جميع العملاء ذوي الرصيد الموجب'
                : 'العملاء المتأخرون فقط',
            'مدة السماح' => $settings['credit_days'].' يومًا',
            'كما في تاريخ' => $settings['as_of'],
            'حالة المخاطر' => $settings['risk']
                ? ($riskOptions[$settings['risk']] ?? $settings['risk'])
                : null,
            'الحد الأدنى للمتأخر' => $settings['minimum_overdue'] > 0
                ? number_format($settings['minimum_overdue'], 2).' ل.س'
                : null,
            'المنطقة' => $areaId
                ? Area::query()->find($areaId)?->name_ar
                : null,
            'خط التوزيع' => $routeId
                ? DistributionRoute::query()->find($routeId)?->name
                : null,
            'نوع الدفع' => $paymentType
                ? ($paymentTypeOptions[$paymentType] ?? $paymentType)
                : null,
            'نوع العميل' => $customerType,
            'حالة العميل' => $status,
            'البحث' => $search !== '' ? $search : null,
        ], fn (mixed $value): bool => filled($value));

        return view('reports.overdue-customers.filtered-print', [
            'customers' => $customers,
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

    private function filterValue(
        array $filters,
        string $name,
    ): ?string {
        $state = $filters[$name] ?? null;

        if (! is_array($state)) {
            return is_scalar($state) && filled($state)
                ? (string) $state
                : null;
        }

        $value = $state['value'] ?? null;

        return is_scalar($value) && filled($value)
            ? (string) $value
            : null;
    }

    private function normalizeId(?string $value): ?int
    {
        if ($value === null || ! ctype_digit($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
