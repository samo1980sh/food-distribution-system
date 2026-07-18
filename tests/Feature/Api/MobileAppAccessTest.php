<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\Api\MobileAppAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAppAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_field_role_combinations_are_allowed(): void
    {
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $representative = User::factory()->create([
            'role' => User::ROLE_SALES_REPRESENTATIVE,
        ]);
        $dual = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $dual->syncRoles([
            User::ROLE_DRIVER,
            User::ROLE_SALES_REPRESENTATIVE,
        ]);

        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $mixed = User::factory()->create(['role' => User::ROLE_DRIVER]);
        $mixed->syncRoles([
            User::ROLE_DRIVER,
            User::ROLE_MANAGER,
        ]);

        $this->assertTrue(MobileAppAccess::allows($driver));
        $this->assertTrue(MobileAppAccess::allows($representative));
        $this->assertTrue(MobileAppAccess::allows($dual));
        $this->assertFalse(MobileAppAccess::allows($manager));
        $this->assertFalse(MobileAppAccess::allows($mixed));
    }
}