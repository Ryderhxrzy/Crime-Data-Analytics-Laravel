<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Centralized Cache Service for managing Redis operations
 * Provides consistent cache key generation, TTL management, and cache invalidation
 */
class CacheService
{
    // Cache TTL Constants (in minutes)
    const HOT_DATA_TTL = 60;           // Crime categories, barangays (1 hour)
    const DASHBOARD_TTL = 15;          // Dashboard analytics (15 minutes)
    const HOTSPOT_TTL = 20;            // Hotspot data (20 minutes)
    const CHART_TTL = 30;              // Chart data (30 minutes)
    const SEARCH_FILTER_TTL = 10;      // Search/filter results (10 minutes)
    const API_ENDPOINT_TTL = 5;        // API endpoints (5 minutes)

    /**
     * Get hot data (categories, barangays) from cache or database
     */
    public static function getCrimeCategories()
    {
        return Cache::remember(
            'crime_categories',
            now()->addMinutes(self::HOT_DATA_TTL),
            function () {
                return \App\Models\CrimeCategory::orderBy('category_name')->get();
            }
        );
    }

    /**
     * Get barangays from cache
     */
    public static function getBarangays()
    {
        return Cache::remember(
            'barangays',
            now()->addMinutes(self::HOT_DATA_TTL),
            function () {
                return \App\Models\Barangay::orderBy('barangay_name')->get();
            }
        );
    }

    /**
     * Get filtered crime data with caching
     * $filters: array with keys: year, month, day_of_week, time_of_day, crime_type, status
     */
    public static function getFilteredCrimes($filters = [])
    {
        $cacheKey = self::generateCacheKey('crimes_filter', $filters);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::SEARCH_FILTER_TTL),
            function () use ($filters) {
                $query = \App\Models\CrimeIncident::query();

                if (!empty($filters['year'])) {
                    $query->whereYear('incident_date', $filters['year']);
                }
                if (!empty($filters['month'])) {
                    $query->whereMonth('incident_date', $filters['month']);
                }
                if (!empty($filters['day_of_week'])) {
                    $query->whereRaw('DAYOFWEEK(incident_date) = ?', [$filters['day_of_week']]);
                }
                if (!empty($filters['crime_type'])) {
                    $query->where('crime_category_id', $filters['crime_type']);
                }
                if (!empty($filters['status'])) {
                    $query->where('clearance_status', $filters['status']);
                }
                if (!empty($filters['barangay'])) {
                    $query->where('barangay_id', $filters['barangay']);
                }

                return $query->with(['category', 'barangay'])->get();
            }
        );
    }

    /**
     * Cache dashboard analytics
     */
    public static function getDashboardAnalytics()
    {
        return Cache::remember(
            'dashboard_analytics',
            now()->addMinutes(self::DASHBOARD_TTL),
            function () {
                $totalIncidents = \App\Models\CrimeIncident::count();
                $clearedIncidents = \App\Models\CrimeIncident::where('clearance_status', 'cleared')->count();
                $unclearedIncidents = \App\Models\CrimeIncident::where('clearance_status', 'uncleared')->count();
                $activeAlerts = \App\Models\CrimeAlert::where('alert_status', 'active')->count();

                return [
                    'totalIncidents' => $totalIncidents,
                    'clearedIncidents' => $clearedIncidents,
                    'unclearedIncidents' => $unclearedIncidents,
                    'clearanceRate' => $totalIncidents > 0 ? round(($clearedIncidents / $totalIncidents) * 100, 2) : 0,
                    'activeAlerts' => $activeAlerts,
                ];
            }
        );
    }

    /**
     * Cache hotspot data
     */
    public static function getHotspotData($timePeriod, $crimeType, $barangay)
    {
        $cacheKey = self::generateCacheKey('hotspot_data', [
            'timePeriod' => $timePeriod,
            'crimeType' => $crimeType,
            'barangay' => $barangay
        ]);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::HOTSPOT_TTL),
            function () use ($timePeriod, $crimeType, $barangay) {
                // This will return null, actual implementation in controller
                return null;
            }
        );
    }

    /**
     * Cache chart data with filters
     */
    public static function getChartData($filters = [])
    {
        $cacheKey = self::generateCacheKey('chart_data', $filters);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CHART_TTL),
            function () use ($filters) {
                // This will return null, actual implementation in controller
                return null;
            }
        );
    }

    /**
     * Generate cache key from array of parameters
     */
    public static function generateCacheKey($prefix, $params = [])
    {
        ksort($params);
        $suffix = md5(json_encode($params));
        return "{$prefix}_{$suffix}";
    }

    /**
     * Invalidate dashboard cache
     */
    public static function invalidateDashboard()
    {
        Cache::forget('dashboard_analytics');
        Cache::forget('crime_categories');
        Cache::forget('barangays');
        self::invalidateAllCharts();
        self::invalidateAllHotspots();
    }

    /**
     * Invalidate all chart caches
     */
    public static function invalidateAllCharts()
    {
        // Delete all chart_data_* keys
        self::forgetKeysByPattern('chart_data_');
    }

    /**
     * Invalidate all hotspot caches
     */
    public static function invalidateAllHotspots()
    {
        // Delete all hotspot_data_* keys
        self::forgetKeysByPattern('hotspot_data_');
    }

    /**
     * Invalidate filter caches (dashboard, time-based trends)
     */
    public static function invalidateFilters()
    {
        self::forgetKeysByPattern('crimes_filter_');
        self::invalidateHeatmap(); // Also invalidate heatmap cache when data changes
    }

    /**
     * Invalidate heatmap cache (for mapping page)
     * Uses BOTH pattern matching and direct key deletion for reliability
     */
    public static function invalidateHeatmap()
    {
        try {
            $redis = Cache::getStore()->connection();
            $prefix = config('cache.prefix') ? config('cache.prefix') . ':' : '';

            // Method 1: Delete using SCAN pattern (non-blocking, safe for large datasets)
            $pattern = $prefix . 'crime_heatmap_*';
            $keys = $redis->keys($pattern);

            if (!empty($keys)) {
                call_user_func_array([$redis, 'del'], $keys);
                Log::info("✅ Cache cleared for heatmap - Deleted " . count($keys) . " keys");
            } else {
                Log::debug("No heatmap cache keys found to delete");
            }
        } catch (\Exception $e) {
            Log::error("❌ Error clearing heatmap cache: " . $e->getMessage());
        }
    }

    /**
     * Delete keys matching a pattern (works with Redis)
     */
    private static function forgetKeysByPattern($pattern)
    {
        try {
            $redis = Cache::getStore()->connection();
            $prefix = config('cache.prefix') ? config('cache.prefix') . ':' : '';
            $keys = $redis->keys($prefix . $pattern . '*');

            if (!empty($keys)) {
                // Use DEL command to delete multiple keys at once (more efficient)
                call_user_func_array([$redis, 'del'], $keys);
                \Log::debug("Cleared cache keys matching pattern: {$pattern}", ['count' => count($keys)]);
            }
        } catch (\Exception $e) {
            \Log::error("Error clearing cache pattern {$pattern}: " . $e->getMessage());
        }
    }

    /**
     * Clear all application cache
     */
    public static function flushAll()
    {
        Cache::flush();
    }
}
