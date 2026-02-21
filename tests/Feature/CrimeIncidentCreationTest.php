<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CrimeIncidentCreationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required records for CrimeIncident
        if (!CrimeCategory::exists()) {
            CrimeCategory::factory()->create();
        }
        if (!Barangay::exists()) {
            Barangay::factory()->create();
        }
    }

    /**
     * Test that crime incident creation page loads
     */
    public function test_crime_incident_creation_page_loads()
    {
        // Act
        $response = $this->get('/crime-incident/create');

        // Assert
        $response->assertStatus(200);
        $response->assertViewHas('categories');
        $response->assertViewHas('barangays');
    }

    /**
     * Test that crime incident can be created with valid data
     */
    public function test_crime_incident_can_be_created_with_valid_data()
    {
        // Arrange
        $category = CrimeCategory::first();
        $barangay = Barangay::first();

        $crimeData = [
            'incident_title' => 'Test Crime Incident',
            'incident_description' => 'This is a test crime description',
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now()->format('Y-m-d'),
            'incident_time' => '14:30',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'address_details' => 'Test address in QC',
            'victim_count' => 1,
            'suspect_count' => 1,
            'modus_operandi' => 'Test MO'
        ];

        // Act
        $response = $this->postJson('/api/crimes', $crimeData);

        // Assert
        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Crime incident created successfully'
        ]);

        $this->assertDatabaseHas('crime_department_crime_incidents', [
            'incident_title' => 'Test Crime Incident',
            'incident_description' => 'This is a test crime description',
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
        ]);
    }

    /**
     * Test that crime incident creation fails with missing required fields
     */
    public function test_crime_incident_creation_fails_with_missing_required_fields()
    {
        // Arrange
        $incompleteData = [
            'incident_title' => 'Test Crime',
            // Missing incident_description, category, barangay, etc.
        ];

        // Act
        $response = $this->postJson('/api/crimes', $incompleteData);

        // Assert
        $response->assertStatus(422); // Validation error
        $response->assertJsonValidationErrors(['incident_description', 'crime_category_id', 'barangay_id']);
    }

    /**
     * Test that crime incident must have valid coordinates
     */
    public function test_crime_incident_requires_valid_coordinates()
    {
        // Arrange
        $category = CrimeCategory::first();
        $barangay = Barangay::first();

        $invalidData = [
            'incident_title' => 'Test Crime',
            'incident_description' => 'Test description',
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now()->format('Y-m-d'),
            'incident_time' => '14:30',
            'latitude' => 95, // Invalid: > 90
            'longitude' => 120,
            'address_details' => 'Test address'
        ];

        // Act
        $response = $this->postJson('/api/crimes', $invalidData);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude']);
    }

    /**
     * Test that crime incident is assigned incident code
     */
    public function test_crime_incident_is_assigned_incident_code()
    {
        // Arrange
        $category = CrimeCategory::first();
        $barangay = Barangay::first();

        $crimeData = [
            'incident_title' => 'Test Crime',
            'incident_description' => 'Test description',
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now()->format('Y-m-d'),
            'incident_time' => '14:30',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'address_details' => 'Test address'
        ];

        // Act
        $response = $this->postJson('/api/crimes', $crimeData);

        // Assert
        $response->assertStatus(201);
        $crime = CrimeIncident::where('incident_title', 'Test Crime')->first();
        $this->assertNotNull($crime->incident_code);
        $this->assertStringStartsWith('INC-', $crime->incident_code);
    }

    /**
     * Test that crime incident has default status
     */
    public function test_crime_incident_has_default_status()
    {
        // Arrange
        $category = CrimeCategory::first();
        $barangay = Barangay::first();

        $crimeData = [
            'incident_title' => 'Test Crime',
            'incident_description' => 'Test description',
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now()->format('Y-m-d'),
            'incident_time' => '14:30',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ];

        // Act
        $response = $this->postJson('/api/crimes', $crimeData);

        // Assert
        $response->assertStatus(201);
        $crime = CrimeIncident::where('incident_title', 'Test Crime')->first();
        $this->assertEquals('reported', $crime->status);
    }

    /**
     * Test that multiple crimes can be created
     */
    public function test_multiple_crimes_can_be_created()
    {
        // Arrange
        $category = CrimeCategory::first();
        $barangay = Barangay::first();
        $this->assertDatabaseCount('crime_department_crime_incidents', 0);

        // Act
        for ($i = 1; $i <= 5; $i++) {
            $crimeData = [
                'incident_title' => "Test Crime {$i}",
                'incident_description' => "Test description {$i}",
                'crime_category_id' => $category->id,
                'barangay_id' => $barangay->id,
                'incident_date' => now()->format('Y-m-d'),
                'incident_time' => '14:30',
                'latitude' => 14.5995 + $i * 0.01,
                'longitude' => 120.9842 + $i * 0.01,
            ];

            $this->postJson('/api/crimes', $crimeData);
        }

        // Assert
        $this->assertDatabaseCount('crime_department_crime_incidents', 5);
    }
}
