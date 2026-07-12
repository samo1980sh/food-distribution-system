<?php

namespace Tests\Feature;

use App\Http\Controllers\Reports\VehicleExpensePrintController;
use App\Models\VehicleExpense;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class ReportFinalConsistencyTest extends TestCase
{
    public function test_application_uses_damascus_timezone(): void
    {
        $this->assertSame(
            'Asia/Damascus',
            config('app.timezone'),
        );
    }

    public function test_guest_report_prints_redirect_to_filament_login(): void
    {
        $loginUrl = route('filament.admin.auth.login');

        $this
            ->get(route('reports.vehicle-expenses.print-filtered'))
            ->assertRedirect($loginUrl);

        $response = app(VehicleExpensePrintController::class)(
            new VehicleExpense(),
        );

        $this->assertInstanceOf(
            RedirectResponse::class,
            $response,
        );

        $this->assertSame(
            $loginUrl,
            $response->getTargetUrl(),
        );
    }
}
