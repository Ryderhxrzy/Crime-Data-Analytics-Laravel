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
        Schema::create('crime_department_audit_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->unsignedBigInteger('admin_id');
            $table->enum('action_type', [
                'INSERT_INCIDENT',
                'UPDATE_INCIDENT',
                'DELETE_INCIDENT',
                'VIEW_INCIDENT',
                'INSERT_PERSON',
                'UPDATE_PERSON',
                'DELETE_PERSON',
                'VIEW_PERSON_DETAILS',
                'DECRYPT_PERSON_FIELD',
                'INSERT_EVIDENCE',
                'UPDATE_EVIDENCE',
                'DELETE_EVIDENCE',
                'VIEW_EVIDENCE',
                'DECRYPT_EVIDENCE_FIELD'
            ]);
            $table->string('target_table', 255);
            $table->unsignedBigInteger('target_id');
            $table->string('ip_address', 45);
            $table->string('user_agent', 255)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('action_type');
            $table->index('target_table');
            $table->index('admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crime_department_audit_logs');
    }
};
