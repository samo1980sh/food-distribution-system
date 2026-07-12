<?php

namespace Tests\Feature;

use App\Filament\Resources\OverdueCustomerReports\Tables\OverdueCustomerReportsTable;
use App\Models\Customer;
use App\Models\User;
use App\Services\Reports\OverdueCustomerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OverdueCustomerReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        OverdueCustomerReportService::forgetCache();
    }

    public function test_fifo_allocation_separates_overdue_and_not_due_balances(): void
    {
        $this->insertReportData();

        $summary = app(OverdueCustomerReportService::class)
            ->summaryForCustomer(
                customerId: 101,
                creditDays: 30,
                asOf: today()->toDateString(),
            );

        $this->assertSame(800.0, $summary['current_balance']);
        $this->assertSame(300.0, $summary['overdue_amount']);
        $this->assertSame(500.0, $summary['not_due_amount']);
        $this->assertSame(1, $summary['overdue_invoices_count']);
        $this->assertSame('INV-OLD', $summary['invoices'][0]['invoice_number']);
        $this->assertSame(300.0, $summary['invoices'][0]['remaining_amount']);
        $this->assertSame(500.0, $summary['invoices'][1]['remaining_amount']);
    }

    public function test_overdue_scope_excludes_settled_and_recent_only_customers(): void
    {
        $this->insertReportData();

        $service = app(OverdueCustomerReportService::class);

        $overdueIds = $service->customerIds(
            creditDays: 30,
            asOf: today()->toDateString(),
            criteria: ['scope' => 'overdue'],
        );

        $allPositiveIds = $service->customerIds(
            creditDays: 30,
            asOf: today()->toDateString(),
            criteria: ['scope' => 'all_positive'],
        );

        $this->assertSame([101], $overdueIds);
        $this->assertSame([101, 103], $allPositiveIds);
    }

    public function test_row_print_action_resolves_customer_print_route(): void
    {
        $customer = new Customer();
        $customer->forceFill(['id' => 77]);

        $this->assertSame(
            route('reports.overdue-customers.print', [
                'customer' => 77,
                'credit_days' => 45,
                'as_of' => '2026-07-12',
            ]),
            OverdueCustomerReportsTable::printUrlFor($customer, [
                'credit_days' => 45,
                'as_of' => '2026-07-12',
            ]),
        );
    }

    public function test_filtered_print_shows_only_overdue_customers(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->insertReportData();

        $state = $this->encodeState([
            'filters' => [
                'overdue_settings' => [
                    'scope' => 'overdue',
                    'credit_days' => 30,
                    'custom_credit_days' => null,
                    'as_of' => today()->toDateString(),
                    'risk' => null,
                    'minimum_overdue' => 0,
                ],
            ],
            'search' => '',
        ]);

        $this
            ->actingAs($user)
            ->get(route('reports.overdue-customers.print-filtered', [
                'state' => $state,
            ]))
            ->assertOk()
            ->assertSee('تقرير العملاء المتأخرين بالدفع')
            ->assertSee('عميل متأخر جزئيًا')
            ->assertDontSee('عميل مسدد')
            ->assertDontSee('عميل حديث')
            ->assertSee('300.00')
            ->assertSee('500.00');
    }

    public function test_single_print_contains_fifo_invoice_details(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->insertReportData();

        $this
            ->actingAs($user)
            ->get(route('reports.overdue-customers.print', [
                'customer' => 101,
                'credit_days' => 30,
                'as_of' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('كشف مديونية عميل')
            ->assertSee('عميل متأخر جزئيًا')
            ->assertSee('INV-OLD')
            ->assertSee('INV-RECENT')
            ->assertSee('PAY-FIFO')
            ->assertSee('300.00')
            ->assertSee('800.00');
    }

    private function insertReportData(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::table('customers')->insert([
                [
                    'id' => 101,
                    'code' => 'CUS-101',
                    'name' => 'عميل متأخر جزئيًا',
                    'owner_name' => null,
                    'phone' => '0111111111',
                    'mobile' => null,
                    'customer_type' => 'grocery',
                    'area_id' => null,
                    'route_id' => null,
                    'address' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'credit_limit' => 1000,
                    'payment_type' => 'credit',
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 102,
                    'code' => 'CUS-102',
                    'name' => 'عميل مسدد',
                    'owner_name' => null,
                    'phone' => null,
                    'mobile' => null,
                    'customer_type' => 'grocery',
                    'area_id' => null,
                    'route_id' => null,
                    'address' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'credit_limit' => 500,
                    'payment_type' => 'credit',
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 103,
                    'code' => 'CUS-103',
                    'name' => 'عميل حديث',
                    'owner_name' => null,
                    'phone' => null,
                    'mobile' => null,
                    'customer_type' => 'grocery',
                    'area_id' => null,
                    'route_id' => null,
                    'address' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'credit_limit' => 1000,
                    'payment_type' => 'credit',
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('sales_invoices')->insert([
                $this->invoiceRow(
                    id: 201,
                    number: 'INV-OLD',
                    customerId: 101,
                    date: today()->subDays(45)->toDateString(),
                    total: 1000,
                ),
                $this->invoiceRow(
                    id: 202,
                    number: 'INV-RECENT',
                    customerId: 101,
                    date: today()->subDays(10)->toDateString(),
                    total: 500,
                ),
                $this->invoiceRow(
                    id: 203,
                    number: 'INV-SETTLED',
                    customerId: 102,
                    date: today()->subDays(50)->toDateString(),
                    total: 400,
                ),
                $this->invoiceRow(
                    id: 204,
                    number: 'INV-NEW',
                    customerId: 103,
                    date: today()->subDays(5)->toDateString(),
                    total: 600,
                ),
            ]);

            DB::table('customer_payments')->insert([
                $this->paymentRow(
                    id: 301,
                    number: 'PAY-FIFO',
                    customerId: 101,
                    amount: 700,
                ),
                $this->paymentRow(
                    id: 302,
                    number: 'PAY-SETTLED',
                    customerId: 102,
                    amount: 400,
                ),
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        OverdueCustomerReportService::forgetCache();
    }

    private function invoiceRow(
        int $id,
        string $number,
        int $customerId,
        string $date,
        float $total,
    ): array {
        return [
            'id' => $id,
            'invoice_number' => $number,
            'customer_id' => $customerId,
            'vehicle_id' => null,
            'route_id' => null,
            'warehouse_id' => 1,
            'sales_representative_id' => null,
            'invoice_date' => $date,
            'status' => 'confirmed',
            'payment_type' => 'credit',
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $total,
            'paid_amount' => 0,
            'invoice_cash_amount' => 0,
            'remaining_amount' => $total,
            'notes' => null,
            'created_by' => null,
            'confirmed_by' => null,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function paymentRow(
        int $id,
        string $number,
        int $customerId,
        float $amount,
    ): array {
        return [
            'id' => $id,
            'payment_number' => $number,
            'customer_id' => $customerId,
            'sales_invoice_id' => null,
            'vehicle_id' => null,
            'route_id' => null,
            'warehouse_id' => 1,
            'sales_representative_id' => null,
            'payment_date' => today()->subDays(2)->toDateString(),
            'payment_method' => 'cash',
            'status' => 'confirmed',
            'amount' => $amount,
            'reference_number' => null,
            'notes' => null,
            'created_by' => null,
            'confirmed_by' => null,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
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
