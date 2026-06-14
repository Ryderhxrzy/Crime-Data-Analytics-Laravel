<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DataDecryptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_decryption_endpoint_requires_authentication()
    {
        $response = $this->postJson('/api/decrypt');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_decryption()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/decrypt', [
            'data' => 'encrypted_data'
        ]);

        $response->assertStatus(200);
    }

    public function test_decryption_validates_input()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/decrypt', []);

        $response->assertStatus(422);
    }
}
