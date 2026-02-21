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
        Schema::create('crime_department_persons_involved', function (Blueprint $table) {
            $table->id('person_id');
            $table->foreignId('incident_id')->constrained('crime_department_crime_incidents')->cascadeOnDelete();
            $table->enum('person_type', ['complainant', 'victim', 'suspect']);
            $table->text('first_name')->nullable();
            $table->text('middle_name')->nullable();
            $table->text('last_name')->nullable();
            $table->text('contact_number')->nullable();
            $table->text('other_info')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crime_department_persons_involved');
    }
};
