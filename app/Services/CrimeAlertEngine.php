<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\Barangay;
use App\Models\CrimeAlert;
use App\Models\CrimeCategory;
use App\Models\CrimeIncident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns alert rules into alerts against real incidents.
 *
 * Every incident this system holds is in Barangay San Agustin, so rules are
 * evaluated PER STREET: "3 thefts on Katarungan Street in 7 days" is something
 * an officer can act on, where "3 crimes in San Agustin" is just the dataset.
 *
 * A rule's machine-readable condition lives in AlertRule::conditions_data:
 *
 *     scope            street | street_category | street_severity
 *                      | barangay | category           (what is being counted)
 *     category_id      only count this crime category  (optional)
 *     severity_filter  only count these category severities (optional)
 *     operator         >= | >                           (default >=)
 *     threshold        how many incidents trip the rule
 *     time_window_hours   how far back to count
 *
 * Windows are measured back from the most recent recorded incident, not from
 * the wall clock. With a dataset that lags the calendar, "last 24 hours" from
 * today matches nothing and no rule would ever fire.
 */
class CrimeAlertEngine
{
    /** Rules only ever concern this barangay. */
    public const BARANGAY = 'San Agustin';

    public function evaluateAllRules(): Collection
    {
        $created = collect();

        foreach (AlertRule::enabled()->get() as $rule) {
            $created = $created->merge($this->evaluateRule($rule));
        }

        // An alert whose condition no longer holds is finished business: close
        // it out so it leaves Active Alerts and lands in the history.
        $this->autoResolveStaleAlerts();

        return $created;
    }

    /**
     * Real-time trigger hooked into CrimeIncident::booted().
     */
    public function evaluateForIncident(CrimeIncident $incident): Collection
    {
        return $this->evaluateAllRules();
    }

    /**
     * Anchor for every relative window: the latest recorded incident.
     */
    public function referenceDate(): Carbon
    {
        $latest = CrimeIncident::max('incident_date');

        return $latest ? Carbon::parse($latest)->endOfDay() : Carbon::now();
    }

    /** The street an incident happened on, or null when it cannot be read */
    public function streetOf($incident): ?string
    {
        $street = trim(explode(',', (string) $incident->address_details)[0] ?? '');

        if ($street === '' || str_starts_with($street, 'Purok')) {
            return null;
        }

        return $street;
    }

    // ------------------------------------------------------------------
    // Evaluation
    // ------------------------------------------------------------------

    protected function evaluateRule(AlertRule $rule): Collection
    {
        $config = $rule->conditions_data ?? [];
        $scope = $config['scope'] ?? null;

        if (! $scope) {
            return collect();   // free-text rule with no machine condition
        }

        $threshold = max(1, (int) ($config['threshold'] ?? 1));
        $operator = ($config['operator'] ?? '>=') === '>' ? '>' : '>=';
        $windowHours = max(1, (int) ($config['time_window_hours'] ?? 24));
        $categoryId = $config['category_id'] ?? null;
        $severityFilter = array_filter((array) ($config['severity_filter'] ?? []));

        $windowStart = $this->referenceDate()->copy()->subHours($windowHours);

        $incidents = $this->incidentsInWindow($windowStart, $categoryId, $severityFilter);

        if ($incidents->isEmpty()) {
            return collect();
        }

        $meets = fn (int $count) => $operator === '>' ? $count > $threshold : $count >= $threshold;
        $created = collect();

        switch ($scope) {
            case 'street':
            case 'street_severity':
                foreach ($this->groupByStreet($incidents) as $street => $group) {
                    if ($meets($group->count())) {
                        $created->push($this->createAlertIfNew($rule, $group, $street, null, $windowHours));
                    }
                }
                break;

            case 'street_category':
                foreach ($this->groupByStreet($incidents) as $street => $streetGroup) {
                    foreach ($streetGroup->groupBy('crime_category_id') as $catId => $group) {
                        if ($meets($group->count())) {
                            $created->push($this->createAlertIfNew($rule, $group, $street, $catId ?: null, $windowHours));
                        }
                    }
                }
                break;

            case 'category':
                foreach ($incidents->groupBy('crime_category_id') as $catId => $group) {
                    if ($meets($group->count())) {
                        $created->push($this->createAlertIfNew($rule, $group, null, $catId ?: null, $windowHours));
                    }
                }
                break;

            case 'barangay':
            default:
                if ($meets($incidents->count())) {
                    $created->push($this->createAlertIfNew($rule, $incidents, null, null, $windowHours));
                }
                break;
        }

        return $created->filter();
    }

    /**
     * Incidents inside a time window, optionally narrowed to one category or a
     * set of category severities.
     *
     * The date is filtered in SQL (indexed) and the boundary day is refined in
     * PHP against incident_time. Doing the whole comparison in SQL needed
     * TIMESTAMP(), which is MySQL-only and could not use the index anyway.
     */
    protected function incidentsInWindow(Carbon $windowStart, ?int $categoryId = null, array $severityFilter = []): Collection
    {
        return CrimeIncident::query()
            ->with(['category', 'barangay'])
            ->where('incident_date', '>=', $windowStart->toDateString())
            ->when($categoryId, fn ($q) => $q->where('crime_category_id', $categoryId))
            ->when($severityFilter, fn ($q) => $q->whereHas(
                'category',
                fn ($c) => $c->whereIn('severity_level', $severityFilter)
            ))
            ->get()
            ->filter(function ($incident) use ($windowStart) {
                $date = $incident->incident_date?->format('Y-m-d');
                if (! $date) {
                    return false;
                }

                $stamp = Carbon::parse($date.' '.($incident->incident_time ?: '00:00:00'));

                return $stamp->greaterThanOrEqualTo($windowStart);
            })
            ->values();
    }

    protected function groupByStreet(Collection $incidents): Collection
    {
        return $incidents
            ->filter(fn ($i) => $this->streetOf($i) !== null)
            ->groupBy(fn ($i) => $this->streetOf($i));
    }

    protected function createAlertIfNew(
        AlertRule $rule,
        Collection $group,
        ?string $street,
        ?int $categoryId,
        int $windowHours
    ): ?CrimeAlert {
        // One open alert per rule + street + category. Re-running the engine
        // refreshes the count on the open alert instead of stacking duplicates.
        $existing = CrimeAlert::query()
            ->where('alert_rule_id', $rule->id)
            ->where('street_name', $street)
            ->where('crime_category_id', $categoryId)
            ->whereIn('alert_status', ['active', 'acknowledged', 'investigating'])
            ->first();

        $barangay = Barangay::whereRaw('LOWER(TRIM(barangay_name)) = ?', [mb_strtolower(self::BARANGAY)])->first();
        $category = $categoryId ? CrimeCategory::find($categoryId) : null;
        $latest = $group->sortByDesc(fn ($i) => $i->incident_date.' '.$i->incident_time)->first();

        $payload = [
            'incident_count' => $group->count(),
            'related_incidents' => $group->pluck('id')->implode(','),
            'alert_description' => $this->buildDescription($rule, $group->count(), $street, $category, $windowHours),
            'center_latitude' => round($group->avg('latitude'), 8),
            'center_longitude' => round($group->avg('longitude'), 8),
        ];

        if ($existing) {
            $existing->update($payload);

            return null;   // refreshed, not newly raised
        }

        $alert = CrimeAlert::create(array_merge($payload, [
            'alert_rule_id' => $rule->id,
            'alert_code' => $this->generateAlertCode(),
            'alert_title' => $rule->rule_name,
            'alert_type' => $this->mapAlertType($rule->rule_type),
            'severity' => $rule->severity,
            'barangay_id' => $barangay?->id,
            'street_name' => $street,
            'crime_category_id' => $categoryId,
            'radius_meters' => 250,
            'alert_status' => 'active',
        ]));

        $rule->recordTrigger();

        return $alert;
    }

    /**
     * Close open alerts whose rule no longer matches - the surge passed, the
     * window moved on, or the rule was disabled or deleted.
     */
    public function autoResolveStaleAlerts(): int
    {
        $open = CrimeAlert::with('rule')->activeStatus()->get();
        $closed = 0;

        foreach ($open as $alert) {
            $rule = $alert->rule;

            if (! $rule || ! $rule->enabled) {
                $alert->update([
                    'alert_status' => 'resolved',
                    'resolved_at' => now(),
                    'resolution_notes' => 'Closed automatically: the rule behind this alert is no longer active.',
                ]);
                $closed++;
                continue;
            }

            if (! $this->stillHolds($rule, $alert)) {
                $alert->update([
                    'alert_status' => 'resolved',
                    'resolved_at' => now(),
                    'resolution_notes' => 'Closed automatically: activity fell back below the rule threshold.',
                ]);
                $closed++;
            }
        }

        return $closed;
    }

    /** Does this alert's rule still match, for this alert's street/category? */
    protected function stillHolds(AlertRule $rule, CrimeAlert $alert): bool
    {
        $config = $rule->conditions_data ?? [];
        if (! ($config['scope'] ?? null)) {
            return true;   // nothing to check against; leave it to a human
        }

        $threshold = max(1, (int) ($config['threshold'] ?? 1));
        $operator = ($config['operator'] ?? '>=') === '>' ? '>' : '>=';
        $windowHours = max(1, (int) ($config['time_window_hours'] ?? 24));
        $severityFilter = array_filter((array) ($config['severity_filter'] ?? []));
        $windowStart = $this->referenceDate()->copy()->subHours($windowHours);

        $incidents = $this->incidentsInWindow(
            $windowStart,
            $alert->crime_category_id ?: ($config['category_id'] ?? null),
            $severityFilter
        );

        if ($alert->street_name) {
            $incidents = $incidents->filter(fn ($i) => $this->streetOf($i) === $alert->street_name);
        }

        $count = $incidents->count();

        return $operator === '>' ? $count > $threshold : $count >= $threshold;
    }

    /**
     * What a condition would match right now, without saving a rule or raising
     * anything. Powers the "Test this rule" button in Alert Management.
     *
     * @return Collection<int, array{label: string, count: int, category: ?string}>
     */
    public function previewMatches(array $config): Collection
    {
        $scope = $config['scope'] ?? null;
        if (! $scope) {
            return collect();
        }

        $threshold = max(1, (int) ($config['threshold'] ?? 1));
        $operator = ($config['operator'] ?? '>=') === '>' ? '>' : '>=';
        $windowHours = max(1, (int) ($config['time_window_hours'] ?? 24));
        $categoryId = $config['category_id'] ?? null;
        $severityFilter = array_filter((array) ($config['severity_filter'] ?? []));
        $windowStart = $this->referenceDate()->copy()->subHours($windowHours);

        $incidents = $this->incidentsInWindow($windowStart, $categoryId, $severityFilter);

        $meets = fn (int $count) => $operator === '>' ? $count > $threshold : $count >= $threshold;
        $matches = collect();

        switch ($scope) {
            case 'street':
            case 'street_severity':
                foreach ($this->groupByStreet($incidents) as $street => $group) {
                    if ($meets($group->count())) {
                        $matches->push(['label' => $street, 'count' => $group->count(), 'category' => null]);
                    }
                }
                break;

            case 'street_category':
                foreach ($this->groupByStreet($incidents) as $street => $streetGroup) {
                    foreach ($streetGroup->groupBy('category_name') as $name => $group) {
                        if ($meets($group->count())) {
                            $matches->push([
                                'label' => $street,
                                'count' => $group->count(),
                                'category' => $name ?: 'Uncategorized',
                            ]);
                        }
                    }
                }
                break;

            case 'category':
                foreach ($incidents->groupBy('category_name') as $name => $group) {
                    if ($meets($group->count())) {
                        $matches->push([
                            'label' => 'Barangay '.self::BARANGAY,
                            'count' => $group->count(),
                            'category' => $name ?: 'Uncategorized',
                        ]);
                    }
                }
                break;

            default:
                if ($meets($incidents->count())) {
                    $matches->push([
                        'label' => 'Barangay '.self::BARANGAY,
                        'count' => $incidents->count(),
                        'category' => null,
                    ]);
                }
                break;
        }

        return $matches->sortByDesc('count')->values();
    }

    // ------------------------------------------------------------------
    // Wording
    // ------------------------------------------------------------------

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

    protected function buildDescription(AlertRule $rule, int $count, ?string $street, ?CrimeCategory $category, int $windowHours): string
    {
        $where = $street ? $street : 'Barangay '.self::BARANGAY;
        $what = $category ? $category->category_name.' incidents' : 'crime incidents';
        $window = $this->formatWindow($windowHours);

        return "{$count} {$what} recorded on {$where} within the last {$window} — meets the \"{$rule->rule_name}\" rule.";
    }

    public function formatWindow(int $hours): string
    {
        if ($hours % 24 === 0 && $hours >= 24) {
            $days = intdiv($hours, 24);

            return $days === 1 ? '24 hours' : "{$days} days";
        }

        return $hours === 1 ? '1 hour' : "{$hours} hours";
    }

    /**
     * Human sentence describing exactly when a rule fires. Written from
     * conditions_data, so what the page shows is what the engine does.
     */
    public function formatCondition(?AlertRule $rule): string
    {
        $config = $rule?->conditions_data ?? [];
        $scope = $config['scope'] ?? null;

        if (! $scope) {
            return 'No automatic condition configured — this rule never fires.';
        }

        $threshold = (int) ($config['threshold'] ?? 1);
        $operator = ($config['operator'] ?? '>=') === '>' ? 'more than' : 'at least';
        $window = $this->formatWindow(max(1, (int) ($config['time_window_hours'] ?? 24)));
        $severityFilter = array_filter((array) ($config['severity_filter'] ?? []));

        $category = ! empty($config['category_id'])
            ? CrimeCategory::find($config['category_id'])?->category_name
            : null;

        $what = $category
            ? $category.' incident'.($threshold === 1 ? '' : 's')
            : ($severityFilter
                ? implode('/', $severityFilter).'-severity incident'.($threshold === 1 ? '' : 's')
                : 'incident'.($threshold === 1 ? '' : 's'));

        $where = match ($scope) {
            'street', 'street_severity' => 'on the same street',
            'street_category' => 'of the same type on the same street',
            'category' => 'of the same type in the barangay',
            default => 'anywhere in the barangay',
        };

        return "{$operator} {$threshold} {$what} {$where} within {$window}";
    }

    /** Short label for the scope, for tables and dropdowns */
    public function scopeLabel(?string $scope): string
    {
        return match ($scope) {
            'street' => 'Per street',
            'street_category' => 'Per street & crime type',
            'street_severity' => 'Per street (by severity)',
            'category' => 'Per crime type',
            'barangay' => 'Whole barangay',
            default => 'Not configured',
        };
    }

    protected function generateAlertCode(): string
    {
        $year = now()->format('Y');
        $sequence = CrimeAlert::whereYear('created_at', $year)->count() + 1;

        do {
            $code = "ALT-{$year}-".str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (CrimeAlert::where('alert_code', $code)->exists());

        return $code;
    }
}
