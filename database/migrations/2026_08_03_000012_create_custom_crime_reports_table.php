<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved "crime data" reports from the Reports > Crime Data page: a named
 * selection of incident codes (e.g. every resolved robbery on Susano Road)
 * that staff can reopen later and download as a PDF when someone requests
 * crime data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crime_department_custom_reports')) {
            return;
        }

        Schema::create('crime_department_custom_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('purpose', 500)->nullable();
            $table->json('incident_codes');
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crime_department_custom_reports');
    }
};
