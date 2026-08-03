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
        $crimeType = $request->get('crime_type', null);
        $dayType = $request->get('day_type', null); // weekday | weekend

        // Generate cache key
        $cacheKey = CacheService::generateCacheKey('chart_data', [
            'year' => $year,
            'month' => $month,
            'day_of_week' => $dayOfWeek,
            'time_of_day' => $timeOfDay,
            'crime_type' => $crimeType,
            'day_type' => $dayType,
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
        if ($crimeType) {
            $monthlyTrend->where('crime_category_id', $crimeType);
        }
        if ($dayType) {
            $this->applyDayTypeFilter($monthlyTrend, $dayType);
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
        if ($crimeType) {
            $query->where('crime_category_id', $crimeType);
        }
        if ($dayType) {
            $this->applyDayTypeFilter($query, $dayType);
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

        // 5. Crime Types Distribution
        $crimeTypes = (clone $query)
            ->join('crime_department_crime_categories', 'crime_department_crime_incidents.crime_category_id', '=', 'crime_department_crime_categories.id')
            ->select('crime_department_crime_categories.category_name', DB::raw('COUNT(*) as count'))
            ->groupBy('crime_department_crime_categories.id', 'crime_department_crime_categories.category_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // 6. Generate Heatmap Data (Day vs Hour)
        $heatmapData = $this->generateHeatmapData($query);

        $response = [
            'monthlyTrend' => [
                'labels' => $monthlyTrend->pluck('month')->values(),
                'data'   => $monthlyTrend->pluck('count')->values(),
            ],
            'crimeTypes' => [
                'labels' => $crimeTypes->pluck('category_name')->values(),
                'data'   => $crimeTypes->pluck('count')->values(),
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
     * Apply weekday/weekend filter to query (MySQL DAYOFWEEK: 1=Sun, 7=Sat)
     */
    private function applyDayTypeFilter(&$query, $dayType)
    {
        if ($dayType === 'weekday') {
            $query->whereRaw('DAYOFWEEK(incident_date) BETWEEN 2 AND 6');
        } elseif ($dayType === 'weekend') {
            $query->whereRaw('DAYOFWEEK(incident_date) IN (1, 7)');
        }
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

        // Query directly so a stale/empty cache can never blank out the filters
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

        // Query directly so a stale/empty cache can never blank out the filters.
        // All chart/table data is fetched client-side from getCrimeTypeTrendsData(),
        // which groups by the real crime_categories table rather than fixed buckets.
        $barangays = Barangay::orderBy('barangay_name')->get();
        $crimeCategories = CrimeCategory::orderBy('category_name')->get();

        return view('crime-type-trends', compact('barangays', 'crimeCategories', 'currentUser', 'userEmail', 'userRole', 'userDepartment', 'departmentName'));
    }

    /**
     * Anchor date for all relative time windows.
     *
     * Windows are measured back from the most recent recorded incident rather
     * than today's date, so trend/period analytics still return results when the
     * dataset lags behind the current date.
     */
    private function referenceDate(): Carbon
    {
        $latest = CrimeIncident::max('incident_date');

        return $latest ? Carbon::parse($latest) : Carbon::now();
    }

    /**
     * Read a filter value as a string.
     *
     * The ConvertEmptyStringsToNull middleware turns "?barangay=" into null, so a
     * plain `$request->get('barangay', '')` yields null (the key exists) and any
     * `!== ''` check then wrongly applies a `WHERE col IS NULL` clause. Always
     * normalise filter inputs through this helper.
     */
    private function filterValue(Request $request, string $key, string $default = ''): string
    {
        $value = $request->input($key);

        return ($value === null || $value === '') ? $default : (string) $value;
    }

    /**
     * Crime type trends data: distribution, monthly series per category,
     * severity breakdown, and per-location category comparison.
     */
    public function getCrimeTypeTrendsData(Request $request)
    {
        try {
            $timePeriod = $this->filterValue($request, 'time_period', 'all');
            $barangayId = $this->filterValue($request, 'barangay');
            $categoryId = $this->filterValue($request, 'category');
            $status = $this->filterValue($request, 'status');
            $clearance = $this->filterValue($request, 'clearance');

            $reference = $this->referenceDate();

            $applyFilters = function ($query) use ($timePeriod, $barangayId, $categoryId, $status, $clearance, $reference) {
                if ($timePeriod !== 'all') {
                    $query->where('incident_date', '>=', $reference->copy()->subDays((int) $timePeriod)->toDateString());
                }
                if ($barangayId !== '') {
                    $query->where('barangay_id', $barangayId);
                }
                if ($categoryId !== '') {
                    $query->where('crime_category_id', $categoryId);
                }
                if ($status !== '') {
                    $query->where('status', $status);
                }
                if ($clearance !== '') {
                    $query->where('clearance_status', $clearance);
                }
            };

            // 1. Distribution per category
            $distribution = CrimeIncident::with('category')
                ->select('crime_category_id', DB::raw('COUNT(*) as total'))
                ->tap($applyFilters)
                ->groupBy('crime_category_id')
                ->orderByDesc('total')
                ->get();

            $distributionOut = [
                'labels' => $distribution->map(fn ($d) => $d->category->category_name ?? 'Unknown')->values(),
                'values' => $distribution->pluck('total')->values(),
                'colors' => $distribution->map(fn ($d) => $d->category->color_code ?? '#6b7280')->values(),
            ];

            // 2. Monthly series (last 6 months) for the top 5 categories
            $topCategoryIds = $distribution->take(5)->pluck('crime_category_id');
            $monthLabels = [];
            for ($i = 5; $i >= 0; $i--) {
                $monthLabels[] = $reference->copy()->subMonths($i)->format('M Y');
            }

            $trendDatasets = [];
            foreach ($topCategoryIds as $catId) {
                $row = $distribution->firstWhere('crime_category_id', $catId);
                $values = [];
                for ($i = 5; $i >= 0; $i--) {
                    $date = $reference->copy()->subMonths($i);
                    $values[] = CrimeIncident::where('crime_category_id', $catId)
                        ->whereYear('incident_date', $date->year)
                        ->whereMonth('incident_date', $date->month)
                        ->tap(function ($q) use ($barangayId, $status, $clearance) {
                            if ($barangayId !== '') {
                                $q->where('barangay_id', $barangayId);
                            }
                            if ($status !== '') {
                                $q->where('status', $status);
                            }
                            if ($clearance !== '') {
                                $q->where('clearance_status', $clearance);
                            }
                        })
                        ->count();
                }
                $trendDatasets[] = [
                    'name' => $row->category->category_name ?? 'Unknown',
                    'color' => $row->category->color_code ?? '#6b7280',
                    'values' => $values,
                ];
            }

            // 3. Severity breakdown (via the category's severity level)
            $severityCounts = CrimeIncident::join('crime_department_crime_categories as cat', 'crime_department_crime_incidents.crime_category_id', '=', 'cat.id')
                ->select('cat.severity_level', DB::raw('COUNT(*) as total'))
                ->tap($applyFilters)
                ->groupBy('cat.severity_level')
                ->pluck('total', 'severity_level');

            $severityOut = [
                'low' => (int) ($severityCounts['low'] ?? 0),
                'medium' => (int) ($severityCounts['medium'] ?? 0),
                'high' => (int) ($severityCounts['high'] ?? 0),
                'critical' => (int) ($severityCounts['critical'] ?? 0),
            ];

            // 4. Trending up/down: most recent 30 days of data vs the 30 days before it
            $trendWindowStart = $reference->copy()->subDays(30)->toDateString();
            $trendWindowPrevStart = $reference->copy()->subDays(60)->toDateString();

            $currentMonthCounts = CrimeIncident::select('crime_category_id', DB::raw('COUNT(*) as total'))
                ->where('incident_date', '>=', $trendWindowStart)
                ->groupBy('crime_category_id')->pluck('total', 'crime_category_id');
            $previousMonthCounts = CrimeIncident::select('crime_category_id', DB::raw('COUNT(*) as total'))
                ->whereBetween('incident_date', [$trendWindowPrevStart, $trendWindowStart])
                ->groupBy('crime_category_id')->pluck('total', 'crime_category_id');

            $changes = [];
            foreach ($distribution as $row) {
                $current = (int) ($currentMonthCounts[$row->crime_category_id] ?? 0);
                $previous = (int) ($previousMonthCounts[$row->crime_category_id] ?? 0);
                $changes[] = [
                    'name' => $row->category->category_name ?? 'Unknown',
                    'change' => $current - $previous,
                ];
            }
            usort($changes, fn ($a, $b) => $b['change'] <=> $a['change']);
            $trendingUp = $changes && $changes[0]['change'] > 0 ? $changes[0]['name'] : 'None';
            $lastChange = end($changes);
            $trendingDown = $changes && $lastChange['change'] < 0 ? $lastChange['name'] : 'None';

            // 5. Per-location breakdown for the top 5 categories (top 8 barangays)
            $topBarangays = CrimeIncident::with('barangay')
                ->select('barangay_id', DB::raw('COUNT(*) as total'))
                ->tap($applyFilters)
                ->groupBy('barangay_id')
                ->orderByDesc('total')
                ->limit(8)
                ->get();

            $locationLabels = $topBarangays->map(fn ($b) => $b->barangay->barangay_name ?? 'Unknown')->values();
            $locationTotals = $topBarangays->pluck('total')->map(fn ($t) => (int) $t)->values();
            $locationDatasets = [];
            foreach ($topCategoryIds as $catId) {
                $row = $distribution->firstWhere('crime_category_id', $catId);
                $values = [];
                foreach ($topBarangays as $b) {
                    $values[] = CrimeIncident::where('barangay_id', $b->barangay_id)
                        ->where('crime_category_id', $catId)
                        ->tap($applyFilters)
                        ->count();
                }
                $locationDatasets[] = [
                    'name' => $row->category->category_name ?? 'Unknown',
                    'color' => $row->category->color_code ?? '#6b7280',
                    'values' => $values,
                ];
            }

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_types' => $distribution->count(),
                    'most_common' => $distributionOut['labels'][0] ?? 'None',
                    'trending_up' => $trendingUp,
                    'trending_down' => $trendingDown,
                ],
                'distribution' => $distributionOut,
                'monthly' => ['labels' => $monthLabels, 'datasets' => $trendDatasets],
                'severity' => $severityOut,
                'by_location' => [
                    'labels' => $locationLabels,
                    'totals' => $locationTotals,
                    'datasets' => $locationDatasets,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getCrimeTypeTrendsData: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Location trends data: per-barangay current-vs-previous comparison,
     * monthly time series for the top areas, and quarterly seasonal breakdown.
     */
    public function getLocationTrendsData(Request $request)
    {
        try {
            $timePeriod = $this->filterValue($request, 'time_period', 'all');
            $barangayId = $this->filterValue($request, 'barangay');
            $crimeType = $this->filterValue($request, 'crime_type');
            $caseStatus = $this->filterValue($request, 'case_status');

            $applyFilters = function ($query) use ($barangayId, $crimeType, $caseStatus) {
                if ($barangayId !== '') {
                    $query->where('barangay_id', $barangayId);
                }
                if ($crimeType !== '') {
                    $query->where('crime_category_id', $crimeType);
                }
                if ($caseStatus !== '') {
                    $query->where('status', $caseStatus);
                }
            };

            if ($timePeriod === 'all') {
                // All Time: count everything, and derive the trend by splitting the
                // full recorded span in half (second half vs first half).
                $earliest = CrimeIncident::min('incident_date');
                $latest = CrimeIncident::max('incident_date');

                $currentStart = null; // no lower bound for counting
                if ($earliest && $latest) {
                    $earliestDate = Carbon::parse($earliest);
                    $latestDate = Carbon::parse($latest);
                    $midpoint = $earliestDate->copy()->addDays((int) ($earliestDate->diffInDays($latestDate) / 2));
                    $trendCurrentStart = $midpoint->toDateString();
                    $trendPreviousStart = $earliestDate->toDateString();
                    $windowDays = (int) $midpoint->diffInDays($latestDate);
                } else {
                    $trendCurrentStart = null;
                    $trendPreviousStart = null;
                    $windowDays = 0;
                }
            } else {
                // Fixed window measured back from the latest recorded incident
                $windowDays = (int) $timePeriod;
                $reference = $this->referenceDate();
                $currentStart = $reference->copy()->subDays($windowDays)->toDateString();
                $trendCurrentStart = $currentStart;
                $trendPreviousStart = $reference->copy()->subDays($windowDays * 2)->toDateString();
            }

            // Counts per barangay for the selected period
            $currentQuery = CrimeIncident::with('barangay')
                ->select('barangay_id', DB::raw('COUNT(*) as total'))
                ->tap($applyFilters);
            if ($currentStart !== null) {
                $currentQuery->where('incident_date', '>=', $currentStart);
            }
            $currentCounts = $currentQuery->groupBy('barangay_id')->get();

            // Comparison counts used only for the trend direction
            $trendCurrent = collect();
            $previousCounts = collect();
            if ($trendCurrentStart && $trendPreviousStart) {
                $trendCurrent = CrimeIncident::select('barangay_id', DB::raw('COUNT(*) as total'))
                    ->where('incident_date', '>=', $trendCurrentStart)
                    ->tap($applyFilters)
                    ->groupBy('barangay_id')
                    ->pluck('total', 'barangay_id');

                $previousCounts = CrimeIncident::select('barangay_id', DB::raw('COUNT(*) as total'))
                    ->whereBetween('incident_date', [$trendPreviousStart, $trendCurrentStart])
                    ->tap($applyFilters)
                    ->groupBy('barangay_id')
                    ->pluck('total', 'barangay_id');
            }

            $locations = [];
            foreach ($currentCounts as $row) {
                $current = (int) $row->total;
                // The trend compares two equal windows; the display count above is
                // the total for the whole selected period, so keep them separate.
                $trendNow = (int) ($trendCurrent[$row->barangay_id] ?? 0);
                $trendBefore = (int) ($previousCounts[$row->barangay_id] ?? 0);

                if ($trendBefore > 0) {
                    $changePercent = (int) round(($trendNow - $trendBefore) / $trendBefore * 100);
                } elseif ($trendNow > 0) {
                    $changePercent = 100; // new activity where there was none
                } else {
                    $changePercent = 0;
                }

                // A barangay with no incidents in either comparison window is not a
                // meaningful trend - flag it so it can't win "most stable".
                $hasTrendData = ($trendNow + $trendBefore) > 0;

                $locations[] = [
                    'barangay_id' => $row->barangay_id,
                    'name' => $row->barangay->barangay_name ?? 'Unknown',
                    'current' => $current,
                    'trend_current' => $trendNow,
                    'trend_previous' => $trendBefore,
                    'previous' => $trendBefore,
                    'change_percent' => $changePercent,
                    'has_trend_data' => $hasTrendData,
                    'trend' => $changePercent > 10 ? 'increasing' : ($changePercent < -10 ? 'decreasing' : 'stable'),
                ];
            }

            usort($locations, fn ($a, $b) => $b['current'] <=> $a['current']);

            // Monthly time series for the top 5 barangays, ending at the latest recorded incident
            $reference = $this->referenceDate();
            $topBarangayIds = array_column(array_slice($locations, 0, 5), 'barangay_id');
            $monthsCount = $timePeriod === 'all' ? 12 : max(3, (int) ceil($windowDays / 30));
            $seriesLabels = [];
            $seriesData = [];

            for ($i = $monthsCount - 1; $i >= 0; $i--) {
                $seriesLabels[] = $reference->copy()->subMonths($i)->format('M Y');
            }

            foreach ($topBarangayIds as $id) {
                $location = collect($locations)->firstWhere('barangay_id', $id);
                $values = [];
                for ($i = $monthsCount - 1; $i >= 0; $i--) {
                    $date = $reference->copy()->subMonths($i);
                    $values[] = CrimeIncident::where('barangay_id', $id)
                        ->whereYear('incident_date', $date->year)
                        ->whereMonth('incident_date', $date->month)
                        ->tap($applyFilters)
                        ->count();
                }
                $seriesData[] = ['name' => $location['name'] ?? 'Unknown', 'values' => $values];
            }

            // Quarterly seasonal breakdown for the year of the latest incident
            $seasonalYear = $reference->year;
            $seasonal = [];
            foreach ([1, 2, 3, 4] as $quarter) {
                $values = [];
                foreach ($topBarangayIds as $id) {
                    $values[] = CrimeIncident::where('barangay_id', $id)
                        ->whereYear('incident_date', $seasonalYear)
                        ->whereRaw('QUARTER(incident_date) = ?', [$quarter])
                        ->tap($applyFilters)
                        ->count();
                }
                $seasonal[] = ['quarter' => "Q{$quarter} {$seasonalYear}", 'values' => $values];
            }

            $increasing = array_values(array_filter($locations, fn ($l) => $l['trend'] === 'increasing'));
            $decreasing = array_values(array_filter($locations, fn ($l) => $l['trend'] === 'decreasing'));
            $stable = array_values(array_filter($locations, fn ($l) => $l['trend'] === 'stable'));

            $fastestGrowing = collect($increasing)->sortByDesc('change_percent')->first();

            // "Most stable" must come from barangays that actually have activity in
            // both comparison windows; otherwise a flat-but-empty area (or a sharply
            // declining one) would win by default.
            $mostStable = collect($stable)->filter(fn ($l) => $l['has_trend_data'])
                ->sortByDesc('current')
                ->first()
                ?? collect($locations)->filter(fn ($l) => $l['has_trend_data'])
                    ->sortBy(fn ($l) => abs($l['change_percent']))
                    ->first();

            return response()->json([
                'success' => true,
                'window_days' => $windowDays,
                'summary' => [
                    'increasing_count' => count($increasing),
                    'decreasing_count' => count($decreasing),
                    'stable_count' => count($stable),
                    'fastest_growing' => $fastestGrowing,
                    'most_stable' => $mostStable,
                ],
                'locations' => $locations,
                'series' => ['labels' => $seriesLabels, 'datasets' => $seriesData],
                'seasonal' => ['areas' => array_map(fn ($id) => collect($locations)->firstWhere('barangay_id', $id)['name'] ?? 'Unknown', $topBarangayIds), 'quarters' => $seasonal],
                'migration' => [
                    'gaining' => array_slice($increasing, 0, 5),
                    'losing' => array_slice($decreasing, 0, 5),
                    'stable' => array_slice($stable, 0, 5),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getLocationTrendsData: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
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
     * Run a "what-if" crime pattern simulation against real baseline incident data.
     * Not a trained predictive model - projects real data using published
     * crime-prevention research effect sizes (see PatternSimulationService).
     */
    public function simulatePatterns(Request $request, \App\Services\PatternSimulationService $simulator)
    {
        try {
            $result = $simulator->run([
                'mode' => $request->input('mode', 'predictive'),
                'time_period_days' => $request->input('time_period_days', 90),
                'barangay_id' => $request->input('barangay_id'),
                'category_id' => $request->input('category_id'),
                'cctv' => $request->input('cctv', 'none'),
                'cctv_custom_units' => $request->input('cctv_custom_units', 0),
                'lighting' => $request->input('lighting', false),
                'patrol' => $request->input('patrol', 0),
                'community' => $request->input('community', false),
                'checkpoints' => $request->input('checkpoints', false),
                'stress_multiplier' => $request->input('stress_multiplier', 1.5),
            ]);

            return response()->json($result, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error in simulatePatterns: '.$e->getMessage());

            return response()->json(['error' => 'Error running simulation', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Pattern detection over real incident data.
     *
     * Simulation is opt-in: without ?simulation=1 the analysis touches real
     * records only and nothing synthetic can enter the response.
     */
    public function detectPatterns(Request $request, \App\Services\PatternDetectionService $detector)
    {
        try {
            $simulationOn = filter_var($request->input('simulation', false), FILTER_VALIDATE_BOOLEAN);

            $scenarios = [];
            if ($simulationOn) {
                $scenarios = [
                    'volume_multiplier' => min(3.0, max(0.0, (float) $request->input('volume_multiplier', 0.5))),
                ];

                if ($request->filled('surge_category')) {
                    $scenarios['category_surge'] = [
                        'category'   => $request->input('surge_category'),
                        'multiplier' => min(10.0, max(1.0, (float) $request->input('surge_category_multiplier', 2))),
                    ];
                }

                // Multi-select crime types surge together
                $crimeTypes = array_values(array_filter(array_map(
                    fn ($s) => trim((string) $s),
                    (array) $request->input('crime_types', [])
                )));
                if (! empty($crimeTypes)) {
                    $scenarios['category_surges'] = [
                        'categories' => $crimeTypes,
                        'multiplier' => min(10.0, max(1.0, (float) $request->input('crime_types_multiplier', 3))),
                    ];
                }

                if ($request->filled('spike_start_hour') && $request->filled('spike_end_hour')) {
                    $scenarios['time_spike'] = [
                        'start_hour' => (int) $request->input('spike_start_hour'),
                        'end_hour'   => (int) $request->input('spike_end_hour'),
                        'multiplier' => min(10.0, max(1.0, (float) $request->input('spike_multiplier', 2))),
                    ];
                }

                if ($request->boolean('location_surge')) {
                    $scenarios['location_surge'] = [
                        'multiplier' => min(10.0, max(1.0, (float) $request->input('location_surge_multiplier', 2))),
                    ];
                }

                // Focus area: specific streets. Empty / absent = whole barangay.
                $streets = array_values(array_filter(array_map(
                    fn ($s) => trim((string) $s),
                    (array) $request->input('focus_streets', [])
                )));
                if (! empty($streets)) {
                    $points = $this->sanAgustinStreetPoints($streets);
                    if (! empty($points)) {
                        $scenarios['focus_streets'] = $streets;
                        $scenarios['street_points'] = $points;
                    }
                }

                // Prevention interventions that blunt the surge
                $prevention = [
                    'patrol'      => min(2, max(0, (int) $request->input('prev_patrol', 0))),
                    'cctv'        => $request->boolean('prev_cctv'),
                    'lighting'    => $request->boolean('prev_lighting'),
                    'community'   => $request->boolean('prev_community'),
                    'checkpoints' => $request->boolean('prev_checkpoints'),
                ];
                if ($prevention['patrol'] > 0 || $prevention['cctv'] || $prevention['lighting'] || $prevention['community'] || $prevention['checkpoints']) {
                    $scenarios['prevention'] = $prevention;
                }
            }

            $result = $detector->analyzeWithInsights([
                'days'       => min(730, max(7, (int) $request->input('days', 180))),
                'simulation' => $simulationOn,
                'scenarios'  => $scenarios,
            ]);

            return response()->json($result, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error in detectPatterns: '.$e->getMessage());

            return response()->json(['error' => 'Error running pattern detection', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Polyline points ([lat, lng]) for the named San Agustin streets, read from
     * the public street geojson. Used to land a street-targeted simulated surge
     * on exactly the streets the user picked. Cached — the file is static.
     */
    private function sanAgustinStreetPoints(array $streetNames): array
    {
        $path = public_path('data/san_agustin_streets.geojson');
        if (! is_file($path)) {
            return [];
        }

        $index = \Illuminate\Support\Facades\Cache::remember('sa_street_points_v1', now()->addHours(6), function () use ($path) {
            $geo = json_decode((string) file_get_contents($path), true);
            $index = [];

            foreach (($geo['features'] ?? []) as $feature) {
                $name = mb_strtolower(trim((string) ($feature['properties']['name'] ?? '')));
                if ($name === '') {
                    continue;
                }

                foreach (($feature['geometry']['coordinates'] ?? []) as $coord) {
                    // GeoJSON stores [lng, lat]; the simulator wants [lat, lng]
                    if (isset($coord[0], $coord[1]) && is_numeric($coord[0]) && is_numeric($coord[1])) {
                        $index[$name][] = [(float) $coord[1], (float) $coord[0]];
                    }
                }
            }

            return $index;
        });

        $points = [];
        foreach ($streetNames as $name) {
            $key = mb_strtolower(trim($name));
            if (! empty($index[$key])) {
                array_push($points, ...$index[$key]);
            }
        }

        return $points;
    }

    /**
     * Pattern analysis of San Agustin incidents. Default engine is the instant
     * rule-based one (per street, per crime category — same engine as the
     * crime-mapping street modal); Gemini remains available via ?ai=1.
     */
    public function aiPatternAnalysis(Request $request, \App\Services\GeminiPatternAnalysisService $ai)
    {
        try {
            $days = (int) $request->input('days', 180);
            $result = $request->boolean('ai') ? $ai->analyze($days) : $ai->analyzeRuleBased($days);

            $status = ($result['success'] ?? false) ? 200 : 422;

            return response()->json($result, $status, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error in aiPatternAnalysis: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'Error running AI analysis: '.$e->getMessage()], 500);
        }
    }

    /**
     * AI what-if SIMULATION analysis via Gemini. Same output shape as
     * aiPatternAnalysis, but the AI reasons about a scenario (safeguards absent,
     * or prevention measures deployed) instead of the raw baseline.
     */
    public function aiSimulateAnalysis(Request $request, \App\Services\GeminiPatternAnalysisService $ai)
    {
        try {
            $scenario = [
                'scenario_type'       => $request->input('scenario_type') === 'prevention' ? 'prevention' : 'risk',
                'missing_safeguards'  => array_values(array_filter(array_map('strval', (array) $request->input('missing_safeguards', [])))),
                'prevention_measures' => array_values(array_filter(array_map('strval', (array) $request->input('prevention_measures', [])))),
                'crime_types'         => array_values(array_filter(array_map('strval', (array) $request->input('crime_types', [])))),
                'focus'               => $request->input('focus') === 'streets' ? 'streets' : 'barangay',
                'streets'             => array_values(array_filter(array_map('strval', (array) $request->input('streets', [])))),
            ];

            $result = $ai->analyzeSimulation((int) $request->input('days', 180), $scenario);

            $status = ($result['success'] ?? false) ? 200 : 422;

            return response()->json($result, $status, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error in aiSimulateAnalysis: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'Error running AI simulation: '.$e->getMessage()], 500);
        }
    }

    /**
     * Save a generated AI analysis to the database. The forecast + findings
     * become one 'analysis' row; every recommendation becomes its own
     * 'recommendation' row, all sharing a batch_key.
     */
    public function saveAiAnalysis(Request $request)
    {
        try {
            $meta = $request->input('meta', []);
            $analysis = $request->input('analysis', []);
            $forecast = $analysis['forecast'] ?? null;

            // Street AI reports (from the crime mapping street modal) have
            // risk_level + suggestions instead of a forecast — saved through
            // their own branch into the same table.
            if (!$forecast && !empty($analysis['risk_level'])) {
                return $this->saveStreetAiReport($meta, $analysis);
            }

            if (!$forecast || !isset($forecast['direction'])) {
                return response()->json(['success' => false, 'error' => 'No AI analysis to save. Run the analysis first.'], 422);
            }

            $authData = $this->getAuthUser();
            $savedBy = $authData['userEmail'] ?? null;

            $dataSource = $request->input('data_source') === 'simulation' ? 'simulation' : 'real';
            $scenario = $dataSource === 'simulation' ? $request->input('scenario') : null;

            $batchKey = (string) \Illuminate\Support\Str::uuid();
            $shared = [
                'batch_key'    => $batchKey,
                'barangay_name'=> 'San Agustin',
                'data_source'  => $dataSource,
                'scenario'     => $scenario,
                'period_days'  => (int) ($meta['period_days'] ?? 0),
                'period_start' => $meta['period_start'] ?? null,
                'period_end'   => $meta['period_end'] ?? null,
                'records_used' => (int) ($meta['records_used'] ?? 0),
                'model'        => $meta['model'] ?? null,
                'saved_by'     => $savedBy,
            ];

            $pct = $forecast['expected_change_percent'] ?? null;
            \App\Models\SanAgustinAiReport::create($shared + [
                'report_type' => 'analysis',
                'title'       => 'Crime forecast: ' . strtoupper((string) $forecast['direction'])
                    . (is_numeric($pct) ? ' (' . ($pct > 0 ? '+' : '') . $pct . '%)' : ''),
                'summary'     => $forecast['summary'] ?? null,
                'payload'     => [
                    'forecast'     => $forecast,
                    'key_findings' => $analysis['key_findings'] ?? [],
                ],
            ]);

            $saved = 1;
            foreach ((array) ($analysis['recommendations'] ?? []) as $rec) {
                if (empty($rec['action'])) {
                    continue;
                }

                \App\Models\SanAgustinAiReport::create($shared + [
                    'report_type' => 'recommendation',
                    'title'       => mb_substr((string) $rec['action'], 0, 255),
                    'summary'     => trim(($rec['location'] ?? '') !== '' ? $rec['location'] . ' — ' . ($rec['rationale'] ?? '') : ($rec['rationale'] ?? '')) ?: null,
                    'payload'     => $rec,
                ]);
                $saved++;
            }

            return response()->json(['success' => true, 'batch_key' => $batchKey, 'saved_rows' => $saved]);
        } catch (\Exception $e) {
            \Log::error('Error in saveAiAnalysis: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'Error saving AI report: '.$e->getMessage()], 500);
        }
    }

    /**
     * Persist a street AI report (crime mapping street modal): the risk/summary
     * becomes one 'analysis' row and every suggestion its own 'recommendation'
     * row, sharing a batch_key — the same layout pattern-detection saves use,
     * so the saved-reports pages render them with no changes.
     */
    private function saveStreetAiReport(array $meta, array $analysis)
    {
        $authData = $this->getAuthUser();
        $savedBy = $authData['userEmail'] ?? null;

        $streets = array_values(array_filter(array_map('strval', (array) ($meta['streets'] ?? []))));
        if (empty($streets) && !empty($meta['street'])) {
            $streets = [(string) $meta['street']];
        }
        $label = $streets ? implode(', ', $streets) : 'San Agustin';

        $batchKey = (string) \Illuminate\Support\Str::uuid();
        $shared = [
            'batch_key'    => $batchKey,
            'barangay_name'=> 'San Agustin',
            'data_source'  => 'real',
            'scenario'     => ['type' => 'street_advice', 'streets' => $streets],
            'period_days'  => (int) ($meta['period_days'] ?? 0),
            'period_start' => $meta['period_start'] ?? null,
            'period_end'   => $meta['period_end'] ?? null,
            'records_used' => (int) ($meta['records_used'] ?? 0),
            'model'        => $meta['model'] ?? null,
            'saved_by'     => $savedBy,
        ];

        $risk = strtoupper((string) ($analysis['risk_level'] ?? 'low'));
        \App\Models\SanAgustinAiReport::create($shared + [
            'report_type' => 'analysis',
            'title'       => mb_substr('Street AI advice: ' . $label . ' — ' . $risk . ' RISK', 0, 255),
            'summary'     => $analysis['summary'] ?? null,
            'payload'     => [
                'risk_level'   => $analysis['risk_level'] ?? null,
                'streets'      => $streets,
                'key_findings' => [],
            ],
        ]);

        $saved = 1;
        foreach ((array) ($analysis['suggestions'] ?? []) as $rec) {
            if (empty($rec['action'])) {
                continue;
            }

            // street + time_window double as 'location' so these rows render
            // exactly like pattern-detection recommendations everywhere
            $location = trim(implode(', ', array_filter([
                (string) ($rec['street'] ?? $label),
                (string) ($rec['time_window'] ?? ''),
            ])));

            \App\Models\SanAgustinAiReport::create($shared + [
                'report_type' => 'recommendation',
                'title'       => mb_substr((string) $rec['action'], 0, 255),
                'summary'     => trim($location !== '' ? $location . ' — ' . ($rec['rationale'] ?? '') : ($rec['rationale'] ?? '')) ?: null,
                'payload'     => $rec + ['location' => $location],
            ]);
            $saved++;
        }

        return response()->json(['success' => true, 'batch_key' => $batchKey, 'saved_rows' => $saved]);
    }

    /**
     * Recent saved AI reports (analysis + recommendation rows).
     */
    public function listAiReports(Request $request)
    {
        try {
            $reports = \App\Models\SanAgustinAiReport::query()
                ->orderByDesc('created_at')
                ->limit(min(50, max(1, (int) $request->input('limit', 20))))
                ->get(['id', 'batch_key', 'data_source', 'report_type', 'title', 'summary', 'scenario', 'period_days', 'records_used', 'saved_by', 'created_at']);

            return response()->json(['success' => true, 'reports' => $reports]);
        } catch (\Exception $e) {
            \Log::error('Error in listAiReports: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'Error loading saved reports'], 500);
        }
    }

    /**
     * "Custom Report" page: every saved AI output, grouped by save batch.
     * Each batch = one Save click on Pattern Detection (1 analysis row + N
     * recommendation rows sharing a batch_key). Rendered server-side so it
     * reads straight from the production DB with no client fetch.
     */
    public function savedAiReports(Request $request)
    {
        return view('saved-ai-reports', ['batches' => $this->collectSavedAiReportBatches()]);
    }

    /**
     * JSON API for the "Custom Report" page: the same saved AI outputs grouped
     * by batch, with full payloads (forecast, findings, recommendations).
     * Exposed so the data can be pulled programmatically / copied from the UI.
     */
    public function savedAiReportsData(Request $request)
    {
        try {
            $batches = array_map(function ($b) {
                $analysis = $b['analysis'];
                return [
                    'batch_key'     => $b['batch_key'],
                    'barangay_name' => $b['barangay_name'],
                    'data_source'   => $b['data_source'] ?? 'real',
                    'scenario'      => $b['scenario'] ?? null,
                    'saved_at'      => $b['created_at']?->toIso8601String(),
                    'saved_by'      => $b['saved_by'],
                    'period_days'   => $b['period_days'],
                    'period_start'  => $b['period_start']?->toDateString(),
                    'period_end'    => $b['period_end']?->toDateString(),
                    'records_used'  => $b['records_used'],
                    'model'         => $b['model'],
                    'analysis'      => $analysis ? [
                        'title'        => $analysis->title,
                        'summary'      => $analysis->summary,
                        'forecast'     => $analysis->payload['forecast'] ?? null,
                        'key_findings' => $analysis->payload['key_findings'] ?? [],
                    ] : null,
                    'recommendations' => array_map(function ($rec) {
                        return $rec->payload ?: ['action' => $rec->title, 'rationale' => $rec->summary];
                    }, $b['recommendations']),
                ];
            }, $this->collectSavedAiReportBatches());

            return response()->json([
                'success'   => true,
                'count'     => count($batches),
                'generated' => now()->toIso8601String(),
                'reports'   => array_values($batches),
            ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error in savedAiReportsData: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'Error loading saved reports'], 500);
        }
    }

    /**
     * Load every saved AI report row and group it by save batch (one Save
     * click = 1 analysis row + N recommendation rows sharing a batch_key).
     * Returns a newest-first list. Shared by the page and its JSON API.
     */
    private function collectSavedAiReportBatches(): array
    {
        $batches = [];

        try {
            $rows = \App\Models\SanAgustinAiReport::query()
                ->orderByDesc('created_at')
                ->orderBy('id')
                ->limit(1000)
                ->get();

            foreach ($rows as $row) {
                $key = $row->batch_key ?: ('row-' . $row->id);

                if (!isset($batches[$key])) {
                    $batches[$key] = [
                        'batch_key'       => $key,
                        'barangay_name'   => $row->barangay_name,
                        'data_source'     => $row->data_source ?? 'real',
                        'scenario'        => $row->scenario,
                        'created_at'      => $row->created_at,
                        'saved_by'        => $row->saved_by,
                        'period_days'     => $row->period_days,
                        'period_start'    => $row->period_start,
                        'period_end'      => $row->period_end,
                        'records_used'    => $row->records_used,
                        'model'           => $row->model,
                        'analysis'        => null,
                        'recommendations' => [],
                    ];
                }

                if ($row->report_type === 'analysis') {
                    $batches[$key]['analysis'] = $row;
                    // Prefer the analysis row's timestamp/meta for the batch header
                    $batches[$key]['created_at'] = $row->created_at;
                    $batches[$key]['saved_by']   = $row->saved_by ?? $batches[$key]['saved_by'];
                } else {
                    $batches[$key]['recommendations'][] = $row;
                }
            }

            // Newest batch first
            usort($batches, function ($a, $b) {
                return ($b['created_at']?->timestamp ?? 0) <=> ($a['created_at']?->timestamp ?? 0);
            });
        } catch (\Exception $e) {
            \Log::error('Error in collectSavedAiReportBatches: '.$e->getMessage());
            $batches = [];
        }

        return $batches;
    }

    /** 21 → "9:00 PM" — user-facing hours are 12-hour format */
    private function sanAgustinHour12(int $h): string
    {
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $hh = $h % 12 ?: 12;

        return $hh . ':00 ' . $ampm;
    }

    /**
     * Fingerprint of the San Agustin incident table. Caches keyed by this
     * refresh AUTOMATICALLY the moment the data changes (e.g. a migration
     * relocates rows) — a plain TTL kept serving stale hover counts.
     */
    private function sanAgustinDataFingerprint(): string
    {
        try {
            $row = \App\Models\SanAgustinIncident::query()
                ->selectRaw('COUNT(*) AS c, MAX(updated_at) AS u, MAX(id) AS m')
                ->first();

            return md5(($row->c ?? 0) . '|' . ($row->u ?? '') . '|' . ($row->m ?? 0));
        } catch (\Exception $e) {
            return 'nofp';
        }
    }

    /**
     * Per-street incident stats for the San Agustin street map
     * (hover tooltips: count, dominant crime, peak hours).
     */
    public function sanAgustinStreetStats()
    {
        try {
            $cacheKey = 'sa_street_stats_v4_' . $this->sanAgustinDataFingerprint();
            $stats = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(10), function () {
                $rows = \App\Models\SanAgustinIncident::query()
                    ->get(['incident_code', 'address_details', 'category_name', 'incident_date', 'incident_time', 'latitude', 'longitude']);

                $streets = [];
                foreach ($rows as $row) {
                    $street = trim(explode(',', (string) $row->address_details)[0] ?? '');
                    if ($street === '' || str_starts_with($street, 'Purok')) {
                        continue;
                    }

                    $streets[$street] ??= ['count' => 0, 'categories' => [], 'hours' => [], 'incidents' => []];
                    $streets[$street]['count']++;

                    $cat = $row->category_name ?: 'Uncategorized';
                    $streets[$street]['categories'][$cat] = ($streets[$street]['categories'][$cat] ?? 0) + 1;

                    if ($row->incident_time && preg_match('/^(\d{1,2}):/', (string) $row->incident_time, $m)) {
                        $h = (int) $m[1];
                        $streets[$street]['hours'][$h] = ($streets[$street]['hours'][$h] ?? 0) + 1;
                    }

                    // Per-incident coordinates so the map can point from the
                    // hovered street to each of its crime dots
                    $streets[$street]['incidents'][] = [
                        'code'     => $row->incident_code,
                        'category' => $cat,
                        'date'     => $row->incident_date ? $row->incident_date->toDateString() : null,
                        'time'     => $row->incident_time ? substr((string) $row->incident_time, 0, 5) : null,
                        'lat'      => (float) $row->latitude,
                        'lng'      => (float) $row->longitude,
                    ];
                }

                $out = [];
                foreach ($streets as $name => $s) {
                    arsort($s['categories']);
                    arsort($s['hours']);
                    $peaks = array_slice(array_keys($s['hours']), 0, 2);

                    $out[$name] = [
                        'count'        => $s['count'],
                        'top_category' => array_key_first($s['categories']),
                        'peak_hours'   => array_map(fn ($h) => $this->sanAgustinHour12((int) $h), $peaks),
                        'incidents'    => $s['incidents'],
                    ];
                }

                return $out;
            });

            return response()->json(['streets' => $stats], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error in sanAgustinStreetStats: '.$e->getMessage());

            return response()->json(['error' => 'Error loading street stats', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Full incident details for ONE San Agustin street (street map modal).
     * Streets are matched the same way as sanAgustinStreetStats: the part of
     * address_details before the first comma must equal the street name.
     */
    public function sanAgustinStreetDetail(Request $request)
    {
        try {
            $street = trim((string) $request->input('street', ''));
            if ($street === '') {
                return response()->json(['error' => 'Missing street name.'], 422);
            }

            $cacheKey = 'sa_street_detail_v3_' . md5(mb_strtolower($street) . '|' . $this->sanAgustinDataFingerprint());
            $payload = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(10), function () use ($street) {
                $rows = \App\Models\SanAgustinIncident::query()
                    ->orderByDesc('incident_date')
                    ->orderByDesc('incident_time')
                    ->get([
                        'incident_code', 'record_type', 'category_name', 'incident_title',
                        'incident_description', 'incident_date', 'incident_time',
                        'latitude', 'longitude', 'address_details', 'victim_count',
                        'suspect_count', 'status', 'clearance_status', 'clearance_date',
                        'modus_operandi', 'weather_condition', 'assigned_officer',
                    ])
                    ->filter(function ($row) use ($street) {
                        $first = trim(explode(',', (string) $row->address_details)[0] ?? '');
                        return mb_strtolower($first) === mb_strtolower($street);
                    })
                    ->values();

                $categories = [];
                $hours = [];
                foreach ($rows as $row) {
                    $cat = $row->category_name ?: 'Uncategorized';
                    $categories[$cat] = ($categories[$cat] ?? 0) + 1;
                    if ($row->incident_time && preg_match('/^(\d{1,2}):/', (string) $row->incident_time, $m)) {
                        $h = (int) $m[1];
                        $hours[$h] = ($hours[$h] ?? 0) + 1;
                    }
                }
                arsort($categories);
                arsort($hours);

                return [
                    'street' => $street,
                    'summary' => [
                        'count'        => $rows->count(),
                        'top_category' => array_key_first($categories),
                        'categories'   => $categories,
                        'peak_hours'   => array_map(fn ($h) => $this->sanAgustinHour12((int) $h), array_slice(array_keys($hours), 0, 3)),
                        'unresolved'   => $rows->whereNotIn('status', ['solved', 'resolved', 'closed', 'cleared'])->count(),
                    ],
                    'incidents' => $rows->map(fn ($row) => [
                        'code'             => $row->incident_code,
                        'record_type'      => $row->record_type,
                        'category'         => $row->category_name ?: 'Uncategorized',
                        'title'            => $row->incident_title,
                        'description'      => $row->incident_description,
                        'date'             => $row->incident_date?->toDateString(),
                        'time'             => $row->incident_time ? substr((string) $row->incident_time, 0, 5) : null,
                        'lat'              => (float) $row->latitude,
                        'lng'              => (float) $row->longitude,
                        'address'          => $row->address_details,
                        'victim_count'     => (int) $row->victim_count,
                        'suspect_count'    => (int) $row->suspect_count,
                        'status'           => $row->status,
                        'clearance_status' => $row->clearance_status,
                        'clearance_date'   => $row->clearance_date?->toDateString(),
                        'modus_operandi'   => $row->modus_operandi,
                        'weather'          => $row->weather_condition,
                        'assigned_officer' => $row->assigned_officer,
                    ])->values(),
                ];
            });

            return response()->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error in sanAgustinStreetDetail: ' . $e->getMessage());

            return response()->json(['error' => 'Error loading street detail', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AI (Gemini) suggestions for ONE San Agustin street — what the barangay
     * should do on that street, grounded in its incident profile. Cached in
     * the service so repeat opens of the same street spend no API quota.
     */
    public function sanAgustinStreetAiSuggest(Request $request, \App\Services\GeminiPatternAnalysisService $ai)
    {
        try {
            // Accepts one street (?street=) or several (?streets[]=) analyzed together
            $streets = array_values(array_filter(array_map(
                fn ($s) => trim((string) $s),
                (array) $request->input('streets', [])
            )));
            if (empty($streets) && $request->filled('street')) {
                $streets = [trim((string) $request->input('street'))];
            }
            if (empty($streets)) {
                return response()->json(['success' => false, 'error' => 'Missing street name.'], 422);
            }
            $streets = array_slice(array_unique($streets), 0, 10);   // hard cap

            // DEFAULT: instant rule-based suggestions from the system itself
            // (no Gemini call — immune to timeouts and token quotas). Pass
            // ?ai=1 to use the Gemini engine as a fallback.
            $days = (int) $request->input('days', 365);
            $result = $request->boolean('ai')
                ? $ai->analyzeStreets($streets, $days)
                : $ai->suggestStreetsRuleBased($streets, $days);

            $status = ($result['success'] ?? false) ? 200 : 422;

            return response()->json($result, $status, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error in sanAgustinStreetAiSuggest: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => 'Error running street AI suggestions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get hotspot data with analytics for Crime Hotspot Analysis page (with Redis caching)
     */
    public function getHotspotData(Request $request, \App\Services\HotspotAnalyticsService $analytics)
    {
        try {
            $timePeriod = $this->filterValue($request, 'timePeriod', 'all');
            $crimeType = $this->filterValue($request, 'crimeType');
            $barangay = $this->filterValue($request, 'barangay');
            $caseStatus = $this->filterValue($request, 'caseStatus');

            $cacheKey = CacheService::generateCacheKey('hotspot_data_v2', [
                'timePeriod' => $timePeriod,
                'crimeType' => $crimeType,
                'barangay' => $barangay,
                'caseStatus' => $caseStatus,
            ]);

            $response = \Illuminate\Support\Facades\Cache::remember(
                $cacheKey,
                now()->addMinutes(CacheService::HOTSPOT_TTL),
                fn () => $analytics->analyze($timePeriod, $crimeType, $barangay, $caseStatus)
            );

            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('Error in getHotspotData: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading hotspot data', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Trend-based hotspot forecast (linear regression over weekly counts).
     */
    public function getHotspotForecast(Request $request, \App\Services\HotspotAnalyticsService $analytics)
    {
        try {
            $historicalDays = max(28, min(365, (int) $this->filterValue($request, 'historical_days', '90')));
            $forecastDays = max(7, min(90, (int) $this->filterValue($request, 'forecast_days', '14')));
            $crimeType = $this->filterValue($request, 'crime_type');
            $barangay = $this->filterValue($request, 'barangay');

            $result = $analytics->forecast($historicalDays, $forecastDays, $crimeType, $barangay);

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error in getHotspotForecast: ' . $e->getMessage());
            return response()->json(['error' => 'Error generating forecast', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get pattern detection data with linked crimes and analysis
     */
    public function getPatternData(Request $request)
    {
        try {
            // Get filters
            $timePeriod = $this->filterValue($request, 'timePeriod', 'all');
            $crimeType = $this->filterValue($request, 'crimeType');
            $barangay = $this->filterValue($request, 'barangay');

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
