<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved Gemini AI outputs for the San Agustin pattern-detection page.
 *
 * One click of "Save" stores a batch: the forecast + key findings as a single
 * `analysis` row, and every recommended intervention as its own
 * `recommendation` row. report_type is what tells them apart, batch_key ties
 * the rows of one save together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crime_department_san_agustin_ai_reports', function (Blueprint $table) {
            $table->id();
            $table->string('batch_key', 40)->index();
            $table->string('barangay_name', 100)->default('San Agustin');

            $table->enum('report_type', ['analysis', 'recommendation'])->index();
            $table->string('title', 255);
            $table->text('summary')->nullable();

            // Full structured item as returned by the AI (forecast/findings or
            // one recommendation). longText, not json, so old MariaDB works too.
            $table->longText('payload');

            $table->unsignedInteger('period_days')->default(0);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('records_used')->default(0);
            $table->string('model', 100)->nullable();
            $table->string('saved_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crime_department_san_agustin_ai_reports');
    }
};
