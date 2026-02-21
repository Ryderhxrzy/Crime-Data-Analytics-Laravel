<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BarangayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $barangays = [
            'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5',
            'Barangay 6', 'Barangay 7', 'Barangay 8', 'Barangay 9', 'Barangay 10',
            'Barangay 11', 'Barangay 12', 'Barangay 13', 'Barangay 14', 'Barangay 15',
            'Barangay 16', 'Barangay 17', 'Barangay 18', 'Barangay 19', 'Barangay 20',
            'Barangay 21', 'Barangay 22', 'Barangay 23', 'Barangay 24', 'Barangay 25',
            'Barangay 26', 'Barangay 27', 'Barangay 28', 'Barangay 29', 'Barangay 30',
            'Barangay 31', 'Barangay 32', 'Barangay 33', 'Barangay 34', 'Barangay 35',
            'Barangay 36', 'Barangay 37', 'Barangay 38', 'Barangay 39', 'Barangay 40',
            'Barangay 41', 'Barangay 42', 'Barangay 43', 'Barangay 44', 'Barangay 45',
            'Barangay 46', 'Barangay 47', 'Barangay 48', 'Barangay 49', 'Barangay 50',
            'Barangay 51', 'Barangay 52', 'Barangay 53', 'Barangay 54', 'Barangay 55',
            'Barangay 56', 'Barangay 57', 'Barangay 58', 'Barangay 59', 'Barangay 60',
            'Barangay 61', 'Barangay 62', 'Barangay 63', 'Barangay 64', 'Barangay 65',
            'Barangay 66', 'Barangay 67', 'Barangay 68', 'Barangay 69', 'Barangay 70',
            'Barangay 71', 'Barangay 72', 'Barangay 73', 'Barangay 74', 'Barangay 75',
            'Barangay 76', 'Barangay 77', 'Barangay 78', 'Barangay 79', 'Barangay 80'
        ];

        $barangayName = $this->faker->randomElement($barangays);

        return [
            'barangay_name' => $barangayName,
            'barangay_code' => 'QC-' . $this->faker->numerify('###'),
            'city_municipality' => 'Quezon City',
            'province' => 'Metro Manila',
            'region' => 'NCR',
            'latitude' => $this->faker->latitude(14.5, 14.8),
            'longitude' => $this->faker->longitude(120.8, 121.1),
            'population' => $this->faker->numberBetween(10000, 100000),
            'area_sqkm' => $this->faker->randomFloat(2, 0.5, 5),
            'is_active' => true,
        ];
    }
}
