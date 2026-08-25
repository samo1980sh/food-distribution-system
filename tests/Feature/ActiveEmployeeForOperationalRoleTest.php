<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Rules\ActiveEmployeeForOperationalRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ActiveEmployeeForOperationalRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_sales_representative_is_operationally_eligible(): void
    {
        $active = Employee::query()->create([
            'employee_code' => 'ACTIVE-REP',
            'name' => 'Active Representative',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $inactive = Employee::query()->create([
            'employee_code' => 'INACTIVE-REP',
            'name' => 'Inactive Representative',
            'type' => 'sales_representative',
            'status' => 'inactive',
        ]);
        $supervisor = Employee::query()->create([
            'employee_code' => 'SUPERVISOR',
            'name' => 'Supervisor',
            'type' => 'supervisor',
            'status' => 'active',
        ]);

        $this->assertTrue($this->validatorPasses($active->id));
        $this->assertFalse($this->validatorPasses($inactive->id));
        $this->assertFalse($this->validatorPasses($supervisor->id));
    }

    private function validatorPasses(int $employeeId): bool
    {
        return Validator::make(
            ['employee_id' => $employeeId],
            ['employee_id' => [
                'required',
                'integer',
                new ActiveEmployeeForOperationalRole(UserRole::SALES_REPRESENTATIVE),
            ]],
        )->passes();
    }
}
