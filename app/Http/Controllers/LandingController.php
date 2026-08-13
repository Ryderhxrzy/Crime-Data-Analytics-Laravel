<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\CrimeCategory;
use App\Models\CrimeIncident;
use App\Models\CrimeTip;
use App\Models\SanAgustinIncident;

class LandingController extends Controller
{
    /**
     * Display the landing page
     */
    public function index()
    {
        // Get basic statistics for hero section
        $totalIncidents = CrimeIncident::count();
        $recentIncidents = CrimeIncident::where('incident_date', '>=', now()->subDays(30))->count();

        return view('landing.index', compact('totalIncidents', 'recentIncidents'));
    }

    /**
     * Display the crime mapping page (authenticated users only)
     */
    public function mapping()
    {
        // The map opens on whatever the user chose in Settings
        return view('mapping', ['preferences' => \App\Models\UserPreference::current()]);
    }

    /**
     * Get crime location data as JSON for the heatmap
     * Can be used by: Web landing page, authenticated mapping page
     * Supports filtering by crime type, status, barangay, and date range
     * Data is cached for 10 minutes per filter combination
     */
    public function getCrimeData(Request $request)
    {
        // Served from the San Agustin incident table. That table keeps category_name
        // and barangay_name as plain text, so colours/icons are looked up by name.
        $categories = CrimeCategory::select('id', 'category_name', 'color_code', 'icon')
            ->get()
            ->keyBy(fn ($c) => mb_strtolower(trim($c->category_name)));

        $query = SanAgustinIncident::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Apply date filter
        $dateRange = $request->query('range', 'all');
        if ($dateRange !== 'all') {
            $query->where('incident_date', '>=', now()->subDays(intval($dateRange)));
        }

        // The map sends a category id; this table stores the name
        if ($request->filled('crime_type')) {
            $name = CrimeCategory::where('id', $request->query('crime_type'))->value('category_name');
            $query->where('category_name', $name ?? '__no_such_category__');
        }

        // Apply case status (workflow) filter
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Apply clearance status filter
        if ($request->filled('clearance_status')) {
            $query->where('clearance_status', $request->query('clearance_status'));
        }

        // crime | incident
        if ($request->filled('record_type')) {
            $query->where('record_type', $request->query('record_type'));
        }

        // Barangay is matched by name. Every row is San Agustin, so choosing another
        // barangay correctly returns nothing instead of the wrong barangay's data.
        if ($request->filled('barangay')) {
            $query->whereRaw('LOWER(TRIM(barangay_name)) = ?', [
                mb_strtolower(trim($request->query('barangay'))),
            ]);
        }

        $crimeData = $query->get()
            ->map(function ($incident) use ($categories) {
                $category = $categories->get(mb_strtolower(trim($incident->category_name)));

                // The street is the part of address_details before the first
                // comma — the same rule the street layer, the hotspot ranking
                // and the crime-data report all use.
                $street = trim(explode(',', (string) $incident->address_details)[0] ?? '');
                if ($street === '' || str_starts_with($street, 'Purok')) {
                    $street = null;
                }

                return [
                    'id' => $incident->id,
                    'latitude' => (float) $incident->latitude,
                    'longitude' => (float) $incident->longitude,
                    'incident_date' => optional($incident->incident_date)->format('Y-m-d'),
                    'incident_time' => $incident->incident_time ? substr((string) $incident->incident_time, 0, 5) : null,
                    'incident_title' => $incident->incident_title,
                    'status' => $incident->status,
                    'clearance_status' => $incident->clearance_status,
                    'record_type' => $incident->record_type,
                    'crime_category_id' => $category->id ?? null,
                    'location' => $incident->barangay_name,
                    'barangay_name' => $incident->barangay_name,
                    'street' => $street,
                    'address' => $incident->address_details,
                    'category_name' => $incident->category_name ?: 'Unknown',
                    'color_code' => $category->color_code ?? '#274d4c',
                    'icon' => $category->icon ?? 'fa-exclamation-circle',
                ];
            })
            ->values()  // Re-index array keys after mapping
            ->toArray();

        return response()->json($crimeData);
    }

    /**
     * Get full incident details (authenticated only)
     */
    public function getIncidentDetails($id)
    {
        try {
            $incident = SanAgustinIncident::with('evidence')->findOrFail($id);

            $category = CrimeCategory::whereRaw('LOWER(TRIM(category_name)) = ?', [
                mb_strtolower(trim($incident->category_name)),
            ])->first();

            return response()->json([
                'id' => $incident->id,
                'category_name' => $incident->category_name ?: 'Unknown',
                'color_code' => $category->color_code ?? '#274d4c',
                'icon' => $category->icon ?? 'fa-exclamation-circle',
                'record_type' => $incident->record_type,
                'incident_title' => $incident->incident_title,
                'incident_date' => optional($incident->incident_date)->format('Y-m-d') ?? 'Not specified',
                'incident_time' => $incident->incident_time ?? 'Not specified',
                'location' => $incident->barangay_name ?? 'Not specified',
                'address' => $incident->address_details ?? 'Not specified',
                'barangay_name' => $incident->barangay_name ?? 'Unknown',
                'status' => $incident->status,
                'clearance_status' => $incident->clearance_status,
                'case_number' => $incident->incident_code ?? 'N/A',
                'incident_details' => $incident->incident_description ?? 'No additional details',
                'evidence_count' => $incident->evidence->count(),
                'evidence_types' => $incident->evidence->pluck('evidence_type')->unique()->values(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Incident not found'], 404);
        }
    }

/**
     * Handle anonymous tip submission (Web form)
     */
    public function submitTip(Request $request)
    {
        // Validate form
        $validated = $request->validate([
            'crime_type' => 'required|string|max:100',
            'location' => 'required|string|max:500',
            'incident_date' => 'nullable|string|max:255',
            'tip_content' => 'required|string|min:10|max:2000',
            'cf-turnstile-response' => 'required'
        ]);

        // Verify CAPTCHA (using submit tip secret key)
        $captchaToken = $request->input('cf-turnstile-response');
        if (!$this->verifyCaptcha($captchaToken, 'submit_tip')) {
            return back()->withErrors(['captcha' => 'Security verification failed. Please try again.'])->withInput();
        }

        // Save tip to database
        CrimeTip::create([
            'crime_type' => $validated['crime_type'],
            'location' => $validated['location'],
            'date_of_crime' => $validated['incident_date'] ? $validated['incident_date'] : null,
            'details' => $validated['tip_content'],
            'status' => 'open'
        ]);

        return back()->with('success', 'Thank you for your tip! We appreciate your help in keeping our community safe.');
    }

    /**
     * Handle anonymous tip submission (API endpoint)
     * CAPTCHA is optional - for mobile apps, they can submit without CAPTCHA
     */
    public function submitTipApi(Request $request)
    {
        // Validate API request
        $validated = $request->validate([
            'crime_type' => 'required|string|max:100',
            'location' => 'required|string|max:500',
            'date_of_crime' => 'nullable|date_format:Y-m-d\TH:i',
            'details' => 'required|string|min:10|max:2000',
            'cf-turnstile-response' => 'nullable|string'  // Optional for mobile
        ]);

        // Verify CAPTCHA only if token is provided (for web requests)
        $captchaToken = $request->input('cf-turnstile-response');
        if (!empty($captchaToken)) {
            if (!$this->verifyCaptcha($captchaToken, 'submit_tip')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security verification failed. Please try again.'
                ], 422);
            }
        }

        // Save tip to database
        try {
            $tip = CrimeTip::create([
                'crime_type' => $validated['crime_type'],
                'location' => $validated['location'],
                'date_of_crime' => $validated['date_of_crime'] ?? null,
                'details' => $validated['details'],
                'status' => 'open'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your tip! We appreciate your help in keeping our community safe.',
                'data' => [
                    'id' => $tip->id,
                    'created_at' => $tip->created_at
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your submission. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify Cloudflare Turnstile CAPTCHA
     */
    private function verifyCaptcha($token, $type = 'general')
    {
        try {
            // Get the appropriate secret key based on type
            $secretKey = ($type === 'submit_tip')
                ? config('captcha.submit_tip_secret')
                : config('captcha.secret');

            // Check if secret key is set
            if (empty($secretKey)) {
                \Log::error('CAPTCHA: Secret key not configured', ['type' => $type]);
                return false;
            }

            // Check if token is empty
            if (empty($token)) {
                \Log::warning('CAPTCHA: Token is empty');
                return false;
            }

            // Use Laravel's HTTP client for better reliability
            \Log::info('CAPTCHA: Starting verification', ['token_length' => strlen($token)]);

            $response = Http::asForm()->timeout(10)->withoutRedirecting()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token
            ]);

            \Log::info('CAPTCHA: Response received', ['status' => $response->status()]);

            if (!$response->successful()) {
                \Log::error('CAPTCHA API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ]);
                return false;
            }

            $body = $response->json();

            if (isset($body['success']) && $body['success'] === true) {
                return true;
            }

            \Log::warning('CAPTCHA verification failed', ['errors' => $body['error-codes'] ?? []]);
            return false;
        } catch (\Exception $e) {
            \Log::error('CAPTCHA verification error: ' . $e->getMessage());
            return false;
        }
    }
}
