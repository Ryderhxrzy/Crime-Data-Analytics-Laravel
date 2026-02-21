<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CrimeIncidentFilteringTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!CrimeCategory::exists()) {
            CrimeCategory::factory()->count(3)->create();
        }
        if (!Barangay::exists()) {
            Barangay::factory()->count(3)->create();
        }
    }

    /**
     * Test filtering crimes by category
     */
    public function test_filter_crimes_by_category()
    {
        // Arrange
        $category1 = CrimeCategory::first();
        $category2 = CrimeCategory::skip(1)->first();

        CrimeIncident::factory()->count(3)->create(['crime_category_id' => $category1->id]);
        CrimeIncident::factory()->count(2)->create(['crime_category_id' => $category2->id]);

        // Act
        $response = $this->getJson('/api/crimes?category=' . $category1->id);

        // Assert
        $response->assertStatus(200);
        $crimes = $response->json();
        $this->assertCount(3, $crimes);
        foreach ($crimes as $crime) {
            $this->assertEquals($category1->id, $crime['category']['id']);
        }
    }

    /**
     * Test filtering crimes by barangay
     */
    public function test_filter_crimes_by_barangay()
    {
        // Arrange
        $barangay1 = Barangay::first();
        $barangay2 = Barangay::skip(1)->first();

        CrimeIncident::factory()->count(2)->create(['barangay_id' => $barangay1->id]);
        CrimeIncident::factory()->count(3)->create(['barangay_id' => $barangay2->id]);

        // Act
        $response = $this->getJson('/api/crimes?barangay=' . $barangay1->id);

        // Assert
        $response->assertStatus(200);
        $crimes = $response->json();
        $this->assertCount(2, $crimes);
        foreach ($crimes as $crime) {
            $this->assertEquals($barangay1->id, $crime['barangay']['id']);
        }
    }

    /**
     * Test filtering crimes by status
     */
    public function test_filter_crimes_by_status()
    {
        // Arrange
        CrimeIncident::factory()->count(2)->create(['status' => 'reported']);
        CrimeIncident::factory()->count(3)->create(['status' => 'under_investigation']);

        // Act
        $response = $this->getJson('/api/crimes?status=reported');

        // Assert
        $response->assertStatus(200);
        $crimes = $response->json();
        $this->assertCount(2, $crimes);
        foreach ($crimes as $crime) {
            $this->assertEquals('reported', $crime['status']);
        }
    }

    /**
     * Test filtering crimes by date range
     */
    public function test_filter_crimes_by_date_range()
    {
        // Arrange
        $startDate = now()->subDays(10)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        CrimeIncident::factory()->count(2)->create([
            'incident_date' => now()->subDays(5)->format('Y-m-d')
        ]);
        CrimeIncident::factory()->count(1)->create([
            'incident_date' => now()->subDays(15)->format('Y-m-d')
        ]);

        // Act
        $response = $this->getJson("/api/crimes?start_date={$startDate}&end_date={$endDate}");

        // Assert
        $response->assertStatus(200);
        $crimes = $response->json();
        $this->assertCount(2, $crimes);
    }

    /**
     * Test filtering crimes by clearance status
     */
    public function test_filter_crimes_by_clearance_status()
    {
        // Arrange
        CrimeIncident::factory()->count(3)->create(['clearance_status' => 'cleared']);
        CrimeIncident::factory()->count(2)->create(['clearance_status' => 'uncleared']);

        // Act
        $response = $this->getJson('/api/crimes?clearance_status=cleared');

        // Assert
        $response->assertStatus(200);
        $crimes = $response->json();
        $this->assertCount(3, $crimes);
        foreach ($crimes as $crime) {
            $this->assertEquals('cleared', $crime['clearance_status']);
        }
    }

    /**
     * Test multiple filters work together
     */
    public function test_multiple_filters_work_together()
    {
        // Arrange
        $category = CrimeCategory::first();
        $barangay = Barangay::first();

        CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'status' => 'reported'
        ]);
        CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'status' => 'solved'
        ]);
        CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => Barangay::skip(1)->first()->id,
            'status' => 'reported'
        ]);

        // Act
        $response = $this->getJson("/api/crimes?category={$category->id}&barangay={$barangay->id}&status=reported");

        // Assert
        $response->assertStatus(200);
        $crimes = $response->json();
        $this->assertCount(1, $crimes);
        $this->assertEquals($category->id, $crimes[0]['category']['id']);
        $this->assertEquals($barangay->id, $crimes[0]['barangay']['id']);
        $this->assertEquals('reported', $crimes[0]['status']);
    }

    /**
     * Test search by incident code
     */
    public function test_search_by_incident_code()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create();

        // Act
        $response = $this->getJson('/api/crimes?search=' . $crime->incident_code);

        // Assert
        $response->assertStatus(200);
        $crimes = $response->json();
        $this->assertCount(1, $crimes);
        $this->assertEquals($crime->incident_code, $crimes[0]['incident_code']);
    }

    /**
     * Test search by incident title
     */
    public function test_search_by_incident_title()
    {
        // Arrange
        $crime = CrimeIncident::factory()->create(['incident_title' => 'Unique Robbery Case']);

        // Act
        $response = $this->getJson('/api/crimes?search=Unique');

        // Assert
        $response->assertStatus(200);
        $crimes = $response->json();
        $this->assertCount(1, $crimes);
        $this->assertStringContainsString('Unique', $crimes[0]['incident_title']);
    }

    /**
     * Test no results when filter matches nothing
     */
    public function test_no_results_when_filter_matches_nothing()
    {
        // Arrange
        CrimeIncident::factory()->count(3)->create();

        // Act
        $response = $this->getJson('/api/crimes?status=archived');

        // Assert
        $response->assertStatus(200);
        $crimes = $response->json();
        $this->assertCount(0, $crimes);
    }
}
