<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CrimeCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $crimeTypes = [
            'Theft',
            'Robbery',
            'Homicide',
            'Assault',
            'Sexual Abuse',
            'Drug Trafficking',
            'Burglary',
            'Fraud',
            'Cybercrime',
            'Traffic Violation'
        ];

        $type = $this->faker->randomElement($crimeTypes);

        return [
            'category_name' => $type,
            'category_code' => strtoupper(substr($type, 0, 3)) . '-' . $this->faker->numerify('###'),
            'description' => $this->faker->sentence(),
            'source_system' => $this->faker->randomElement(['law_enforcement', 'emergency_response', 'community_policing', 'fire_rescue', 'traffic_transport']),
            'severity_level' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'color_code' => $this->faker->hexColor(),
            'icon' => 'fas fa-exclamation',
            'is_active' => true,
        ];
    }
}
