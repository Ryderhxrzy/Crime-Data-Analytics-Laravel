<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CrimeIncidentModelTest extends TestCase
{
    use DatabaseTransactions;

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
     * Test that CrimeIncident can be created
     */
    public function test_crime_incident_can_be_created()
    {
        // Act
        $crime = CrimeIncident::factory()->create();

        // Assert
        $this->assertNotNull($crime->id);
        $this->assertInstanceOf(CrimeIncident::class, $crime);
    }

    /**
     * Test that crime incident has required attributes
     */
    public function test_crime_incident_has_required_attributes()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Assert
        $this->assertNotNull($crime->incident_code);
        $this->assertNotNull($crime->incident_title);
        $this->assertNotNull($crime->incident_description);
        $this->assertNotNull($crime->incident_date);
    }

    /**
     * Test that crime incident has default status
     */
    public function test_crime_incident_has_default_status()
    {
        // Act
        $crime = CrimeIncident::factory()->create();

        // Assert
        $this->assertEquals('reported', $crime->status);
    }

    /**
     * Test that crime incident has default clearance status
     */
    public function test_crime_incident_has_default_clearance_status()
    {
        // Act
        $crime = CrimeIncident::factory()->create();

        // Assert
        $this->assertEquals('uncleared', $crime->clearance_status);
    }

    /**
     * Test that crime incident belongs to category
     */
    public function test_crime_incident_belongs_to_category()
    {
        // Arrange
        $category = CrimeCategory::first();
        $crime = CrimeIncident::factory()->create(['crime_category_id' => $category->id]);

        // Assert
        $this->assertNotNull($crime->category);
        $this->assertEquals($category->id, $crime->category->id);
    }

    /**
     * Test that crime incident belongs to barangay
     */
    public function test_crime_incident_belongs_to_barangay()
    {
        // Arrange
        $barangay = Barangay::first();
        $crime = CrimeIncident::factory()->create(['barangay_id' => $barangay->id]);

        // Assert
        $this->assertNotNull($crime->barangay);
        $this->assertEquals($barangay->id, $crime->barangay->id);
    }

    /**
     * Test that crime incident has valid coordinates
     */
    public function test_crime_incident_has_valid_coordinates()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Assert
        $this->assertIsNumeric($crime->latitude);
        $this->assertIsNumeric($crime->longitude);
        $this->assertGreaterThanOrEqual(-90, $crime->latitude);
        $this->assertLessThanOrEqual(90, $crime->latitude);
        $this->assertGreaterThanOrEqual(-180, $crime->longitude);
        $this->assertLessThanOrEqual(180, $crime->longitude);
    }

    /**
     * Test that crime incident code is unique
     */
    public function test_crime_incident_code_is_unique()
    {
        // Arrange
        $crime1 = CrimeIncident::factory()->create();

        // Act & Assert - Try to create another with same code
        $crime2 = CrimeIncident::factory()->create();

        $this->assertNotEquals($crime1->incident_code, $crime2->incident_code);
    }

    /**
     * Test that crime incident can have persons involved
     */
    public function test_crime_incident_can_have_persons_involved()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Assert
        $this->assertNotNull($crime->personsInvolved);
        $this->assertIsIterable($crime->personsInvolved);
    }

    /**
     * Test that crime incident can have evidence
     */
    public function test_crime_incident_can_have_evidence()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Assert
        $this->assertNotNull($crime->evidence);
        $this->assertIsIterable($crime->evidence);
    }

    /**
     * Test that crime incident status can be updated
     */
    public function test_crime_incident_status_can_be_updated()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create(['status' => 'reported']);

        // Act
        $crime->update(['status' => 'under_investigation']);

        // Assert
        $this->assertEquals('under_investigation', $crime->fresh()->status);
    }

    /**
     * Test that crime incident clearance status can be updated
     */
    public function test_crime_incident_clearance_status_can_be_updated()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create(['clearance_status' => 'uncleared']);

        // Act
        $crime->update(['clearance_status' => 'cleared']);

        // Assert
        $this->assertEquals('cleared', $crime->fresh()->clearance_status);
    }

    /**
     * Test that crime incident can be deleted
     */
    public function test_crime_incident_can_be_deleted()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();
        $crimeId = $crime->id;

        // Act
        $crime->delete();

        // Assert
        $this->assertNull(CrimeIncident::find($crimeId));
    }

    /**
     * Test that crime incident has timestamps
     */
    public function test_crime_incident_has_timestamps()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Assert
        $this->assertNotNull($crime->created_at);
        $this->assertNotNull($crime->updated_at);
    }

    /**
     * Test that crime incident can be filtered by status
     */
    public function test_crime_incident_can_be_filtered_by_status()
    {
        // Arrange
        CrimeIncident::factory()->create(['status' => 'reported']);
        CrimeIncident::factory()->create(['status' => 'under_investigation']);

        // Act
        $crimes = CrimeIncident::where('status', 'reported')->get();

        // Assert
        $this->assertCount(1, $crimes);
        $this->assertEquals('reported', $crimes[0]->status);
    }

    /**
     * Test that crime incident can be filtered by category
     */
    public function test_crime_incident_can_be_filtered_by_category()
    {
        // Arrange
        $category = CrimeCategory::first();
        CrimeIncident::factory()->create(['crime_category_id' => $category->id]);

        // Act
        $crimes = CrimeIncident::where('crime_category_id', $category->id)->get();

        // Assert
        $this->assertCount(1, $crimes);
        $this->assertEquals($category->id, $crimes[0]->crime_category_id);
    }

    /**
     * Test that crime incident has incident description
     */
    public function test_crime_incident_has_description()
    {
        // Arrange
        $description = 'Test crime description';
        $crime = CrimeIncident::factory()->create(['incident_description' => $description]);

        // Assert
        $this->assertEquals($description, $crime->incident_description);
    }

    /**
     * Test that crime incident can have victim count
     */
    public function test_crime_incident_can_have_victim_count()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create(['victim_count' => 3]);

        // Assert
        $this->assertEquals(3, $crime->victim_count);
    }

    /**
     * Test that crime incident can have suspect count
     */
    public function test_crime_incident_can_have_suspect_count()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create(['suspect_count' => 2]);

        // Assert
        $this->assertEquals(2, $crime->suspect_count);
    }
}
