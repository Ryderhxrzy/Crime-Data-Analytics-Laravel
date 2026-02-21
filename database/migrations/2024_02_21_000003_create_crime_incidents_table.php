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
        Schema::create('crime_department_crime_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code', 50)->unique();
            $table->foreignId('crime_category_id')->constrained('crime_department_crime_categories')->restrictOnDelete();
            $table->foreignId('barangay_id')->constrained('crime_department_barangays')->restrictOnDelete();
            $table->string('incident_title', 255);
            $table->text('incident_description')->nullable();
            $table->date('incident_date');
            $table->time('incident_time')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->text('address_details')->nullable();
            $table->integer('victim_count')->default(0);
            $table->integer('suspect_count')->default(0);
            $table->enum('status', ['reported', 'under_investigation', 'solved', 'closed', 'archived'])->default('reported');
            $table->enum('clearance_status', ['cleared', 'uncleared'])->default('uncleared');
            $table->date('clearance_date')->nullable();
            $table->text('modus_operandi')->nullable();
            $table->string('weather_condition', 50)->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->string('assigned_officer', 255)->nullable();
            $table->timestamps();
            $table->index('incident_date');
            $table->index('status');
            $table->index('crime_category_id');
            $table->index('barangay_id');
            $table->index(['latitude', 'longitude']);
            $table->index('clearance_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crime_department_crime_incidents');
    }
};
