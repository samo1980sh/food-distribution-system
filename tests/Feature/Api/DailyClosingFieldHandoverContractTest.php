<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class DailyClosingFieldHandoverContractTest extends TestCase
{
    public function test_field_handover_uses_one_daily_closing_and_dedicated_role_endpoints(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));
        $migration = file_get_contents(database_path('migrations/2026_07_22_120000_add_field_handover_to_daily_closings.php'));
        $service = file_get_contents(app_path('Services/Distribution/DailyClosingFieldHandoverService.php'));

        $this->assertStringContainsString('/daily-closings/open-today', $routes);
        $this->assertStringContainsString('/daily-closings/{dailyClosing}/submit-inventory', $routes);
        $this->assertStringContainsString('/daily-closings/{dailyClosing}/submit-cash', $routes);
        $this->assertStringContainsString("Schema::table('daily_closings'", $migration);
        $this->assertStringNotContainsString('Schema::create(', $migration);
        $this->assertStringContainsString('refreshTotals', $service);
    }

    public function test_field_handover_does_not_add_a_parallel_custody_or_submitted_status(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_07_22_120000_add_field_handover_to_daily_closings.php'));
        $service = file_get_contents(app_path('Services/Distribution/DailyClosingFieldHandoverService.php'));
        $closingService = file_get_contents(app_path('Services/Distribution/DailyClosingService.php'));

        $this->assertStringNotContainsString('custod', strtolower($migration));
        $this->assertStringNotContainsString('field_closings', strtolower($migration));
        $this->assertStringNotContainsString("'submitted'", $service);
        $this->assertStringNotContainsString("'submitted'", $closingService);
    }
}
