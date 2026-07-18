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

    private function role(UserRole $role): Role
    {
        return Role::query()->firstOrCreate([
            'name' => $role->value,
            'guard_name' => 'web',
        ]);
    }

    /** @param list<int> $roleIds */
    private function validator(array $roleIds): ValidatorContract
    {
        return Validator::make(
            ['roles' => $roleIds],
            ['roles' => ['required', new AllowedUserRoleCombination]],
        );
    }
}
