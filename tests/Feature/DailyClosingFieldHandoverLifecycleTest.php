<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
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
        $driver = $this->userForEmployee(User::ROLE_DRIVER, $context['driver']);
        $sales = $this->userForEmployee(User::ROLE_SALES_REPRESENTATIVE, $context['representative']);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $handover = app(DailyClosingFieldHandoverService::class);

        $this->actingAs($driver);
        $closing = $handover->openToday($driver, $context['route']->id);
        $closing = $handover->submitInventory($closing, $driver, [
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

    public function test_field_closing_rejects_admin_edit_but_allows_admin_review_and_confirmation(): void
    {
        $context = $this->context();
        $driver = $this->userForEmployee(User::ROLE_DRIVER, $context['driver']);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);

        $this->actingAs($driver);
        $closing = app(DailyClosingFieldHandoverService::class)->openToday(
            $driver,
            $context['route']->id,
        );

        $this->assertTrue($driver->can('submitInventory', $closing));
        $this->assertFalse($driver->can('submitCash', $closing));

        $this->actingAs($manager);
        $closing = $closing->fresh();

        $this->assertTrue($manager->can('view', $closing));
        $this->assertFalse($manager->can('update', $closing));
        $this->assertFalse($manager->can('confirm', $closing));
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
        $driver = Employee::query()->create([
            'employee_code' => 'LIFE-DRV',
            'name' => 'سائق دورة الإغلاق',
            'type' => 'driver',
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
            'driver_id' => $driver->id,
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

        return compact('area', 'vehicle', 'warehouse', 'driver', 'representative', 'route', 'product');
    }

    private function userForEmployee(string $role, Employee $employee): User
    {
        $user = User::factory()->create(['role' => $role]);
        $employee->update(['user_id' => $user->id]);

        return $user;
    }
}
