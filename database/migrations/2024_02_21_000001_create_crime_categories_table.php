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
        Schema::create('crime_department_crime_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name', 100)->unique();
            $table->string('category_code', 20)->unique();
            $table->text('description')->nullable();
            $table->enum('source_system', ['law_enforcement', 'emergency_response', 'community_policing', 'fire_rescue', 'traffic_transport']);
            $table->enum('severity_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('color_code', 7)->default('#FF0000');
            $table->string('icon', 50)->default('fas fa-exclamation');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('category_code');
            $table->index('severity_level');
            $table->index('source_system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crime_department_crime_categories');
    }
};
