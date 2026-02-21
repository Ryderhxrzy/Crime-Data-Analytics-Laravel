<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CrimeIncidentUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!CrimeCategory::exists()) {
            CrimeCategory::factory()->create();
        }
        if (!Barangay::exists()) {
            Barangay::factory()->create();
        }
    }

    /**
     * Test that crime incident can be updated with valid data
     */
    public function test_crime_incident_can_be_updated_with_valid_data()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create([
            'incident_title' => 'Original Title'
        ]);
        $category = CrimeCategory::first();
        $barangay = Barangay::first();

        $updateData = [
            'incident_title' => 'Updated Title',
            'incident_description' => 'Updated description',
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now()->format('Y-m-d'),
            'incident_time' => '15:30',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'address_details' => 'Updated address'
        ];

        // Act
        $response = $this->putJson("/api/crimes/{$crime->id}", $updateData);

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Crime incident updated successfully'
        ]);

        $this->assertDatabaseHas('crime_department_crime_incidents', [
            'id' => $crime->id,
            'incident_title' => 'Updated Title'
        ]);
    }

    /**
     * Test that crime incident update fails with invalid coordinates
     */
    public function test_crime_incident_update_fails_with_invalid_coordinates()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();
        $category = CrimeCategory::first();
        $barangay = Barangay::first();

        $invalidData = [
            'incident_title' => 'Test Crime',
            'incident_description' => 'Test description',
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now()->format('Y-m-d'),
            'incident_time' => '15:30',
            'latitude' => 100, // Invalid
            'longitude' => 120,
        ];

        // Act
        $response = $this->putJson("/api/crimes/{$crime->id}", $invalidData);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude']);
    }

    /**
     * Test that updating nonexistent crime returns 404
     */
    public function test_updating_nonexistent_crime_returns_404()
    {
        // Arrange
        $category = CrimeCategory::first();
        $barangay = Barangay::first();

        $updateData = [
            'incident_title' => 'Test Crime',
            'incident_description' => 'Test description',
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now()->format('Y-m-d'),
            'incident_time' => '15:30',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ];

        // Act
        $response = $this->putJson('/api/crimes/99999', $updateData);

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test that crime status can be updated
     */
    public function test_crime_status_can_be_updated()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create(['status' => 'reported']);

        $updateData = [
            'status' => 'under_investigation'
        ];

        // Act
        $response = $this->patchJson("/api/crimes/{$crime->id}/status", $updateData);

        // Assert
        $response->assertStatus(200);
        $this->assertDatabaseHas('crime_department_crime_incidents', [
            'id' => $crime->id,
            'status' => 'under_investigation'
        ]);
    }

    /**
     * Test that crime clearance status can be updated
     */
    public function test_crime_clearance_status_can_be_updated()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create(['clearance_status' => 'uncleared']);

        $updateData = [
            'clearance_status' => 'cleared'
        ];

        // Act
        $response = $this->patchJson("/api/crimes/{$crime->id}/clearance", $updateData);

        // Assert
        $response->assertStatus(200);
        $this->assertDatabaseHas('crime_department_crime_incidents', [
            'id' => $crime->id,
            'clearance_status' => 'cleared'
        ]);
    }

    /**
     * Test that multiple crimes can be updated
     */
    public function test_multiple_crime_status_updates()
    {
        // Arrange
        $crime1 = CrimeIncident::factory()->create(['status' => 'reported']);
        $crime2 = CrimeIncident::factory()->create(['status' => 'reported']);

        // Act
        $this->patchJson("/api/crimes/{$crime1->id}/status", ['status' => 'solved']);
        $this->patchJson("/api/crimes/{$crime2->id}/status", ['status' => 'closed']);

        // Assert
        $this->assertDatabaseHas('crime_department_crime_incidents', [
            'id' => $crime1->id,
            'status' => 'solved'
        ]);
        $this->assertDatabaseHas('crime_department_crime_incidents', [
            'id' => $crime2->id,
            'status' => 'closed'
        ]);
    }

    /**
     * Test that original incident code is preserved on update
     */
    public function test_incident_code_is_preserved_on_update()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();
        $originalCode = $crime->incident_code;

        $category = CrimeCategory::first();
        $barangay = Barangay::first();

        $updateData = [
            'incident_title' => 'Updated Title',
            'incident_description' => 'Updated description',
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now()->format('Y-m-d'),
            'incident_time' => '15:30',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ];

        // Act
        $this->putJson("/api/crimes/{$crime->id}", $updateData);

        // Assert
        $updatedCrime = CrimeIncident::find($crime->id);
        $this->assertEquals($originalCode, $updatedCrime->incident_code);
    }

    /**
     * Test that update timestamp is recorded
     */
    public function test_update_timestamp_is_recorded()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();
        $originalUpdated = $crime->updated_at;

        sleep(1);

        $category = CrimeCategory::first();
        $barangay = Barangay::first();

        $updateData = [
            'incident_title' => 'Updated Title',
            'incident_description' => 'Updated description',
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now()->format('Y-m-d'),
            'incident_time' => '15:30',
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ];

        // Act
        $this->putJson("/api/crimes/{$crime->id}", $updateData);

        // Assert
        $updatedCrime = CrimeIncident::find($crime->id);
        $this->assertGreaterThan($originalUpdated, $updatedCrime->updated_at);
    }
}
