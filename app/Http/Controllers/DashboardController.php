<?php

namespace App\Http\Controllers;

use App\Models\CrimeIncident;
use App\Models\Barangay;
use App\Models\CrimeCategory;
use App\Models\CrimeAlert;
use App\Services\CacheService;
use App\Events\CrimeIncidentUpdated;
use App\Events\CrimeIncidentDeleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get main domain URL
     */
    private function getMainDomain()
    {
        return env('MAIN_DOMAIN', 'https://alertaraqc.com');
    }
    
    /**
     * Authenticate user with JWT token from URL and redirect to dashboard
     */
    public function authenticateWithToken($token)
    {
        // Store token in session
        session(['jwt_token' => $token]);
        
        // Redirect to main dashboard
        return redirect()->route('dashboard');
    }
    
    /**
     * Get authenticated user data from session (via JWT API middleware)
     */
    private function getAuthUser()
    {
        // Try to get user from JWT API session first (centralized auth)
        $authUser = session('auth_user');

        if ($authUser) {
            // User authenticated via JWT API
            return [
                'currentUser' => $authUser,
                'userEmail' => $authUser['email'] ?? '',
                'userRole' => $authUser['role'] ?? 'user',
                'userDepartment' => $authUser['department'] ?? '',
                'departmentName' => $authUser['department_name'] ?? 'Department'
            ];
        }

        // Fall back to local Laravel auth if JWT auth is not available
        if (auth()->check()) {
            $currentUser = auth()->user();
            return [
                'currentUser' => $currentUser,
                'userEmail' => $currentUser->email ?? '',
                'userRole' => $currentUser->role ?? 'user',
                'userDepartment' => $currentUser->department ?? '',
                'departmentName' => ucfirst($currentUser->department ?? '') . ' Department'
            ];
        }

        // No authentication found
        return null;
    }
    
    public function index()
    {
        // Get authenticated user data
        $authData = $this->getAuthUser();
        if (!$authData) {
            return redirect()->route('login');
        }

        extract($authData);

        // Get cached dashboard analytics
        $analytics = CacheService::getDashboardAnalytics();
        $totalIncidents = $analytics['totalIncidents'];
        $clearedIncidents = $analytics['clearedIncidents'];
        $unclearedIncidents = $analytics['unclearedIncidents'];
        $clearanceRate = $analytics['clearanceRate'];
        $activeAlerts = $analytics['activeAlerts'];

        // Incidents by Category
        $incidentsByCategory = CrimeIncident::select('crime_category_id', DB::raw('COUNT(*) as count'))
            ->with('category')
            ->groupBy('crime_category_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $categoryLabels = $incidentsByCategory->map(fn($item) => $item->category->category_name ?? 'Unknown')->toArray();
        $categoryData = $incidentsByCategory->map(fn($item) => $item->count)->toArray();

        // Monthly Trends (Last 12 months)
        $monthlyTrends = CrimeIncident::select(
            DB::raw('DATE_FORMAT(incident_date, "%Y-%m") as month'),
            DB::raw('COUNT(*) as count')
        )
            ->where('incident_date', '>=', Carbon::now()->subMonths(12))
            ->groupBy(DB::raw('DATE_FORMAT(incident_date, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        $monthLabels = $monthlyTrends->map(fn($item) => $item->month)->toArray();
        $monthData = $monthlyTrends->map(fn($item) => $item->count)->toArray();

        // Crime Status Distribution
        $statusDistribution = CrimeIncident::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $statusLabels = $statusDistribution->map(fn($item) => ucfirst($item->status))->toArray();
        $statusData = $statusDistribution->map(fn($item) => $item->count)->toArray();

        // Top Barangays by Incidents
        $topBarangays = CrimeIncident::select('barangay_id', DB::raw('COUNT(*) as count'))
            ->with('barangay')
            ->groupBy('barangay_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $barangayLabels = $topBarangays->map(fn($item) => $item->barangay->barangay_name ?? 'Unknown')->toArray();
        $barangayData = $topBarangays->map(fn($item) => $item->count)->toArray();

        // Recent Incidents (Last 10)
        $recentIncidents = CrimeIncident::with('category', 'barangay')
            ->orderByDesc('incident_date')
            ->limit(10)
            ->get();

        // Severity Distribution (from alerts)
        $severityDistribution = CrimeAlert::select('severity', DB::raw('COUNT(*) as count'))
            ->groupBy('severity')
            ->get();

        $severityLabels = $severityDistribution->map(fn($item) => ucfirst($item->severity))->toArray();
        $severityData = $severityDistribution->map(fn($item) => $item->count)->toArray();

        // Clearance Status Pie Chart
        $clearanceData = [
            'cleared' => $clearedIncidents,
            'uncleared' => $unclearedIncidents,
        ];

        // Latest Alerts
        $latestAlerts = CrimeAlert::with('barangay', 'category')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'totalIncidents' => $totalIncidents,
            'clearedIncidents' => $clearedIncidents,
            'unclearedIncidents' => $unclearedIncidents,
            'clearanceRate' => $clearanceRate,
            'activeAlerts' => $activeAlerts,
            'recentIncidents' => $recentIncidents,
            'latestAlerts' => $latestAlerts,
            // User data from JWT authentication
            'currentUser' => $currentUser,
            'userEmail' => getUserEmail(),
            'userRole' => getUserRole(),
            'userDepartment' => getUserDepartment(),
            'departmentName' => getDepartmentName(),
            // Chart data
            'categoryLabels' => json_encode($categoryLabels),
            'categoryData' => json_encode($categoryData),
            'monthLabels' => json_encode($monthLabels),
            'monthData' => json_encode($monthData),
            'statusLabels' => json_encode($statusLabels),
            'statusData' => json_encode($statusData),
            'barangayLabels' => json_encode($barangayLabels),
            'barangayData' => json_encode($barangayData),
            'severityLabels' => json_encode($severityLabels),
            'severityData' => json_encode($severityData),
            'clearanceLabels' => json_encode(['Cleared', 'Uncleared']),
            'clearanceChartData' => json_encode([$clearedIncidents, $unclearedIncidents]),
        ]);
    }

    /**
     * Time-Based Trends Analysis Page
     */
    public function timeBasedTrends()
    {
        // Get authenticated user data
        $authData = $this->getAuthUser();
        if (!$authData) {
            return redirect()->route('login');
        }
        
        extract($authData);

        // Monthly Trends (Last 12 months)
        $monthlyTrends = CrimeIncident::select(
            DB::raw('DATE_FORMAT(incident_date, "%Y-%m") as month'),
            DB::raw('COUNT(*) as count')
        )
            ->where('incident_date', '>=', Carbon::now()->subMonths(12))
            ->groupBy(DB::raw('DATE_FORMAT(incident_date, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        $monthLabels = $monthlyTrends->map(fn($item) => $item->month)->toArray();
        $monthData = $monthlyTrends->map(fn($item) => $item->count)->toArray();

        // Daily Distribution (by day of week)
        $dailyDist = CrimeIncident::select(
            DB::raw('DAYOFWEEK(incident_date) as day_num'),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy(DB::raw('DAYOFWEEK(incident_date)'))
            ->orderBy('day_num')
            ->get();

        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dailyLabels = [];
        $dailyData = [];
        for ($i = 1; $i <= 7; $i++) {
            $dailyLabels[] = $dayNames[$i - 1];
            $dailyData[] = $dailyDist->firstWhere('day_num', $i)?->count ?? 0;
        }

        // Hourly Distribution (by hour)
        $hourlyDist = CrimeIncident::select(
            DB::raw('HOUR(incident_time) as hour'),
            DB::raw('COUNT(*) as count')
        )
            ->whereNotNull('incident_time')
            ->groupBy(DB::raw('HOUR(incident_time)'))
            ->orderBy('hour')
            ->get();

        $hourlyLabels = [];
        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $period = $h < 12 ? 'AM' : 'PM';
            $displayHour = $h === 0 ? 12 : ($h > 12 ? $h - 12 : $h);
            $hourlyLabels[] = $displayHour . ':00 ' . $period;
            $hourlyData[] = $hourlyDist->firstWhere('hour', $h)?->count ?? 0;
        }

        return view('time-based-trends', [
            'monthLabels' => json_encode($monthLabels),
            'monthData' => json_encode($monthData),
            'dailyLabels' => json_encode($dailyLabels),
            'dailyData' => json_encode($dailyData),
            'hourlyLabels' => json_encode($hourlyLabels),
            'hourlyData' => json_encode($hourlyData),
            // User data from JWT authentication
            'currentUser' => $currentUser,
            'userEmail' => getUserEmail(),
            'userRole' => getUserRole(),
            'userDepartment' => getUserDepartment(),
            'departmentName' => getDepartmentName(),
        ]);
    }

    /**
     * Get filtered chart data via AJAX (with Redis caching)
     */
    public function getChartData(Request $request)
    {
        $year  = $request->get('year', now()->year);
        $month = $request->get('month', null);
        $dayOfWeek = $request->get('day_of_week', null);
        $timeOfDay = $request->get('time_of_day', null);

        // Generate cache key
        $cacheKey = CacheService::generateCacheKey('chart_data', [
            'year' => $year,
            'month' => $month,
            'day_of_week' => $dayOfWeek,
            'time_of_day' => $timeOfDay,
        ]);

        // Try to get from cache first
        $cachedData = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cachedData) {
            return response()->json($cachedData);
        }

        // 1. Monthly Crime Trend (by month for the selected year)
        $monthlyTrend = CrimeIncident::select(
                DB::raw('DATE_FORMAT(incident_date, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->whereYear('incident_date', $year);

        if ($month) {
            $monthlyTrend->whereMonth('incident_date', $month);
        }
        if ($dayOfWeek) {
            $monthlyTrend->whereRaw('DAYOFWEEK(incident_date) = ?', [$dayOfWeek]);
        }
        if ($timeOfDay) {
            $this->applyTimeOfDayFilter($monthlyTrend, $timeOfDay);
        }

        $monthlyTrend = $monthlyTrend->groupBy(DB::raw('DATE_FORMAT(incident_date, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        // Build query for time-based charts
        $query = CrimeIncident::query()->whereYear('incident_date', $year);
        if ($month) {
            $query->whereMonth('incident_date', $month);
        }
        if ($dayOfWeek) {
            $query->whereRaw('DAYOFWEEK(incident_date) = ?', [$dayOfWeek]);
        }
        if ($timeOfDay) {
            $this->applyTimeOfDayFilter($query, $timeOfDay);
        }

        // 3. Weekly Distribution (by day of week)
        $weeklyDist = (clone $query)
            ->select(DB::raw('DAYOFWEEK(incident_date) as day_num'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DAYOFWEEK(incident_date)'))
            ->orderBy('day_num')
            ->get();

        // 4. Peak Crime Hours (by hour from incident_time)
        $peakHours = (clone $query)
            ->select(DB::raw('HOUR(incident_time) as hour'), DB::raw('COUNT(*) as count'))
            ->whereNotNull('incident_time')
            ->groupBy(DB::raw('HOUR(incident_time)'))
            ->orderBy('hour')
            ->get();

        // Map day numbers to names
        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $weeklyFormatted = collect(range(1, 7))->map(fn($d) => [
            'day'   => $dayNames[$d - 1],
            'count' => $weeklyDist->firstWhere('day_num', $d)?->count ?? 0,
        ]);

        // Map all 24 hours with 12-hour format
        $hoursFormatted = collect(range(0, 23))->map(fn($h) => [
            'hour'  => ($h === 0 ? 12 : ($h > 12 ? $h - 12 : $h)) . ':00 ' . ($h < 12 ? 'AM' : 'PM'),
            'count' => $peakHours->firstWhere('hour', $h)?->count ?? 0,
        ]);

        // 5. Generate Heatmap Data (Day vs Hour)
        $heatmapData = $this->generateHeatmapData($query);

        $response = [
            'monthlyTrend' => [
                'labels' => $monthlyTrend->pluck('month')->values(),
                'data'   => $monthlyTrend->pluck('count')->values(),
            ],
            'weeklyDist' => [
                'labels' => $weeklyFormatted->pluck('day')->values(),
                'data'   => $weeklyFormatted->pluck('count')->values(),
            ],
            'peakHours' => [
                'labels' => $hoursFormatted->pluck('hour')->values(),
                'data'   => $hoursFormatted->pluck('count')->values(),
            ],
            // Time-focused features data
            'heatmapData' => $heatmapData,
        ];

        // Cache the result for 30 minutes
        \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            now()->addMinutes(CacheService::CHART_TTL),
            function () use ($response) {
                return $response;
            }
        );

        return response()->json($response);
    }

    /**
     * Generate heatmap data (Day vs Hour)
     */
    private function generateHeatmapData($query)
    {
        $heatmapData = [];
        
        // Get data for each day of week and hour
        for ($day = 1; $day <= 7; $day++) {
            $dayData = [];
            for ($hour = 0; $hour < 24; $hour++) {
                $count = (clone $query)
                    ->whereRaw('DAYOFWEEK(incident_date) = ?', [$day])
                    ->whereRaw('HOUR(incident_time) = ?', [$hour])
                    ->count();
                $dayData[] = $count;
            }
            $heatmapData[] = $dayData;
        }
        
        return $heatmapData;
    }

    /**
     * Apply time of day filter to query
     */
    private function applyTimeOfDayFilter(&$query, $timeOfDay)
    {
        switch($timeOfDay) {
            case 'morning':
                $query->whereRaw('HOUR(incident_time) >= 6 AND HOUR(incident_time) < 12');
                break;
            case 'afternoon':
                $query->whereRaw('HOUR(incident_time) >= 12 AND HOUR(incident_time) < 18');
                break;
            case 'evening':
                $query->whereRaw('HOUR(incident_time) >= 18 OR HOUR(incident_time) < 0');
                break;
            case 'night':
                $query->whereRaw('HOUR(incident_time) >= 0 AND HOUR(incident_time) < 6');
                break;
        }
    }

    /**
     * Show location trends page
     */
     public function locationTrends()
    {
        // Get authenticated user data
        $authData = $this->getAuthUser();
        if (!$authData) {
            return redirect()->route('login');
        }

        extract($authData);

        // Get cached hot data
        $barangays = CacheService::getBarangays();
        $crimeCategories = CacheService::getCrimeCategories();

        return view('location-trends', compact('barangays', 'crimeCategories', 'currentUser', 'userEmail', 'userRole', 'userDepartment', 'departmentName'));
    }

    /**
     * Show crime type trends page
     */
    public function crimeTypeTrends()
    {
        // Get authenticated user data
        $authData = $this->getAuthUser();
        if (!$authData) {
            return redirect()->route('login');
        }

        extract($authData);

        // Get cached hot data
        $barangays = CacheService::getBarangays();
        $crimeCategories = CacheService::getCrimeCategories();
        
        // Get crime type statistics
        $crimeTypeStats = CrimeIncident::select('crime_category_id', DB::raw('COUNT(*) as total'))
            ->with('category')
            ->groupBy('crime_category_id')
            ->orderByDesc('total')
            ->get();

        // Get monthly crime type trends
        $monthlyTrends = CrimeIncident::select(
                'crime_category_id',
                DB::raw('DATE_FORMAT(incident_date, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->with('category')
            ->where('incident_date', '>=', Carbon::now()->subMonths(6))
            ->groupBy('crime_category_id', 'month')
            ->orderBy('month')
            ->orderBy('count', 'desc')
            ->get();

        // Get crime type breakdown by location
        $locationCrimeBreakdown = CrimeIncident::select(
                'barangay_id',
                'crime_category_id',
                DB::raw('COUNT(*) as count')
            )
            ->with(['barangay', 'category'])
            ->groupBy('barangay_id', 'crime_category_id')
            ->orderBy('barangay_id')
            ->orderBy('count', 'desc')
            ->get()
            ->groupBy('barangay_id');

        // Transform data for easier use in view
        $locationData = [];
        foreach ($locationCrimeBreakdown as $barangayId => $incidents) {
            $locationName = $incidents->first()->barangay->barangay_name ?? 'Unknown';
            $locationData[$barangayId] = [
                'name' => $locationName,
                'theft' => 0,
                'assault' => 0,
                'vandalism' => 0,
                'burglary' => 0,
                'fraud' => 0,
                'total' => 0
            ];
            
            foreach ($incidents as $incident) {
                $categoryName = strtolower($incident->category->category_name ?? 'other');
                $count = $incident->count;
                
                if (str_contains($categoryName, 'theft')) {
                    $locationData[$barangayId]['theft'] = $count;
                } elseif (str_contains($categoryName, 'assault')) {
                    $locationData[$barangayId]['assault'] = $count;
                } elseif (str_contains($categoryName, 'vandalism')) {
                    $locationData[$barangayId]['vandalism'] = $count;
                } elseif (str_contains($categoryName, 'burglary')) {
                    $locationData[$barangayId]['burglary'] = $count;
                } elseif (str_contains($categoryName, 'fraud')) {
                    $locationData[$barangayId]['fraud'] = $count;
                }
                
                $locationData[$barangayId]['total'] += $count;
            }
        }

        return view('crime-type-trends', compact('barangays', 'crimeCategories', 'crimeTypeStats', 'monthlyTrends', 'locationData', 'currentUser', 'userEmail', 'userRole', 'userDepartment', 'departmentName'));
    }

    /**
     * Get filtered location chart data via AJAX
     */
    public function getLocationChartData(Request $request)
    {
        try {
            $barangay = $request->get('barangay');
            $crimeType = $request->get('crime_type');
            $timePeriod = $request->get('time_period');
            $riskLevel = $request->get('risk_level');
            $dateRange = $request->get('date_range');

            // Build base query
            $query = CrimeIncident::query()->with(['barangay', 'category']);

            // Apply filters
            if ($barangay && $barangay !== '') {
                $query->where('barangay_id', $barangay);
            }
            
            if ($crimeType && $crimeType !== '') {
                $query->where('crime_category_id', $crimeType);
            }
            
            // Apply date range filter
            if ($dateRange && $dateRange !== '') {
                switch($dateRange) {
                    case 'today':
                        $query->whereDate('incident_date', Carbon::today());
                        break;
                    case '7days':
                        $query->whereBetween('incident_date', [Carbon::now()->subDays(7), Carbon::now()]);
                        break;
                    case '30days':
                        $query->whereBetween('incident_date', [Carbon::now()->subDays(30), Carbon::now()]);
                        break;
                    case 'thismonth':
                        $query->whereMonth('incident_date', Carbon::now()->month)
                              ->whereYear('incident_date', Carbon::now()->year);
                        break;
                }
            } elseif ($timePeriod && $timePeriod !== '') {
                switch($timePeriod) {
                    case '7':
                        $query->whereBetween('incident_date', [Carbon::now()->subDays(7), Carbon::now()]);
                        break;
                    case '30':
                        $query->whereBetween('incident_date', [Carbon::now()->subDays(30), Carbon::now()]);
                        break;
                    case '90':
                        $query->whereBetween('incident_date', [Carbon::now()->subDays(90), Carbon::now()]);
                        break;
                    case '365':
                        $query->whereBetween('incident_date', [Carbon::now()->subYear(), Carbon::now()]);
                        break;
                }
            }

            // 1. Location comparison data
            $locationComparison = (clone $query)
                ->select('barangay_id', DB::raw('COUNT(*) as total'))
                ->with('barangay')
                ->groupBy('barangay_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            // 2. Crime type by location data
            $crimeTypeData = (clone $query)
                ->select('crime_category_id', DB::raw('COUNT(*) as count'))
                ->with('category')
                ->groupBy('crime_category_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            // 3. Generate heatmap data
            $heatmapData = $this->generateLocationHeatmapData($query);

            // 4. Risk level analysis
            $riskAnalysis = $this->analyzeRiskLevels($query);

            return response()->json([
                'success' => true,
                'locationComparison' => [
                    'labels' => $locationComparison->map(fn($item) => $item->barangay->barangay_name ?? 'Unknown')->values(),
                    'data' => $locationComparison->pluck('total')->values(),
                ],
                'crimeTypeData' => [
                    'labels' => $crimeTypeData->map(fn($item) => $item->category->category_name ?? 'Unknown')->values(),
                    'data' => $crimeTypeData->pluck('count')->values(),
                ],
                'heatmapData' => $heatmapData,
                'riskAnalysis' => $riskAnalysis,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate location heatmap data
     */
    private function generateLocationHeatmapData($query)
    {
        $locationData = (clone $query)
            ->select('barangay_id', DB::raw('COUNT(*) as incident_count'))
            ->with('barangay')
            ->groupBy('barangay_id')
            ->get();

        $heatmapPoints = [];
        $x = 10;
        $y = 10;
        
        foreach ($locationData as $location) {
            $heatmapPoints[] = [
                'x' => $x,
                'y' => $y,
                'v' => $location->incident_count,
                'label' => $location->barangay->barangay_name ?? 'Unknown'
            ];
            
            $x += 20;
            $y += 15;
            if ($x > 90) {
                $x = 10;
                $y = 10;
            }
        }

        return $heatmapPoints;
    }


    /**
     * Analyze risk levels by location
     */
    private function analyzeRiskLevels($query)
    {
        $locationStats = (clone $query)
            ->select('barangay_id', DB::raw('COUNT(*) as total'))
            ->with('barangay')
            ->groupBy('barangay_id')
            ->orderByDesc('total')
            ->get();

        $riskLevels = [];
        foreach ($locationStats as $stat) {
            $risk = 'low';
            if ($stat->total >= 10) {
                $risk = 'high';
            } elseif ($stat->total >= 5) {
                $risk = 'medium';
            }

            $riskLevels[] = [
                'barangay' => $stat->barangay->barangay_name ?? 'Unknown',
                'incidents' => $stat->total,
                'risk_level' => $risk
            ];
        }

        return $riskLevels;
    }

    /**
     * Show crime hotspot analysis page
     */
    public function crimeHotspot()
    {
        // Get authenticated user data
        $authData = $this->getAuthUser();
        if (!$authData) {
            return redirect()->route('login');
        }
        
        extract($authData);
        
        $barangays = Barangay::orderBy('barangay_name')->get();
        $crimeCategories = CrimeCategory::orderBy('category_name')->get();
        
        return view('crime-hotspot', compact('barangays', 'crimeCategories', 'currentUser', 'userEmail', 'userRole', 'userDepartment', 'departmentName'));
    }

    /**
     * Show risk forecasting page
     */
    public function riskForecasting()
    {
        // Get authenticated user data
        $authData = $this->getAuthUser();
        if (!$authData) {
            return redirect()->route('login');
        }
        
        extract($authData);
        
        $barangays = Barangay::orderBy('barangay_name')->get();
        $crimeCategories = CrimeCategory::orderBy('category_name')->get();
        
        return view('risk-forecasting', compact('barangays', 'crimeCategories', 'currentUser', 'userEmail', 'userRole', 'userDepartment', 'departmentName'));
    }

    /**
     * Show pattern detection page
     */
    public function patternDetection()
    {
        // Get authenticated user data
        $authData = $this->getAuthUser();
        if (!$authData) {
            return redirect()->route('login');
        }

        extract($authData);

        $barangays = Barangay::orderBy('barangay_name')->get();
        $crimeCategories = CrimeCategory::orderBy('category_name')->get();

        return view('pattern-detection', compact('barangays', 'crimeCategories', 'currentUser', 'userEmail', 'userRole', 'userDepartment', 'departmentName'));
    }

    /**
     * Get hotspot data with analytics for Crime Hotspot Analysis page (with Redis caching)
     */
    public function getHotspotData(Request $request)
    {
        try {
            // Get filters
            $timePeriod = $request->query('timePeriod', 'all');
            $crimeType = $request->query('crimeType', '');
            $barangay = $request->query('barangay', '');

            // Generate cache key
            $cacheKey = CacheService::generateCacheKey('hotspot_data', [
                'timePeriod' => $timePeriod,
                'crimeType' => $crimeType,
                'barangay' => $barangay,
            ]);

            // Try to get from cache first
            $cachedData = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($cachedData) {
                return response()->json($cachedData);
            }

            // Build base query for getting crimes
            $crimeQuery = CrimeIncident::with(['category', 'barangay'])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude');

            // Apply time period filter
            if ($timePeriod !== 'all') {
                $days = (int)$timePeriod;
                $crimeQuery->where('incident_date', '>=', Carbon::now()->subDays($days));
            }

            // Apply crime type filter
            if (!empty($crimeType)) {
                $crimeQuery->where('crime_category_id', $crimeType);
            }

            // Apply barangay filter
            if (!empty($barangay)) {
                $crimeQuery->where('barangay_id', $barangay);
            }

            // Get all crimes with associated data
            $crimes = $crimeQuery->get()
                ->map(function($crime) {
                    return [
                        'id' => $crime->id,
                        'incident_title' => $crime->incident_title,
                        'incident_date' => $crime->incident_date?->format('Y-m-d'),
                        'latitude' => (float)$crime->latitude,
                        'longitude' => (float)$crime->longitude,
                        'barangay_name' => $crime->barangay?->barangay_name ?? 'Unknown',
                        'barangay_id' => $crime->barangay_id,
                        'crime_category_id' => $crime->crime_category_id
                    ];
                });

            // Calculate hotspots by barangay
            $hotspots = [];
            $barangayGroups = $crimes->groupBy('barangay_name');

            foreach ($barangayGroups as $barangayName => $incidents) {
                if ($incidents->count() > 0) {
                    // Calculate density score (0-10)
                    $incidentCount = $incidents->count();
                    $densityScore = min(10, ($incidentCount / 50) * 10);

                    // Calculate average coordinates
                    $avgLat = $incidents->average('latitude');
                    $avgLng = $incidents->average('longitude');

                    // Determine trend direction
                    $trendDirection = $this->calculateTrendDirection($incidents->first()['barangay_id'], $timePeriod, $crimeType);

                    $hotspots[] = [
                        'area_name' => $barangayName,
                        'incident_count' => $incidentCount,
                        'density_score' => round($densityScore, 2),
                        'latitude' => $avgLat,
                        'longitude' => $avgLng,
                        'trend_direction' => $trendDirection
                    ];
                }
            }

            // Sort by density score descending
            usort($hotspots, function($a, $b) {
                return $b['density_score'] <=> $a['density_score'];
            });

            // Calculate monthly trends
            $monthlyTrends = $this->getMonthlyTrends($timePeriod, $crimeType, $barangay);

            // Calculate crime type distribution
            $typeDistribution = $this->getCrimeTypeDistribution($timePeriod, $crimeType, $barangay);

            $response = [
                'crimes' => $crimes,
                'hotspots' => $hotspots,
                'monthly_trends' => $monthlyTrends,
                'type_distribution' => $typeDistribution
            ];

            // Cache the result for 20 minutes
            \Illuminate\Support\Facades\Cache::remember(
                $cacheKey,
                now()->addMinutes(CacheService::HOTSPOT_TTL),
                function () use ($response) {
                    return $response;
                }
            );

            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('Error in getHotspotData: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading hotspot data', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Calculate trend direction for a barangay
     */
    private function calculateTrendDirection($barangayId, $timePeriod, $crimeType)
    {
        // Current period count
        $currentQuery = CrimeIncident::where('barangay_id', $barangayId);
        if ($timePeriod !== 'all') {
            $days = (int)$timePeriod;
            $currentQuery->where('incident_date', '>=', Carbon::now()->subDays($days));
        }
        if (!empty($crimeType)) {
            $currentQuery->where('crime_category_id', $crimeType);
        }
        $currentCount = $currentQuery->count();

        // Previous period count
        $previousQuery = CrimeIncident::where('barangay_id', $barangayId);
        if ($timePeriod !== 'all') {
            $days = (int)$timePeriod;
            $previousQuery->whereBetween('incident_date', [
                Carbon::now()->subDays($days * 2),
                Carbon::now()->subDays($days)
            ]);
        }
        if (!empty($crimeType)) {
            $previousQuery->where('crime_category_id', $crimeType);
        }
        $previousCount = $previousQuery->count();

        if ($previousCount === 0 || $currentCount === 0) return 'stable';

        $percentChange = (($currentCount - $previousCount) / $previousCount) * 100;

        if ($percentChange > 10) {
            return 'increasing';
        } elseif ($percentChange < -10) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    /**
     * Get monthly trend data for the last 12 months
     */
    private function getMonthlyTrends($timePeriod, $crimeType, $barangay)
    {
        $months = [];
        $values = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $month = $date->format('M');
            $months[] = $month;

            $query = CrimeIncident::query()
                ->whereYear('incident_date', $date->year)
                ->whereMonth('incident_date', $date->month);

            if ($timePeriod !== 'all') {
                $days = (int)$timePeriod;
                $query->where('incident_date', '>=', Carbon::now()->subDays($days));
            }

            if (!empty($crimeType)) {
                $query->where('crime_category_id', $crimeType);
            }

            if (!empty($barangay)) {
                $query->where('barangay_id', $barangay);
            }

            $count = $query->count();
            $values[] = $count;
        }

        return [
            'labels' => $months,
            'values' => $values
        ];
    }

    /**
     * Get crime type distribution in hotspots
     */
    private function getCrimeTypeDistribution($timePeriod, $crimeType, $barangay)
    {
        $query = CrimeIncident::query();

        if ($timePeriod !== 'all') {
            $days = (int)$timePeriod;
            $query->where('incident_date', '>=', Carbon::now()->subDays($days));
        }

        if (!empty($crimeType)) {
            $query->where('crime_category_id', $crimeType);
        }

        if (!empty($barangay)) {
            $query->where('barangay_id', $barangay);
        }

        $distribution = $query->select('crime_category_id', DB::raw('COUNT(*) as count'))
            ->groupBy('crime_category_id')
            ->get();

        $labels = [];
        $values = [];

        foreach ($distribution as $item) {
            $category = CrimeCategory::find($item->crime_category_id);
            if ($category) {
                $labels[] = $category->category_name;
                $values[] = $item->count;
            }
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    /**
     * Get pattern detection data with linked crimes and analysis
     */
    public function getPatternData(Request $request)
    {
        try {
            // Get filters
            $timePeriod = $request->query('timePeriod', 'all');
            $crimeType = $request->query('crimeType', '');
            $barangay = $request->query('barangay', '');

            // Build base query
            $query = CrimeIncident::with(['category', 'barangay'])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude');

            // Apply time period filter
            if ($timePeriod !== 'all') {
                $days = (int)$timePeriod;
                $query->where('incident_date', '>=', Carbon::now()->subDays($days));
            }

            // Apply crime type filter
            if (!empty($crimeType)) {
                $query->where('crime_category_id', $crimeType);
            }

            // Apply barangay filter
            if (!empty($barangay)) {
                $query->where('barangay_id', $barangay);
            }

            // Get all crimes
            $crimes = $query->orderByDesc('incident_date')->get();

            // Detect patterns
            $patterns = [];
            $patternId = 0;

            // 1. Group by Modus Operandi (MO Patterns)
            $moPatterns = $crimes->whereNotNull('modus_operandi')
                ->groupBy('modus_operandi');

            foreach ($moPatterns as $mo => $crimeGroup) {
                if ($crimeGroup->count() >= 2) { // Pattern needs at least 2 incidents
                    $patternId++;
                    $crimeIds = $crimeGroup->pluck('id')->toArray();
                    $avgLat = $crimeGroup->average('latitude');
                    $avgLng = $crimeGroup->average('longitude');

                    $patterns[] = [
                        'id' => 'mo_' . $patternId,
                        'type' => 'modus_operandi',
                        'crime_ids' => $crimeIds,
                        'strength' => min(100, ($crimeGroup->count() / $crimes->count()) * 100),
                        'barangay' => $crimeGroup->first()->barangay->barangay_name ?? 'Multiple',
                        'centroid_lat' => round($avgLat, 6),
                        'centroid_lng' => round($avgLng, 6),
                        'description' => substr($mo, 0, 50) . (strlen($mo) > 50 ? '...' : ''),
                        'incident_count' => $crimeGroup->count()
                    ];
                }
            }

            // 2. Group by Location + Crime Type (Spatial Patterns)
            $locationPatterns = $crimes->groupBy(function($crime) {
                return $crime->barangay_id . '_' . $crime->crime_category_id;
            });

            foreach ($locationPatterns as $key => $crimeGroup) {
                if ($crimeGroup->count() >= 3) { // Need 3+ incidents for pattern
                    $patternId++;
                    $crimeIds = $crimeGroup->pluck('id')->toArray();
                    $avgLat = $crimeGroup->average('latitude');
                    $avgLng = $crimeGroup->average('longitude');

                    $patterns[] = [
                        'id' => 'spatial_' . $patternId,
                        'type' => 'location_cluster',
                        'crime_ids' => $crimeIds,
                        'strength' => min(100, ($crimeGroup->count() / $crimes->count()) * 100),
                        'barangay' => $crimeGroup->first()->barangay->barangay_name ?? 'Unknown',
                        'centroid_lat' => round($avgLat, 6),
                        'centroid_lng' => round($avgLng, 6),
                        'description' => $crimeGroup->first()->category->category_name . ' Cluster',
                        'incident_count' => $crimeGroup->count()
                    ];
                }
            }

            // Map crimes with pattern IDs
            $crimeWithPatterns = $crimes->map(function($crime) use ($patterns) {
                $patternId = null;
                foreach ($patterns as $pattern) {
                    if (in_array($crime->id, $pattern['crime_ids'])) {
                        $patternId = $pattern['id'];
                        break;
                    }
                }

                return [
                    'id' => $crime->id,
                    'incident_title' => $crime->incident_title,
                    'incident_date' => $crime->incident_date?->format('Y-m-d'),
                    'incident_time' => $crime->incident_time,
                    'latitude' => (float)$crime->latitude,
                    'longitude' => (float)$crime->longitude,
                    'barangay_name' => $crime->barangay?->barangay_name ?? 'Unknown',
                    'barangay_id' => $crime->barangay_id,
                    'crime_category_id' => $crime->crime_category_id,
                    'category_name' => $crime->category?->category_name ?? 'Unknown',
                    'category_color' => $crime->category?->color_code ?? '#6B7280',
                    'modus_operandi' => $crime->modus_operandi ?? 'Not specified',
                    'status' => $crime->status,
                    'clearance_status' => $crime->clearance_status,
                    'pattern_id' => $patternId
                ];
            })->toArray();

            // 3. Modus Operandi Statistics
            $moStats = $crimes->whereNotNull('modus_operandi')
                ->groupBy('modus_operandi')
                ->map(function($group) use ($crimes) {
                    $count = $group->count();
                    $affectedBarangays = $group->groupBy('barangay_id')->count();
                    return [
                        'modus' => $group->first()->modus_operandi,
                        'count' => $count,
                        'percentage' => round(($count / $crimes->count()) * 100, 2),
                        'affected_barangays' => $affectedBarangays,
                        'severity' => $count > 5 ? 'high' : ($count > 2 ? 'medium' : 'low')
                    ];
                })
                ->sortByDesc('count')
                ->values()
                ->toArray();

            // 4. Monthly Trend (Patterns detected over time)
            $monthlyTrends = [];
            for ($i = 11; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $monthStr = $date->format('M');

                // Get crimes for this month
                $monthCrimes = $crimes->filter(function($crime) use ($date) {
                    return $crime->incident_date &&
                        $crime->incident_date->format('Y-m') === $date->format('Y-m');
                });

                // Count patterns detected in this month
                $patternsCount = 0;
                foreach ($patterns as $pattern) {
                    foreach ($pattern['crime_ids'] as $crimeId) {
                        if ($monthCrimes->contains('id', $crimeId)) {
                            $patternsCount++;
                        }
                    }
                }

                $monthlyTrends[] = [
                    'month' => $monthStr,
                    'patterns_detected' => ceil($patternsCount / 2), // Avoid double counting
                    'incidents' => $monthCrimes->count()
                ];
            }

            // 5. Pattern Summary
            $summary = [
                'total_incidents' => $crimes->count(),
                'patterns_detected' => count($patterns),
                'top_modus' => $moStats[0]['modus'] ?? 'None',
                'high_risk_areas' => count(array_filter($patterns, fn($p) => $p['strength'] > 50)),
                'affected_barangays' => $crimes->groupBy('barangay_id')->count()
            ];

            return response()->json([
                'crimes' => $crimeWithPatterns,
                'patterns' => $patterns,
                'modus_operandi_stats' => $moStats,
                'monthly_trends' => $monthlyTrends,
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getPatternData: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading pattern data', 'message' => $e->getMessage()], 500);
        }
    }
}
