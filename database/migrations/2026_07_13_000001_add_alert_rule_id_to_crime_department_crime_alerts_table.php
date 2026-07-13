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
        Schema::table('crime_department_crime_alerts', function (Blueprint $table) {
            // signed integer to match the existing `id int(11)` primary keys in this legacy schema
            $table->integer('alert_rule_id')->nullable()->after('id');
            $table->foreign('alert_rule_id')
                ->references('id')->on('crime_department_alert_rules')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crime_department_crime_alerts', function (Blueprint $table) {
            $table->dropForeign(['alert_rule_id']);
            $table->dropColumn('alert_rule_id');
        });
    }
};
