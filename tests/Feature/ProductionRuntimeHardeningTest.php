<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ProductionRuntimeHardeningTest extends TestCase
{
    public function test_root_redirects_to_filament_admin_instead_of_laravel_welcome_page(): void
    {
        $this->get('/')
            ->assertRedirect('/admin');

        $routes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString("Route::redirect('/', '/admin');", $routes);
        $this->assertStringNotContainsString("return view('welcome');", $routes);
    }

    public function test_unexpected_api_exception_uses_safe_standard_error_envelope(): void
    {
        Route::get('/api/runtime-hardening/unexpected-error', static function (): never {
            throw new RuntimeException('SENSITIVE_INTERNAL_RUNTIME_DETAIL');
        });

        $response = $this->getJson('/api/runtime-hardening/unexpected-error');

        $response
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'server_error')
            ->assertJsonPath('message', 'حدث خطأ غير متوقع في الخادم.');

        $this->assertStringNotContainsString(
            'SENSITIVE_INTERNAL_RUNTIME_DETAIL',
            $response->getContent(),
        );
    }

    public function test_production_environment_template_disables_debug_and_requires_https_shape(): void
    {
        $environment = file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('APP_ENV=production', $environment);
        $this->assertStringContainsString('APP_DEBUG=false', $environment);
        $this->assertStringContainsString('APP_URL=https://example.com', $environment);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $environment);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $environment);
        $this->assertMatchesRegularExpression('/^APP_KEY=\s*$/m', $environment);
    }

    public function test_deployment_runbook_requires_scheduler_backups_and_safe_deployment_commands(): void
    {
        $runbook = file_get_contents(base_path('docs/PRODUCTION_DEPLOYMENT.md'));

        $this->assertStringContainsString('php artisan migrate --force', $runbook);
        $this->assertStringContainsString('php artisan db:seed --force', $runbook);
        $this->assertStringContainsString('php artisan schedule:run', $runbook);
        $this->assertStringContainsString('sanctum:prune-expired --hours=24', $runbook);
        $this->assertStringContainsString('mobile-sync:prune --apply', $runbook);
        $this->assertStringContainsString('storage/app/private', $runbook);
        $this->assertStringContainsString('APP_DEBUG=false', $runbook);
        $this->assertStringContainsString('composer audit', $runbook);
        $this->assertStringContainsString('لا تستخدم `migrate:rollback` بشكل أعمى', $runbook);
    }

    public function test_readme_is_project_specific_and_points_to_production_runbook(): void
    {
        $readme = file_get_contents(base_path('README.md'));

        $this->assertStringContainsString('نظام التوزيع والمبيعات', $readme);
        $this->assertStringContainsString('Food Distribution System', $readme);
        $this->assertStringContainsString('docs/PRODUCTION_DEPLOYMENT.md', $readme);
        $this->assertStringNotContainsString('## About Laravel', $readme);
    }
}
