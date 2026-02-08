<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CrimeIncident;

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
     * Get crime location data as JSON for the heatmap
     * Public API endpoint - returns only non-sensitive location data
     * Used by: Web landing page heatmap visualization
     */
    public function getCrimeData(Request $request)
    {
        // Determine date range filter
        $dateRange = $request->query('range', 'all'); // default all records

        $query = CrimeIncident::select('latitude', 'longitude', 'incident_date')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Apply date filter
        if ($dateRange !== 'all') {
            $days = intval($dateRange);
            $query->where('incident_date', '>=', now()->subDays($days));
        }

        $crimeData = $query->get()
            ->map(function($incident) {
                return [
                    'lat' => (float) $incident->latitude,
                    'lng' => (float) $incident->longitude,
                    'date' => $incident->incident_date->format('Y-m-d')
                ];
            });

        return response()->json($crimeData);
    }

/**
     * Handle anonymous tip submission
     */
    public function submitTip(Request $request)
    {
        // Validate form
        $validated = $request->validate([
            'tip_content' => 'required|string|min:10|max:2000',
            'location' => 'nullable|string|max:500',
            'contact_info' => 'nullable|string|max:255',
            'cf-turnstile-response' => 'required'
        ]);

        // Verify CAPTCHA
        $captchaToken = $request->input('cf-turnstile-response');
        if (!$this->verifyCaptcha($captchaToken)) {
            return back()->withErrors(['captcha' => 'Security verification failed. Please try again.'])->withInput();
        }

        // TODO: Save tip to database (if CrimeTip model is created)
        // CrimeTip::create([
        //     'tip_content' => $validated['tip_content'],
        //     'location' => $validated['location'],
        //     'contact_info' => $validated['contact_info'],
        //     'status' => 'pending'
        // ]);

        return back()->with('success', 'Thank you for your tip! We appreciate your help in keeping our community safe.');
    }

    /**
     * Verify Cloudflare Turnstile CAPTCHA
     */
    private function verifyCaptcha($token)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://challenges.cloudflare.com/turnstile/validate', [
                'form_params' => [
                    'secret' => config('captcha.secret'),
                    'response' => $token
                ]
            ]);

            $body = json_decode($response->getBody(), true);
            return $body['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
