<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AlertRule;
use App\Models\AlertSettings;

class AlertsController extends Controller
{
    public function activeAlerts()
    {
        return view('alerts-active');
    }

    public function history()
    {
        return view('alerts-history');
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
