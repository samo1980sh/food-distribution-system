<?php

namespace Tests\Feature;

use App\Models\DistributionRoute;
use App\Models\Employee;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Lr4LegacyAdminDemoResidualCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_demo_presentation_advertises_only_the_unified_field_workflow(): void
    {
        $command = file_get_contents(app_path('Console/Commands/ResetProfessionalDemoDatabase.php'));
        $manifest = file_get_contents(base_path('docs/PROFESSIONAL_DEMO_DATABASE_MANIFEST.txt'));

        foreach ([$command, $manifest] as $contents) {
            $this->assertIsString($contents);
            $this->assertStringNotContainsString('Ready sales and driver journeys', $contents);
            $this->assertStringNotContainsString('Ready driver journeys', $contents);
        }

        $this->assertStringNotContainsString('Today driver journeys', $command);
        $this->assertStringNotContainsString('Today driver deliveries', $command);
        $this->assertStringNotContainsString('Flutter driver', $command);
        $this->assertStringNotContainsString('Driver only', $command);
        $this->assertStringContainsString('Ready unified representative journeys', $command);
        $this->assertStringContainsString('Retired driver identity - no mobile runtime', $command);
        $this->assertStringContainsString('Legacy driver identities retained only for compatibility', $manifest);
    }

    public function test_standalone_master_data_sample_uses_representative_only_assignments(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(0, Employee::query()->where('type', 'driver')->count());
        $this->assertGreaterThan(0, Employee::query()->where('type', 'sales_representative')->count());
        $this->assertGreaterThan(0, DistributionRoute::query()->count());
        $this->assertSame(0, DistributionRoute::query()->whereNotNull('driver_id')->count());
        $this->assertSame(
            DistributionRoute::query()->count(),
            DistributionRoute::query()->whereNotNull('sales_representative_id')->count(),
        );
    }

    public function test_historical_driver_reporting_fields_remain_read_only_available(): void
    {
        $routeTable = file_get_contents(app_path('Filament/Resources/DistributionRoutes/Tables/DistributionRoutesTable.php'));
        $loadReport = file_get_contents(app_path('Filament/Resources/VehicleLoadReports/Tables/VehicleLoadReportsTable.php'));
        $expenseReport = file_get_contents(app_path('Filament/Resources/VehicleExpenseReports/Tables/VehicleExpenseReportsTable.php'));

        $this->assertStringContainsString("SelectFilter::make('driver_id')", $routeTable);
        $this->assertStringContainsString("SelectFilter::make('driver_id')", $loadReport);
        $this->assertStringContainsString("SelectFilter::make('driver_id')", $expenseReport);
    }
}
