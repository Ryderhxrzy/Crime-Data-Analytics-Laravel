<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recalibrates the street alert rules seeded by 2026_08_13_000002.
 *
 * Those first windows were too tight for how this barangay's data actually
 * looks — roughly 200 incidents over two years spread across a dozen streets,
 * so about two incidents per street per quarter. "3 crimes on one street in
 * 7 days" never happens here, and rules that can never fire are worse than no
 * rules: the Active Alerts page just sits empty.
 *
 * Measured against the real spread, these windows put every rule in range
 * while still needing a genuine concentration to trip:
 *
 *   Critical Crime on a Street       >= 1 critical      / 90 days
 *   Street Crime Surge               >= 3 any type      / 30 days
 *   Repeat Crime Type on a Street    >= 3 same type     / 90 days
 *   Street Hotspot                   >= 6 any type      / 180 days
 *   High-Severity Cluster            >= 2 high+critical / 90 days
 *
 * Rules are matched by their machine condition (scope + severity filter), not
 * by name, so a rule that was renamed for its new window is still recognised.
 */
return new class extends Migration
{
    private const RULES_TABLE = 'crime_department_alert_rules';

    /** [old name, new name, conditions] */
    private function rules(): array
    {
        return [
            [
                'Critical Crime on a Street',
                'Critical Crime on a Street (90 Days)',
                'critical',
                [
                    'scope' => 'street_severity',
                    'severity_filter' => ['critical'],
                    'operator' => '>=',
                    'threshold' => 1,
                    'time_window_hours' => 2160,
                ],
            ],
            [
                'Street Crime Surge (7 Days)',
                'Street Crime Surge (30 Days)',
                'high',
                [
                    'scope' => 'street',
                    'operator' => '>=',
                    'threshold' => 3,
                    'time_window_hours' => 720,
                ],
            ],
            [
                'Repeat Crime Type on a Street (30 Days)',
                'Repeat Crime Type on a Street (90 Days)',
                'high',
                [
                    'scope' => 'street_category',
                    'operator' => '>=',
                    'threshold' => 3,
                    'time_window_hours' => 2160,
                ],
            ],
            [
                'Street Hotspot (90 Days)',
                'Street Hotspot (180 Days)',
                'medium',
                [
                    'scope' => 'street',
                    'operator' => '>=',
                    'threshold' => 6,
                    'time_window_hours' => 4320,
                ],
            ],
            [
                'High-Severity Cluster on a Street (30 Days)',
                'High-Severity Cluster on a Street (90 Days)',
                'high',
                [
                    'scope' => 'street_severity',
                    'severity_filter' => ['high', 'critical'],
                    'operator' => '>=',
                    'threshold' => 2,
                    'time_window_hours' => 2160,
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

        foreach ($this->rules() as [$oldName, $newName, $severity, $conditions]) {
            $readable = $engine->formatCondition(
                new \App\Models\AlertRule(['conditions_data' => $conditions])
            );

            $row = [
                'rule_name' => $newName,
                'severity' => $severity,
                'rule_condition' => ucfirst($readable).'.',
                'conditions_data' => json_encode($conditions),
                'updated_at' => now(),
            ];

            $existing = DB::table(self::RULES_TABLE)
                ->whereIn('rule_name', [$oldName, $newName])
                ->first();

            if ($existing) {
                DB::table(self::RULES_TABLE)->where('id', $existing->id)->update($row);
                continue;
            }

            // Not present at all (rule was deleted, or 000002 never ran here)
            DB::table(self::RULES_TABLE)->insert(array_merge($row, [
                'rule_type' => $conditions['scope'] === 'street_category' ? 'pattern' : 'hotspot',
                'enabled' => 1,
                'trigger_count' => 0,
                'created_at' => now(),
            ]));
        }

        // Alerts raised under the old windows describe conditions that no longer
        // exist. Re-evaluating closes those and raises the ones these rules mean.
        try {
            $engine->evaluateAllRules();
        } catch (\Throwable $e) {
            \Log::warning('Alert re-evaluation skipped: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        // Nothing to undo: this only retunes thresholds on existing rules.
    }
};
