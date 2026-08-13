<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user preferences for this system.
 *
 * Accounts live in the centralized portal, so the key here is the user's email
 * rather than a local foreign key — the same person keeps their preferences
 * whether they arrive via the JWT session or a local login.
 *
 * Only settings this application actually reads belong here. A preference that
 * nothing honours is the same dead switch as a link that goes nowhere.
 */
return new class extends Migration
{
    private const TABLE = 'crime_department_user_preferences';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('user_email', 191)->unique();
            $table->string('display_name', 120)->nullable();
            $table->string('contact_number', 40)->nullable();
            $table->string('position', 120)->nullable();

            // Map + analytics defaults, read when a page first loads
            $table->string('default_view_mode', 20)->default('markers');
            $table->string('default_time_period', 10)->default('all');
            $table->string('default_barangay', 100)->nullable();
            $table->unsignedSmallInteger('rows_per_page')->default(25);
            $table->string('suggestion_language', 5)->default('en');

            // Alert-related preferences that the alert pages read
            $table->boolean('alert_sound')->default(false);
            $table->string('alert_min_severity', 20)->default('low');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
