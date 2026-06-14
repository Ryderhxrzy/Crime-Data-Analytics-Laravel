<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_page_requires_authentication()
    {
        $response = $this->get('/audit-logs');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_audit_logs()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/audit-logs');

        $response->assertStatus(200);
    }

    public function test_audit_logs_display_user_activities()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/audit-logs');

        $response->assertStatus(200);
        $response->assertViewHas('logs');
    }
}
