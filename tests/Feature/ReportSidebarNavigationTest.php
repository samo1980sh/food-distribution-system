<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomerStatementReport;
use App\Filament\Resources\CustomerPaymentReports\CustomerPaymentReportResource;
use App\Filament\Resources\DailyClosingReports\DailyClosingReportResource;
use App\Filament\Resources\ExpiryRiskReports\ExpiryRiskReportResource;
use App\Filament\Resources\OverdueCustomerReports\OverdueCustomerReportResource;
use App\Filament\Resources\ProfitReports\ProfitReportResource;
use App\Filament\Resources\RoutePerformanceReports\RoutePerformanceReportResource;
use App\Filament\Resources\SalesReports\SalesReportResource;
use App\Filament\Resources\SalesReturnReports\SalesReturnReportResource;
use App\Filament\Resources\TopCustomerReports\TopCustomerReportResource;
use App\Filament\Resources\VehicleExpenseReports\VehicleExpenseReportResource;
use App\Filament\Resources\VehicleLoadReports\VehicleLoadReportResource;
use App\Filament\Resources\VehicleStockReports\VehicleStockReportResource;
use Tests\TestCase;

class ReportSidebarNavigationTest extends TestCase
{
    public function test_report_navigation_is_split_into_four_collapsed_groups(): void
    {
        $provider = $this->source('app/Providers/Filament/AdminPanelProvider.php');

        $groups = [
            'تقارير المبيعات والعملاء',
            'تقارير المركبات والتوزيع',
            'تقارير المخزون والربحية',
            'تقارير الرقابة اليومية',
        ];

        $this->assertStringContainsString('use Filament\\Navigation\\NavigationGroup;', $provider);
        $this->assertSame(4, substr_count($provider, 'NavigationGroup::make()'));
        $this->assertSame(4, substr_count($provider, '->collapsed()'));

        $previousPosition = -1;

        foreach ($groups as $group) {
            $needle = "->label('{$group}')";
            $position = strpos($provider, $needle);

            $this->assertNotFalse($position, "مجموعة التنقل [{$group}] غير مسجلة.");
            $this->assertGreaterThan($previousPosition, $position, "ترتيب مجموعة [{$group}] غير صحيح.");

            $previousPosition = $position;
        }

        foreach ([
            "'التهيئة الأساسية'",
            "'المخزون'",
            "'التوزيع والأسطول'",
            "'المبيعات والتحصيل'",
            "'الإغلاق والمطابقة'",
        ] as $existingGroup) {
            $this->assertStringContainsString($existingGroup, $provider);
        }

        $this->assertStringNotContainsString("                'التقارير',", $provider);
        $this->assertStringNotContainsString('->icon(', $this->navigationGroupsBlock($provider));
        $this->assertStringNotContainsString('collapsibleNavigationGroups(false)', $provider);
    }

    public function test_every_report_keeps_an_independent_link_with_a_clear_group_and_order(): void
    {
        $expectations = [
            SalesReportResource::class => ['تقارير المبيعات والعملاء', 10],
            CustomerPaymentReportResource::class => ['تقارير المبيعات والعملاء', 20],
            SalesReturnReportResource::class => ['تقارير المبيعات والعملاء', 30],
            CustomerStatementReport::class => ['تقارير المبيعات والعملاء', 40],
            OverdueCustomerReportResource::class => ['تقارير المبيعات والعملاء', 50],
            TopCustomerReportResource::class => ['تقارير المبيعات والعملاء', 60],
            VehicleLoadReportResource::class => ['تقارير المركبات والتوزيع', 10],
            VehicleStockReportResource::class => ['تقارير المركبات والتوزيع', 20],
            VehicleExpenseReportResource::class => ['تقارير المركبات والتوزيع', 30],
            RoutePerformanceReportResource::class => ['تقارير المركبات والتوزيع', 40],
            ExpiryRiskReportResource::class => ['تقارير المخزون والربحية', 10],
            ProfitReportResource::class => ['تقارير المخزون والربحية', 20],
            DailyClosingReportResource::class => ['تقارير الرقابة اليومية', 10],
        ];

        foreach ($expectations as $class => [$group, $sort]) {
            $this->assertSame($group, $class::getNavigationGroup(), "مجموعة [{$class}] غير صحيحة.");
            $this->assertSame($sort, $class::getNavigationSort(), "ترتيب [{$class}] غير صحيح.");
            $this->assertNotSame('', $class::getNavigationLabel(), "اسم رابط [{$class}] فارغ.");
        }
    }

    public function test_sidebar_reorganization_preserves_routes_permissions_and_report_registration(): void
    {
        $files = [
            'app/Filament/Pages/CustomerStatementReport.php',
            'app/Filament/Resources/CustomerPaymentReports/CustomerPaymentReportResource.php',
            'app/Filament/Resources/DailyClosingReports/DailyClosingReportResource.php',
            'app/Filament/Resources/ExpiryRiskReports/ExpiryRiskReportResource.php',
            'app/Filament/Resources/OverdueCustomerReports/OverdueCustomerReportResource.php',
            'app/Filament/Resources/ProfitReports/ProfitReportResource.php',
            'app/Filament/Resources/RoutePerformanceReports/RoutePerformanceReportResource.php',
            'app/Filament/Resources/SalesReports/SalesReportResource.php',
            'app/Filament/Resources/SalesReturnReports/SalesReturnReportResource.php',
            'app/Filament/Resources/TopCustomerReports/TopCustomerReportResource.php',
            'app/Filament/Resources/VehicleExpenseReports/VehicleExpenseReportResource.php',
            'app/Filament/Resources/VehicleLoadReports/VehicleLoadReportResource.php',
            'app/Filament/Resources/VehicleStockReports/VehicleStockReportResource.php',
        ];

        $combined = '';

        foreach ($files as $file) {
            $source = $this->source($file);
            $combined .= $source;

            $this->assertStringNotContainsString("return 'التقارير';", $source);
            $this->assertStringNotContainsString('protected static bool $shouldRegisterNavigation = false;', $source);
            $this->assertStringNotContainsString('$navigationParentItem', $source);
            $this->assertStringNotContainsString('Cluster', $source);
        }

        foreach ([
            'PermissionName::REPORT_SALES->value',
            'PermissionName::REPORT_CUSTOMER_PAYMENTS->value',
            'PermissionName::REPORT_SALES_RETURNS->value',
            'PermissionName::REPORT_CUSTOMER_STATEMENT->value',
            'PermissionName::REPORT_OVERDUE_CUSTOMERS->value',
            'PermissionName::REPORT_TOP_CUSTOMERS->value',
            'PermissionName::REPORT_VEHICLE_LOADS->value',
            'PermissionName::REPORT_VEHICLE_STOCK->value',
            'PermissionName::REPORT_VEHICLE_EXPENSES->value',
            'PermissionName::REPORT_ROUTE_PERFORMANCE->value',
            'PermissionName::REPORT_EXPIRY_RISK->value',
            'PermissionName::REPORT_PROFIT->value',
            'PermissionName::REPORT_DAILY_CLOSINGS->value',
        ] as $permission) {
            $this->assertStringContainsString($permission, $combined);
        }

        foreach ([
            "ManageSalesReports::route('/')",
            "ManageCustomerPaymentReports::route('/')",
            "ManageSalesReturnReports::route('/')",
            "protected static ?string \$slug = 'customer-statement';",
            "ManageOverdueCustomerReports::route('/')",
            "ManageTopCustomerReports::route('/')",
            "ManageVehicleLoadReports::route('/')",
            "ManageVehicleStockReports::route('/')",
            "ManageVehicleExpenseReports::route('/')",
            "ManageRoutePerformanceReports::route('/')",
            "ManageExpiryRiskReports::route('/')",
            "ManageProfitReports::route('/')",
            "ManageDailyClosingReports::route('/')",
        ] as $routeRegistration) {
            $this->assertStringContainsString($routeRegistration, $combined);
        }

        $this->assertStringNotContainsString('ReportCenter', $combined);
        $this->assertStringNotContainsString('report-center', $combined);
        $this->assertStringNotContainsString('theme.css', $combined);
    }

    private function navigationGroupsBlock(string $provider): string
    {
        $start = strpos($provider, '->navigationGroups([');
        $end = strpos($provider, '            ])', $start === false ? 0 : $start);

        $this->assertNotFalse($start, 'تعذر العثور على بداية navigationGroups().');
        $this->assertNotFalse($end, 'تعذر العثور على نهاية navigationGroups().');

        return substr($provider, $start, ($end - $start) + strlen('            ])'));
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($contents, "تعذر قراءة الملف [{$relativePath}].");

        return (string) $contents;
    }
}
