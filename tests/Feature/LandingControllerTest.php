<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeIncident;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LandingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_displays_total_incidents()
    {
        CrimeIncident::factory()->count(5)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('totalIncidents', 5);
    }

    public function test_landing_page_displays_recent_incidents_within_30_days()
    {
        CrimeIncident::factory()->count(3)->create(['incident_date' => now()]);
        CrimeIncident::factory()->count(2)->create(['incident_date' => now()->subDays(60)]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('recentIncidents', 3);
    }

    public function test_landing_page_returns_view()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('landing.index');
    }
}
