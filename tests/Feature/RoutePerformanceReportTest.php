<?php

namespace Tests\Feature;

use App\Filament\Resources\RoutePerformanceReports\Tables\RoutePerformanceReportsTable;
use App\Models\DistributionRoute;
use App\Models\User;
use App\Services\Reports\RoutePerformanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoutePerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RoutePerformanceReportService::forgetCache();
    }

    public function test_metrics_include_returns_cost_expenses_and_collections(): void
    {
        $this->insertData(100);

        $summary = app(RoutePerformanceReportService::class)
            ->summaryForRoute(101, $this->settings());

        $this->assertSame(900.0, $summary['net_sales']);
        $this->assertSame(9.0, $summary['net_quantity']);
        $this->assertSame(450.0, $summary['gross_profit']);
        $this->assertSame(350.0, $summary['net_contribution']);
        $this->assertSame(500.0, $summary['total_collections']);
        $this->assertSame(20.0, $summary['loaded_quantity']);
        $this->assertSame(10.0, $summary['cash_difference']);
        $this->assertSame(100.0, $summary['service_coverage_percent']);
    }

    public function test_activity_routes_stay_before_zero_activity_routes(): void
    {
        $this->insertData(1000);

        $rows = app(RoutePerformanceReportService::class)
            ->rankings($this->settings());

        $this->assertSame([101, 102], $rows->pluck('route_id')->all());
        $this->assertSame(-550.0, $rows[0]['net_contribution']);
        $this->assertTrue($rows[0]['has_activity']);
        $this->assertFalse($rows[1]['has_activity']);
    }

    public function test_unassigned_payment_is_not_added_to_route_collections(): void
    {
        $this->insertData(100);

        $service = app(RoutePerformanceReportService::class);
        $summary = $service->summaryForRoute(101, $this->settings());
        $unassigned = $service->unassignedSummary($this->settings());

        $this->assertSame(500.0, $summary['total_collections']);
        $this->assertSame(1, $unassigned['payment_count']);
        $this->assertSame(250.0, $unassigned['payment_amount']);
    }

    public function test_row_print_action_builds_route_print_url(): void
    {
        $route = new DistributionRoute();
        $route->forceFill(['id' => 77]);

        $this->assertSame(
            route('reports.route-performance.print', [
                'distributionRoute' => 77,
                'from' => '2026-07-01',
                'until' => '2026-07-12',
            ]),
            RoutePerformanceReportsTable::printUrlFor($route, [
                'from' => '2026-07-01',
                'until' => '2026-07-12',
            ]),
        );
    }

    public function test_filtered_and_single_print_views_render(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->insertData(100);

        $state = $this->encode([
            'filters' => [
                'performance_settings' => $this->settings(),
            ],
        ]);

        $this
            ->actingAs($user)
            ->get(route('reports.route-performance.print-filtered', [
                'state' => $state,
            ]))
            ->assertOk()
            ->assertSee('تقرير أداء خطوط التوزيع')
            ->assertSee('خط النشاط')
            ->assertSee('خط دون نشاط')
            ->assertSee('تنبيه جودة البيانات')
            ->assertSee('350.00');

        $this
            ->actingAs($user)
            ->get(route('reports.route-performance.print', [
                'distributionRoute' => 101,
                'from' => $this->settings()['from'],
                'until' => $this->settings()['until'],
            ]))
            ->assertOk()
            ->assertSee('تفصيل أداء خط توزيع')
            ->assertSee('ROUTE-INV-1')
            ->assertSee('ROUTE-RET-1')
            ->assertSee('ROUTE-PAY-1')
            ->assertSee('ROUTE-EXP-1')
            ->assertSee('ROUTE-LOAD-1')
            ->assertSee('ROUTE-CLOSE-1');
    }

    private function insertData(float $expenseAmount): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::table('distribution_routes')->insert([
                $this->route(101, 'RT-ACTIVE', 'خط النشاط'),
                $this->route(102, 'RT-ZERO', 'خط دون نشاط'),
            ]);

            DB::table('customers')->insert([
                $this->customer(201, 101, 'ROUTE-CUS-1', 'عميل الخط'),
                $this->customer(202, 102, 'ROUTE-CUS-2', 'عميل بلا حركة'),
            ]);

            DB::table('sales_invoices')->insert([
                [
                    'id' => 301,
                    'invoice_number' => 'ROUTE-INV-1',
                    'customer_id' => 201,
                    'vehicle_id' => null,
                    'route_id' => 101,
                    'warehouse_id' => 1,
                    'sales_representative_id' => null,
                    'invoice_date' => today()->subDays(7)->toDateString(),
                    'status' => 'confirmed',
                    'payment_type' => 'partial',
                    'subtotal' => 1000,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 1000,
                    'paid_amount' => 200,
                    'invoice_cash_amount' => 200,
                    'remaining_amount' => 500,
                    'notes' => null,
                    'created_by' => null,
                    'confirmed_by' => null,
                    'confirmed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('sales_invoice_items')->insert([
                [
                    'id' => 401,
                    'sales_invoice_id' => 301,
                    'product_id' => 1,
                    'batch_number' => null,
                    'expiry_date' => null,
                    'quantity' => 10,
                    'unit_price' => 100,
                    'unit_cost' => 50,
                    'discount_amount' => 0,
                    'line_total' => 1000,
                    'total_cost' => 500,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('sales_returns')->insert([
                [
                    'id' => 501,
                    'return_number' => 'ROUTE-RET-1',
                    'customer_id' => 201,
                    'sales_invoice_id' => 301,
                    'vehicle_id' => null,
                    'route_id' => 101,
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
                    'id' => 601,
                    'sales_return_id' => 501,
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

            DB::table('customer_payments')->insert([
                $this->payment(701, 'ROUTE-PAY-1', 101, 300),
                $this->payment(
                    702,
                    'ROUTE-PAY-UNASSIGNED',
                    null,
                    250,
                ),
            ]);

            DB::table('vehicle_expenses')->insert([
                [
                    'id' => 801,
                    'expense_number' => 'ROUTE-EXP-1',
                    'expense_date' => today()->subDay()->toDateString(),
                    'vehicle_id' => 1,
                    'warehouse_id' => 1,
                    'route_id' => 101,
                    'driver_id' => null,
                    'sales_representative_id' => null,
                    'expense_type' => 'fuel',
                    'amount' => $expenseAmount,
                    'payment_method' => 'cash',
                    'receipt_path' => null,
                    'status' => 'approved',
                    'notes' => null,
                    'rejection_reason' => null,
                    'created_by' => null,
                    'approved_by' => null,
                    'approved_at' => now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('vehicle_loads')->insert([
                [
                    'id' => 901,
                    'load_number' => 'ROUTE-LOAD-1',
                    'vehicle_id' => 1,
                    'route_id' => 101,
                    'driver_id' => null,
                    'sales_representative_id' => null,
                    'from_warehouse_id' => 1,
                    'to_warehouse_id' => 2,
                    'load_date' => today()->subDays(8)->toDateString(),
                    'status' => 'approved',
                    'total_quantity' => 20,
                    'total_cost' => 1000,
                    'notes' => null,
                    'created_by' => null,
                    'approved_by' => null,
                    'approved_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $closing = [
                'id' => 1001,
                'closing_number' => 'ROUTE-CLOSE-1',
                'closing_date' => today()->toDateString(),
                'vehicle_id' => null,
                'route_id' => 101,
                'warehouse_id' => 1,
                'sales_representative_id' => null,
                'status' => 'confirmed',
                'total_loaded_quantity' => 20,
                'total_sold_quantity' => 10,
                'total_returned_quantity' => 1,
                'total_sales_amount' => 1000,
                'total_returns_amount' => 100,
                'total_collections_amount' => 500,
                'invoice_cash_amount' => 200,
                'cash_collections_amount' => 300,
                'bank_transfer_collections_amount' => 0,
                'cheque_collections_amount' => 0,
                'other_collections_amount' => 0,
                'non_cash_collections_amount' => 0,
                'total_vehicle_expenses_amount' => $expenseAmount,
                'cash_vehicle_expenses_amount' => $expenseAmount,
                'non_cash_vehicle_expenses_amount' => 0,
                'expected_cash_amount' => 400,
                'actual_cash_amount' => 410,
                'cash_difference' => 10,
                'notes' => null,
                'created_by' => null,
                'confirmed_by' => null,
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('daily_closings', 'active_scope_key')) {
                $closing['active_scope_key'] =
                    today()->toDateString().'|1';
            }

            DB::table('daily_closings')->insert([$closing]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        RoutePerformanceReportService::forgetCache();
    }

    private function route(
        int $id,
        string $code,
        string $name,
    ): array {
        return [
            'id' => $id,
            'area_id' => 1,
            'vehicle_id' => null,
            'driver_id' => null,
            'sales_representative_id' => null,
            'code' => $code,
            'name' => $name,
            'visit_days' => null,
            'status' => 'active',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function customer(
        int $id,
        int $routeId,
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
            'area_id' => 1,
            'route_id' => $routeId,
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

    private function payment(
        int $id,
        string $number,
        ?int $routeId,
        float $amount,
    ): array {
        return [
            'id' => $id,
            'payment_number' => $number,
            'customer_id' => 201,
            'sales_invoice_id' => 301,
            'vehicle_id' => null,
            'route_id' => $routeId,
            'warehouse_id' => 1,
            'sales_representative_id' => null,
            'payment_date' => today()->subDay()->toDateString(),
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

    private function settings(): array
    {
        return [
            'from' => today()->subDays(20)->toDateString(),
            'until' => today()->toDateString(),
            'ranking_metric' => 'net_contribution',
            'scope' => 'all',
            'limit' => 'all',
            'status' => 'active',
            'route_id' => null,
            'area_id' => null,
            'vehicle_id' => null,
            'driver_id' => null,
            'sales_representative_id' => null,
            'minimum_net_sales' => 0,
            'minimum_contribution' => null,
            'search' => '',
        ];
    }

    private function encode(array $state): string
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
