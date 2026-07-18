<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileOperationalReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/operational/bootstrap')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_sales_representative_bootstrap_and_lists_are_scope_limited(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $user = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE]);
        $first['representative']->update(['user_id' => $user->id]);
        $token = $user->createToken('test', [(string) config('mobile_api.token_ability')])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/operational/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.modules.customers', true)
            ->assertJsonCount(1, 'data.context.routes')
            ->assertJsonPath('data.context.routes.0.id', $first['route']->id);

        $this->withToken($token)->getJson('/api/v1/operational/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $first['customer']->id)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->withToken($token)->getJson('/api/v1/operational/sales-invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $first['invoice']->id)
            ->assertJsonPath('data.items.*.id', [$first['invoice']->id]);
    }

    public function test_driver_can_read_assigned_route_vehicle_stock_and_loads_but_not_customers(): void
    {
        $first = $this->context('A');
        $this->context('B');
        $user = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $first['driver']->update(['user_id' => $user->id]);
        $token = $user->createToken('test', [(string) config('mobile_api.token_ability')])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/operational/routes')
            ->assertOk()->assertJsonCount(1, 'data.items');
        $this->withToken($token)->getJson('/api/v1/operational/vehicles')
            ->assertOk()->assertJsonCount(1, 'data.items');
        $this->withToken($token)->getJson('/api/v1/operational/stock-balances')
            ->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.average_unit_cost', null);
        $this->withToken($token)->getJson('/api/v1/operational/vehicle-loads')
            ->assertOk()->assertJsonCount(1, 'data.items');
        $this->withToken($token)->getJson('/api/v1/operational/customers')
            ->assertForbidden()->assertJsonPath('code', 'http_403');
    }

    public function test_out_of_scope_detail_is_hidden_as_not_found(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $user = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE]);
        $first['representative']->update(['user_id' => $user->id]);
        $token = $user->createToken('test', [(string) config('mobile_api.token_ability')])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/operational/sales-invoices/'.$second['invoice']->id)
            ->assertNotFound()
            ->assertJsonPath('code', 'http_404');
    }

    public function test_operational_lists_validate_filters_and_return_pagination_metadata(): void
    {
        $first = $this->context('A');
        $user = User::factory()->create([
            'role' => User::ROLE_SALES_REPRESENTATIVE,
        ]);
        $first['representative']->update(['user_id' => $user->id]);
        $token = $user->createToken(
            'test',
            [(string) config('mobile_api.token_ability')],
        )->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/operational/customers?search=CUS-A&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $first['customer']->id)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->withToken($token)
            ->getJson('/api/v1/operational/customers?per_page=101')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed');
    }

    /** @return array<string, mixed> */
    private function context(string $suffix): array
    {
        $area = Area::query()->create(['code'=>'AREA-'.$suffix,'name_ar'=>'منطقة '.$suffix,'status'=>'active']);
        $vehicle = Vehicle::query()->create(['code'=>'VEH-'.$suffix,'plate_number'=>'PLATE-'.$suffix,'status'=>'active']);
        $warehouse = Warehouse::query()->create(['vehicle_id'=>$vehicle->id,'code'=>'WH-'.$suffix,'name'=>'مستودع '.$suffix,'type'=>'vehicle','status'=>'active']);
        $driver = Employee::query()->create(['employee_code'=>'DRV-'.$suffix,'name'=>'سائق '.$suffix,'type'=>'driver','status'=>'active']);
        $representative = Employee::query()->create(['employee_code'=>'REP-'.$suffix,'name'=>'مندوب '.$suffix,'type'=>'sales_representative','status'=>'active']);
        $route = DistributionRoute::query()->create(['area_id'=>$area->id,'vehicle_id'=>$vehicle->id,'driver_id'=>$driver->id,'sales_representative_id'=>$representative->id,'code'=>'ROUTE-'.$suffix,'name'=>'خط '.$suffix,'status'=>'active']);
        $customer = Customer::query()->create(['code'=>'CUS-'.$suffix,'name'=>'عميل '.$suffix,'area_id'=>$area->id,'route_id'=>$route->id,'status'=>'active']);
        $category = ProductCategory::query()->create(['code'=>'CAT-'.$suffix,'name_ar'=>'تصنيف '.$suffix,'status'=>'active']);
        $unit = Unit::query()->create(['code'=>'UNIT-'.$suffix,'name_ar'=>'وحدة '.$suffix,'symbol'=>'U','status'=>'active']);
        $product = Product::query()->create(['sku'=>'SKU-'.$suffix,'name_ar'=>'منتج '.$suffix,'category_id'=>$category->id,'unit_id'=>$unit->id,'purchase_price'=>5,'sale_price'=>10,'wholesale_price'=>9,'status'=>'active']);
        $stock = StockBalance::query()->create(['warehouse_id'=>$warehouse->id,'product_id'=>$product->id,'quantity'=>20,'average_unit_cost'=>5]);
        $invoice = SalesInvoice::query()->create(['invoice_number'=>'INV-'.$suffix,'customer_id'=>$customer->id,'vehicle_id'=>$vehicle->id,'route_id'=>$route->id,'warehouse_id'=>$warehouse->id,'sales_representative_id'=>$representative->id,'invoice_date'=>today(),'status'=>'draft','payment_type'=>'cash','total_amount'=>10]);
        $load = VehicleLoad::query()->create(['load_number'=>'LOAD-'.$suffix,'vehicle_id'=>$vehicle->id,'route_id'=>$route->id,'driver_id'=>$driver->id,'sales_representative_id'=>$representative->id,'from_warehouse_id'=>$warehouse->id,'to_warehouse_id'=>$warehouse->id,'load_date'=>today(),'status'=>'draft']);
        return compact('area','vehicle','warehouse','driver','representative','route','customer','product','stock','invoice','load');
    }
}
