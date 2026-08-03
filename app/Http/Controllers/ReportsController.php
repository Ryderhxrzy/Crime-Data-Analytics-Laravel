<?php

namespace App\Http\Controllers;

use App\Models\CustomCrimeReport;
use App\Models\SanAgustinIncident;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index()
    {
        return view('reports-index');
    }

    /** Email of the logged-in user (JWT session first, local auth fallback) */
    private function currentUserEmail(): ?string
    {
        return session('auth_user.email') ?? auth()->user()?->email;
    }

    // ------------------------------------------------ Crime Data report page

    /**
     * Reports > Crime Data: every recorded San Agustin crime by street, with
     * a hover map, search, saved selections, and per-crime PDF download — so
     * staff can hand over crime data (with the map of where it happened)
     * whenever someone requests it.
     */
    public function crimeData()
    {
        return view('crime-data-report');
    }

    /** JSON list of crimes for the Crime Data page (filter client-side too) */
    public function crimeDataList()
    {
        $rows = SanAgustinIncident::query()
            ->orderByDesc('incident_date')
            ->orderByDesc('incident_time')
            ->get([
                'incident_code', 'category_name', 'incident_title', 'incident_description',
                'incident_date', 'incident_time', 'latitude', 'longitude', 'address_details',
                'victim_count', 'suspect_count', 'status', 'clearance_status', 'clearance_date',
                'modus_operandi', 'weather_condition', 'assigned_officer',
            ])
            ->map(function ($row) {
                $street = trim(explode(',', (string) $row->address_details)[0] ?? '');

                return [
                    'code'        => $row->incident_code,
                    'category'    => $row->category_name ?: 'Uncategorized',
                    'title'       => $row->incident_title,
                    'description' => $row->incident_description,
                    'date'        => $row->incident_date?->toDateString(),
                    'time'        => $row->incident_time ? substr((string) $row->incident_time, 0, 5) : null,
                    'lat'         => (float) $row->latitude,
                    'lng'         => (float) $row->longitude,
                    'street'      => $street !== '' ? $street : null,
                    'address'     => $row->address_details,
                    'victims'     => (int) $row->victim_count,
                    'suspects'    => (int) $row->suspect_count,
                    'status'      => $row->status,
                    'clearance'   => $row->clearance_status,
                    'cleared_on'  => $row->clearance_date?->toDateString(),
                    'modus'       => $row->modus_operandi,
                    'weather'     => $row->weather_condition,
                    'officer'     => $row->assigned_officer,
                ];
            })
            ->values();

        return response()->json(['success' => true, 'incidents' => $rows]);
    }

    /** Save a named selection of crimes so it can be reopened later */
    public function storeCrimeReport(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'purpose'          => 'nullable|string|max:500',
            'incident_codes'   => 'required|array|min:1',
            'incident_codes.*' => 'string|max:50',
        ]);

        $report = CustomCrimeReport::create([
            'title'          => $validated['title'],
            'purpose'        => $validated['purpose'] ?? null,
            'incident_codes' => array_values(array_unique($validated['incident_codes'])),
            'created_by'     => $this->currentUserEmail(),
        ]);

        return response()->json(['success' => true, 'id' => $report->id]);
    }

    /** Saved crime-data reports, newest first */
    public function listCrimeReports()
    {
        $reports = CustomCrimeReport::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'title'          => $r->title,
                'purpose'        => $r->purpose,
                'incident_codes' => $r->incident_codes,
                'count'          => count($r->incident_codes ?? []),
                'created_by'     => $r->created_by,
                'created_at'     => $r->created_at?->format('M j, Y g:i A'),
            ]);

        return response()->json(['success' => true, 'reports' => $reports]);
    }

    public function deleteCrimeReport($id)
    {
        $report = CustomCrimeReport::find($id);
        if (!$report) {
            return response()->json(['success' => false, 'error' => 'Report not found.'], 404);
        }

        $report->delete();

        return response()->json(['success' => true]);
    }

    public function create()
    {
        return view('reports-create');
    }

    public function store(Request $request)
    {
        // Validate report data
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:incidents,hotspots,trends,analytics',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'barangay' => 'nullable|string',
            'crime_type' => 'nullable|string',
        ]);

        // TODO: Generate and store report
        // For now, redirect back with success message
        return redirect()->route('reports.index')->with('success', 'Report generated successfully!');
    }

    public function show($id)
    {
        // TODO: Fetch report from database
        return view('reports.show', [
            'report' => [
                'id' => $id,
                'title' => 'Sample Report',
                'type' => 'incidents',
                'generated_at' => now(),
            ]
        ]);
    }

    public function download($id)
    {
        // TODO: Generate and download report as PDF
        return response()->json(['message' => 'Download started']);
    }
}
