<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesJourney;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\Distribution\DailyClosingFieldHandoverService;
use App\Services\Distribution\DailyClosingGuard;
use App\Services\Distribution\DailyClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DailyClosingFieldHandoverLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_handover_remains_draft_until_existing_admin_confirmation(): void
    {
        $context = $this->context();
        $sales = $this->userForEmployee(User::ROLE_SALES_REPRESENTATIVE, $context['representative']);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $handover = app(DailyClosingFieldHandoverService::class);
        $this->completedJourney($context, $sales);

        $this->actingAs($sales);
        $closing = $handover->openToday($sales, $context['route']->id);
        $closing = $handover->submitInventory($closing, $sales, [
            'items' => [[
                'product_id' => $context['product']->id,
                'actual_quantity' => 20,
            ]],
        ]);

        $this->assertTrue($closing->inventorySubmitted());
        $this->assertFalse($closing->cashSubmitted());
        $this->assertSame('draft', $closing->status);

        app(DailyClosingGuard::class)->ensureOpen(
            today()->toDateString(),
            $context['warehouse']->id,
        );

        $this->actingAs($manager);

        try {
            app(DailyClosingService::class)->confirm($closing);
            $this->fail('Field closing must not be confirmed before both sections are submitted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('تسليم جرد السيارة والنقد', $exception->getMessage());
        }

        $this->actingAs($sales);
        $closing = $handover->submitCash($closing->fresh(), $sales, [
            'actual_cash_amount' => 0,
        ]);

        $this->assertTrue($closing->fieldHandoverComplete());
        $this->assertSame('draft', $closing->status);

        app(DailyClosingGuard::class)->ensureOpen(
            today()->toDateString(),
            $context['warehouse']->id,
        );

        $this->actingAs($manager);
        $closing = app(DailyClosingService::class)->confirm($closing);

        $this->assertSame('confirmed', $closing->status);
        $this->assertNotNull($closing->confirmed_at);

        $this->expectException(RuntimeException::class);
        app(DailyClosingGuard::class)->ensureOpen(
            today()->toDateString(),
            $context['warehouse']->id,
        );
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        $area = Area::query()->create([
            'code' => 'LIFE-AREA',
            'name_ar' => 'منطقة دورة الإغلاق',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'LIFE-VEH',
            'plate_number' => 'LIFE-PLATE',
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'LIFE-WH',
            'name' => 'مستودع دورة الإغلاق',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'LIFE-REP',
            'name' => 'مندوب دورة الإغلاق',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'sales_representative_id' => $representative->id,
            'code' => 'LIFE-ROUTE',
            'name' => 'خط دورة الإغلاق',
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'LIFE-CAT',
            'name_ar' => 'تصنيف دورة الإغلاق',
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'LIFE-UNIT',
            'name_ar' => 'وحدة دورة الإغلاق',
            'symbol' => 'U',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'LIFE-SKU',
            'name_ar' => 'منتج دورة الإغلاق',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 5,
            'sale_price' => 10,
            'wholesale_price' => 9,
            'status' => 'active',
        ]);
        StockBalance::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'average_unit_cost' => 5,
        ]);

        return compact('area', 'vehicle', 'warehouse', 'representative', 'route', 'product');
    }

    private function userForEmployee(string $role, Employee $employee): User
    {
        $user = User::factory()->create(['role' => $role]);
        $employee->update(['user_id' => $user->id]);

        return $user;
    }

    /** @param array<string, mixed> $context */
    private function completedJourney(array $context, User $user): SalesJourney
    {
        return SalesJourney::query()->create([
            'journey_number' => 'FIELD-CLOSE-LIFECYCLE-JOURNEY',
            'journey_date' => today(),
            'route_id' => $context['route']->id,
            'vehicle_id' => $context['vehicle']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'created_by' => $user->id,
            'operation_source' => 'mobile_sales',
        ]);
    }
}
