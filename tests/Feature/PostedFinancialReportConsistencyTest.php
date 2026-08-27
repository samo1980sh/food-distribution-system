<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerPaymentReports\CustomerPaymentReportResource;
use App\Filament\Resources\DailyClosingReports\DailyClosingReportResource;
use App\Filament\Resources\SalesReturnReports\SalesReturnReportResource;
use App\Filament\Resources\VehicleExpenseReports\VehicleExpenseReportResource;
use Tests\TestCase;

class PostedFinancialReportConsistencyTest extends TestCase
{
    public function test_financial_report_resources_are_restricted_to_posted_states(): void
    {
        $this->assertContains(
            'confirmed',
            CustomerPaymentReportResource::getEloquentQuery()->getBindings(),
        );
        $this->assertContains(
            'confirmed',
            SalesReturnReportResource::getEloquentQuery()->getBindings(),
        );
        $this->assertContains(
            'confirmed',
            DailyClosingReportResource::getEloquentQuery()->getBindings(),
        );
        $this->assertContains(
            'approved',
            VehicleExpenseReportResource::getEloquentQuery()->getBindings(),
        );
    }

    public function test_filtered_prints_cannot_reintroduce_draft_or_cancelled_financial_rows(): void
    {
        $controllers = [
            'Http/Controllers/Reports/CustomerPaymentReportFilteredPrintController.php' => 'confirmed',
            'Http/Controllers/Reports/SalesReturnReportFilteredPrintController.php' => 'confirmed',
            'Http/Controllers/Reports/DailyClosingFilteredPrintController.php' => 'confirmed',
            'Http/Controllers/Reports/VehicleExpenseReportFilteredPrintController.php' => 'approved',
        ];

        foreach ($controllers as $path => $postedStatus) {
            $source = file_get_contents(app_path($path));

            $this->assertStringContainsString(
                "->where('status', '{$postedStatus}')",
                $source,
                "Filtered print [{$path}] must be posted-state only.",
            );
            $this->assertStringNotContainsString(
                "filterValue(\$filters, 'status')",
                $source,
                "Filtered print [{$path}] must not accept a financial status override.",
            );
        }
    }

    public function test_aggregate_services_use_only_final_operational_states(): void
    {
        $profit = file_get_contents(app_path('Services/Reports/ProfitReportQuery.php'));
        $topCustomers = file_get_contents(app_path('Services/Reports/TopCustomerReportService.php'));
        $routePerformance = file_get_contents(app_path('Services/Reports/RoutePerformanceReportService.php'));

        $this->assertGreaterThanOrEqual(2, substr_count($profit, "->where('sales_invoices.status', 'confirmed')") + substr_count($profit, "->where('sales_returns.status', 'confirmed')"));
        $this->assertStringContainsString("->where('status', 'confirmed')", $topCustomers);
        $this->assertStringContainsString("->where('status', 'confirmed')", $routePerformance);
        $this->assertStringContainsString("->where('status', 'approved')", $routePerformance);
    }
}
