<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Rules\AllowedUserRoleCombination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleCombinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_exactly_one_known_role_is_allowed(): void
    {
        $representative = $this->role(UserRole::SALES_REPRESENTATIVE);
        $manager = $this->role(UserRole::MANAGER);

        $this->assertTrue($this->passes([$representative->id]));
        $this->assertTrue($this->passes([$manager->id]));
    }

    public function test_multiple_or_unknown_roles_are_rejected(): void
    {
        $representative = $this->role(UserRole::SALES_REPRESENTATIVE);
        $manager = $this->role(UserRole::MANAGER);

        $this->assertFalse($this->passes([$representative->id, $manager->id]));
        $this->assertFalse($this->passes([999999]));
    }

    private function role(UserRole $role): Role
    {
        return Role::query()->firstOrCreate([
            'name' => $role->value,
            'guard_name' => 'web',
        ]);
    }

    /** @param list<int> $roleIds */
    private function passes(array $roleIds): bool
    {
        return Validator::make(
            ['roles' => $roleIds],
            ['roles' => ['required', new AllowedUserRoleCombination]],
        )->passes();
    }
}
