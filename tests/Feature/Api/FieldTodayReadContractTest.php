<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class FieldTodayReadContractTest extends TestCase
{
    public function test_field_today_read_contract_is_read_only_and_has_no_migration(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Api/V1/Operational/FieldTodayController.php'));
        $service = file_get_contents(app_path('Services/Distribution/FieldTodayReadService.php'));
        $resolver = file_get_contents(app_path('Services/Distribution/FieldRouteAssignmentResolver.php'));

        $this->assertStringContainsString("Route::get('/today'", $routes);
        $this->assertStringNotContainsString("Route::post('/today'", $routes);
        $this->assertStringContainsString('ApiResponse::success', $controller);
        $this->assertStringContainsString('available_roles', $service);
        $this->assertStringContainsString('visit_days', $resolver);
        $this->assertStringContainsString('resolveForClosing', $resolver);

        $matchingMigrations = glob(database_path('migrations/*field_today*')) ?: [];
        $this->assertSame([], $matchingMigrations);
    }

    public function test_daily_closing_uses_the_shared_route_assignment_resolver(): void
    {
        $service = file_get_contents(app_path('Services/Distribution/DailyClosingFieldHandoverService.php'));

        $this->assertStringContainsString('FieldRouteAssignmentResolver', $service);
        $this->assertStringContainsString('resolveForClosing', $service);
        $this->assertStringNotContainsString('private function resolveRoute', $service);
    }
}
