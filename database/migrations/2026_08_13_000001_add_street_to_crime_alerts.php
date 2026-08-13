<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alerts are raised per STREET in Barangay San Agustin.
 *
 * The alerts table only had barangay_id and crime_category_id, so every alert
 * pointed at the one barangay this system holds data for — which made the
 * Active Alerts page a list of near-identical rows. The street is what an
 * officer acts on, so it is stored on the alert itself.
 */
return new class extends Migration
{
    private const TABLE = 'crime_department_crime_alerts';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLE, 'street_name')) {
                $table->string('street_name', 120)->nullable()->after('barangay_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'street_name')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn('street_name');
        });
    }
};
