<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_requires_authentication()
    {
        $response = $this->get('/reports');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_reports()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports');

        $response->assertStatus(200);
    }

    public function test_reports_display_incident_statistics()
    {
        $user = User::factory()->create();
        $category = CrimeCategory::factory()->create();
        $barangay = Barangay::factory()->create();

        CrimeIncident::factory()->count(10)->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id
        ]);

        $response = $this->actingAs($user)->get('/reports');

        $response->assertStatus(200);
    }
}
