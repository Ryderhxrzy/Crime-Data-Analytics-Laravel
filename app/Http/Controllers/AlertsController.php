<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
        return view('alerts-settings');
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'hotspot_density' => 'required|numeric|min:1|max:10',
            'incident_increase_percentage' => 'required|numeric|min:0|max:100',
            'response_time_minutes' => 'required|numeric|min:1',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
        ]);

        // TODO: Save settings to database
        return redirect()->route('alerts.settings')->with('success', 'Alert settings updated successfully!');
    }
}
