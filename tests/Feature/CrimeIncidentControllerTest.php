<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CrimeIncidentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_crime_incident_list_requires_auth()
    {
        $response = $this->get('/incidents');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_incidents_list()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/incidents');

        $response->assertStatus(200);
    }

    public function test_incident_list_displays_all_incidents()
    {
        $user = User::factory()->create();
        $category = CrimeCategory::factory()->create();
        $barangay = Barangay::factory()->create();

        CrimeIncident::factory()->count(5)->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id
        ]);

        $response = $this->actingAs($user)->get('/incidents');

        $response->assertStatus(200);
    }
}
