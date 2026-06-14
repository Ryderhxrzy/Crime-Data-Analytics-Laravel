<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AlertsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_page_requires_authentication()
    {
        $response = $this->get('/alerts');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_alerts()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertStatus(200);
    }

    public function test_alerts_display_correctly()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertStatus(200);
        $response->assertViewHas('alerts');
    }
}
