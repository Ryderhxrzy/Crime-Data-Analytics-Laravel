<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CrimeIncidentListingTest extends TestCase
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
     * Test that crime incidents index page loads
     */
    public function test_crime_incidents_index_page_loads()
    {
        // Arrange
        CrimeIncident::factory()->count(5)->create();

        // Act
        $response = $this->get('/crimes');

        // Assert
        $response->assertStatus(200);
        $response->assertViewHas('crimes');
    }

    /**
     * Test that crimes are returned as JSON via API
     */
    public function test_crimes_are_returned_as_json_via_api()
    {
        // Arrange
        CrimeIncident::factory()->count(3)->create();

        // Act
        $response = $this->getJson('/api/crimes');

        // Assert
        $response->assertStatus(200);
        $response->assertIsArray($response->json());
        $this->assertCount(3, $response->json());
    }

    /**
     * Test that crime JSON includes required fields
     */
    public function test_crime_json_includes_required_fields()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Act
        $response = $this->getJson('/api/crimes');

        // Assert
        $response->assertStatus(200);
        $crimeData = $response->json()[0];
        $this->assertArrayHasKey('id', $crimeData);
        $this->assertArrayHasKey('incident_code', $crimeData);
        $this->assertArrayHasKey('incident_title', $crimeData);
        $this->assertArrayHasKey('incident_date', $crimeData);
        $this->assertArrayHasKey('status', $crimeData);
        $this->assertArrayHasKey('category', $crimeData);
        $this->assertArrayHasKey('barangay', $crimeData);
    }

    /**
     * Test that crimes are ordered by ID descending
     */
    public function test_crimes_are_ordered_by_id_descending()
    {
        // Arrange
        $crime1 = CrimeIncident::factory()->create();
        $crime2 = CrimeIncident::factory()->create();
        $crime3 = CrimeIncident::factory()->create();

        // Act
        $response = $this->getJson('/api/crimes');

        // Assert
        $crimes = $response->json();
        $this->assertEquals($crime3->id, $crimes[0]['id']);
        $this->assertEquals($crime2->id, $crimes[1]['id']);
        $this->assertEquals($crime1->id, $crimes[2]['id']);
    }

    /**
     * Test that crime persons involved count is included
     */
    public function test_crime_persons_involved_count_is_included()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Act
        $response = $this->getJson('/api/crimes');

        // Assert
        $crimeData = $response->json()[0];
        $this->assertArrayHasKey('persons_involved_count', $crimeData);
        $this->assertEquals(0, $crimeData['persons_involved_count']);
    }

    /**
     * Test that crime evidence count is included
     */
    public function test_crime_evidence_count_is_included()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Act
        $response = $this->getJson('/api/crimes');

        // Assert
        $crimeData = $response->json()[0];
        $this->assertArrayHasKey('evidence_count', $crimeData);
        $this->assertEquals(0, $crimeData['evidence_count']);
    }

    /**
     * Test that empty crime list returns empty array
     */
    public function test_empty_crime_list_returns_empty_array()
    {
        // Act
        $response = $this->getJson('/api/crimes');

        // Assert
        $response->assertStatus(200);
        $this->assertCount(0, $response->json());
    }

    /**
     * Test that HTML view includes all crimes
     */
    public function test_html_view_includes_all_crimes()
    {
        // Arrange
        $crime1 = CrimeIncident::factory()->create(['incident_title' => 'Robbery Case']);
        $crime2 = CrimeIncident::factory()->create(['incident_title' => 'Theft Case']);

        // Act
        $response = $this->get('/crimes');

        // Assert
        $response->assertStatus(200);
        $crimes = $response->viewData('crimes');
        $this->assertCount(2, $crimes);
    }

    /**
     * Test that crimes include relationships
     */
    public function test_crimes_include_relationships()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Act
        $response = $this->get('/crimes');

        // Assert
        $crimes = $response->viewData('crimes');
        $this->assertNotNull($crimes[0]->category);
        $this->assertNotNull($crimes[0]->barangay);
    }
}
