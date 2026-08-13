<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AlertRule;
use App\Models\AlertSettings;
use App\Models\CrimeAlert;
use App\Models\CrimeCategory;
use App\Models\CrimeIncident;
use App\Models\UserPreference;
use App\Services\CrimeAlertEngine;
use Illuminate\Support\Carbon;

class AlertsController extends Controller
{
    /**
     * Laravel's response()->json() escapes <, >, &, ' as \uXXXX by default
     * (safe for inline <script> embedding). These are plain JSON API responses,
     * not embedded in HTML, so turn that off - otherwise ">=" renders as ">=".
     */
    private const JSON_OPTIONS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /** Worst-first ordering for alerts and rules */
    private const SEVERITY_RANK = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    public function activeAlerts()
    {
        return view('alerts-active', ['preferences' => UserPreference::current()]);
    }

    public function history()
    {
        return view('alerts-history');
    }

    /**
     * JSON data for the Active Alerts page.
     *
     * Only alerts tied to a street in San Agustin are returned — a barangay-wide
     * alert says nothing an officer can act on when the whole dataset is one
     * barangay.
     */
    public function activeData(CrimeAlertEngine $engine)
    {
        // "Only show me alerts of at least ..." from the user's Settings. With no
        // session (this endpoint is also public) the default is 'low' - everything.
        $minSeverity = UserPreference::current()['alert_min_severity'] ?? 'low';
        $minRank = self::SEVERITY_RANK[$minSeverity] ?? 3;

        $openTotal = CrimeAlert::activeStatus()->whereNotNull('street_name')->count();

        $alerts = CrimeAlert::with(['rule', 'barangay', 'category'])
            ->activeStatus()
            ->whereNotNull('street_name')
            ->orderByDesc('incident_count')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($a) => (self::SEVERITY_RANK[$a->severity] ?? 9) <= $minRank)
            // Worst first, then busiest
            ->sortBy(fn ($a) => self::SEVERITY_RANK[$a->severity] ?? 9)
            ->values();

        return response()->json([
            'stats' => [
                'total_active' => $alerts->count(),
                'critical' => $alerts->where('severity', 'critical')->count(),
                'high' => $alerts->where('severity', 'high')->count(),
                'medium' => $alerts->where('severity', 'medium')->count(),
                'low' => $alerts->where('severity', 'low')->count(),
                'streets_affected' => $alerts->pluck('street_name')->filter()->unique()->count(),
                'incidents_covered' => (int) $alerts->sum('incident_count'),
                'min_severity' => $minSeverity,
                'hidden_by_filter' => max(0, $openTotal - $alerts->count()),
            ],
            'alerts' => $alerts->map(fn ($alert) => $this->formatAlert($alert, $engine))->values(),
        ], 200, [], self::JSON_OPTIONS);
    }

    /**
     * JSON data for the Alert History page: everything that has been closed,
     * whether resolved or dismissed as a false alarm.
     */
    public function historyData(Request $request, CrimeAlertEngine $engine)
    {
        $days = (int) $request->query('days', 0);
        $severity = trim((string) $request->query('severity', ''));
        $street = trim((string) $request->query('street', ''));

        $alerts = CrimeAlert::with(['rule', 'barangay', 'category'])
            ->whereIn('alert_status', ['resolved', 'dismissed'])
            ->when($days > 0, fn ($q) => $q->where(
                fn ($w) => $w->where('resolved_at', '>=', now()->subDays($days))
                    ->orWhere('updated_at', '>=', now()->subDays($days))
            ))
            ->when($severity !== '', fn ($q) => $q->where('severity', $severity))
            ->when($street !== '', fn ($q) => $q->where('street_name', $street))
            // resolved_at can be null on rows closed before it was recorded;
            // updated_at keeps those in a sensible place instead of last.
            ->orderByRaw('COALESCE(resolved_at, updated_at) DESC')
            ->get();

        $closed = $alerts->filter(fn ($a) => $a->resolved_at);

        $avgMinutes = $closed
            ->map(fn ($a) => Carbon::parse($a->created_at)->diffInMinutes(Carbon::parse($a->resolved_at)))
            ->avg();

        $byStreet = $alerts->whereNotNull('street_name')
            ->groupBy('street_name')
            ->map->count()
            ->sortDesc();

        return response()->json([
            'stats' => [
                'total_resolved' => $alerts->where('alert_status', 'resolved')->count(),
                'this_month' => $closed->filter(fn ($a) => Carbon::parse($a->resolved_at)->isCurrentMonth())->count(),
                'avg_resolution_minutes' => $avgMinutes ? (int) round($avgMinutes) : 0,
                'false_alarms' => $alerts->where('alert_status', 'dismissed')->count(),
                'busiest_street' => $byStreet->keys()->first(),
                'busiest_street_count' => $byStreet->first() ?? 0,
            ],
            'streets' => CrimeAlert::whereNotNull('street_name')
                ->distinct()->orderBy('street_name')->pluck('street_name')->values(),
            'alerts' => $alerts->map(fn ($alert) => $this->formatAlert($alert, $engine))->values(),
        ], 200, [], self::JSON_OPTIONS);
    }

    /**
     * Re-run the rule engine. Safe to call repeatedly: open alerts are refreshed
     * rather than duplicated, and alerts whose condition has passed are closed.
     */
    public function evaluate(CrimeAlertEngine $engine)
    {
        $created = $engine->evaluateAllRules();

        return response()->json([
            'created_count' => $created->count(),
            'alerts' => $created->map(fn ($alert) => $this->formatAlert($alert->load(['rule', 'barangay', 'category']), $engine))->values(),
        ], 200, [], self::JSON_OPTIONS);
    }

    public function resolve(Request $request, $code)
    {
        return $this->closeAlert($request, $code, 'resolved');
    }

    /** Close an alert as a false alarm */
    public function dismiss(Request $request, $code)
    {
        return $this->closeAlert($request, $code, 'dismissed');
    }

    /** Mark that someone has picked the alert up, without closing it */
    public function acknowledge($code)
    {
        $alert = CrimeAlert::where('alert_code', $code)->firstOrFail();

        $alert->update([
            'alert_status' => 'acknowledged',
            'acknowledged_by' => $this->currentUserId(),
            'acknowledged_at' => now(),
        ]);

        return response()->json(['success' => true, 'status' => 'acknowledged']);
    }

    private function closeAlert(Request $request, string $code, string $status)
    {
        $alert = CrimeAlert::where('alert_code', $code)->firstOrFail();

        $alert->update([
            'alert_status' => $status,
            'resolved_by' => $this->currentUserId(),
            'resolved_at' => now(),
            'resolution_notes' => $request->input('resolution_notes')
                ?: ($status === 'dismissed' ? 'Dismissed as a false alarm.' : 'Resolved by an operator.'),
        ]);

        return response()->json(['success' => true, 'status' => $status]);
    }

    /** JWT session first, local auth second */
    private function currentUserId(): ?int
    {
        return session('auth_user.id') ?? auth()->id();
    }

    private function formatAlert(CrimeAlert $alert, ?CrimeAlertEngine $engine = null): array
    {
        $engine ??= app(CrimeAlertEngine::class);

        $config = $alert->rule?->conditions_data ?? [];
        $windowHours = $config['time_window_hours'] ?? null;
        $barangay = $alert->barangay;
        $incidents = $this->incidentsFor($alert);

        // What the alert is actually made of: the crime types behind the count
        $breakdown = $incidents
            ->countBy(fn ($i) => $i->category_name ?: 'Uncategorized')
            ->sortDesc()
            ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
            ->values();

        $latest = $incidents->sortByDesc(fn ($i) => $i->incident_date.' '.$i->incident_time)->first();

        return [
            'alert_id' => $alert->alert_code,
            'rule_name' => $alert->rule?->rule_name ?? $alert->alert_title,
            'rule_type' => $this->ruleTypeLabel($alert->rule?->rule_type),
            'severity' => strtoupper($alert->severity),
            'condition' => $engine->formatCondition($alert->rule) ?: $alert->alert_description,
            'description' => $alert->alert_description,
            'street' => $alert->street_name,
            'barangay' => $barangay?->barangay_name ?? CrimeAlertEngine::BARANGAY,
            'area_name' => $alert->street_name ?? ($barangay?->barangay_name ?? CrimeAlertEngine::BARANGAY),
            'category' => $alert->category?->category_name,
            'latitude' => $alert->center_latitude,
            'longitude' => $alert->center_longitude,
            'incident_count' => $alert->incident_count,
            'breakdown' => $breakdown,
            'last_incident' => $latest?->incident_date?->format('Y-m-d'),
            'time_window' => $windowHours ? 'last '.$engine->formatWindow((int) $windowHours) : null,
            'triggered_at' => $alert->created_at?->setTimezone('Asia/Manila')->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->setTimezone('Asia/Manila')->toIso8601String(),
            'resolution_notes' => $alert->resolution_notes,
            'status' => $alert->alert_status,
        ];
    }

    private function ruleTypeLabel(?string $ruleType): ?string
    {
        return match ($ruleType) {
            'crime_surge' => 'Crime Surge',
            'hotspot' => 'Hotspot',
            'pattern' => 'Pattern',
            'threshold' => 'Threshold',
            default => $ruleType,
        };
    }

    /** The incidents an alert was raised from */
    private function incidentsFor(CrimeAlert $alert)
    {
        $ids = array_filter(explode(',', (string) $alert->related_incidents));

        if (! $ids) {
            return collect();
        }

        return CrimeIncident::whereIn('id', $ids)
            ->get(['id', 'category_name', 'incident_date', 'incident_time', 'address_details']);
    }

    public function settings()
    {
        $settings = AlertSettings::where('user_id', auth()->id())
            ->where('setting_type', 'user')
            ->first();

        return view('alerts-settings', ['settings' => $settings]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'hotspot_density' => 'required|numeric|min:1|max:10',
            'incident_increase_percentage' => 'required|numeric|min:0|max:100',
            'response_time_minutes' => 'required|numeric|min:1',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'general_enabled' => 'boolean',
            'alert_sensitivity' => 'numeric|min:1|max:5',
            'retry_attempts' => 'numeric|min:1',
            'quiet_hours_enabled' => 'boolean',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',
            'crime_thresholds' => 'array',
            'location_thresholds' => 'array',
            'notification_system' => 'boolean',
        ]);

        $settings = AlertSettings::where('user_id', auth()->id())
            ->where('setting_type', 'user')
            ->first();

        if (!$settings) {
            $settings = new AlertSettings([
                'user_id' => auth()->id(),
                'setting_type' => 'user',
            ]);
        }

        $settings->general_settings = [
            'enabled' => $validated['general_enabled'] ?? false,
            'sensitivity' => $validated['alert_sensitivity'] ?? 3,
            'retry_attempts' => $validated['retry_attempts'] ?? 3,
            'quiet_hours_enabled' => $validated['quiet_hours_enabled'] ?? false,
            'quiet_hours_start' => $validated['quiet_hours_start'] ?? null,
            'quiet_hours_end' => $validated['quiet_hours_end'] ?? null,
        ];

        $settings->crime_thresholds = $validated['crime_thresholds'] ?? [];
        $settings->location_thresholds = $validated['location_thresholds'] ?? [];

        $settings->notification_settings = [
            'email' => $validated['email_notifications'] ?? false,
            'sms' => $validated['sms_notifications'] ?? false,
            'system' => $validated['notification_system'] ?? false,
            'hotspot_density' => $validated['hotspot_density'],
            'incident_increase_percentage' => $validated['incident_increase_percentage'],
            'response_time_minutes' => $validated['response_time_minutes'],
        ];

        $settings->save();

        return redirect()->route('alerts.settings')->with('success', 'Alert settings updated successfully!');
    }

    public function management(CrimeAlertEngine $engine)
    {
        $alertRules = AlertRule::orderByDesc('enabled')
            ->orderByDesc('created_at')
            ->get()
            ->sortBy(fn ($r) => [$r->enabled ? 0 : 1, self::SEVERITY_RANK[$r->severity] ?? 9])
            ->values()
            ->map(function ($rule) use ($engine) {
                $config = $rule->conditions_data ?? [];

                // Everything the edit form needs, flattened for the view
                $rule->scope = $config['scope'] ?? null;
                $rule->scope_label = $engine->scopeLabel($config['scope'] ?? null);
                $rule->threshold = $config['threshold'] ?? null;
                $rule->operator = $config['operator'] ?? '>=';
                $rule->window_hours = $config['time_window_hours'] ?? null;
                $rule->category_id = $config['category_id'] ?? null;
                $rule->severity_filter = array_values(array_filter((array) ($config['severity_filter'] ?? [])));
                $rule->readable_condition = $engine->formatCondition($rule);
                $rule->is_configured = ! empty($config['scope']);

                return $rule;
            });

        return view('alerts-management', [
            'alertRules' => $alertRules,
            'categories' => CrimeCategory::orderBy('category_name')->get(['id', 'category_name']),
            'scopes' => $this->scopeOptions(),
            'windows' => $this->windowOptions(),
        ]);
    }

    /** Scopes a rule can be evaluated at, matching CrimeAlertEngine */
    private function scopeOptions(): array
    {
        return [
            'street' => 'Per street — count all crimes on one street',
            'street_category' => 'Per street & crime type — count one crime type on one street',
            'street_severity' => 'Per street by severity — count only the severities you pick',
            'category' => 'Per crime type — count one crime type across the barangay',
            'barangay' => 'Whole barangay — count everything in San Agustin',
        ];
    }

    private function windowOptions(): array
    {
        return [
            24 => 'Last 24 hours',
            72 => 'Last 3 days',
            168 => 'Last 7 days',
            336 => 'Last 14 days',
            720 => 'Last 30 days',
            2160 => 'Last 90 days',
        ];
    }

    /**
     * Validation shared by create and update.
     *
     * A rule is a real condition, not a sentence: the scope, threshold and
     * window below are what the engine evaluates. The human-readable
     * description is generated from them so the two can never disagree.
     */
    private function ruleRules(): array
    {
        return [
            'rule_name' => 'required|string|max:255',
            'rule_type' => 'required|in:crime_surge,hotspot,pattern,threshold',
            'severity' => 'required|in:critical,high,medium,low',
            'scope' => 'required|in:street,street_category,street_severity,category,barangay',
            'operator' => 'required|in:>=,>',
            'threshold' => 'required|integer|min:1|max:500',
            'time_window_hours' => 'required|integer|min:1|max:8760',
            'category_id' => 'nullable|integer|exists:crime_department_crime_categories,id',
            'severity_filter' => 'nullable|array',
            'severity_filter.*' => 'in:low,medium,high,critical',
            'enabled' => 'boolean',
        ];
    }

    private function conditionPayload(array $validated, CrimeAlertEngine $engine): array
    {
        $conditions = [
            'scope' => $validated['scope'],
            'operator' => $validated['operator'],
            'threshold' => (int) $validated['threshold'],
            'time_window_hours' => (int) $validated['time_window_hours'],
        ];

        // Only the scopes that use them carry a category or a severity filter
        if (in_array($validated['scope'], ['street_category', 'category'], true) && ! empty($validated['category_id'])) {
            $conditions['category_id'] = (int) $validated['category_id'];
        }
        if ($validated['scope'] === 'street_severity') {
            $conditions['severity_filter'] = array_values($validated['severity_filter'] ?? ['critical']);
        }

        $readable = $engine->formatCondition(new AlertRule(['conditions_data' => $conditions]));

        return [
            'rule_name' => $validated['rule_name'],
            'rule_type' => $validated['rule_type'],
            'severity' => $validated['severity'],
            'rule_condition' => ucfirst($readable).'.',
            'conditions_data' => $conditions,
            'enabled' => $validated['enabled'] ?? false,
        ];
    }

    public function createRule(Request $request, CrimeAlertEngine $engine)
    {
        $validated = $request->validate($this->ruleRules());

        AlertRule::create(array_merge($this->conditionPayload($validated, $engine), [
            'created_by' => $this->currentUserId(),
            'updated_by' => $this->currentUserId(),
        ]));

        $engine->evaluateAllRules();   // a new rule should show its alerts right away

        return redirect()->route('alerts.management')->with('success', 'Alert rule created and evaluated.');
    }

    public function updateRule(Request $request, $id, CrimeAlertEngine $engine)
    {
        $validated = $request->validate($this->ruleRules());

        $alertRule = AlertRule::findOrFail($id);
        $alertRule->update(array_merge($this->conditionPayload($validated, $engine), [
            'updated_by' => $this->currentUserId(),
        ]));

        $engine->evaluateAllRules();

        return redirect()->route('alerts.management')->with('success', 'Alert rule updated and re-evaluated.');
    }

    public function deleteRule($id, CrimeAlertEngine $engine)
    {
        $alertRule = AlertRule::findOrFail($id);

        // Close anything this rule raised, so no orphaned alerts are left behind
        CrimeAlert::where('alert_rule_id', $alertRule->id)
            ->activeStatus()
            ->update([
                'alert_status' => 'resolved',
                'resolved_at' => now(),
                'resolution_notes' => 'Closed automatically: the rule behind this alert was deleted.',
            ]);

        $alertRule->delete();

        return redirect()->route('alerts.management')->with('success', 'Alert rule deleted; its open alerts were closed.');
    }

    /** Try a rule's condition against real data without saving it */
    public function previewRule(Request $request, CrimeAlertEngine $engine)
    {
        $validated = $request->validate(array_merge($this->ruleRules(), [
            'rule_name' => 'nullable|string|max:255',
        ]));
        $validated['rule_name'] = $validated['rule_name'] ?? 'Preview rule';

        $payload = $this->conditionPayload($validated, $engine);
        $matches = $engine->previewMatches($payload['conditions_data']);

        return response()->json([
            'success' => true,
            'condition' => $payload['rule_condition'],
            'match_count' => $matches->count(),
            'matches' => $matches->take(10)->values(),
        ], 200, [], self::JSON_OPTIONS);
    }
}
