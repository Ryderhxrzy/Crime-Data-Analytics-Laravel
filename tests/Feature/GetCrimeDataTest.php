<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GetCrimeDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_crime_data_returns_all_incidents_with_coordinates()
    {
        $category = CrimeCategory::factory()->create();
        $barangay = Barangay::factory()->create();

        CrimeIncident::factory()->count(3)->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'latitude' => '14.5994',
            'longitude' => '120.9842'
        ]);

        $response = $this->getJson('/api/crime-data');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    }

    public function test_get_crime_data_filters_by_date_range()
    {
        $category = CrimeCategory::factory()->create();
        $barangay = Barangay::factory()->create();

        CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now(),
            'latitude' => '14.5994',
            'longitude' => '120.9842'
        ]);

        CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'incident_date' => now()->subDays(90),
            'latitude' => '14.5994',
            'longitude' => '120.9842'
        ]);

        $response = $this->getJson('/api/crime-data?range=30');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_get_crime_data_filters_by_crime_type()
    {
        $category1 = CrimeCategory::factory()->create();
        $category2 = CrimeCategory::factory()->create();
        $barangay = Barangay::factory()->create();

        CrimeIncident::factory()->create([
            'crime_category_id' => $category1->id,
            'barangay_id' => $barangay->id,
            'latitude' => '14.5994',
            'longitude' => '120.9842'
        ]);

        CrimeIncident::factory()->create([
            'crime_category_id' => $category2->id,
            'barangay_id' => $barangay->id,
            'latitude' => '14.5994',
            'longitude' => '120.9842'
        ]);

        $response = $this->getJson("/api/crime-data?crime_type={$category1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_get_crime_data_excludes_incidents_without_coordinates()
    {
        $category = CrimeCategory::factory()->create();
        $barangay = Barangay::factory()->create();

        CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'latitude' => '14.5994',
            'longitude' => '120.9842'
        ]);

        CrimeIncident::factory()->create([
            'crime_category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'latitude' => null,
            'longitude' => '120.9842'
        ]);

        $response = $this->getJson('/api/crime-data');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }
}
