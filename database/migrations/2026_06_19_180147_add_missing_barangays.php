<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('crime_department_barangays')->insert([
            ['barangay_name' => 'Bagumbuhay', 'barangay_code' => 'BGY', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Bagong Lipunan Ng Crame', 'barangay_code' => 'BLC', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Botocan', 'barangay_code' => 'BOT', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Central', 'barangay_code' => 'CTR', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Kristong Hari', 'barangay_code' => 'KRH', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Damayang Lagi', 'barangay_code' => 'DML', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Don Manuel', 'barangay_code' => 'DNM', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'N.S. Amoranto', 'barangay_code' => 'NSA', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Kalusugan', 'barangay_code' => 'KLS', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Kamuning', 'barangay_code' => 'KMN', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Krus Na Ligas', 'barangay_code' => 'KNL', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Laging Handa', 'barangay_code' => 'LGH', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Malaya', 'barangay_code' => 'MLY', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Old Capitol Site', 'barangay_code' => 'OCS', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Paligsahan', 'barangay_code' => 'PLG', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Pinyahan', 'barangay_code' => 'PNY', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Pinagkaisahan', 'barangay_code' => 'PGK', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Roxas', 'barangay_code' => 'RXS', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Sacred Heart', 'barangay_code' => 'SHT', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Saint Peter', 'barangay_code' => 'STP', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'San Isidro', 'barangay_code' => 'SID', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'San Martin De Porres', 'barangay_code' => 'SMP', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'San Vicente', 'barangay_code' => 'SVT', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Santo Niño', 'barangay_code' => 'STN', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Santol', 'barangay_code' => 'SNL', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Sikatuna Village', 'barangay_code' => 'SKV', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'South Triangle', 'barangay_code' => 'STR', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Tatalon', 'barangay_code' => 'TTL', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Teachers Village East', 'barangay_code' => 'TVE', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Teachers Village West', 'barangay_code' => 'TVW', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'U.P. Campus', 'barangay_code' => 'UPC', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'U.P. Village', 'barangay_code' => 'UPV', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['barangay_name' => 'Valencia', 'barangay_code' => 'VLC', 'city_municipality' => 'Quezon City', 'province' => 'Metro Manila', 'region' => 'NCR', 'latitude' => 0, 'longitude' => 0, 'population' => 0, 'area_sqkm' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('crime_department_barangays')->whereIn('barangay_code', [
            'BGY', 'BLC', 'BOT', 'CTR', 'KRH', 'DML', 'DNM', 'NSA', 'KLS', 'KMN', 'KNL', 'LGH', 'MLY', 'OCS', 'PLG', 'PNY', 'PGK', 'RXS', 'SHT', 'STP', 'SID', 'SMP', 'SVT', 'STN', 'SNL', 'SKV', 'STR', 'TTL', 'TVE', 'TVW', 'UPC', 'UPV', 'VLC'
        ])->delete();
    }
};
