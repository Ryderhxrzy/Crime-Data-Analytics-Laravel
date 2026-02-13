<?php

namespace App\Http\Controllers;

use App\Models\CrimeIncident;
use App\Models\Barangay;
use App\Models\CrimeCategory;
use App\Models\CrimeAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

// Include centralized authentication
require_once app_path('auth-include.php');

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
     * Get authenticated user data based on environment
     */
    private function getAuthUser()
    {
        $environment = app()->environment();
        $currentUser = null;
        $userEmail = '';
        $userRole = '';
        $userDepartment = '';
        $departmentName = '';
        
        if ($environment === 'local') {
            // Use Laravel's built-in authentication in local environment
            if (!auth()->check()) {
                return null;
            }
            
            $currentUser = auth()->user();
            $userEmail = $currentUser->email ?? '';
            $userRole = $currentUser->role ?? 'user';
            $userDepartment = $currentUser->department ?? '';
            $departmentName = ucfirst($userDepartment) . ' Department';
        } else {
            // Use JWT authentication in production
            $currentUser = getCurrentUser();
            
            if (!$currentUser) {
                return null;
            }
            
            $userEmail = getUserEmail();
            $userRole = getUserRole();
            $userDepartment = getUserDepartment();
            $departmentName = getDepartmentName();
        }
        
        return [
            'currentUser' => $currentUser,
            'userEmail' => $userEmail,
            'userRole' => $userRole,
            'userDepartment' => $userDepartment,
            'departmentName' => $departmentName
        ];
    }
    
    public function index()
    {
        // Get authenticated user data
        $authData = $this->getAuthUser();
        if (!$authData) {
            return redirect()->route('login');
        }
        
        extract($authData);
        // Total Incidents
        $totalIncidents = CrimeIncident::count();

        // Total Cleared/Uncleared
        $clearedIncidents = CrimeIncident::where('clearance_status', 'cleared')->count();
        $unclearedIncidents = CrimeIncident::where('clearance_status', 'uncleared')->count();
        $clearanceRate = $totalIncidents > 0 ? round(($clearedIncidents / $totalIncidents) * 100, 2) : 0;

        // Active Alerts
        $activeAlerts = CrimeAlert::where('alert_status', 'active')->count();

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
     * Get filtered chart data via AJAX
     */
    public function getChartData(Request $request)
    {
        $year  = $request->get('year', now()->year);
        $month = $request->get('month', null);
        $dayOfWeek = $request->get('day_of_week', null);
        $timeOfDay = $request->get('time_of_day', null);

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

        return response()->json([
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
        ]);
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
        
        // Get initial data for location trends
        $barangays = Barangay::orderBy('barangay_name')->get();
        $crimeCategories = CrimeCategory::orderBy('category_name')->get();
        
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
        
        // Get initial data for crime type trends
        $barangays = \App\Models\Barangay::orderBy('barangay_name')->get();
        $crimeCategories = \App\Models\CrimeCategory::orderBy('category_name')->get();
        
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
}
