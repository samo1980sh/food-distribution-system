<?php

namespace Tests\Feature;

use App\Filament\Resources\TopCustomerReports\Tables\TopCustomerReportsTable;
use App\Models\Customer;
use App\Models\User;
use App\Services\Reports\TopCustomerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TopCustomerReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TopCustomerReportService::forgetCache();
    }

    public function test_net_sales_ranking_subtracts_confirmed_returns(): void
    {
        $this->insertReportData();

        $rankings = app(TopCustomerReportService::class)
            ->rankings($this->settings());

        $this->assertSame([101, 102], $rankings
            ->pluck('customer_id')
            ->all());

        $this->assertSame(900.0, $rankings[0]['net_sales']);
        $this->assertSame(100.0, $rankings[0]['returns_amount']);
        $this->assertSame(9.0, $rankings[0]['net_quantity']);
        $this->assertSame(450.0, $rankings[0]['approximate_profit']);
        $this->assertSame(700.0, $rankings[1]['net_sales']);
    }

    public function test_ranking_metric_and_limit_can_be_changed(): void
    {
        $this->insertReportData();

        $rankings = app(TopCustomerReportService::class)
            ->rankings($this->settings([
                'ranking_metric' => 'invoice_count',
                'limit' => '1',
            ]));

        $this->assertCount(1, $rankings);
        $this->assertSame(102, $rankings[0]['customer_id']);
        $this->assertSame(2, $rankings[0]['invoice_count']);
        $this->assertSame(1, $rankings[0]['rank']);
        $this->assertSame(100.0, $rankings[0]['net_sales_share_percent']);
    }

    public function test_row_print_action_resolves_customer_detail_route(): void
    {
        $customer = new Customer();
        $customer->forceFill(['id' => 77]);

        $this->assertSame(
            route('reports.top-customers.print', [
                'customer' => 77,
                'from' => '2026-07-01',
                'until' => '2026-07-12',
            ]),
            TopCustomerReportsTable::printUrlFor($customer, [
                'from' => '2026-07-01',
                'until' => '2026-07-12',
            ]),
        );
    }

    public function test_filtered_print_applies_top_limit_and_ranking(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->insertReportData();

        $state = $this->encodeState([
            'filters' => [
                'ranking_settings' => $this->settings([
                    'limit' => '1',
                ]),
            ],
        ]);

        $this
            ->actingAs($user)
            ->get(route('reports.top-customers.print-filtered', [
                'state' => $state,
            ]))
            ->assertOk()
            ->assertSee('تقرير العملاء الأكثر شراءً')
            ->assertSee('عميل ألف')
            ->assertDontSee('عميل باء')
            ->assertDontSee('عميل خارج الفترة')
            ->assertSee('900.00')
            ->assertSee('450.00');
    }

    public function test_single_print_contains_invoice_and_return_details(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->insertReportData();

        $settings = $this->settings();

        $this
            ->actingAs($user)
            ->get(route('reports.top-customers.print', [
                'customer' => 101,
                'from' => $settings['from'],
                'until' => $settings['until'],
            ]))
            ->assertOk()
            ->assertSee('تفصيل مشتريات عميل')
            ->assertSee('عميل ألف')
            ->assertSee('TOP-INV-A')
            ->assertSee('TOP-RET-A')
            ->assertSee('900.00')
            ->assertSee('450.00');
    }

    private function insertReportData(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::table('customers')->insert([
                $this->customerRow(101, 'TOP-CUS-A', 'عميل ألف'),
                $this->customerRow(102, 'TOP-CUS-B', 'عميل باء'),
                $this->customerRow(103, 'TOP-CUS-C', 'عميل خارج الفترة'),
            ]);

            DB::table('sales_invoices')->insert([
                $this->invoiceRow(
                    id: 201,
                    number: 'TOP-INV-A',
                    customerId: 101,
                    date: today()->subDays(10)->toDateString(),
                    total: 1000,
                ),
                $this->invoiceRow(
                    id: 202,
                    number: 'TOP-INV-B1',
                    customerId: 102,
                    date: today()->subDays(9)->toDateString(),
                    total: 400,
                ),
                $this->invoiceRow(
                    id: 203,
                    number: 'TOP-INV-B2',
                    customerId: 102,
                    date: today()->subDays(8)->toDateString(),
                    total: 300,
                ),
                $this->invoiceRow(
                    id: 204,
                    number: 'TOP-INV-OUTSIDE',
                    customerId: 103,
                    date: today()->subDays(60)->toDateString(),
                    total: 2000,
                ),
            ]);

            DB::table('sales_invoice_items')->insert([
                $this->invoiceItemRow(
                    id: 401,
                    invoiceId: 201,
                    quantity: 10,
                    lineTotal: 1000,
                    unitCost: 50,
                    totalCost: 500,
                ),
                $this->invoiceItemRow(
                    id: 402,
                    invoiceId: 202,
                    quantity: 4,
                    lineTotal: 400,
                    unitCost: 50,
                    totalCost: 200,
                ),
                $this->invoiceItemRow(
                    id: 403,
                    invoiceId: 203,
                    quantity: 3,
                    lineTotal: 300,
                    unitCost: 50,
                    totalCost: 150,
                ),
                $this->invoiceItemRow(
                    id: 404,
                    invoiceId: 204,
                    quantity: 20,
                    lineTotal: 2000,
                    unitCost: 50,
                    totalCost: 1000,
                ),
            ]);

            DB::table('sales_returns')->insert([
                [
                    'id' => 301,
                    'return_number' => 'TOP-RET-A',
                    'customer_id' => 101,
                    'sales_invoice_id' => 201,
                    'vehicle_id' => null,
                    'route_id' => null,
                    'warehouse_id' => 1,
                    'sales_representative_id' => null,
                    'return_date' => today()->subDays(2)->toDateString(),
                    'status' => 'confirmed',
                    'return_reason' => 'مرتجع اختبار',
                    'subtotal' => 100,
                    'discount_amount' => 0,
                    'total_amount' => 100,
                    'notes' => null,
                    'created_by' => null,
                    'confirmed_by' => null,
                    'confirmed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('sales_return_items')->insert([
                [
                    'id' => 501,
                    'sales_return_id' => 301,
                    'product_id' => 1,
                    'batch_number' => null,
                    'expiry_date' => null,
                    'quantity' => 1,
                    'unit_price' => 100,
                    'unit_cost' => 50,
                    'line_total' => 100,
                    'total_cost' => 50,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        TopCustomerReportService::forgetCache();
    }

    private function settings(array $overrides = []): array
    {
        return array_merge([
            'from' => today()->subDays(20)->toDateString(),
            'until' => today()->toDateString(),
            'ranking_metric' => 'net_sales',
            'limit' => 'all',
            'customer_id' => null,
            'area_id' => null,
            'route_id' => null,
            'customer_type' => null,
            'payment_type' => null,
            'status' => null,
            'minimum_net_sales' => 0,
            'search' => '',
        ], $overrides);
    }

    private function customerRow(
        int $id,
        string $code,
        string $name,
    ): array {
        return [
            'id' => $id,
            'code' => $code,
            'name' => $name,
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
        ];
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

    private function invoiceItemRow(
        int $id,
        int $invoiceId,
        float $quantity,
        float $lineTotal,
        float $unitCost,
        float $totalCost,
    ): array {
        return [
            'id' => $id,
            'sales_invoice_id' => $invoiceId,
            'product_id' => 1,
            'batch_number' => null,
            'expiry_date' => null,
            'quantity' => $quantity,
            'unit_price' => $lineTotal / $quantity,
            'unit_cost' => $unitCost,
            'discount_amount' => 0,
            'line_total' => $lineTotal,
            'total_cost' => $totalCost,
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
