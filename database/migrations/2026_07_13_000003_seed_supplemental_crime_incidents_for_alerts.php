<?php

use App\Services\CrimeAlertEngine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Incident code prefix used so this batch can be identified/rolled back
     * without touching the original lgu.sql seed data.
     */
    private string $prefix = 'INC-ALT-';

    /**
     * Extra incidents timed (relative to whenever this migration runs) so that,
     * combined with the rules from the previous migration, several alerts are
     * guaranteed to fire the first time the engine runs. Offsets are in hours
     * to stay inside/outside the right rule windows.
     *
     * barangay/category are resolved by name/code below so this doesn't depend
     * on hardcoded numeric IDs.
     */
    private function incidents(): array
    {
        return [
            // Set A: single critical-severity incident -> Critical Crime Immediate Alert
            ['barangay' => 'Commonwealth', 'category' => 'LE_HOMICIDE', 'hours_ago' => 3, 'title' => 'Fatal shooting incident along Commonwealth Avenue', 'victims' => 1, 'suspects' => 1],

            // Set B: 2 incidents, same barangay, within 24h -> Barangay Crime Surge (24 Hours)
            ['barangay' => 'Holy Spirit', 'category' => 'LE_ROBBERY', 'hours_ago' => 4, 'title' => 'Armed hold-up at Holy Spirit convenience store', 'victims' => 2, 'suspects' => 3],
            ['barangay' => 'Holy Spirit', 'category' => 'LE_ASSAULT', 'hours_ago' => 14, 'title' => 'Physical assault reported near Holy Spirit market', 'victims' => 1, 'suspects' => 2],

            // Set C: 3 incidents, same category, spread across the city within 7 days -> Citywide Category Surge (7 Days)
            ['barangay' => 'Payatas', 'category' => 'LE_VEHICLE', 'hours_ago' => 20, 'title' => 'Motorcycle carnapped in Payatas', 'victims' => 1, 'suspects' => 0],
            ['barangay' => 'Fairview', 'category' => 'LE_VEHICLE', 'hours_ago' => 72, 'title' => 'Sedan stolen from Fairview parking lot', 'victims' => 1, 'suspects' => 0],
            ['barangay' => 'Batasan Hills', 'category' => 'LE_VEHICLE', 'hours_ago' => 144, 'title' => 'Tricycle carnapped near Batasan Complex', 'victims' => 1, 'suspects' => 2],

            // Set D: 4 incidents, same barangay, spread across 30 days -> Barangay Hotspot (30 Days)
            ['barangay' => 'Bagong Silangan', 'category' => 'LE_THEFT', 'hours_ago' => 120, 'title' => 'Cellphone snatching in Bagong Silangan', 'victims' => 1, 'suspects' => 1],
            ['barangay' => 'Bagong Silangan', 'category' => 'LE_BURGLARY', 'hours_ago' => 288, 'title' => 'Break-in reported at Bagong Silangan residence', 'victims' => 0, 'suspects' => 0],
            ['barangay' => 'Bagong Silangan', 'category' => 'LE_FRAUD', 'hours_ago' => 480, 'title' => 'Online selling scam victim in Bagong Silangan', 'victims' => 1, 'suspects' => 1],
            ['barangay' => 'Bagong Silangan', 'category' => 'LE_THEFT', 'hours_ago' => 648, 'title' => 'Store pilferage incident in Bagong Silangan', 'victims' => 0, 'suspects' => 0],

            // Set E: 2 incidents, same barangay + same category, within 60 days -> Repeat Category Pattern (60 Days)
            ['barangay' => 'Baesa', 'category' => 'LE_BURGLARY', 'hours_ago' => 360, 'title' => 'Forced entry reported in Baesa subdivision', 'victims' => 0, 'suspects' => 0],
            ['barangay' => 'Baesa', 'category' => 'LE_BURGLARY', 'hours_ago' => 1080, 'title' => 'Second break-in reported in the same Baesa block', 'victims' => 1, 'suspects' => 0],

            // Set F: extra high/critical-severity incidents within 7 days -> High-Severity Volume Alert (7 Days)
            ['barangay' => 'Balong Bato', 'category' => 'LE_DRUGS', 'hours_ago' => 48, 'title' => 'Buy-bust operation conducted in Balong Bato', 'victims' => 0, 'suspects' => 1],
            ['barangay' => 'Apolonio Samson', 'category' => 'LE_DOMESTIC', 'hours_ago' => 140, 'title' => 'Domestic violence incident in Apolonio Samson', 'victims' => 1, 'suspects' => 1],
            ['barangay' => 'Payatas', 'category' => 'LE_ROBBERY', 'hours_ago' => 96, 'title' => 'Robbery incident near Payatas terminal', 'victims' => 1, 'suspects' => 2],
        ];
    }

    public function up(): void
    {
        $now = Carbon::now();

        foreach ($this->incidents() as $index => $incident) {
            $barangay = DB::table('crime_department_barangays')
                ->where('barangay_name', $incident['barangay'])
                ->first();

            $category = DB::table('crime_department_crime_categories')
                ->where('category_code', $incident['category'])
                ->first();

            if (! $barangay || ! $category) {
                // Referenced seed data (barangay/category) not found - skip rather than fail the migration.
                continue;
            }

            $occurredAt = $now->copy()->subHours($incident['hours_ago']);
            $incidentCode = $this->prefix.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

            if (DB::table('crime_department_crime_incidents')->where('incident_code', $incidentCode)->exists()) {
                continue;
            }

            DB::table('crime_department_crime_incidents')->insert([
                'incident_code' => $incidentCode,
                'crime_category_id' => $category->id,
                'barangay_id' => $barangay->id,
                'incident_title' => $incident['title'],
                'incident_description' => $incident['title'].'. Logged for alert monitoring demonstration data.',
                'incident_date' => $occurredAt->toDateString(),
                'incident_time' => $occurredAt->toTimeString(),
                'latitude' => $barangay->latitude,
                'longitude' => $barangay->longitude,
                'address_details' => $incident['barangay'].', Quezon City',
                'victim_count' => $incident['victims'],
                'suspect_count' => $incident['suspects'],
                'status' => 'reported',
                'clearance_status' => 'uncleared',
                'weather_condition' => 'Clear',
                'assigned_officer' => 'Duty Officer',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Populate the first batch of alerts immediately so Active Alerts has data
        // as soon as this migration finishes, without waiting for the next incident.
        app(CrimeAlertEngine::class)->evaluateAllRules();
    }

    public function down(): void
    {
        $codes = collect($this->incidents())
            ->map(fn ($incident, $index) => $this->prefix.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT));

        DB::table('crime_department_crime_incidents')->whereIn('incident_code', $codes)->delete();
    }
};
