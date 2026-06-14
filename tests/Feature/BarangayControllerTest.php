<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BarangayControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_barangay_list_requires_authentication()
    {
        $response = $this->get('/api/barangays');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_barangay_list()
    {
        $user = User::factory()->create();

        Barangay::factory()->count(5)->create();

        $response = $this->actingAs($user)->getJson('/api/barangays');

        $response->assertStatus(200);
        $response->assertJsonCount(5);
    }

    public function test_barangay_contains_required_fields()
    {
        $user = User::factory()->create();

        $barangay = Barangay::factory()->create([
            'barangay_name' => 'Barangay Test',
            'city' => 'Manila'
        ]);

        $response = $this->actingAs($user)->getJson("/api/barangays/{$barangay->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'barangay_name' => 'Barangay Test'
        ]);
    }
}
