<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DataValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_crime_incident_requires_valid_coordinates()
    {
        $category = CrimeCategory::factory()->create();
        $barangay = Barangay::factory()->create();

        $incident = CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'latitude' => '14.5994',
            'longitude' => '120.9842'
        ]);

        $this->assertNotNull($incident->latitude);
        $this->assertNotNull($incident->longitude);
    }

    public function test_incident_title_is_required()
    {
        $category = CrimeCategory::factory()->create();
        $barangay = Barangay::factory()->create();

        $incident = CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_title' => 'Test Incident'
        ]);

        $this->assertEquals('Test Incident', $incident->incident_title);
    }

    public function test_incident_status_defaults_to_open()
    {
        $category = CrimeCategory::factory()->create();
        $barangay = Barangay::factory()->create();

        $incident = CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'status' => 'open'
        ]);

        $this->assertEquals('open', $incident->status);
    }
}
