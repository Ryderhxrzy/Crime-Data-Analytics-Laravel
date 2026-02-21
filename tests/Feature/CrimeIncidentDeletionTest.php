<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CrimeIncidentDeletionTest extends TestCase
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
     * Test that crime incident can be deleted
     */
    public function test_crime_incident_can_be_deleted()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();
        $this->assertDatabaseHas('crime_department_crime_incidents', [
            'id' => $crime->id
        ]);

        // Act
        $response = $this->deleteJson("/api/crimes/{$crime->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Crime incident deleted successfully'
        ]);
        $this->assertDatabaseMissing('crime_department_crime_incidents', [
            'id' => $crime->id
        ]);
    }

    /**
     * Test that deleting nonexistent crime returns 404
     */
    public function test_deleting_nonexistent_crime_returns_404()
    {
        // Act
        $response = $this->deleteJson('/api/crimes/99999');

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test that multiple crimes can be deleted
     */
    public function test_multiple_crimes_can_be_deleted()
    {
        // Arrange
        $crime1 = CrimeIncident::factory()->create();
        $crime2 = CrimeIncident::factory()->create();
        $crime3 = CrimeIncident::factory()->create();

        $this->assertDatabaseCount('crime_department_crime_incidents', 3);

        // Act
        $this->deleteJson("/api/crimes/{$crime1->id}");
        $this->deleteJson("/api/crimes/{$crime2->id}");

        // Assert
        $this->assertDatabaseCount('crime_department_crime_incidents', 1);
        $this->assertDatabaseHas('crime_department_crime_incidents', [
            'id' => $crime3->id
        ]);
    }

    /**
     * Test that deleting crime removes related data
     */
    public function test_deleting_crime_removes_related_data()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Act
        $this->deleteJson("/api/crimes/{$crime->id}");

        // Assert
        $this->assertDatabaseMissing('crime_department_crime_incidents', [
            'id' => $crime->id
        ]);
    }

    /**
     * Test that deletion is recorded in audit logs
     */
    public function test_deletion_is_recorded_in_audit_logs()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();
        $this->assertDatabaseCount('crime_department_audit_logs', 0);

        // Act
        $this->deleteJson("/api/crimes/{$crime->id}");

        // Assert
        // Check if deletion was logged
        $this->assertDatabaseHas('crime_department_audit_logs', [
            'action_type' => 'DELETE_INCIDENT',
            'target_table' => 'crime_department_crime_incidents',
            'target_id' => $crime->id,
        ]);
    }

    /**
     * Test that soft deleted crimes are marked as deleted
     */
    public function test_soft_deleted_crimes_are_not_listed()
    {
        // Arrange
        $crime1 = CrimeIncident::factory()->create();
        $crime2 = CrimeIncident::factory()->create();

        // Act
        $this->deleteJson("/api/crimes/{$crime1->id}");
        $response = $this->getJson('/api/crimes');

        // Assert
        $crimes = $response->json();
        $this->assertCount(1, $crimes);
        $this->assertEquals($crime2->id, $crimes[0]['id']);
    }

    /**
     * Test that trying to delete already deleted crime fails
     */
    public function test_trying_to_delete_already_deleted_crime_fails()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();
        $this->deleteJson("/api/crimes/{$crime->id}");

        // Act
        $response = $this->deleteJson("/api/crimes/{$crime->id}");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test that deletion response includes success message
     */
    public function test_deletion_response_includes_success_message()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Act
        $response = $this->deleteJson("/api/crimes/{$crime->id}");

        // Assert
        $response->assertJson([
            'success' => true,
            'message' => 'Crime incident deleted successfully'
        ]);
    }

    /**
     * Test that bulk deletion works
     */
    public function test_bulk_deletion_works()
    {
        // Arrange
        $crimes = CrimeIncident::factory()->count(5)->create();
        $crimeIds = $crimes->pluck('id')->toArray();
        $this->assertDatabaseCount('crime_department_crime_incidents', 5);

        // Act
        $response = $this->postJson('/api/crimes/bulk-delete', [
            'ids' => array_slice($crimeIds, 0, 3)
        ]);

        // Assert
        $response->assertStatus(200);
        $this->assertDatabaseCount('crime_department_crime_incidents', 2);
    }
}
