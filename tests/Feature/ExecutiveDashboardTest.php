<?php

namespace Tests\Feature;

use App\Filament\Widgets\DistributionOverviewWidget;
use App\Filament\Widgets\FinancialTrendChartWidget;
use App\Filament\Widgets\OperationalAlertsWidget;
use App\Models\User;
use App\Services\Dashboard\ExecutiveDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ExecutiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ExecutiveDashboardService::forgetCache();
    }

    public function test_summary_calculates_executive_month_metrics(): void
    {
        $this->insertDashboardData();

        $summary = app(ExecutiveDashboardService::class)
            ->summary();

        $this->assertSame(1000.0, $summary['month_sales']);
        $this->assertSame(100.0, $summary['month_returns']);
        $this->assertSame(900.0, $summary['month_net_sales']);
        $this->assertSame(
            500.0,
            $summary['month_total_collections'],
        );
        $this->assertSame(
            450.0,
            $summary['month_approximate_profit'],
        );
        $this->assertSame(100.0, $summary['month_expenses']);
        $this->assertSame(
            350.0,
            $summary['month_net_contribution'],
        );
        $this->assertSame(1, $summary['today_confirmed_closings']);
        $this->assertSame(
            0,
            $summary['today_missing_closing_warehouses'],
        );
    }

    public function test_trend_contains_daily_financial_values(): void
    {
        $this->insertDashboardData();

        $trend = app(ExecutiveDashboardService::class)
            ->trend(days: 14);

        $this->assertCount(14, $trend['labels']);
        $this->assertSame(1000.0, $trend['sales'][13]);
        $this->assertSame(100.0, $trend['returns'][13]);
        $this->assertSame(500.0, $trend['collections'][13]);
        $this->assertSame(100.0, $trend['expenses'][13]);
    }

    public function test_alerts_detect_pending_and_unassigned_documents(): void
    {
        $this->insertDashboardData();

        $pendingExpense = $this->expenseRow(
            id: 602,
            number: 'DASH-EXP-PENDING',
            status: 'pending',
            amount: 75,
        );

        $pendingExpense['route_id'] = null;

        DB::table('vehicle_expenses')->insert([
            $pendingExpense,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($user);

        ExecutiveDashboardService::forgetCache();

        $alerts = collect(
            app(ExecutiveDashboardService::class)
                ->alerts($user)
        );

        $titles = $alerts->pluck('title');

        $this->assertSame(
            1,
            DB::table('vehicle_expenses')
                ->where('status', 'pending')
                ->count(),
        );

        $this->assertContains(
            'مستندات غير مربوطة بخط',
            $titles->all(),
        );

        $this->assertContains(
            'مصاريف سيارات بانتظار الاعتماد',
            $titles->all(),
        );
    }

    public function test_super_admin_can_open_executive_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->insertDashboardData();

        $this->actingAs($user);

        $this
            ->get('/admin')
            ->assertOk()
            ->assertSeeLivewire(
                DistributionOverviewWidget::class,
            )
            ->assertSeeLivewire(
                FinancialTrendChartWidget::class,
            )
            ->assertSeeLivewire(
                OperationalAlertsWidget::class,
            );

        Livewire::test(DistributionOverviewWidget::class)
            ->assertSee('مبيعات اليوم')
            ->assertSee('صافي مبيعات الشهر');

        Livewire::test(FinancialTrendChartWidget::class)
            ->assertSee('الحركة المالية خلال آخر 14 يومًا');

        Livewire::test(OperationalAlertsWidget::class)
            ->assertSee('التنبيهات التشغيلية');
    }

    private function insertDashboardData(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::table('vehicles')->insert([
                [
                    'id' => 1,
                    'code' => 'DASH-VEH-1',
                    'plate_number' => 'DASH-001',
                    'name' => 'سيارة الاختبار',
                    'vehicle_type' => null,
                    'capacity' => 100,
                    'status' => 'active',
                    'current_odometer' => null,
                    'insurance_expiry_date' => null,
                    'license_expiry_date' => null,
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('warehouses')->insert([
                [
                    'id' => 1,
                    'vehicle_id' => null,
                    'code' => 'DASH-WH-1',
                    'name' => 'مستودع لوحة التحكم',
                    'type' => 'main',
                    'address' => null,
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('customers')->insert([
                [
                    'id' => 201,
                    'code' => 'DASH-CUS-1',
                    'name' => 'عميل لوحة التحكم',
                    'owner_name' => null,
                    'phone' => null,
                    'mobile' => null,
                    'customer_type' => 'grocery',
                    'area_id' => null,
                    'route_id' => null,
                    'address' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'credit_limit' => 2000,
                    'payment_type' => 'partial',
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('sales_invoices')->insert([
                [
                    'id' => 301,
                    'invoice_number' => 'DASH-INV-1',
                    'customer_id' => 201,
                    'vehicle_id' => 1,
                    'route_id' => 101,
                    'warehouse_id' => 1,
                    'sales_representative_id' => null,
                    'invoice_date' => today()->toDateString(),
                    'status' => 'confirmed',
                    'payment_type' => 'partial',
                    'subtotal' => 1000,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 1000,
                    'paid_amount' => 500,
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
                    'return_number' => 'DASH-RET-1',
                    'customer_id' => 201,
                    'sales_invoice_id' => 301,
                    'vehicle_id' => 1,
                    'route_id' => 101,
                    'warehouse_id' => 1,
                    'sales_representative_id' => null,
                    'return_date' => today()->toDateString(),
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
                    'id' => 502,
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
                [
                    'id' => 551,
                    'payment_number' => 'DASH-PAY-1',
                    'customer_id' => 201,
                    'sales_invoice_id' => 301,
                    'vehicle_id' => 1,
                    'route_id' => null,
                    'warehouse_id' => 1,
                    'sales_representative_id' => null,
                    'payment_date' => today()->toDateString(),
                    'payment_method' => 'cash',
                    'status' => 'confirmed',
                    'amount' => 300,
                    'reference_number' => null,
                    'notes' => null,
                    'created_by' => null,
                    'confirmed_by' => null,
                    'confirmed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('vehicle_expenses')->insert([
                $this->expenseRow(
                    id: 601,
                    number: 'DASH-EXP-1',
                    status: 'approved',
                    amount: 100,
                ),
            ]);

            $closing = [
                'id' => 701,
                'closing_number' => 'DASH-CLOSE-1',
                'closing_date' => today()->toDateString(),
                'vehicle_id' => 1,
                'route_id' => 101,
                'warehouse_id' => 1,
                'sales_representative_id' => null,
                'status' => 'confirmed',
                'total_loaded_quantity' => 10,
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
                'total_vehicle_expenses_amount' => 100,
                'cash_vehicle_expenses_amount' => 100,
                'non_cash_vehicle_expenses_amount' => 0,
                'expected_cash_amount' => 400,
                'actual_cash_amount' => 400,
                'cash_difference' => 0,
                'notes' => null,
                'created_by' => null,
                'confirmed_by' => null,
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn(
                'daily_closings',
                'active_scope_key',
            )) {
                $closing['active_scope_key'] =
                    today()->toDateString().'|1';
            }

            DB::table('daily_closings')->insert([$closing]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        ExecutiveDashboardService::forgetCache();
    }

    private function expenseRow(
        int $id,
        string $number,
        string $status,
        float $amount,
    ): array {
        return [
            'id' => $id,
            'expense_number' => $number,
            'expense_date' => today()->toDateString(),
            'vehicle_id' => 1,
            'warehouse_id' => 1,
            'route_id' => 101,
            'driver_id' => null,
            'sales_representative_id' => null,
            'expense_type' => 'fuel',
            'amount' => $amount,
            'payment_method' => 'cash',
            'receipt_path' => null,
            'status' => $status,
            'notes' => null,
            'rejection_reason' => null,
            'created_by' => null,
            'approved_by' => null,
            'approved_at' => $status === 'approved'
                ? now()
                : null,
            'rejected_by' => null,
            'rejected_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
