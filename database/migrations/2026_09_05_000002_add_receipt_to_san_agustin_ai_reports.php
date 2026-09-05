<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipt trail for saved AI reports.
 *
 * Every saved report is handed to Public Safety Campaign Management; these
 * columns record who received it and when, so the Saved AI Reports page and
 * the public JSON feed can show the receipt without joining the audit log.
 */
return new class extends Migration
{
    private const TABLE = 'crime_department_san_agustin_ai_reports';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'received_by')) {
                $table->string('received_by', 150)->nullable()->after('saved_by');
            }
            if (!Schema::hasColumn(self::TABLE, 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('received_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            foreach (['received_by', 'received_at'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
