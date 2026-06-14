<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MobileUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_user_api_requires_authentication()
    {
        $response = $this->getJson('/api/mobile/user');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_profile()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/mobile/user/profile');

        $response->assertStatus(200);
    }

    public function test_user_profile_contains_required_fields()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $response = $this->actingAs($user)->getJson('/api/mobile/user/profile');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
    }
}
