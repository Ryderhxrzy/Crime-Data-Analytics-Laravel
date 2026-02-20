<?php

namespace App\Http\Controllers;

use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use App\Events\CrimeIncidentUpdated;
use Illuminate\Http\Request;

class CrimeIncidentController extends Controller
{
    public function index()
    {
        $crimes = CrimeIncident::with('category', 'barangay')->get();

        // Check if request expects JSON (API request)
        if (request()->expectsJson()) {
            return response()->json($crimes);
        }

        return view('crimes.index', compact('crimes'));
    }

    public function create()
    {
        $categories = CrimeCategory::all();
        $barangays = Barangay::all();

        return view('crime-incident-create', compact('categories', 'barangays'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'incident_title' => 'required|string|max:255',
            'incident_description' => 'required|string',
            'crime_category_id' => 'required|exists:crime_department_crime_categories,id',
            'barangay_id' => 'required|exists:crime_department_barangays,id',
            'incident_date' => 'required|date',
            'incident_time' => 'required|date_format:H:i',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address_details' => 'nullable|string|max:500',
            'victim_count' => 'nullable|integer|min:0',
            'suspect_count' => 'nullable|integer|min:0',
            'status' => 'required|in:reported,under_investigation,solved,closed,archived',
            'clearance_status' => 'required|in:cleared,uncleared',
        ]);

        // Auto-generate incident code
        $validated['incident_code'] = 'INC-' . date('YmdHis') . '-' . rand(100, 999);

        \Log::info('📝 Creating crime incident', [
            'incident_title' => $validated['incident_title'],
            'category_id' => $validated['crime_category_id'],
            'barangay_id' => $validated['barangay_id'],
            'coords' => $validated['latitude'] . ',' . $validated['longitude']
        ]);

        $incident = CrimeIncident::create($validated);

        \Log::info('✅ Crime incident created successfully', [
            'incident_id' => $incident->id,
            'incident_code' => $incident->incident_code,
            'incident_title' => $incident->incident_title,
            'latitude' => $incident->latitude,
            'longitude' => $incident->longitude,
            'category_id' => $incident->crime_category_id,
            'barangay_id' => $incident->barangay_id,
            'broadcast_ready' => true
        ]);

        // Broadcast real-time event
        try {
            broadcast(new CrimeIncidentUpdated($incident, 'created'));
            \Log::info('📡 CrimeIncidentUpdated event broadcasted successfully', [
                'incident_id' => $incident->id,
                'event_type' => 'created',
                'channel' => 'crime-incidents'
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Failed to broadcast CrimeIncidentUpdated event', [
                'incident_id' => $incident->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        \Log::info('🔄 Model events should trigger now - check Laravel logs for broadcast events');

        // Check if request is AJAX (from modal)
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Crime incident created successfully!',
                'incident' => $incident
            ]);
        }

        // Regular form submission - redirect back
        return redirect()->back()->with('success', 'Crime incident created successfully! Check the mapping page for real-time update.');
    }
}
