<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * A correct password no longer signs anyone in on its own: it only opens the
     * emailed-code step. See LoginOtpTest for the rest of the flow.
     */
    public function test_valid_credentials_alone_do_not_authenticate()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        $this->assertGuest();
    }

    public function test_user_cannot_login_with_invalid_password()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong'
        ]);

        $this->assertGuest();
    }
}
