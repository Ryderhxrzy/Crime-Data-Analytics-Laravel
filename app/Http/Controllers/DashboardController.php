<?php

namespace App\Http\Controllers;

use App\Models\CrimeIncident;
use App\Models\Barangay;
use App\Models\CrimeCategory;
use App\Models\CrimeAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
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
     * Get filtered chart data via AJAX
     */
    public function getChartData(Request $request)
    {
        $year  = $request->get('year', now()->year);
        $month = $request->get('month', null);

        // 1. Monthly Crime Trend (by month for the selected year)
        $monthlyTrend = CrimeIncident::select(
                DB::raw('DATE_FORMAT(incident_date, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->whereYear('incident_date', $year)
            ->groupBy(DB::raw('DATE_FORMAT(incident_date, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        // Build query for other charts
        $query = CrimeIncident::query()->whereYear('incident_date', $year);
        if ($month) {
            $query->whereMonth('incident_date', $month);
        }

        // 2. Crime Type Distribution (by category)
        $crimeTypes = (clone $query)
            ->select('crime_category_id', DB::raw('COUNT(*) as count'))
            ->with('category')
            ->groupBy('crime_category_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

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

        // Map all 24 hours
        $hoursFormatted = collect(range(0, 23))->map(fn($h) => [
            'hour'  => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00',
            'count' => $peakHours->firstWhere('hour', $h)?->count ?? 0,
        ]);

        return response()->json([
            'monthlyTrend' => [
                'labels' => $monthlyTrend->pluck('month')->values(),
                'data'   => $monthlyTrend->pluck('count')->values(),
            ],
            'crimeTypes' => [
                'labels' => $crimeTypes->map(fn($i) => $i->category->category_name ?? 'Unknown')->values(),
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
        ]);
    }
}
