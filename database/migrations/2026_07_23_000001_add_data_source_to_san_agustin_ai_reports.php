<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguish saved AI reports drawn from REAL data vs a what-if SIMULATION.
 *
 * `data_source` = 'real' (Analyze Real Data) or 'simulation' (Run Simulation).
 * `scenario` holds the simulation configuration (scenario type, missing
 * safeguards or prevention measures, crime types, focus area) so a saved
 * simulation report is self-describing; null for real-data reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crime_department_san_agustin_ai_reports', function (Blueprint $table) {
            $table->enum('data_source', ['real', 'simulation'])
                ->default('real')
                ->after('barangay_name')
                ->index();

            // longText, not json, to stay compatible with older MariaDB
            $table->longText('scenario')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('crime_department_san_agustin_ai_reports', function (Blueprint $table) {
            $table->dropColumn(['data_source', 'scenario']);
        });
    }
};
