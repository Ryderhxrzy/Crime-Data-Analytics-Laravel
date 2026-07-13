<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Default alert rules evaluated by App\Services\CrimeAlertEngine against
     * crime_department_crime_incidents. Thresholds are intentionally low so the
     * rules are easy to reach with normal data entry, not just heavy crime spikes.
     */
    private function rules(): array
    {
        return [
            [
                'rule_name' => 'Critical Crime Immediate Alert',
                'rule_type' => 'threshold',
                'severity' => 'critical',
                'rule_condition' => 'Any single incident classified under a critical-severity crime category (e.g. Homicide, Sexual Offense) within the last 24 hours.',
                'conditions_data' => [
                    'scope' => 'category_severity',
                    'severity_filter' => ['critical'],
                    'operator' => '>=',
                    'threshold' => 1,
                    'time_window_hours' => 24,
                ],
            ],
            [
                'rule_name' => 'Barangay Crime Surge (24 Hours)',
                'rule_type' => 'crime_surge',
                'severity' => 'high',
                'rule_condition' => '2 or more crime incidents (any category) reported in the same barangay within a 24-hour period.',
                'conditions_data' => [
                    'scope' => 'barangay',
                    'operator' => '>=',
                    'threshold' => 2,
                    'time_window_hours' => 24,
                ],
            ],
            [
                'rule_name' => 'Citywide Category Surge (7 Days)',
                'rule_type' => 'crime_surge',
                'severity' => 'critical',
                'rule_condition' => '3 or more incidents of the same crime category reported anywhere in the city within 7 days.',
                'conditions_data' => [
                    'scope' => 'category',
                    'operator' => '>=',
                    'threshold' => 3,
                    'time_window_hours' => 168,
                ],
            ],
            [
                'rule_name' => 'Barangay Hotspot (30 Days)',
                'rule_type' => 'hotspot',
                'severity' => 'medium',
                'rule_condition' => '4 or more crime incidents of any category concentrated in the same barangay within 30 days.',
                'conditions_data' => [
                    'scope' => 'barangay',
                    'operator' => '>=',
                    'threshold' => 4,
                    'time_window_hours' => 720,
                ],
            ],
            [
                'rule_name' => 'Repeat Category Pattern (60 Days)',
                'rule_type' => 'pattern',
                'severity' => 'high',
                'rule_condition' => '2 or more incidents of the same crime category recurring in the same barangay within 60 days.',
                'conditions_data' => [
                    'scope' => 'barangay_category',
                    'operator' => '>=',
                    'threshold' => 2,
                    'time_window_hours' => 1440,
                ],
            ],
            [
                'rule_name' => 'High-Severity Volume Alert (7 Days)',
                'rule_type' => 'threshold',
                'severity' => 'medium',
                'rule_condition' => '5 or more high or critical severity incidents citywide within 7 days.',
                'conditions_data' => [
                    'scope' => 'citywide_severity',
                    'severity_filter' => ['high', 'critical'],
                    'operator' => '>=',
                    'threshold' => 5,
                    'time_window_hours' => 168,
                ],
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->rules() as $rule) {
            $exists = DB::table('crime_department_alert_rules')
                ->where('rule_name', $rule['rule_name'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('crime_department_alert_rules')->insert([
                'rule_name' => $rule['rule_name'],
                'rule_type' => $rule['rule_type'],
                'severity' => $rule['severity'],
                'rule_condition' => $rule['rule_condition'],
                'conditions_data' => json_encode($rule['conditions_data']),
                'enabled' => 1,
                'trigger_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('crime_department_alert_rules')
            ->whereIn('rule_name', array_column($this->rules(), 'rule_name'))
            ->delete();
    }
};
