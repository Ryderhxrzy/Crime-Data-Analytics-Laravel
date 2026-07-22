<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone incident table scoped to Barangay San Agustin, Quezon City.
 *
 * Deliberately different from crime_department_crime_incidents:
 *   - barangay_name and category_name are plain varchars, not foreign keys, so
 *     this table does not depend on the barangay/category reference tables.
 *   - record_type separates a "crime" from a non-criminal "incident".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crime_department_san_agustin_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code', 50)->unique();

            // crime = a criminal offence, incident = fire, medical, accident, rescue...
            $table->enum('record_type', ['crime', 'incident'])->default('crime')->index();

            // Plain text on purpose — no foreign keys
            $table->string('category_name', 100)->index();
            $table->string('barangay_name', 100)->default('San Agustin')->index();

            $table->string('incident_title');
            $table->text('incident_description')->nullable();

            $table->date('incident_date')->index();
            $table->time('incident_time')->nullable();

            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->text('address_details')->nullable();

            $table->integer('victim_count')->default(0);
            $table->integer('suspect_count')->default(0);

            $table->enum('status', ['reported', 'under_investigation', 'solved', 'closed', 'archived'])
                ->default('reported')->index();
            $table->enum('clearance_status', ['cleared', 'uncleared'])
                ->default('uncleared')->index();
            $table->date('clearance_date')->nullable();

            $table->text('modus_operandi')->nullable();
            $table->string('weather_condition', 50)->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->string('assigned_officer')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crime_department_san_agustin_incidents');
    }
};
