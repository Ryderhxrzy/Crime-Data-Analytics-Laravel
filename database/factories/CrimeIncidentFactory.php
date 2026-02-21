<?php

namespace Database\Factories;

use App\Models\CrimeCategory;
use App\Models\Barangay;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrimeIncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_code' => 'INC-' . $this->faker->numerify('######'),
            'crime_category_id' => CrimeCategory::factory(),
            'barangay_id' => Barangay::factory(),
            'incident_title' => $this->faker->sentence(4),
            'incident_description' => $this->faker->paragraph(3),
            'incident_date' => $this->faker->dateTime('-30 days'),
            'incident_time' => $this->faker->time(),
            'latitude' => $this->faker->latitude(14.5, 14.8),
            'longitude' => $this->faker->longitude(120.8, 121.1),
            'address_details' => $this->faker->address(),
            'victim_count' => $this->faker->numberBetween(0, 10),
            'suspect_count' => $this->faker->numberBetween(0, 5),
            'status' => $this->faker->randomElement(['reported', 'under_investigation', 'solved', 'closed', 'archived']),
            'clearance_status' => $this->faker->randomElement(['cleared', 'uncleared']),
            'clearance_date' => $this->faker->optional()->dateTime(),
            'modus_operandi' => $this->faker->paragraph(2),
            'weather_condition' => $this->faker->randomElement(['Sunny', 'Cloudy', 'Rainy', 'Stormy']),
            'reported_by' => null,
            'assigned_officer' => $this->faker->name(),
        ];
    }
}
