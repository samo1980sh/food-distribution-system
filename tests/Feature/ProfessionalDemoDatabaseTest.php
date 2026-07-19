<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\Authorization\AccessScopeService;
use App\Services\Reports\OverdueCustomerReportService;
use App\Services\Reports\RoutePerformanceReportService;
use App\Support\Api\MobileAppAccess;
use Database\Seeders\ProfessionalDemoDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfessionalDemoDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProfessionalDemoDatabaseSeeder::class);
    }

    public function test_professional_demo_dataset_covers_master_operational_and_reporting_domains(): void
    {
        $this->assertSame(10, User::query()->count());
        $this->assertSame(4, DB::table('areas')->count());
        $this->assertSame(5, DistributionRoute::query()->count());
        $this->assertSame(4, DB::table('vehicles')->count());
        $this->assertSame(7, DB::table('warehouses')->count());
        $this->assertSame(20, Customer::query()->count());
        $this->assertSame(15, Product::query()->count());

        $this->assertGreaterThanOrEqual(9, DB::table('vehicle_loads')->where('status', 'approved')->count());
        $this->assertGreaterThanOrEqual(14, SalesInvoice::query()->where('status', 'confirmed')->count());
        $this->assertGreaterThanOrEqual(8, DB::table('customer_payments')->where('status', 'confirmed')->count());
        $this->assertSame(3, DB::table('sales_returns')->where('status', 'confirmed')->count());
        $this->assertGreaterThanOrEqual(5, DB::table('vehicle_expenses')->where('status', 'approved')->count());
        $this->assertSame(5, DB::table('daily_closings')->where('status', 'confirmed')->count());

        $this->assertTrue(SalesInvoice::query()->where('status', 'draft')->exists());
        $this->assertTrue(SalesInvoice::query()->where('status', 'cancelled')->exists());
        $this->assertTrue(DB::table('vehicle_expenses')->where('status', 'pending')->exists());
        $this->assertTrue(DB::table('vehicle_expenses')->where('status', 'rejected')->exists());
        $this->assertTrue(DB::table('daily_closings')->where('status', 'draft')->exists());
    }

    public function test_demo_accounts_support_admin_sales_driver_and_dual_flutter_scenarios(): void
    {
        $admin = User::query()->where('email', 'admin@demo.local')->firstOrFail();
        $sales = User::query()->where('email', 'sales@demo.local')->firstOrFail();
        $driver = User::query()->where('email', 'driver@demo.local')->firstOrFail();
        $dual = User::query()->where('email', 'field.team@demo.local')->firstOrFail();

        foreach ([$admin, $sales, $driver, $dual] as $user) {
            $this->assertTrue(Hash::check('Demo@2026', $user->password));
            $this->assertTrue($user->isActive());
        }

        $this->assertFalse(MobileAppAccess::allows($admin));
        $this->assertTrue(MobileAppAccess::allows($sales));
        $this->assertTrue(MobileAppAccess::allows($driver));
        $this->assertTrue(MobileAppAccess::allows($dual));

        $this->assertEqualsCanonicalizing(
            ['driver', 'sales_representative'],
            $dual->getRoleNames()->all(),
        );

        $scopeService = app(AccessScopeService::class);
        $this->assertNotEmpty($scopeService->for($sales)->routeIds);
        $this->assertNotEmpty($scopeService->for($driver)->vehicleIds);
        $this->assertNotEmpty($scopeService->for($dual)->warehouseIds);
    }

    public function test_demo_inventory_and_financial_cases_feed_risk_and_performance_reports(): void
    {
        $this->assertTrue(StockBalance::query()->where('quantity', '>', 0)->exists());
        $this->assertTrue(StockBalance::query()->whereNull('expiry_date')->where('quantity', '>', 0)->exists());
        $this->assertTrue(StockBalance::query()->whereDate('expiry_date', '<', today())->where('quantity', '>', 0)->exists());
        $this->assertTrue(StockBalance::query()
            ->whereBetween('expiry_date', [today(), today()->addDays(30)])
            ->where('quantity', '>', 0)
            ->exists());

        $overdue = app(OverdueCustomerReportService::class)->filteredSummaries(
            creditDays: OverdueCustomerReportService::DEFAULT_CREDIT_DAYS,
            asOf: today()->toDateString(),
            criteria: ['scope' => 'overdue'],
        );

        $this->assertNotEmpty($overdue);
        $this->assertGreaterThan(0, (float) $overdue->sum('overdue_amount'));

        RoutePerformanceReportService::forgetCache();
        $rankings = app(RoutePerformanceReportService::class)->rankings([
            'from' => today()->startOfMonth()->toDateString(),
            'until' => today()->toDateString(),
            'status' => 'active',
            'scope' => 'all',
        ]);

        $this->assertNotEmpty($rankings);
        $this->assertTrue($rankings->contains(fn (array $row): bool => $row['has_activity']));
        $this->assertTrue($rankings->contains(fn (array $row): bool => ! $row['has_activity']));
    }

    public function test_demo_reset_check_is_non_destructive(): void
    {
        $before = [
            'users' => User::query()->count(),
            'customers' => Customer::query()->count(),
            'invoices' => SalesInvoice::query()->count(),
        ];

        $exitCode = Artisan::call('demo:reset', ['--check' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame($before['users'], User::query()->count());
        $this->assertSame($before['customers'], Customer::query()->count());
        $this->assertSame($before['invoices'], SalesInvoice::query()->count());
        $this->assertStringContainsString(
            'الفحص ناجح',
            Artisan::output(),
        );
    }
}
