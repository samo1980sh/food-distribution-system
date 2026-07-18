<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use App\Rules\ActiveEmployeeForOperationalRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ActiveEmployeeForOperationalRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_roles_extend_employee_operational_eligibility(): void
    {
        $driverUser = User::factory()->create([
            'role' => User::ROLE_DRIVER,
        ]);
        $driverUser->assignRole(User::ROLE_SALES_REPRESENTATIVE);

        $driverEmployee = Employee::query()->create([
            'user_id' => $driverUser->id,
            'employee_code' => 'DUAL-DRV',
            'name' => 'Dual Driver',
            'type' => 'driver',
            'status' => 'active',
        ]);

        $representativeUser = User::factory()->create([
            'role' => User::ROLE_SALES_REPRESENTATIVE,
        ]);
        $representativeUser->assignRole(User::ROLE_DRIVER);

        $representativeEmployee = Employee::query()->create([
            'user_id' => $representativeUser->id,
            'employee_code' => 'DUAL-REP',
            'name' => 'Dual Representative',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);

        $this->assertContains(
            $driverEmployee->id,
            Employee::query()
                ->where('status', 'active')
                ->forOperationalRole(UserRole::SALES_REPRESENTATIVE)
                ->pluck('id')
                ->all(),
        );
        $this->assertContains(
            $representativeEmployee->id,
            Employee::query()
                ->where('status', 'active')
                ->forOperationalRole(UserRole::DRIVER)
                ->pluck('id')
                ->all(),
        );

        $this->assertTrue($this->validatorPasses(
            $driverEmployee->id,
            UserRole::SALES_REPRESENTATIVE,
        ));
        $this->assertTrue($this->validatorPasses(
            $representativeEmployee->id,
            UserRole::DRIVER,
        ));
    }

    public function test_validation_rejects_inactive_or_unqualified_employee(): void
    {
        $ordinaryDriver = Employee::query()->create([
            'employee_code' => 'ONLY-DRV',
            'name' => 'Only Driver',
            'type' => 'driver',
            'status' => 'active',
        ]);

        $inactiveRepresentative = Employee::query()->create([
            'employee_code' => 'INACTIVE-REP',
            'name' => 'Inactive Representative',
            'type' => 'sales_representative',
            'status' => 'inactive',
        ]);

        $this->assertFalse($this->validatorPasses(
            $ordinaryDriver->id,
            UserRole::SALES_REPRESENTATIVE,
        ));
        $this->assertFalse($this->validatorPasses(
            $inactiveRepresentative->id,
            UserRole::SALES_REPRESENTATIVE,
        ));
    }

    private function validatorPasses(int $employeeId, UserRole $role): bool
    {
        return Validator::make(
            ['employee_id' => $employeeId],
            [
                'employee_id' => [
                    'required',
                    'integer',
                    new ActiveEmployeeForOperationalRole($role),
                ],
            ],
        )->passes();
    }
}
