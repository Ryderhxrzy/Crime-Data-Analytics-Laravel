<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crime_department_barangays', function (Blueprint $table) {
            $table->id();
            $table->string('barangay_name', 100);
            $table->string('barangay_code', 20)->nullable();
            $table->string('city_municipality', 100)->default('Quezon City');
            $table->string('province', 100)->default('Metro Manila');
            $table->string('region', 100)->default('NCR');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('population')->nullable();
            $table->decimal('area_sqkm', 10, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('barangay_name');
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crime_department_barangays');
    }
};
