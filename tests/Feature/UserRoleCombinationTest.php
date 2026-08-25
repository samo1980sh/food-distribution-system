<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Rules\AllowedUserRoleCombination;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleCombinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_role_or_exact_field_role_pair_is_allowed(): void
    {
        $driver = $this->role(UserRole::DRIVER);
        $representative = $this->role(UserRole::SALES_REPRESENTATIVE);
        $manager = $this->role(UserRole::MANAGER);

        $this->assertFalse($this->validator([$driver->id])->fails());
        $this->assertFalse($this->validator([$manager->id])->fails());
        $this->assertFalse($this->validator([
            $driver->id,
            $representative->id,
        ])->fails());
    }

    public function test_admin_field_mixes_unknown_roles_and_more_than_two_roles_are_rejected(): void
    {
        $driver = $this->role(UserRole::DRIVER);
        $representative = $this->role(UserRole::SALES_REPRESENTATIVE);
        $manager = $this->role(UserRole::MANAGER);

        $this->assertTrue($this->validator([$manager->id, $driver->id])->fails());
        $this->assertTrue($this->validator([
            $driver->id,
            $representative->id,
            $manager->id,
        ])->fails());
        $this->assertTrue($this->validator([999999])->fails());
    }

    public function test_admin_assignment_rejects_new_driver_roles_but_allows_current_field_role(): void
    {
        $driver = $this->role(UserRole::DRIVER);
        $representative = $this->role(UserRole::SALES_REPRESENTATIVE);

        $this->assertTrue($this->validator([$driver->id], false)->fails());
        $this->assertTrue($this->validator([
            $driver->id,
            $representative->id,
        ], false)->fails());
        $this->assertFalse($this->validator([$representative->id], false)->fails());
    }

    public function test_admin_assignment_can_preserve_an_existing_historical_driver_role(): void
    {
        $driver = $this->role(UserRole::DRIVER);
        $representative = $this->role(UserRole::SALES_REPRESENTATIVE);

        $this->assertFalse($this->validator([$driver->id], true)->fails());
        $this->assertFalse($this->validator([
            $driver->id,
            $representative->id,
        ], true)->fails());
        $this->assertFalse($this->validator([$representative->id], true)->fails());
    }

    private function role(UserRole $role): Role
    {
        return Role::query()->firstOrCreate([
            'name' => $role->value,
            'guard_name' => 'web',
        ]);
    }

    /** @param list<int> $roleIds */
    private function validator(
        array $roleIds,
        bool $allowHistoricalDriver = true,
    ): ValidatorContract {
        return Validator::make(
            ['roles' => $roleIds],
            ['roles' => [
                'required',
                new AllowedUserRoleCombination($allowHistoricalDriver),
            ]],
        );
    }
}
