<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Models\VehicleLoadItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleLoadReadProjectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_receives_approved_load_counts_and_flat_item_projection(): void
    {
        $area = Area::query()->create([
            'code' => 'AREA-READ',
            'name_ar' => 'منطقة القراءة',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'VEH-READ',
            'plate_number' => 'PLATE-READ',
            'name' => 'مركبة القراءة',
            'status' => 'active',
        ]);
        $vehicleWarehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'WH-VEH-READ',
            'name' => 'مستودع المركبة',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'WH-SOURCE-READ',
            'name' => 'المستودع الرئيسي',
            'type' => 'main',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE]);
        $representative = Employee::query()->create([
            'employee_code' => 'REP-READ',
            'name' => 'مندوب القراءة',
            'type' => 'sales_representative',
            'status' => 'active',
            'user_id' => $user->id,
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'sales_representative_id' => $representative->id,
            'code' => 'ROUTE-READ',
            'name' => 'خط القراءة',
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'CAT-READ',
            'name_ar' => 'تصنيف القراءة',
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'UNIT-READ',
            'name_ar' => 'عبوة',
            'symbol' => 'عبوة',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'SKU-READ',
            'name_ar' => 'منتج القراءة',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 5,
            'sale_price' => 10,
            'wholesale_price' => 9,
            'status' => 'active',
        ]);
        $load = VehicleLoad::query()->create([
            'load_number' => 'LOAD-READ',
            'vehicle_id' => $vehicle->id,
            'route_id' => $route->id,
            'sales_representative_id' => $representative->id,
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $vehicleWarehouse->id,
            'load_date' => today(),
            'status' => 'approved',
            'handover_status' => 'discrepancy',
            'total_quantity' => 12.5,
        ]);
        VehicleLoadItem::query()->create([
            'vehicle_load_id' => $load->id,
            'product_id' => $product->id,
            'batch_number' => 'BATCH-READ',
            'expiry_date' => today()->addMonth(),
            'quantity' => 12.5,
            'received_quantity' => 12,
            'unit_cost' => 5,
            'total_cost' => 62.5,
        ]);

        $token = $user->createToken(
            'test',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/operational/vehicle-loads?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $load->id)
            ->assertJsonPath('data.items.0.items_count', 1)
            ->assertJsonPath('data.items.0.different_items_count', 1)
            ->assertJsonPath('data.items.0.total_quantity', '12.500');

        $this->withToken($token)
            ->getJson('/api/v1/operational/vehicle-loads/'.$load->id)
            ->assertOk()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.different_items_count', 1)
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.product_sku', 'SKU-READ')
            ->assertJsonPath('data.items.0.product_name', 'منتج القراءة')
            ->assertJsonPath('data.items.0.unit_label', 'عبوة')
            ->assertJsonPath('data.items.0.quantity', '12.500')
            ->assertJsonPath('data.items.0.received_quantity', '12.000');
    }
}
