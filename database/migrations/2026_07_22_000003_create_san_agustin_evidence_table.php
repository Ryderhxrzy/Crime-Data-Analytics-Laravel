<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidence attached to San Agustin incidents.
 *
 * Mirrors crime_department_evidence. incident_id keeps a real foreign key here —
 * the "no foreign keys" rule was about barangay and category lookups, and evidence
 * is meaningless without its parent incident, so it cascades on delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crime_department_san_agustin_evidence', function (Blueprint $table) {
            $table->id('evidence_id');

            $table->unsignedBigInteger('incident_id');
            $table->foreign('incident_id')
                ->references('id')
                ->on('crime_department_san_agustin_incidents')
                ->cascadeOnDelete();

            $table->enum('evidence_type', [
                'Weapon', 'Clothing', 'Fingerprint', 'Biological Sample', 'Document',
                'Photo', 'Video', 'Audio', 'Digital File', 'Testimonial', 'Other',
            ])->index();

            // Stored encrypted, same as crime_department_evidence
            $table->text('description')->nullable();
            $table->text('evidence_link')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crime_department_san_agustin_evidence');
    }
};
