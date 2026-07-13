<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AlertRule;
use App\Models\AlertSettings;
use App\Models\CrimeAlert;
use App\Services\CrimeAlertEngine;
use Illuminate\Support\Carbon;

class AlertsController extends Controller
{
    /**
     * Fixed grouping id requested for every alert payload (not stored per-row).
     */
    private const SOURCE_GROUP = 5;

    public function activeAlerts()
    {
        return view('alerts-active');
    }

    public function history()
    {
        return view('alerts-history');
    }

    /**
     * JSON data for the Active Alerts page (fetched client-side).
     */
    public function activeData()
    {
        $alerts = CrimeAlert::with(['rule', 'barangay', 'category'])
            ->activeStatus()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'stats' => [
                'total_active' => $alerts->count(),
                'critical' => $alerts->where('severity', 'critical')->count(),
                'high' => $alerts->where('severity', 'high')->count(),
                'medium' => $alerts->where('severity', 'medium')->count(),
                'low' => $alerts->where('severity', 'low')->count(),
            ],
            'alerts' => $alerts->map(fn ($alert) => $this->formatAlert($alert))->values(),
        ]);
    }

    /**
     * JSON data for the Alert History page (fetched client-side).
     */
    public function historyData()
    {
        $alerts = CrimeAlert::with(['rule', 'barangay', 'category'])
            ->whereIn('alert_status', ['resolved', 'dismissed'])
            ->orderByDesc('resolved_at')
            ->get();

        $resolvedThisMonth = $alerts->filter(fn ($a) => $a->resolved_at && Carbon::parse($a->resolved_at)->isCurrentMonth());

        $avgMinutes = $alerts->filter(fn ($a) => $a->resolved_at)
            ->map(fn ($a) => Carbon::parse($a->created_at)->diffInMinutes(Carbon::parse($a->resolved_at)))
            ->average();

        return response()->json([
            'stats' => [
                'total_resolved' => $alerts->where('alert_status', 'resolved')->count(),
                'this_month' => $resolvedThisMonth->count(),
                'avg_resolution_minutes' => $avgMinutes ? round($avgMinutes) : 0,
                'false_alarms' => $alerts->where('alert_status', 'dismissed')->count(),
            ],
            'alerts' => $alerts->map(fn ($alert) => $this->formatAlert($alert))->values(),
        ]);
    }

    /**
     * Manually re-run the rule engine (used by the Refresh button, also safe to
     * call on a schedule later since it dedupes against existing active alerts).
     */
    public function evaluate(CrimeAlertEngine $engine)
    {
        $created = $engine->evaluateAllRules();

        return response()->json([
            'created_count' => $created->count(),
            'alerts' => $created->map(fn ($alert) => $this->formatAlert($alert->load(['rule', 'barangay', 'category'])))->values(),
        ]);
    }

    public function resolve(Request $request, $id)
    {
        $alert = CrimeAlert::findOrFail($id);
        $alert->update([
            'alert_status' => 'resolved',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'resolution_notes' => $request->input('resolution_notes'),
        ]);

        return response()->json(['success' => true]);
    }

    private function formatAlert(CrimeAlert $alert): array
    {
        $windowHours = $alert->rule?->conditions_data['time_window_hours'] ?? null;

        $areaName = $alert->barangay?->barangay_name ?? 'Quezon City (Citywide)';
        if ($alert->category) {
            $areaName .= " — {$alert->category->category_name}";
        }

        return [
            'source_group' => self::SOURCE_GROUP,
            'alert_id' => $alert->id,
            'rule_name' => $alert->rule?->rule_name ?? $alert->alert_title,
            'rule_type' => $alert->rule?->rule_type,
            'severity' => $alert->severity,
            'condition' => $alert->rule?->rule_condition ?? $alert->alert_description,
            'area_name' => $areaName,
            'location' => $alert->center_latitude && $alert->center_longitude
                ? "{$alert->center_latitude},{$alert->center_longitude}"
                : null,
            'route' => route('mapping', array_filter([
                'barangay_id' => $alert->barangay_id,
                'crime_category_id' => $alert->crime_category_id,
            ])),
            'incident_count' => $alert->incident_count,
            'time_window' => $windowHours ? app(CrimeAlertEngine::class)->formatWindow($windowHours) : null,
            'triggered_at' => optional($alert->created_at)->toIso8601String(),
            'resolved_at' => optional($alert->resolved_at)->toIso8601String(),
            'status' => $alert->alert_status,
        ];
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

    public function management()
    {
        $alertRules = AlertRule::all();
        return view('alerts-management', ['alertRules' => $alertRules]);
    }

    public function createRule(Request $request)
    {
        $validated = $request->validate([
            'rule_name' => 'required|string|max:255',
            'rule_type' => 'required|in:crime_surge,hotspot,pattern,threshold',
            'rule_condition' => 'required|string',
            'severity' => 'required|in:critical,high,medium,low',
            'enabled' => 'boolean',
        ]);

        AlertRule::create([
            'rule_name' => $validated['rule_name'],
            'rule_type' => $validated['rule_type'],
            'rule_condition' => $validated['rule_condition'],
            'severity' => $validated['severity'],
            'enabled' => $validated['enabled'] ?? false,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('alerts.management')->with('success', 'Alert rule created successfully!');
    }

    public function updateRule(Request $request, $id)
    {
        $validated = $request->validate([
            'rule_name' => 'required|string|max:255',
            'rule_type' => 'required|in:crime_surge,hotspot,pattern,threshold',
            'rule_condition' => 'required|string',
            'severity' => 'required|in:critical,high,medium,low',
            'enabled' => 'boolean',
        ]);

        $alertRule = AlertRule::findOrFail($id);
        $alertRule->update([
            'rule_name' => $validated['rule_name'],
            'rule_type' => $validated['rule_type'],
            'rule_condition' => $validated['rule_condition'],
            'severity' => $validated['severity'],
            'enabled' => $validated['enabled'] ?? false,
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('alerts.management')->with('success', 'Alert rule updated successfully!');
    }

    public function deleteRule($id)
    {
        $alertRule = AlertRule::findOrFail($id);
        $alertRule->delete();

        return redirect()->route('alerts.management')->with('success', 'Alert rule deleted successfully!');
    }
}
