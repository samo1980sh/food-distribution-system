<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFieldOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_representative_can_run_today_visits_and_create_customer_and_invoice(): void
    {
        $context = $this->context(2);
        $user = $this->salesUser($context['representative']);
        $token = $this->tokenFor($user);

        $this->withFreshToken($token)->getJson('/api/v1/operational/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.field_workspace.role', User::ROLE_SALES_REPRESENTATIVE)
            ->assertJsonPath('data.field_workspace.unified', true)
            ->assertJsonPath('data.field_workspace.legacy', false)
            ->assertJsonPath('data.modules.sales_journeys', true)
            ->assertJsonPath('data.modules.sales_visits', true)
            ->assertJsonPath('data.write.customers.create', true)
            ->assertJsonPath('data.write.sales_journeys.open_today', true)
            ->assertJsonPath('data.write.sales_journeys.start', true)
            ->assertJsonPath('data.write.sales_journeys.finish', true)
            ->assertJsonPath('data.write.sales_visits.complete', true);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonCount(2, 'data.visits');

        $journeyId = (int) $opened->json('data.id');
        $visitId = (int) $opened->json('data.visits.0.id');
        $customerId = (int) $opened->json('data.visits.0.customer.id');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start", [
                'notes' => 'بدء مسار الزيارات.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-visits/{$visitId}/start", [
                'latitude' => 33.5102,
                'longitude' => 36.2913,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/customers', [
                'client_reference' => 'sales-new-customer-0001',
                'name' => 'عميل ميداني جديد',
                'owner_name' => 'صاحب العميل الجديد',
                'mobile' => '0999000000',
                'route_id' => $context['route']->id,
                'address' => 'ضمن خط المندوب',
                'attach_to_today_journey' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.route.id', $context['route']->id)
            ->assertJsonPath('data.area.id', $context['area']->id)
            ->assertJsonPath('data.status', 'active');

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-invoices', [
                'client_reference' => 'sales-visit-invoice-0001',
                'sales_visit_id' => $visitId,
                'customer_id' => $customerId,
                'vehicle_id' => $context['vehicle']->id,
                'route_id' => $context['route']->id,
                'warehouse_id' => $context['warehouse']->id,
                'sales_representative_id' => $context['representative']->id,
                'invoice_date' => today()->toDateString(),
                'payment_type' => 'cash',
                'items' => [[
                    'product_id' => $context['product']->id,
                    'quantity' => 2,
                    'unit_price' => 10,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.sales_visit_id', $visitId)
            ->assertJsonPath('data.status', 'confirmed');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-visits/{$visitId}/complete", [
                'outcome' => 'invoice_created',
                'notes' => 'تم إنشاء طلب العميل.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.documents.invoices', 1);

        $journey = $this->withFreshToken($token)
            ->getJson("/api/v1/operational/sales-journeys/{$journeyId}")
            ->assertOk()
            ->assertJsonCount(3, 'data.visits');

        foreach ($journey->json('data.visits') as $visit) {
            if ($visit['status'] === 'completed') {
                continue;
            }

            $id = (int) $visit['id'];
            $this->withFreshToken($token)
                ->postJson("/api/v1/operational/sales-visits/{$id}/start")
                ->assertOk();
            $this->withFreshToken($token)
                ->postJson("/api/v1/operational/sales-visits/{$id}/complete", [
                    'outcome' => 'no_order',
                ])
                ->assertOk();
        }

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/finish")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.summary.pending', 0)
            ->assertJsonPath('data.summary.in_progress', 0)
            ->assertJsonPath('data.summary.completed', 3);

    }

    public function test_existing_today_journey_keeps_its_visit_plan_frozen_unless_new_customer_is_explicitly_attached(): void
    {
        $context = $this->context(1);
        $user = $this->salesUser($context['representative']);
        $token = $this->tokenFor($user);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated()
            ->assertJsonCount(1, 'data.visits');

        $journeyId = (int) $opened->json('data.id');

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/customers', [
                'client_reference' => 'sales-new-customer-no-attach-0001',
                'name' => 'عميل جديد بدون ربط بالرحلة',
                'mobile' => '0999111100',
                'route_id' => $context['route']->id,
                'attach_to_today_journey' => false,
            ])
            ->assertCreated();

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertOk()
            ->assertJsonPath('data.id', $journeyId)
            ->assertJsonCount(1, 'data.visits');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start")
            ->assertOk()
            ->assertJsonCount(1, 'data.visits');

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/customers', [
                'client_reference' => 'sales-new-customer-explicit-attach-0001',
                'name' => 'عميل جديد مع ربط صريح بالرحلة',
                'mobile' => '0999111101',
                'route_id' => $context['route']->id,
                'attach_to_today_journey' => true,
            ])
            ->assertCreated();

        $this->withFreshToken($token)
            ->getJson("/api/v1/operational/sales-journeys/{$journeyId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.visits');
    }

    public function test_visit_outcome_requires_the_matching_official_document(): void
    {
        $context = $this->context(1);
        $user = $this->salesUser($context['representative']);
        $token = $this->tokenFor($user);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated();
        $journeyId = (int) $opened->json('data.id');
        $visitId = (int) $opened->json('data.visits.0.id');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start")
            ->assertOk();
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-visits/{$visitId}/start")
            ->assertOk();

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-visits/{$visitId}/complete", [
                'outcome' => 'collection_recorded',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'sales_visit_outcome_document_missing');

        $this->assertDatabaseHas('sales_visits', [
            'id' => $visitId,
            'status' => 'in_progress',
            'outcome' => null,
        ]);
    }

    public function test_journey_cannot_finish_while_visits_are_pending(): void
    {
        $context = $this->context(1);
        $user = $this->salesUser($context['representative']);
        $token = $this->tokenFor($user);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated();
        $journeyId = (int) $opened->json('data.id');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start")
            ->assertOk();

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/finish")
            ->assertConflict()
            ->assertJsonPath('code', 'sales_visits_pending');
    }

    public function test_document_context_must_match_the_active_visit(): void
    {
        $context = $this->context(2);
        $user = $this->salesUser($context['representative']);
        $token = $this->tokenFor($user);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated();
        $journeyId = (int) $opened->json('data.id');
        $visitId = (int) $opened->json('data.visits.0.id');
        $otherCustomerId = (int) $opened->json('data.visits.1.customer.id');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start")
            ->assertOk();
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-visits/{$visitId}/start")
            ->assertOk();

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-invoices', [
                'client_reference' => 'sales-context-mismatch-0001',
                'sales_visit_id' => $visitId,
                'customer_id' => $otherCustomerId,
                'vehicle_id' => $context['vehicle']->id,
                'route_id' => $context['route']->id,
                'warehouse_id' => $context['warehouse']->id,
                'sales_representative_id' => $context['representative']->id,
                'invoice_date' => today()->toDateString(),
                'payment_type' => 'cash',
                'items' => [[
                    'product_id' => $context['product']->id,
                    'quantity' => 1,
                    'unit_price' => 10,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'sales_visit_context_mismatch');
    }

    public function test_document_date_must_match_the_active_visit_journey_date(): void
    {
        $context = $this->context(1);
        $user = $this->salesUser($context['representative']);
        $token = $this->tokenFor($user);

        $opened = $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-journeys/open-today')
            ->assertCreated();
        $journeyId = (int) $opened->json('data.id');
        $visitId = (int) $opened->json('data.visits.0.id');
        $customerId = (int) $opened->json('data.visits.0.customer.id');

        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-journeys/{$journeyId}/start")
            ->assertOk();
        $this->withFreshToken($token)
            ->postJson("/api/v1/operational/sales-visits/{$visitId}/start")
            ->assertOk();

        $this->withFreshToken($token)
            ->postJson('/api/v1/operational/sales-invoices', [
                'client_reference' => 'sales-date-mismatch-0001',
                'sales_visit_id' => $visitId,
                'customer_id' => $customerId,
                'vehicle_id' => $context['vehicle']->id,
                'route_id' => $context['route']->id,
                'warehouse_id' => $context['warehouse']->id,
                'sales_representative_id' => $context['representative']->id,
                'invoice_date' => today()->subDay()->toDateString(),
                'payment_type' => 'cash',
                'items' => [[
                    'product_id' => $context['product']->id,
                    'quantity' => 1,
                    'unit_price' => 10,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'sales_visit_document_date_mismatch');
    }

    /** @return array<string, mixed> */
    private function context(int $customerCount): array
    {
        $area = Area::query()->create(['code' => 'SLS-AREA', 'name_ar' => 'منطقة المبيعات', 'status' => 'active']);
        $vehicle = Vehicle::query()->create(['code' => 'SLS-VEH', 'plate_number' => 'SLS-PLATE', 'status' => 'active']);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'SLS-WH',
            'name' => 'مستودع سيارة المبيعات',
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'SLS-REP', 'name' => 'مندوب المبيعات',
            'type' => User::ROLE_SALES_REPRESENTATIVE, 'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'sales_representative_id' => $representative->id,
            'code' => 'SLS-ROUTE',
            'name' => 'خط المبيعات',
            'visit_days' => [],
            'status' => 'active',
        ]);

        for ($index = 1; $index <= $customerCount; $index++) {
            Customer::query()->create([
                'code' => "SLS-CUS-{$index}",
                'name' => "عميل المبيعات {$index}",
                'area_id' => $area->id,
                'route_id' => $route->id,
                'status' => 'active',
            ]);
        }

        $category = ProductCategory::query()->create(['code' => 'SLS-CAT', 'name_ar' => 'تصنيف المبيعات', 'status' => 'active']);
        $unit = Unit::query()->create(['code' => 'SLS-UNIT', 'name_ar' => 'وحدة', 'symbol' => 'U', 'status' => 'active']);
        $product = Product::query()->create([
            'sku' => 'SLS-SKU', 'name_ar' => 'منتج المبيعات',
            'category_id' => $category->id, 'unit_id' => $unit->id,
            'purchase_price' => 5, 'sale_price' => 10, 'wholesale_price' => 9,
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

    private function salesUser(Employee $representative): User
    {
        $user = User::factory()->create(['role' => User::ROLE_SALES_REPRESENTATIVE, 'status' => 'active']);
        $user->syncRoles([User::ROLE_SALES_REPRESENTATIVE]);
        $representative->update(['user_id' => $user->id]);

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('sales-field-api-test', [(string) config('mobile_api.token_ability')])->plainTextToken;
    }

    private function withFreshToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
