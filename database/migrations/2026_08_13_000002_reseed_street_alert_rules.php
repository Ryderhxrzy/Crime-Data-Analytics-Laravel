<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the barangay/citywide alert rules with street-level ones.
 *
 * The old rules counted incidents per barangay, and this system holds one
 * barangay — so every rule matched everything at once and the resulting alerts
 * all said the same thing. These rules count per street, which is the level a
 * patrol is actually assigned to.
 *
 * Thresholds are set against the real spread of the San Agustin data (roughly
 * 200 incidents over two years across a dozen streets), so the rules fire on
 * genuine concentrations rather than on any two incidents in a month.
 */
return new class extends Migration
{
    private const RULES_TABLE = 'crime_department_alert_rules';
    private const ALERTS_TABLE = 'crime_department_crime_alerts';

    private function rules(): array
    {
        return [
            [
                'rule_name' => 'Critical Crime on a Street',
                'rule_type' => 'threshold',
                'severity' => 'critical',
                'conditions_data' => [
                    'scope' => 'street_severity',
                    'severity_filter' => ['critical'],
                    'operator' => '>=',
                    'threshold' => 1,
                    'time_window_hours' => 168,
                ],
            ],
            [
                'rule_name' => 'Street Crime Surge (7 Days)',
                'rule_type' => 'crime_surge',
                'severity' => 'high',
                'conditions_data' => [
                    'scope' => 'street',
                    'operator' => '>=',
                    'threshold' => 3,
                    'time_window_hours' => 168,
                ],
            ],
            [
                'rule_name' => 'Repeat Crime Type on a Street (30 Days)',
                'rule_type' => 'pattern',
                'severity' => 'high',
                'conditions_data' => [
                    'scope' => 'street_category',
                    'operator' => '>=',
                    'threshold' => 3,
                    'time_window_hours' => 720,
                ],
            ],
            [
                'rule_name' => 'Street Hotspot (90 Days)',
                'rule_type' => 'hotspot',
                'severity' => 'medium',
                'conditions_data' => [
                    'scope' => 'street',
                    'operator' => '>=',
                    'threshold' => 8,
                    'time_window_hours' => 2160,
                ],
            ],
            [
                'rule_name' => 'High-Severity Cluster on a Street (30 Days)',
                'rule_type' => 'hotspot',
                'severity' => 'high',
                'conditions_data' => [
                    'scope' => 'street_severity',
                    'severity_filter' => ['high', 'critical'],
                    'operator' => '>=',
                    'threshold' => 2,
                    'time_window_hours' => 720,
                ],
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable(self::RULES_TABLE)) {
            return;
        }

        $engine = app(\App\Services\CrimeAlertEngine::class);

        // Retire the old barangay/citywide rules and anything they raised, so
        // the pages are not left showing alerts nobody can explain.
        $stale = DB::table(self::RULES_TABLE)
            ->whereIn('rule_name', [
                'Critical Crime Immediate Alert',
                'Barangay Crime Surge (24 Hours)',
                'Citywide Category Surge (7 Days)',
                'Barangay Hotspot (30 Days)',
            ])
            ->pluck('id');

        if ($stale->isNotEmpty()) {
            if (Schema::hasTable(self::ALERTS_TABLE)) {
                DB::table(self::ALERTS_TABLE)->whereIn('alert_rule_id', $stale)->delete();
            }
            DB::table(self::RULES_TABLE)->whereIn('id', $stale)->delete();
        }

        foreach ($this->rules() as $rule) {
            $condition = $engine->formatCondition(
                new \App\Models\AlertRule(['conditions_data' => $rule['conditions_data']])
            );

            $row = [
                'rule_type' => $rule['rule_type'],
                'severity' => $rule['severity'],
                // Human sentence, generated from the machine condition so the
                // two can never drift apart.
                'rule_condition' => ucfirst($condition).'.',
                'conditions_data' => json_encode($rule['conditions_data']),
                'enabled' => 1,
                'updated_at' => now(),
            ];

            $existing = DB::table(self::RULES_TABLE)->where('rule_name', $rule['rule_name'])->first();

            if ($existing) {
                DB::table(self::RULES_TABLE)->where('id', $existing->id)->update($row);
            } else {
                DB::table(self::RULES_TABLE)->insert(array_merge($row, [
                    'rule_name' => $rule['rule_name'],
                    'trigger_count' => 0,
                    'created_at' => now(),
                ]));
            }
        }

        // Raise the alerts these rules imply, so the pages have real content
        // the moment the migration finishes.
        try {
            $engine->evaluateAllRules();
        } catch (\Throwable $e) {
            // Never fail a migration over alert generation
            \Log::warning('Initial alert evaluation skipped: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::RULES_TABLE)) {
            return;
        }

        DB::table(self::RULES_TABLE)
            ->whereIn('rule_name', array_column($this->rules(), 'rule_name'))
            ->delete();
    }
};
