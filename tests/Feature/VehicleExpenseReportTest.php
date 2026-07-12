<?php

namespace Tests\Feature;

use App\Filament\Resources\VehicleExpenseReports\Tables\VehicleExpenseReportsTable;
use App\Filament\Resources\VehicleExpenseReports\VehicleExpenseReportResource;
use App\Models\User;
use App\Models\VehicleExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VehicleExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_query_contains_approved_expenses_only(): void
    {
        $this->insertExpenses();

        $numbers = VehicleExpenseReportResource::getEloquentQuery()
            ->orderBy('id')
            ->pluck('expense_number')
            ->all();

        $this->assertSame([
            'VEX-APPROVED-FUEL',
            'VEX-APPROVED-MAINTENANCE',
        ], $numbers);
    }

    public function test_row_print_action_resolves_the_single_expense_print_route(): void
    {
        $expense = new VehicleExpense();
        $expense->forceFill(['id' => 17]);

        $this->assertSame(
            route('reports.vehicle-expenses.print', [
                'vehicleExpense' => 17,
            ]),
            VehicleExpenseReportsTable::printUrlFor($expense),
        );
    }

    public function test_filtered_print_includes_only_approved_expenses_matching_the_filters(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->insertExpenses();

        $state = $this->encodeState([
            'filters' => [
                'expense_date' => [
                    'from' => '2026-07-10',
                    'until' => '2026-07-10',
                ],
                'expense_type' => [
                    'value' => 'fuel',
                ],
                'payment_method' => [
                    'value' => 'cash',
                ],
            ],
            'search' => 'VEX-APPROVED',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reports.vehicle-expenses.print-filtered', [
                'state' => $state,
            ]));

        $response
            ->assertOk()
            ->assertSee('تقرير مصاريف السيارات')
            ->assertSee('VEX-APPROVED-FUEL')
            ->assertDontSee('VEX-APPROVED-MAINTENANCE')
            ->assertDontSee('VEX-PENDING-FUEL')
            ->assertSee('1,250.00');
    }

    public function test_single_print_allows_approved_expense_and_blocks_pending_expense(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->insertExpenses();

        $this
            ->actingAs($user)
            ->get(route('reports.vehicle-expenses.print', [
                'vehicleExpense' => 1,
            ]))
            ->assertOk()
            ->assertSee('VEX-APPROVED-FUEL')
            ->assertSee('1,250.00');

        $this
            ->actingAs($user)
            ->get(route('reports.vehicle-expenses.print', [
                'vehicleExpense' => 3,
            ]))
            ->assertNotFound();
    }

    private function insertExpenses(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::table('vehicle_expenses')->insert([
                [
                    'id' => 1,
                    'expense_number' => 'VEX-APPROVED-FUEL',
                    'expense_date' => '2026-07-10',
                    'vehicle_id' => 1,
                    'warehouse_id' => 1,
                    'route_id' => null,
                    'driver_id' => null,
                    'sales_representative_id' => null,
                    'expense_type' => 'fuel',
                    'amount' => 1250,
                    'payment_method' => 'cash',
                    'receipt_path' => null,
                    'status' => 'approved',
                    'notes' => 'وقود يومي',
                    'approved_at' => '2026-07-10 18:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 2,
                    'expense_number' => 'VEX-APPROVED-MAINTENANCE',
                    'expense_date' => '2026-07-11',
                    'vehicle_id' => 1,
                    'warehouse_id' => 1,
                    'route_id' => null,
                    'driver_id' => null,
                    'sales_representative_id' => null,
                    'expense_type' => 'maintenance',
                    'amount' => 3000,
                    'payment_method' => 'bank_transfer',
                    'receipt_path' => 'vehicle-expenses/receipt.pdf',
                    'status' => 'approved',
                    'notes' => null,
                    'approved_at' => '2026-07-11 18:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 3,
                    'expense_number' => 'VEX-PENDING-FUEL',
                    'expense_date' => '2026-07-10',
                    'vehicle_id' => 1,
                    'warehouse_id' => 1,
                    'route_id' => null,
                    'driver_id' => null,
                    'sales_representative_id' => null,
                    'expense_type' => 'fuel',
                    'amount' => 900,
                    'payment_method' => 'cash',
                    'receipt_path' => null,
                    'status' => 'pending',
                    'notes' => null,
                    'approved_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 4,
                    'expense_number' => 'VEX-REJECTED-FEES',
                    'expense_date' => '2026-07-10',
                    'vehicle_id' => 1,
                    'warehouse_id' => 1,
                    'route_id' => null,
                    'driver_id' => null,
                    'sales_representative_id' => null,
                    'expense_type' => 'fees',
                    'amount' => 500,
                    'payment_method' => 'cash',
                    'receipt_path' => null,
                    'status' => 'rejected',
                    'notes' => null,
                    'approved_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function encodeState(array $state): string
    {
        $json = json_encode(
            $state,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $this->assertNotFalse($json);

        return rtrim(
            strtr(base64_encode($json), '+/', '-_'),
            '=',
        );
    }
}
