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
        Schema::create('crime_department_evidence', function (Blueprint $table) {
            $table->id('evidence_id');
            $table->foreignId('incident_id')->constrained('crime_department_crime_incidents')->cascadeOnDelete();
            $table->enum('evidence_type', [
                'Weapon',
                'Clothing',
                'Fingerprint',
                'Biological Sample',
                'Document',
                'Photo',
                'Video',
                'Audio',
                'Digital File',
                'Testimonial',
                'Other'
            ]);
            $table->text('description')->nullable();
            $table->text('evidence_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crime_department_evidence');
    }
};
