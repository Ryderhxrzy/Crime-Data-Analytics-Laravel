<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IncidentDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_incident_details_returns_correct_data()
    {
        $category = CrimeCategory::factory()->create(['category_name' => 'Theft']);
        $barangay = Barangay::factory()->create(['barangay_name' => 'Barangay 1']);

        $incident = CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_title' => 'Robbery Case',
            'incident_code' => 'INC-2024-001',
            'status' => 'open',
            'clearance_status' => 'uncleared'
        ]);

        $response = $this->getJson("/incident/{$incident->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'incident_title' => 'Robbery Case',
            'category_name' => 'Theft',
            'case_number' => 'INC-2024-001',
            'status' => 'open',
            'clearance_status' => 'uncleared'
        ]);
    }

    public function test_get_incident_details_returns_404_for_nonexistent_incident()
    {
        $response = $this->getJson('/incident/99999');

        $response->assertStatus(404);
        $response->assertJsonFragment(['error' => 'Incident not found']);
    }

    public function test_get_incident_details_handles_missing_category()
    {
        $incident = CrimeIncident::factory()->create([
            'crime_category_id' => null,
            'incident_title' => 'Unknown Crime'
        ]);

        $response = $this->getJson("/incident/{$incident->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['category_name' => 'Unknown']);
    }
}
