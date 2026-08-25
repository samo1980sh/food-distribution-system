<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Rules\AllowedAdminEmployeeType;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Lr4AdminIdentityCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_employee_type_guard_rejects_new_driver_assignment(): void
    {
        $this->assertTrue($this->employeeTypeValidator('driver')->fails());
        $this->assertFalse($this->employeeTypeValidator('sales_representative')->fails());
        $this->assertTrue($this->employeeTypeValidator('driver', 'accountant')->fails());
    }

    public function test_historical_driver_employee_can_be_preserved_or_converted(): void
    {
        $employee = Employee::query()->create([
            'employee_code' => 'LR4-HISTORICAL-DRIVER',
            'name' => 'سائق تاريخي',
            'type' => 'driver',
            'status' => 'active',
        ]);

        $employee->update(['name' => 'سائق تاريخي محدث']);

        $this->assertSame('driver', $employee->refresh()->type);
        $this->assertFalse($this->employeeTypeValidator('driver', 'driver')->fails());
        $this->assertFalse($this->employeeTypeValidator('sales_representative', 'driver')->fails());
    }

    public function test_unrelated_user_edits_preserve_historical_driver_roles(): void
    {
        Role::findOrCreate('driver', 'web');
        Role::findOrCreate('sales_representative', 'web');

        $driver = User::factory()->create();
        $driver->syncRoles(['driver']);
        $dual = User::factory()->create();
        $dual->syncRoles(['driver', 'sales_representative']);

        $driver->update(['name' => 'حساب سائق تاريخي']);
        $dual->update(['name' => 'حساب ميداني تاريخي']);

        $this->assertSame(['driver'], $driver->refresh()->getRoleNames()->sort()->values()->all());
        $this->assertSame(
            ['driver', 'sales_representative'],
            $dual->refresh()->getRoleNames()->sort()->values()->all(),
        );
    }

    public function test_filament_forms_wire_the_server_side_historical_guards(): void
    {
        $userForm = file_get_contents(app_path('Filament/Resources/Users/Schemas/UserForm.php'));
        $employeeForm = file_get_contents(app_path('Filament/Resources/Employees/Schemas/EmployeeForm.php'));

        $this->assertIsString($userForm);
        $this->assertIsString($employeeForm);
        $this->assertStringContainsString('allowHistoricalDriver:', $userForm);
        $this->assertStringContainsString("where('name', '!=', UserRole::DRIVER->value)", $userForm);
        $this->assertStringContainsString('new AllowedAdminEmployeeType($record?->type)', $employeeForm);
        $this->assertStringContainsString("->default('sales_representative')", $employeeForm);
    }

    private function employeeTypeValidator(
        string $type,
        ?string $existingType = null,
    ): ValidatorContract {
        return Validator::make(
            ['type' => $type],
            ['type' => ['required', new AllowedAdminEmployeeType($existingType)]],
        );
    }
}
