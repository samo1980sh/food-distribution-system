<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MasterDataDeletionGuard;
use App\Support\Filament\MasterDataBulkDeleteAction;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\BulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_delete_and_bulk_ui_are_super_admin_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $superAdmin->syncRoles(UserRole::SUPER_ADMIN->value);

        $manager = User::factory()->create(['role' => UserRole::MANAGER->value]);
        $manager->syncRoles(UserRole::MANAGER->value);

        $area = Area::query()->create([
            'code' => 'BULK-AUTH',
            'name_ar' => 'منطقة اختبار الحذف الجماعي',
            'status' => 'active',
        ]);

        $this->assertTrue($superAdmin->can('delete', $area));
        $this->assertTrue($superAdmin->can('deleteAny', Area::class));
        $this->assertFalse($manager->can('delete', $area));
        $this->assertFalse($manager->can('deleteAny', Area::class));

        $this->actingAs($superAdmin);
        $superAdminActions = MasterDataBulkDeleteAction::actionsFor(Area::class);
        $this->assertCount(1, $superAdminActions);
        $this->assertInstanceOf(BulkAction::class, $superAdminActions[0]);
        $this->assertSame('master_data_bulk_delete', $superAdminActions[0]->getName());
        $this->assertSame('حذف المحدد', $superAdminActions[0]->getLabel());

        $this->actingAs($manager);
        $this->assertSame([], MasterDataBulkDeleteAction::actionsFor(Area::class));
    }

    public function test_guard_allows_clean_master_data_and_blocks_master_dependencies(): void
    {
        $guard = app(MasterDataDeletionGuard::class);

        $unit = Unit::query()->create([
            'code' => 'BULK-UNIT',
            'name_ar' => 'وحدة اختبار',
            'symbol' => 'T',
            'status' => 'active',
        ]);

        $this->assertNull($guard->reason($unit));

        Product::query()->create([
            'sku' => 'BULK-SKU-1',
            'name_ar' => 'منتج مرتبط بوحدة الاختبار',
            'unit_id' => $unit->id,
            'purchase_price' => 5,
            'sale_price' => 10,
            'status' => 'active',
        ]);

        $reason = $guard->reason($unit);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('منتجات مرتبطة', $reason);
    }

    public function test_guard_blocks_employee_that_is_still_linked_to_a_user_account(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $guard = app(MasterDataDeletionGuard::class);
        $user = User::factory()->create(['role' => UserRole::SALES_REPRESENTATIVE->value]);
        $user->syncRoles(UserRole::SALES_REPRESENTATIVE->value);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_code' => 'BULK-EMP',
            'name' => 'موظف مرتبط بحساب',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);

        $reason = $guard->reason($employee);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('مرتبط بحساب مستخدم', $reason);
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }

    public function test_guard_reports_operational_history_before_database_restriction(): void
    {
        $guard = app(MasterDataDeletionGuard::class);

        $customer = Customer::query()->create([
            'code' => 'BULK-CUS',
            'name' => 'عميل اختبار الحماية',
            'payment_type' => 'credit',
            'status' => 'active',
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'BULK-WH',
            'name' => 'مستودع اختبار الحماية',
            'type' => 'main',
            'status' => 'active',
        ]);

        SalesInvoice::query()->create([
            'invoice_number' => 'BULK-INV',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => today(),
            'status' => 'draft',
            'payment_type' => 'credit',
            'subtotal' => 10,
            'total_amount' => 10,
            'remaining_amount' => 10,
        ]);

        $reason = $guard->reason($customer);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('فواتير مبيعات تاريخية', $reason);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }
}
