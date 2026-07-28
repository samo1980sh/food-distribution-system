<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\StockMovement;
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

class VehicleLoadHandoverApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_acknowledges_matching_load_without_new_inventory_movement(): void
    {
        $context = $this->handoverContext('MATCH');
        $token = $this->tokenFor($context['user']);
        $movementCount = StockMovement::query()->count();

        $readResponse = $this->withToken($token)
            ->getJson('/api/v1/operational/vehicle-loads/'.$context['load']->id)
            ->assertOk();

        $baseVersion = (string) $readResponse->json('data.sync_version');
        $this->assertMatchesRegularExpression('/^c:\\d+$/', $baseVersion);

        $writeResponse = $this->withToken($token)
            ->postJson(
                '/api/v1/operational/vehicle-loads/'.$context['load']->id.'/acknowledge',
                [
                    'handover_status' => 'received',
                    'notes' => null,
                    'items' => [[
                        'id' => $context['item']->id,
                        'received_quantity' => '12.500',
                        'note' => null,
                    ]],
                ],
            )
            ->assertOk()
            ->assertJsonPath('data.handover_status', 'received')
            ->assertJsonPath('data.different_items_count', 0)
            ->assertJsonPath('data.items.0.received_quantity', '12.500')
            ->assertJsonPath('data.actions.can_acknowledge', false);

        $updatedVersion = (string) $writeResponse->json('data.sync_version');
        $this->assertMatchesRegularExpression('/^c:\\d+$/', $updatedVersion);
        $this->assertNotSame($baseVersion, $updatedVersion);

        $this->assertDatabaseHas('vehicle_loads', [
            'id' => $context['load']->id,
            'handover_status' => 'received',
            'handover_by' => $context['user']->id,
        ]);
        $this->assertDatabaseHas('vehicle_load_items', [
            'id' => $context['item']->id,
            'received_quantity' => 12.500,
            'handover_note' => null,
        ]);
        $this->assertSame($movementCount, StockMovement::query()->count());
    }

    public function test_quantity_difference_requires_item_and_general_notes(): void
    {
        $context = $this->handoverContext('DIFF');
        $token = $this->tokenFor($context['user']);
        $url = '/api/v1/operational/vehicle-loads/'.$context['load']->id.'/acknowledge';

        $this->withToken($token)
            ->postJson($url, [
                'handover_status' => 'discrepancy',
                'notes' => '',
                'items' => [[
                    'id' => $context['item']->id,
                    'received_quantity' => '11.500',
                    'note' => '',
                ]],
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'business_rule_violation');

        $this->assertDatabaseHas('vehicle_loads', [
            'id' => $context['load']->id,
            'handover_status' => 'pending',
        ]);
        $this->assertNull($context['item']->refresh()->received_quantity);

        $this->withToken($token)
            ->postJson($url, [
                'handover_status' => 'discrepancy',
                'notes' => 'تم توثيق نقص عبوة عند التسليم.',
                'items' => [[
                    'id' => $context['item']->id,
                    'received_quantity' => '11.500',
                    'note' => 'نقص: عبوة غير موجودة عند التسليم.',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.handover_status', 'discrepancy')
            ->assertJsonPath('data.different_items_count', 1)
            ->assertJsonPath('data.items.0.handover_note', 'نقص: عبوة غير موجودة عند التسليم.');
    }

    public function test_documented_damage_with_matching_quantity_is_a_discrepancy(): void
    {
        $context = $this->handoverContext('DAMAGE');
        $token = $this->tokenFor($context['user']);

        $this->withToken($token)
            ->postJson(
                '/api/v1/operational/vehicle-loads/'.$context['load']->id.'/acknowledge',
                [
                    'handover_status' => 'discrepancy',
                    'notes' => 'تم استلام كامل الكمية مع وجود عبوة تالفة.',
                    'items' => [[
                        'id' => $context['item']->id,
                        'received_quantity' => '12.500',
                        'note' => 'تالف: العبوة متضررة وتحتاج إلى مراجعة.',
                    ]],
                ],
            )
            ->assertOk()
            ->assertJsonPath('data.handover_status', 'discrepancy')
            ->assertJsonPath('data.different_items_count', 1)
            ->assertJsonPath(
                'data.items.0.handover_note',
                'تالف: العبوة متضررة وتحتاج إلى مراجعة.',
            );
    }

    public function test_acknowledgement_is_hidden_after_the_handover_is_recorded(): void
    {
        $context = $this->handoverContext('LOCKED');
        $context['load']->forceFill([
            'handover_status' => 'received',
            'handover_by' => $context['user']->id,
            'handover_at' => now(),
        ])->save();

        $this->withToken($this->tokenFor($context['user']))
            ->postJson(
                '/api/v1/operational/vehicle-loads/'.$context['load']->id.'/acknowledge',
                [
                    'handover_status' => 'received',
                    'items' => [[
                        'id' => $context['item']->id,
                        'received_quantity' => '12.500',
                    ]],
                ],
            )
            ->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function handoverContext(string $suffix): array
    {
        $area = Area::query()->create([
            'code' => 'HAND-AREA-'.$suffix,
            'name_ar' => 'منطقة الاستلام '.$suffix,
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'HAND-VEH-'.$suffix,
            'plate_number' => 'HAND-PLATE-'.$suffix,
            'name' => 'مركبة الاستلام '.$suffix,
            'status' => 'active',
        ]);
        $vehicleWarehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'HAND-VWH-'.$suffix,
            'name' => 'مستودع المركبة '.$suffix,
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'HAND-SWH-'.$suffix,
            'name' => 'المستودع الرئيسي '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $driver = Employee::query()->create([
            'employee_code' => 'HAND-DRV-'.$suffix,
            'name' => 'سائق الاستلام '.$suffix,
            'type' => 'driver',
            'status' => 'active',
            'user_id' => $user->id,
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'HAND-REP-'.$suffix,
            'name' => 'مندوب الاستلام '.$suffix,
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'HAND-ROUTE-'.$suffix,
            'name' => 'خط الاستلام '.$suffix,
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'code' => 'HAND-CAT-'.$suffix,
            'name_ar' => 'تصنيف الاستلام '.$suffix,
            'status' => 'active',
        ]);
        $unit = Unit::query()->create([
            'code' => 'HAND-UNIT-'.$suffix,
            'name_ar' => 'عبوة',
            'symbol' => 'عبوة',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'sku' => 'HAND-SKU-'.$suffix,
            'name_ar' => 'منتج الاستلام '.$suffix,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 5,
            'sale_price' => 10,
            'wholesale_price' => 9,
            'status' => 'active',
        ]);
        $load = VehicleLoad::query()->create([
            'load_number' => 'HAND-LOAD-'.$suffix,
            'vehicle_id' => $vehicle->id,
            'route_id' => $route->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $vehicleWarehouse->id,
            'load_date' => today(),
            'status' => 'approved',
            'handover_status' => 'pending',
            'total_quantity' => 12.5,
        ]);
        $item = VehicleLoadItem::query()->create([
            'vehicle_load_id' => $load->id,
            'product_id' => $product->id,
            'batch_number' => 'HAND-BATCH-'.$suffix,
            'expiry_date' => today()->addMonth(),
            'quantity' => 12.5,
            'unit_cost' => 5,
            'total_cost' => 62.5,
        ]);

        return compact(
            'area',
            'vehicle',
            'vehicleWarehouse',
            'sourceWarehouse',
            'user',
            'driver',
            'representative',
            'route',
            'product',
            'load',
            'item',
        );
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken(
            'phase3c-test',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;
    }
}
