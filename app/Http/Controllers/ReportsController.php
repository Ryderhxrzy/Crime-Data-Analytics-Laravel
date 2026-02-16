<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index()
    {
        return view('reports-index');
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
