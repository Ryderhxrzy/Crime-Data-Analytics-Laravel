<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff accounts for the Crime Data Analytics system.
 *
 * Admins live in the shared `centralized_admin_user` table, whose `role` is an
 * enum('admin','super_admin') owned by the central portal, so a "staff" role
 * cannot be stored there. Staff therefore get their own table, managed from
 * this app's Staff Management page and signed in through the same login form.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crime_department_staff')) {
            return;
        }

        Schema::create('crime_department_staff', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('email', 150)->unique();
            $table->string('password_hash');
            $table->string('position', 120)->nullable();
            $table->string('contact_number', 40)->nullable();
            $table->boolean('is_active')->default(true)->index();
            // Set when an admin issues a temporary password; cleared once the
            // staff member picks their own.
            $table->boolean('must_change_password')->default(true);
            $table->timestamp('credentials_sent_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_login')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->string('created_by', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crime_department_staff');
    }
};
