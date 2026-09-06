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
     * Google Static Maps image for one crime, for the PDF.
     *
     * The browser cannot draw a live Google map into a PDF, so the report
     * asks here for a picture: hybrid imagery centred on the crime, the
     * street traced on it (encoded polylines), the spot marked. Fetched
     * server-side because the API key stays out of the browser and a
     * same-origin image can be put on a canvas. Cached: the same crime
     * produces the same picture.
     */
    public function staticMap(Request $request)
    {
        $data = $request->validate([
            'center'    => ['required', 'regex:/^-?\d{1,2}(\.\d+)?,-?\d{1,3}(\.\d+)?$/'],
            'zoom'      => ['nullable', 'integer', 'min:14', 'max:20'],
            'size'      => ['nullable', 'regex:/^\d{2,3}x\d{2,3}$/'],
            'maptype'   => ['nullable', 'in:hybrid,roadmap,satellite'],
            'path'      => ['nullable', 'array', 'max:40'],
            'path.*'    => ['string', 'max:2000', 'regex:/^color:0x[0-9a-fA-F]{6,8}\|weight:\d{1,2}\|enc:[?-~]+$/'],
            'markers'   => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9:|.,\-]+$/'],
        ]);

        $key = config('services.google_maps.key');
        if (!$key) {
            return response()->json(['error' => 'Google Maps API key is not configured.'], 503);
        }

        $query = [
            'center'  => $data['center'],
            'zoom'    => $data['zoom'] ?? 17,
            'size'    => $data['size'] ?? '640x300',
            'scale'   => 2,
            'maptype' => $data['maptype'] ?? 'hybrid',
            'key'     => $key,
        ];
        $qs = http_build_query($query);
        foreach ($data['path'] ?? [] as $path) {
            $qs .= '&path=' . rawurlencode($path);
        }
        if (!empty($data['markers'])) {
            $qs .= '&markers=' . rawurlencode($data['markers']);
        }

        $cacheKey = 'report_static_map_' . md5($qs);
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!$cached) {
            try {
                $res = \Illuminate\Support\Facades\Http::timeout(15)
                    ->get('https://maps.googleapis.com/maps/api/staticmap?' . $qs);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'Google Static Maps could not be reached: ' . $e->getMessage()], 502);
            }

            if (!$res->successful() || !str_starts_with((string) $res->header('Content-Type'), 'image/')) {
                // Google answers a refusal in plain text (billing, API not enabled, key restrictions)
                \Illuminate\Support\Facades\Log::warning('Static map refused', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 300)]);
                return response()->json(['error' => trim($res->body()) ?: 'Google Static Maps refused the request.'], 502);
            }

            $cached = ['type' => $res->header('Content-Type'), 'body' => base64_encode($res->body())];
            \Illuminate\Support\Facades\Cache::put($cacheKey, $cached, now()->addDay());
        }

        return response(base64_decode($cached['body']), 200)
            ->header('Content-Type', $cached['type'])
            ->header('Cache-Control', 'private, max-age=86400');
    }

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
