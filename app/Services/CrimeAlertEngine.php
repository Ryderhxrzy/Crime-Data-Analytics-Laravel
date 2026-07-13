<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\Barangay;
use App\Models\CrimeAlert;
use App\Models\CrimeCategory;
use App\Models\CrimeIncident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CrimeAlertEngine
{
    /**
     * Evaluate every enabled rule against all matching crime incidents.
     * Used for the manual "Refresh" / evaluate endpoint and for initial seeding.
     *
     * @return Collection<int, CrimeAlert>
     */
    public function evaluateAllRules(): Collection
    {
        $created = collect();

        foreach (AlertRule::enabled()->get() as $rule) {
            $created = $created->merge($this->evaluateRule($rule));
        }

        return $created;
    }

    /**
     * Evaluate every enabled rule immediately after a single incident is created.
     * This is the real-time trigger hooked into CrimeIncident::booted().
     *
     * @return Collection<int, CrimeAlert>
     */
    public function evaluateForIncident(CrimeIncident $incident): Collection
    {
        return $this->evaluateAllRules();
    }

    /**
     * @return Collection<int, CrimeAlert>
     */
    protected function evaluateRule(AlertRule $rule): Collection
    {
        $config = $rule->conditions_data ?? [];
        $scope = $config['scope'] ?? null;
        $threshold = (int) ($config['threshold'] ?? 1);
        $windowHours = (int) ($config['time_window_hours'] ?? 24);
        $severityFilter = $config['severity_filter'] ?? null;

        if (! $scope) {
            return collect();
        }

        $windowStart = Carbon::now()->subHours($windowHours);

        $query = CrimeIncident::query()
            ->with('category', 'barangay')
            ->whereRaw(
                "TIMESTAMP(incident_date, COALESCE(incident_time, '00:00:00')) >= ?",
                [$windowStart->toDateTimeString()]
            );

        if ($severityFilter) {
            $severities = (array) $severityFilter;
            $query->whereHas('category', fn ($q) => $q->whereIn('severity_level', $severities));
        }

        $incidents = $query->get();
        $created = collect();

        switch ($scope) {
            case 'barangay':
                foreach ($incidents->groupBy('barangay_id') as $barangayId => $group) {
                    if ($group->count() >= $threshold) {
                        $created->push($this->createAlertIfNew($rule, $group, $barangayId, null, $windowHours));
                    }
                }
                break;

            case 'category':
                foreach ($incidents->groupBy('crime_category_id') as $categoryId => $group) {
                    if ($group->count() >= $threshold) {
                        $created->push($this->createAlertIfNew($rule, $group, null, $categoryId, $windowHours));
                    }
                }
                break;

            case 'barangay_category':
                foreach ($incidents->groupBy(fn ($i) => $i->barangay_id.'-'.$i->crime_category_id) as $group) {
                    if ($group->count() >= $threshold) {
                        $first = $group->first();
                        $created->push($this->createAlertIfNew($rule, $group, $first->barangay_id, $first->crime_category_id, $windowHours));
                    }
                }
                break;

            case 'category_severity':
            case 'citywide_severity':
                if ($incidents->count() >= $threshold) {
                    $created->push($this->createAlertIfNew($rule, $incidents, null, null, $windowHours));
                }
                break;
        }

        return $created->filter();
    }

    protected function createAlertIfNew(AlertRule $rule, Collection $incidentGroup, ?int $barangayId, ?int $categoryId, int $windowHours): ?CrimeAlert
    {
        // Avoid re-creating an alert for a rule/scope combination that already has an unresolved alert.
        $alreadyActive = CrimeAlert::query()
            ->where('alert_rule_id', $rule->id)
            ->where('barangay_id', $barangayId)
            ->where('crime_category_id', $categoryId)
            ->whereIn('alert_status', ['active', 'acknowledged', 'investigating'])
            ->exists();

        if ($alreadyActive) {
            return null;
        }

        $barangay = $barangayId ? Barangay::find($barangayId) : null;
        $category = $categoryId ? CrimeCategory::find($categoryId) : null;
        $latestIncident = $incidentGroup->sortByDesc(fn ($i) => $i->incident_date.' '.$i->incident_time)->first();

        $alert = CrimeAlert::create([
            'alert_rule_id' => $rule->id,
            'alert_code' => $this->generateAlertCode(),
            'alert_title' => $rule->rule_name,
            'alert_type' => $this->mapAlertType($rule->rule_type),
            'severity' => $rule->severity,
            'barangay_id' => $barangayId,
            'crime_category_id' => $categoryId,
            'center_latitude' => $barangay->latitude ?? $latestIncident->latitude,
            'center_longitude' => $barangay->longitude ?? $latestIncident->longitude,
            'radius_meters' => 500,
            'alert_description' => $this->buildDescription($rule, $incidentGroup->count(), $barangay, $category, $windowHours),
            'incident_count' => $incidentGroup->count(),
            'related_incidents' => $incidentGroup->pluck('id')->implode(','),
            'alert_status' => 'active',
        ]);

        $rule->recordTrigger();

        return $alert;
    }

    protected function mapAlertType(string $ruleType): string
    {
        return match ($ruleType) {
            'crime_surge' => 'spike',
            'hotspot' => 'cluster',
            'pattern' => 'pattern',
            'threshold' => 'threshold',
            default => 'custom',
        };
    }

    protected function buildDescription(AlertRule $rule, int $count, ?Barangay $barangay, ?CrimeCategory $category, int $windowHours): string
    {
        $area = $barangay->barangay_name ?? 'Quezon City (citywide)';
        $window = $this->formatWindow($windowHours);
        $categoryText = $category ? " involving {$category->category_name}" : '';

        return "{$count} incidents{$categoryText} detected in {$area} within the last {$window}, meeting the \"{$rule->rule_name}\" rule.";
    }

    public function formatWindow(int $hours): string
    {
        if ($hours % 24 === 0 && $hours >= 24) {
            $days = $hours / 24;

            return $days === 1 ? '1 day' : "{$days} days";
        }

        return $hours === 1 ? '1 hour' : "{$hours} hours";
    }

    protected function generateAlertCode(): string
    {
        do {
            $code = 'ALT-'.now()->format('Y').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (CrimeAlert::where('alert_code', $code)->exists());

        return $code;
    }
}
