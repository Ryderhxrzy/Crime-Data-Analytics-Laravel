<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds crime_category_id and barangay_id to the San Agustin table.
 *
 * These are PLAIN INTEGERS with no foreign key constraints — category_name and
 * barangay_name remain the source of truth. They exist so the dashboard, hotspot,
 * alert and pattern code, which groups and filters on these ids, keeps working
 * against this table without rewriting ~50 query sites.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crime_department_san_agustin_incidents')) {
            return;
        }

        Schema::table('crime_department_san_agustin_incidents', function (Blueprint $table) {
            if (!Schema::hasColumn('crime_department_san_agustin_incidents', 'crime_category_id')) {
                $table->unsignedBigInteger('crime_category_id')->nullable()->after('record_type')->index();
            }
            if (!Schema::hasColumn('crime_department_san_agustin_incidents', 'barangay_id')) {
                $table->unsignedBigInteger('barangay_id')->nullable()->after('crime_category_id')->index();
            }
        });

        // Backfill from the plain-text names
        if (Schema::hasTable('crime_department_crime_categories')) {
            foreach (DB::table('crime_department_crime_categories')->get(['id', 'category_name']) as $category) {
                DB::table('crime_department_san_agustin_incidents')
                    ->whereRaw('LOWER(TRIM(category_name)) = ?', [mb_strtolower(trim($category->category_name))])
                    ->update(['crime_category_id' => $category->id]);
            }
        }

        if (Schema::hasTable('crime_department_barangays')) {
            $sanAgustin = DB::table('crime_department_barangays')
                ->whereRaw('LOWER(TRIM(barangay_name)) = ?', ['san agustin'])
                ->value('id');

            if ($sanAgustin) {
                DB::table('crime_department_san_agustin_incidents')
                    ->whereRaw('LOWER(TRIM(barangay_name)) = ?', ['san agustin'])
                    ->update(['barangay_id' => $sanAgustin]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('crime_department_san_agustin_incidents')) {
            return;
        }

        Schema::table('crime_department_san_agustin_incidents', function (Blueprint $table) {
            foreach (['crime_category_id', 'barangay_id'] as $column) {
                if (Schema::hasColumn('crime_department_san_agustin_incidents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
