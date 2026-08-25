<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\Api\MobileAppAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAppAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_sales_representative_can_use_mobile_application(): void
    {
        $representative = User::factory()->create([
            'role' => User::ROLE_SALES_REPRESENTATIVE,
        ]);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);

        $this->assertTrue(MobileAppAccess::allows($representative));
        $this->assertTrue(MobileAppAccess::allowsLogin($representative));
        $this->assertFalse(MobileAppAccess::allows($manager));
        $this->assertFalse(MobileAppAccess::allowsLogin($manager));
    }
}
