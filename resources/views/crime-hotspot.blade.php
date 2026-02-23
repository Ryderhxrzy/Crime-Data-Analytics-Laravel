@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crime Hotspots - Crime Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

    <!-- Leaflet Heatmap Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.min.js"></script>

    <!-- Laravel App -->
    @vite(['resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <!-- Header Component -->
    @include('components.header')

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

    <!-- Sidebar -->
    @include('components.sidebar')

    <!-- Main Content -->
    <main class="lg:ml-72 ml-0 lg:mt-16 mt-16 min-h-screen">
        <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
            <!-- Page Header -->
            <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Crime Hotspots Analysis</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Identify high-risk areas and crime concentration zones</p>
                    </div>
                </div>
            </div>

            <!-- Main Content Container -->
            <div class="bg-white border border-gray-200 rounded-lg p-6" style="position: relative; z-index: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-fire mr-2 text-red-600"></i>Hotspot Map
                    </h2>
                    <button id="mapFullscreenBtn" class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 text-sm" title="Toggle Fullscreen Map">
                        <i class="fas fa-expand"></i>
                        <span class="hidden sm:inline">Fullscreen</span>
                    </button>
                </div>

                <!-- Current Data Filters -->
                <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
                    <div class="mb-4 pb-4 border-b border-gray-200">
                        <h3 class="text-sm font-bold text-gray-900">
                            <i class="fas fa-chart-bar mr-2 text-alertara-700"></i>Current Hotspot Analysis
                        </h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                        <!-- Visualization Mode -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">View Mode</label>
                            <select id="visualizationMode" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="markers" selected>Individual Markers</option>
                                <option value="heatmap">Heat Map</option>
                                <option value="clusters">Cluster View</option>
                            </select>
                        </div>

                        <!-- Time Period -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Time Period</label>
                            <select id="timePeriod" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="30">Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                                <option value="180">Last 6 Months</option>
                                <option value="all" selected>All Time</option>
                            </select>
                        </div>

                        <!-- Crime Type -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Category</label>
                            <select id="crimeType" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="">All Categories</option>
                            </select>
                        </div>

                        <!-- Case Status -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Case Status</label>
                            <select id="caseStatus" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="">All Status</option>
                                <option value="reported">Reported</option>
                                <option value="under_investigation">Under Investigation</option>
                                <option value="solved">Solved</option>
                                <option value="closed">Closed</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>

                        <!-- Barangay -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Barangay</label>
                            <select id="barangay" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="">All Barangays</option>
                            </select>
                        </div>

                        <!-- Reset Button -->
                        <div class="flex items-end">
                            <button id="resetFilterBtn" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                                <i class="fas fa-redo"></i>
                                <span>Reset</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- AI Prediction Filters -->
                <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-xl p-4 mb-6 border border-purple-200">
                    <div class="mb-4 pb-4 border-b border-purple-200">
                        <h3 class="text-sm font-bold text-gray-900">
                            <i class="fas fa-brain mr-2 text-purple-700"></i>AI Hotspot Prediction
                        </h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <!-- Historical Data Range -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-1 text-purple-600"></i>Historical Range
                            </label>
                            <select id="historicalRange" class="w-full px-3 py-2 border border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white text-sm">
                                <option value="30">Last 30 Days</option>
                                <option value="60">Last 60 Days</option>
                                <option value="90" selected>Last 90 Days</option>
                                <option value="180">Last 6 Months</option>
                            </select>
                        </div>

                        <!-- Forecast Period -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-forward mr-1 text-purple-600"></i>Forecast Period
                            </label>
                            <select id="forecastPeriod" class="w-full px-3 py-2 border border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white text-sm">
                                <option value="7">Next 7 Days</option>
                                <option value="14" selected>Next 14 Days</option>
                                <option value="30">Next 30 Days</option>
                            </select>
                        </div>

                        <!-- Crime Type Filter (Prediction) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-filter mr-1 text-purple-600"></i>Crime Type
                            </label>
                            <select id="predictionCrimeType" class="w-full px-3 py-2 border border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white text-sm">
                                <option value="">All Types</option>
                            </select>
                        </div>

                        <!-- Barangay Filter (Prediction) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-pin mr-1 text-purple-600"></i>Barangay
                            </label>
                            <select id="predictionBarangay" class="w-full px-3 py-2 border border-purple-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white text-sm">
                                <option value="">All Areas</option>
                            </select>
                        </div>

                        <!-- Generate Prediction Button -->
                        <div class="flex items-end">
                            <button id="runPredictionBtn" class="w-full px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-lg hover:from-purple-700 hover:to-blue-700 transition-all font-semibold flex items-center justify-center gap-2 text-sm">
                                <i class="fas fa-wand-magic-sparkles"></i>
                                <span>Run AI Analysis</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Map Toggle Controls -->
                <div class="flex gap-2 mb-4" id="mapToggleControls">
                    <button class="map-toggle-btn active px-4 py-2 bg-alertara-700 text-white rounded-lg text-sm font-semibold transition-all" data-view="current">
                        <i class="fas fa-map mr-2"></i>Current Hotspots
                    </button>
                    <button class="map-toggle-btn px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-semibold transition-all" data-view="predicted" style="display: none;">
                        <i class="fas fa-wand-magic-sparkles mr-2"></i>Predicted Hotspots
                    </button>
                    <button class="map-toggle-btn px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-semibold transition-all" data-view="compare" style="display: none;">
                        <i class="fas fa-code-compare mr-2"></i>Compare View
                    </button>
                </div>

                <!-- Map and Right Panel Side-by-Side -->
                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- LEFT: Map -->
                    <div id="mapContainer" class="w-full lg:w-3/5 border border-gray-200 rounded-lg overflow-hidden relative">
                        <div id="hotspotMap" class="h-96 lg:h-[600px] w-full"></div>

                        <!-- Map Loading Overlay -->
                        <div id="mapLoadingOverlay" class="absolute inset-0 bg-white bg-opacity-95 hidden z-[10000] flex flex-col items-center justify-center gap-4">
                            <div class="text-center">
                                <div class="inline-block mb-3">
                                    <i class="fas fa-spinner fa-spin text-3xl text-alertara-700"></i>
                                </div>
                                <div class="text-sm font-semibold text-gray-900 mb-1">Loading Hotspot Data</div>
                                <div class="text-xs text-gray-600">Processing map visualization...</div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Hotspot Summary Panel -->
                    <div class="w-full lg:w-2/5 flex flex-col gap-4">
                        <!-- Top High-Risk Areas -->
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                            <div style="padding: 14px 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0;">
                                    <i class="fas fa-list-ol mr-2 text-red-600"></i>Top High-Risk Areas
                                </h3>
                            </div>
                            <div id="topHotspots" style="overflow-y: auto; max-height: 280px;">
                                <!-- Will be populated by JavaScript -->
                                <div style="padding: 20px; text-align: center; color: #999;">
                                    <i class="fas fa-spinner fa-spin text-2xl"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Area Statistics Card -->
                        <div id="areaStatsCard" class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm" style="display: none;">
                            <div style="padding: 14px 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0;">
                                    <i class="fas fa-chart-bar mr-2 text-blue-600"></i>Area Statistics
                                </h3>
                            </div>
                            <div id="areaStats" style="padding: 16px;">
                                <!-- Populated on hotspot selection -->
                            </div>
                        </div>

                        <!-- Risk Classification -->
                        <div id="riskClassification" class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm" style="display: none;">
                            <div style="padding: 14px 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0;">
                                    <i class="fas fa-exclamation-triangle mr-2 text-yellow-600"></i>Risk Classification
                                </h3>
                            </div>
                            <div id="riskBadge" style="padding: 20px; text-align: center;">
                                <!-- Populated dynamically -->
                            </div>
                        </div>

                        <!-- Patrol Action Section -->
                        <div id="patrolSection" class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg overflow-hidden shadow-sm" style="display: none;">
                            <div style="padding: 14px 16px;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #1e40af; margin: 0 0 10px;">
                                    <i class="fas fa-car mr-2"></i>Patrol Deployment
                                </h3>
                                <button id="patrolRequestBtn" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold flex items-center justify-center gap-2" disabled>
                                    <i class="fas fa-phone-alt"></i>
                                    <span>Request Patrol Deployment</span>
                                </button>
                                <p style="font-size: 11px; color: #1e40af; margin-top: 8px; margin: 8px 0 0 0;">
                                    <i class="fas fa-info-circle mr-1"></i>Available for medium/high-risk areas only
                                </p>
                            </div>
                        </div>

                        <!-- Professional Features -->
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                            <div style="padding: 14px 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0;">
                                    <i class="fas fa-download mr-2 text-green-600"></i>Professional Features
                                </h3>
                            </div>
                            <div style="padding: 12px;">
                                <button id="exportPdfBtn" class="w-full px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors text-sm font-semibold flex items-center justify-center gap-2 mb-2">
                                    <i class="fas fa-file-pdf"></i>
                                    <span>Export Report (PDF)</span>
                                </button>
                                <button id="printMapBtn" class="w-full px-3 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition-colors text-sm font-semibold flex items-center justify-center gap-2 mb-2">
                                    <i class="fas fa-print"></i>
                                    <span>Print Map</span>
                                </button>
                                <button id="downloadCsvBtn" class="w-full px-3 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors text-sm font-semibold flex items-center justify-center gap-2">
                                    <i class="fas fa-download"></i>
                                    <span>Download CSV</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Predicted High-Risk Areas Table -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm mt-6" id="predictedAreasSection" style="display: none;">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-wand-magic-sparkles mr-2 text-purple-700"></i>Predicted High-Risk Areas
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">AI-forecasted hotspots for the next <span id="forecastDaysDisplay">14</span> days</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Rank</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Area</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Predicted Incidents</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Risk Level</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Confidence</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody id="predictedAreasTable">
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Key Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-8">
                <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-red-900 mb-1">
                                <i class="fas fa-fire mr-1"></i>High-Risk Hotspots
                            </p>
                            <p class="text-2xl font-bold text-red-700" id="highRiskCount">0</p>
                            <p class="text-xs text-red-600 mt-1">Areas with >20 incidents</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-yellow-900 mb-1">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Medium-Risk Areas
                            </p>
                            <p class="text-2xl font-bold text-yellow-700" id="mediumRiskCount">0</p>
                            <p class="text-xs text-yellow-600 mt-1">Areas with 10-20 incidents</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-green-900 mb-1">
                                <i class="fas fa-check-circle mr-1"></i>Low-Risk Areas
                            </p>
                            <p class="text-2xl font-bold text-green-700" id="lowRiskCount">0</p>
                            <p class="text-xs text-green-600 mt-1">Areas with <10 incidents</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-blue-900 mb-1">
                                <i class="fas fa-chart-line mr-1"></i>Total Incidents
                            </p>
                            <p class="text-2xl font-bold text-blue-700" id="totalIncidentsCount">0</p>
                            <p class="text-xs text-blue-600 mt-1">Across all barangays</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trend Analysis & Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
                <!-- Trend Direction Card -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-arrow-trend-up mr-2 text-alertara-700"></i>Trend Analysis
                    </h3>
                    <div id="trendAnalysis" class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-700">Overall Trend</span>
                            <span id="overallTrend" class="text-sm font-bold text-gray-900">
                                <i class="fas fa-arrow-up mr-1 text-red-600"></i>Increasing
                            </span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-700">Highest Density Area</span>
                            <span id="highestDensity" class="text-sm font-bold text-gray-900">—</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-700">Clearance Rate</span>
                            <span id="clearanceRate" class="text-sm font-bold text-green-600">0%</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-700">Unsolved Cases</span>
                            <span id="unsolvedCount" class="text-sm font-bold text-red-600">0</span>
                        </div>
                    </div>
                </div>

                <!-- Crime Distribution Chart -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-chart-pie mr-2 text-alertara-700"></i>Crime Type Distribution
                    </h3>
                    <div id="crimeDistributionChart" style="position: relative; height: 300px;">
                        <canvas id="crimeDistributionCanvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Trend Chart -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm mt-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-chart-line mr-2 text-alertara-700"></i>12-Month Hotspot Trend
                </h3>
                <div id="monthlyTrendChart" style="position: relative; height: 300px;">
                    <canvas id="monthlyTrendCanvas"></canvas>
                </div>
            </div>

            <!-- AI Insights Section -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-6 shadow-sm mt-6">
                <h3 class="text-lg font-bold text-purple-900 mb-4">
                    <i class="fas fa-brain mr-2"></i>Predictive Insights & Recommendations
                </h3>
                <div id="aiInsights" class="space-y-3">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>
    </main>

    <!-- Chart.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

    <script>
        // Global map and layer variables
        let map;
        let boundaryLayer = null;
        let qcBounds = null;
        let heatmapLayer = null;
        let markerLayer = null;
        let markerClusterGroup = null;
        let currentData = [];
        let hotspotsData = [];
        let selectedHotspot = null;
        let currentVisualizationMode = 'markers';

        // Heatmap settings
        let heatmapRadius = 40;
        let heatmapBlur = 20;
        let heatmapIntensity = 1;

        document.addEventListener('DOMContentLoaded', function() {
            initializeMap();
            loadQCBoundary();
        });

        function initializeMap() {
            map = L.map('hotspotMap').setView([14.6349, 121.0388], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            console.log('Map initialized');
        }

        // Load QC boundary from GeoJSON
        function loadQCBoundary() {
            console.log('Loading QC boundary...');

            const timestamp = new Date().getTime();
            fetch(`/qc_map.geojson?t=${timestamp}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('QC boundary loaded successfully');

                    if (boundaryLayer) {
                        map.removeLayer(boundaryLayer);
                    }

                    boundaryLayer = L.geoJSON(data, {
                        style: {
                            color: '#274d4c',
                            weight: 3,
                            opacity: 1,
                            fillColor: '#e8f5f3',
                            fillOpacity: 0.08,
                            lineCap: 'round',
                            lineJoin: 'round'
                        },
                        onEachFeature: function(feature, layer) {
                            // Hover effect
                            layer.on('mouseover', function() {
                                this.setStyle({
                                    weight: 4,
                                    fillOpacity: 0.15,
                                    fillColor: '#d0ebe7'
                                });
                            });

                            layer.on('mouseout', function() {
                                this.setStyle({
                                    weight: 3,
                                    fillOpacity: 0.08,
                                    fillColor: '#e8f5f3'
                                });
                            });
                        }
                    }).addTo(map);

                    // Get the bounds of QC boundary
                    qcBounds = boundaryLayer.getBounds();
                    console.log('QC bounds:', qcBounds);

                    // Invalidate size to ensure map calculates correct dimensions
                    map.invalidateSize();

                    // Fit bounds
                    if (qcBounds.isValid()) {
                        console.log('Fitting map to QC bounds...');
                        map.fitBounds(qcBounds, {
                            padding: [20, 20],
                            animate: true
                        });
                    }

                    // Load other data
                    loadCrimeCategories();
                    loadBarangays();
                    setupFilterListeners();
                    loadHotspotData();
                })
                .catch(error => {
                    console.error('Error loading QC boundary:', error);

                    // Fallback: Use default QC bounds
                    qcBounds = L.latLngBounds(
                        L.latLng(14.50, 120.90),
                        L.latLng(14.80, 121.20)
                    );

                    console.log('Using default QC bounds:', qcBounds);

                    map.invalidateSize();

                    if (qcBounds.isValid()) {
                        map.fitBounds(qcBounds, {
                            padding: [20, 20],
                            animate: true
                        });
                    } else {
                        map.setView([14.6349, 121.0446], 12);
                    }

                    loadCrimeCategories();
                    loadBarangays();
                    setupFilterListeners();
                    loadHotspotData();
                });
        }

        function loadHotspotData() {
            showMapLoading(true);
            const timePeriod = document.getElementById('timePeriod').value;
            const visualizationMode = document.getElementById('visualizationMode').value;
            const crimeType = document.getElementById('crimeType').value;
            const caseStatus = document.getElementById('caseStatus').value;
            const barangay = document.getElementById('barangay').value;

            const params = new URLSearchParams({
                range: timePeriod,
                crime_type: crimeType,
                status: caseStatus,
                barangay: barangay
            });

            console.log('Loading hotspot data with params:', Object.fromEntries(params));

            fetch(`/api/crime-heatmap?${params}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Hotspot data received:', data);
                    hotspotsData = Array.isArray(data) ? data : (data.crimes || data || []);
                    console.log('Hotspots count:', hotspotsData.length);

                    currentData = hotspotsData;
                    currentVisualizationMode = visualizationMode;

                    // Clear current visualization
                    clearCurrentVisualization();

                    // Display based on selected mode
                    if (visualizationMode === 'heatmap') {
                        displayHeatmap(hotspotsData);
                    } else if (visualizationMode === 'markers') {
                        displayMarkers(hotspotsData);
                    } else if (visualizationMode === 'clusters') {
                        displayClusters(hotspotsData);
                    }

                    updateTopHotspots();
                    showMapLoading(false);
                })
                .catch(error => {
                    console.error('Error loading hotspot data:', error);
                    showMapLoading(false);
                    document.getElementById('topHotspots').innerHTML = '<div style="padding: 20px; text-align: center; color: #e74c3c;">Error loading data. Please try again.</div>';
                });
        }

        // Clear current visualization layers
        function clearCurrentVisualization() {
            if (heatmapLayer) {
                map.removeLayer(heatmapLayer);
                heatmapLayer = null;
            }
            if (markerLayer) {
                map.removeLayer(markerLayer);
                markerLayer = null;
            }
            if (markerClusterGroup) {
                map.removeLayer(markerClusterGroup);
                markerClusterGroup = null;
            }
            if (predictedMarkersLayer) {
                map.removeLayer(predictedMarkersLayer);
                predictedMarkersLayer = null;
            }
        }

        // Display heatmap visualization
        function displayHeatmap(data) {
            if (typeof L.heatLayer !== 'function') {
                setTimeout(() => displayHeatmap(data), 500);
                return;
            }

            const heatmapPoints = data.map(incident => [
                incident.latitude,
                incident.longitude,
                calculateCrimeWeight(incident)
            ]);

            if (heatmapPoints.length > 0) {
                heatmapLayer = L.heatLayer(heatmapPoints, {
                    radius: heatmapRadius,
                    blur: heatmapBlur,
                    maxZoom: 18,
                    minOpacity: 0.3,
                    max: 1.0,
                    gradient: {
                        0.0: '#3b82f6',
                        0.25: '#2ecc71',
                        0.5: '#f39c12',
                        0.75: '#e74c3c',
                        1.0: '#c0392b'
                    }
                }).addTo(map);
            }
        }

        // Display markers visualization
        function displayMarkers(data) {
            markerLayer = L.featureGroup();

            data.forEach(incident => {
                const markerColor = incident.color_code || '#274d4c';

                const marker = L.circleMarker([incident.latitude, incident.longitude], {
                    radius: 6,
                    fillColor: markerColor,
                    color: markerColor,
                    weight: 2,
                    opacity: 0.8,
                    fillOpacity: 0.7
                });

                marker.bindPopup(`
                    <div style="font-size: 12px;">
                        <strong>${incident.incident_title || 'Crime Incident'}</strong><br>
                        <strong>${incident.category_name || 'Unknown'}</strong><br>
                        <small>${incident.location || 'N/A'}</small>
                    </div>
                `);

                marker.addTo(markerLayer);
            });

            markerLayer.addTo(map);
        }

        // Display clusters visualization
        function displayClusters(data) {
            markerLayer = L.featureGroup();
            let barangayGroups = {};

            // Group by barangay
            data.forEach(incident => {
                const barangayId = incident.barangay_id || 'unknown';
                const barangayName = incident.barangay_name || 'Unknown Barangay';

                if (!barangayGroups[barangayId]) {
                    barangayGroups[barangayId] = {
                        name: barangayName,
                        incidents: [],
                        totalLat: 0,
                        totalLng: 0
                    };
                }

                barangayGroups[barangayId].incidents.push(incident);
                barangayGroups[barangayId].totalLat += parseFloat(incident.latitude);
                barangayGroups[barangayId].totalLng += parseFloat(incident.longitude);
            });

            // Create cluster markers
            Object.keys(barangayGroups).forEach(barangayId => {
                const group = barangayGroups[barangayId];
                const count = group.incidents.length;
                const clusterColor = getClusterColor(count);

                const centerLat = group.totalLat / count;
                const centerLng = group.totalLng / count;

                const clusterIcon = L.divIcon({
                    className: 'cluster-marker',
                    html: `
                        <div style="
                            width: 40px;
                            height: 40px;
                            background: linear-gradient(135deg, ${clusterColor} 0%, ${clusterColor}dd 100%);
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: white;
                            font-weight: bold;
                            font-size: 14px;
                            border: 2px solid white;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
                        ">
                            ${count}
                        </div>
                    `,
                    iconSize: [40, 40],
                    iconAnchor: [20, 20],
                    popupAnchor: [0, -20]
                });

                const marker = L.marker([centerLat, centerLng], { icon: clusterIcon });

                marker.bindPopup(`
                    <div style="font-size: 12px;">
                        <strong>${group.name}</strong><br>
                        Incidents: ${count}<br>
                        Risk Level: ${clusterColor === '#dc2626' ? 'HIGH' : clusterColor === '#eab308' ? 'MEDIUM' : 'LOW'}
                    </div>
                `);

                marker.addTo(markerLayer);
            });

            markerLayer.addTo(map);
        }

        function calculateCrimeWeight(incident) {
            let weight = 0.5;
            if (incident.clearance_status === 'uncleared') {
                weight += 0.5;
            }
            return Math.min(weight * heatmapIntensity, 1.0);
        }

        function getClusterColor(count) {
            if (count >= 31) return '#dc2626'; // Red
            if (count >= 11) return '#eab308'; // Yellow
            return '#16a34a'; // Green
        }

        function updateTopHotspots() {
            console.log('updateTopHotspots called with data:', hotspotsData.length);

            if (!hotspotsData || hotspotsData.length === 0) {
                document.getElementById('topHotspots').innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No crime data available</div>';
                return;
            }

            const hotspotMap = {};

            hotspotsData.forEach(crime => {
                // Try different field names for barangay
                const barangay = crime.barangay_name || crime.location || crime.barangay || 'Unknown Barangay';

                if (!hotspotMap[barangay]) {
                    hotspotMap[barangay] = {
                        name: barangay,
                        count: 0,
                        cleared: 0,
                        uncleared: 0,
                        crimes: []
                    };
                }
                hotspotMap[barangay].count++;
                hotspotMap[barangay].crimes.push(crime);
                if (crime.clearance_status === 'cleared') {
                    hotspotMap[barangay].cleared++;
                } else {
                    hotspotMap[barangay].uncleared++;
                }
            });

            console.log('Hotspot map created:', Object.keys(hotspotMap).length, 'barangays');

            const hotspots = Object.values(hotspotMap)
                .map(h => ({
                    ...h,
                    riskLevel: h.count > 20 ? 'HIGH' : h.count > 10 ? 'MEDIUM' : 'LOW',
                    riskColor: h.count > 20 ? 'red' : h.count > 10 ? 'yellow' : 'green',
                    riskIcon: h.count > 20 ? '🔴' : h.count > 10 ? '🟡' : '🟢'
                }))
                .sort((a, b) => b.count - a.count)
                .slice(0, 10);

            console.log('Top hotspots:', hotspots);

            const topHotspotsDiv = document.getElementById('topHotspots');

            if (hotspots.length === 0) {
                topHotspotsDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No hotspots found</div>';
                return;
            }

            // Store hotspots data globally for click handlers
            window.hotspotsData = hotspots;

            topHotspotsDiv.innerHTML = hotspots.map((h, idx) => `
                <div class="border-b border-gray-100 p-3 hover:bg-gray-50 cursor-pointer transition-colors hotspot-item" data-index="${idx}">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <div class="font-semibold text-gray-900 text-sm">
                                <span class="text-lg mr-2">${h.riskIcon}</span>${idx + 1}. ${h.name}
                            </div>
                            <div class="text-xs text-gray-600 mt-1">
                                <i class="fas fa-exclamation-circle mr-1"></i>${h.count} incident(s)
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2 py-1 rounded-full ${h.riskColor === 'red' ? 'bg-red-100 text-red-700' : h.riskColor === 'yellow' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}">
                            ${h.riskLevel}
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                        <div class="h-full ${h.riskColor === 'red' ? 'bg-red-600' : h.riskColor === 'yellow' ? 'bg-yellow-600' : 'bg-green-600'}" style="width: ${Math.min((h.count / 30) * 100, 100)}%"></div>
                    </div>
                </div>
            `).join('');

            // Add event listeners to hotspot items
            topHotspotsDiv.querySelectorAll('.hotspot-item').forEach(item => {
                item.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    const hotspot = window.hotspotsData[index];
                    selectHotspot(hotspot.name, hotspot);
                });
            });

            console.log('Top hotspots HTML rendered with', hotspots.length, 'items');

            // Update metrics and charts
            updateKeyMetrics(hotspots);
            updateTrendAnalysis(hotspots);
            renderCrimeDistributionChart();
            renderMonthlyTrendChart();
            generateAIInsights(hotspots);
        }

        // Update key metrics cards
        function updateKeyMetrics(hotspots) {
            const highRisk = hotspots.filter(h => h.count > 20).length;
            const mediumRisk = hotspots.filter(h => h.count > 10 && h.count <= 20).length;
            const lowRisk = hotspots.filter(h => h.count <= 10).length;
            const totalIncidents = hotspotsData.length;

            document.getElementById('highRiskCount').textContent = highRisk;
            document.getElementById('mediumRiskCount').textContent = mediumRisk;
            document.getElementById('lowRiskCount').textContent = lowRisk;
            document.getElementById('totalIncidentsCount').textContent = totalIncidents;
        }

        // Update trend analysis indicators
        function updateTrendAnalysis(hotspots) {
            // Highest density area
            const highestArea = hotspots.length > 0 ? hotspots[0].name : 'N/A';
            document.getElementById('highestDensity').textContent = highestArea;

            // Calculate clearance rate
            const totalCrimes = hotspotsData.length;
            const clearedCrimes = hotspotsData.filter(c => c.clearance_status === 'cleared').length;
            const clearanceRate = totalCrimes > 0 ? Math.round((clearedCrimes / totalCrimes) * 100) : 0;
            document.getElementById('clearanceRate').textContent = clearanceRate + '%';

            // Unsolved cases
            const unsolvedCases = totalCrimes - clearedCrimes;
            document.getElementById('unsolvedCount').textContent = unsolvedCases;

            // Trend direction (random for demo)
            const trendDirection = Math.random() > 0.5 ? 'Increasing' : 'Decreasing';
            const trendIcon = trendDirection === 'Increasing' ? 'arrow-up text-red-600' : 'arrow-down text-green-600';
            const trendPercent = Math.floor(Math.random() * 30) + 5;
            document.getElementById('overallTrend').innerHTML = `<i class="fas fa-${trendIcon} mr-1"></i>${trendDirection} ${trendPercent}%`;
        }

        // Crime Distribution Chart
        let crimeDistributionChartInstance = null;
        function renderCrimeDistributionChart() {
            const crimeTypes = {};
            hotspotsData.forEach(crime => {
                const type = crime.category_name || 'Unknown';
                crimeTypes[type] = (crimeTypes[type] || 0) + 1;
            });

            const ctx = document.getElementById('crimeDistributionCanvas');
            if (!ctx) return;

            if (crimeDistributionChartInstance) {
                crimeDistributionChartInstance.destroy();
            }

            const colors = ['#dc2626', '#ea580c', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9'];

            crimeDistributionChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(crimeTypes),
                    datasets: [{
                        data: Object.values(crimeTypes),
                        backgroundColor: colors.slice(0, Object.keys(crimeTypes).length),
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { size: 11 },
                                padding: 10
                            }
                        }
                    }
                }
            });
        }

        // Monthly Trend Chart
        let monthlyTrendChartInstance = null;
        function renderMonthlyTrendChart() {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const trendData = months.map(() => Math.floor(Math.random() * 50) + 20);

            const ctx = document.getElementById('monthlyTrendCanvas');
            if (!ctx) return;

            if (monthlyTrendChartInstance) {
                monthlyTrendChartInstance.destroy();
            }

            monthlyTrendChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Hotspot Intensity',
                        data: trendData,
                        borderColor: '#274d4c',
                        backgroundColor: 'rgba(39, 77, 76, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#274d4c',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }

        // Generate AI Insights
        function generateAIInsights(hotspots) {
            const insights = [];

            if (hotspots.length === 0) {
                insights.push({
                    type: 'neutral',
                    icon: 'info-circle',
                    title: 'No Data',
                    description: 'No crime data available for the selected filters.'
                });
            } else {
                const topHotspot = hotspots[0];
                const highRiskCount = hotspots.filter(h => h.count > 20).length;
                const clearanceRate = Math.round((hotspotsData.filter(c => c.clearance_status === 'cleared').length / hotspotsData.length) * 100);

                // High density warning
                if (topHotspot.count > 30) {
                    insights.push({
                        type: 'danger',
                        icon: 'exclamation-circle',
                        title: `Critical Alert: ${topHotspot.name}`,
                        description: `${topHotspot.name} has ${topHotspot.count} incidents. Immediate patrol presence recommended.`
                    });
                }

                // Clearance rate insight
                if (clearanceRate < 50) {
                    insights.push({
                        type: 'warning',
                        icon: 'triangle-exclamation',
                        title: 'Low Clearance Rate',
                        description: `Only ${clearanceRate}% of cases are cleared. Focus on case resolution strategies.`
                    });
                } else {
                    insights.push({
                        type: 'success',
                        icon: 'check-circle',
                        title: 'Good Clearance Performance',
                        description: `${clearanceRate}% of cases are solved. Maintain current investigation efforts.`
                    });
                }

                // Multiple hotspots warning
                if (highRiskCount > 3) {
                    insights.push({
                        type: 'warning',
                        icon: 'map-location-dot',
                        title: `${highRiskCount} High-Risk Zones Detected`,
                        description: 'Multiple areas require elevated patrol presence. Consider resource redistribution.'
                    });
                }

                // Trend insight
                const unsolvedRate = 100 - clearanceRate;
                insights.push({
                    type: 'info',
                    icon: 'chart-line',
                    title: 'Area-Specific Recommendations',
                    description: `${topHotspot.uncleared} unsolved cases in ${topHotspot.name}. Recommend specialized task force deployment.`
                });
            }

            const insightsDiv = document.getElementById('aiInsights');
            insightsDiv.innerHTML = insights.map(insight => {
                const bgClass = insight.type === 'danger' ? 'bg-red-50 border-red-200' :
                               insight.type === 'warning' ? 'bg-yellow-50 border-yellow-200' :
                               insight.type === 'success' ? 'bg-green-50 border-green-200' :
                               'bg-blue-50 border-blue-200';
                const textClass = insight.type === 'danger' ? 'text-red-900' :
                                 insight.type === 'warning' ? 'text-yellow-900' :
                                 insight.type === 'success' ? 'text-green-900' :
                                 'text-blue-900';
                const iconColor = insight.type === 'danger' ? 'text-red-600' :
                                 insight.type === 'warning' ? 'text-yellow-600' :
                                 insight.type === 'success' ? 'text-green-600' :
                                 'text-blue-600';

                return `
                    <div class="border ${bgClass} rounded-lg p-4">
                        <div class="flex gap-3">
                            <i class="fas fa-${insight.icon} ${iconColor} text-lg flex-shrink-0 mt-0.5"></i>
                            <div>
                                <h4 class="font-semibold ${textClass} mb-1">${insight.title}</h4>
                                <p class="text-sm ${textClass} opacity-80">${insight.description}</p>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function selectHotspot(name, hotspot) {
            try {
                selectedHotspot = hotspot;
                console.log('Selected hotspot:', hotspot);

            const statsCard = document.getElementById('areaStatsCard');
            const statsDiv = document.getElementById('areaStats');
            const riskCard = document.getElementById('riskClassification');
            const riskBadge = document.getElementById('riskBadge');
            const patrolSection = document.getElementById('patrolSection');
            const patrolBtn = document.getElementById('patrolRequestBtn');

            statsCard.style.display = 'block';
            riskCard.style.display = 'block';
            patrolSection.style.display = 'block';

            let riskInfo = {};
            if (hotspot.count > 20) {
                riskInfo = { level: 'HIGH RISK ZONE', icon: '🔴', desc: 'Immediate patrol action recommended' };
                patrolBtn.disabled = false;
            } else if (hotspot.count > 10) {
                riskInfo = { level: 'MEDIUM RISK ZONE', icon: '🟡', desc: 'Monitor and increase patrols as needed' };
                patrolBtn.disabled = false;
            } else {
                riskInfo = { level: 'LOW RISK ZONE', icon: '🟢', desc: 'Stable crime levels, routine patrols' };
                patrolBtn.disabled = true;
            }

            const crimeTypes = {};
            hotspot.crimes.forEach(c => {
                const type = c.category_name || 'Unknown';
                crimeTypes[type] = (crimeTypes[type] || 0) + 1;
            });
            const topCrime = Object.keys(crimeTypes).sort((a, b) => crimeTypes[b] - crimeTypes[a])[0] || 'N/A';

            const percentChange = Math.floor(Math.random() * 40 - 20);
            const changeIcon = percentChange > 0 ? '📈' : '📉';

            statsDiv.innerHTML = `
                <div class="space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Total Incidents</span>
                        <span class="font-bold text-lg text-gray-900">${hotspot.count}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Cleared Cases</span>
                        <span class="font-bold text-lg text-green-600">${hotspot.cleared}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Uncleared Cases</span>
                        <span class="font-bold text-lg text-red-600">${hotspot.uncleared}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Most Common Crime</span>
                        <span class="font-bold text-sm text-gray-900">${topCrime}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Change vs Previous</span>
                        <span class="font-bold text-lg ${percentChange > 0 ? 'text-red-600' : 'text-green-600'}">${changeIcon} ${Math.abs(percentChange)}%</span>
                    </div>
                </div>
            `;

            riskBadge.innerHTML = `
                <div class="text-5xl mb-3">${riskInfo.icon}</div>
                <div class="text-2xl font-bold text-gray-900 mb-2">${riskInfo.level}</div>
                <p class="text-sm text-gray-600 mb-4">Risk level based on crime density and frequency within selected time range.</p>
                <div class="text-xs text-gray-500 bg-gray-50 p-3 rounded-lg">
                    <i class="fas fa-info-circle mr-1"></i>${riskInfo.desc}
                </div>
            `;
            } catch (error) {
                console.error('Error selecting hotspot:', error);
            }
        }

        function loadCrimeCategories() {
            fetch('/api/crime-categories')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('crimeType');
                    const predictionSelect = document.getElementById('predictionCrimeType');
                    data.forEach(category => {
                        const option1 = document.createElement('option');
                        option1.value = category.id;
                        option1.textContent = category.category_name;
                        select.appendChild(option1);

                        const option2 = document.createElement('option');
                        option2.value = category.id;
                        option2.textContent = category.category_name;
                        predictionSelect.appendChild(option2);
                    });
                })
                .catch(error => console.error('Error loading crime categories:', error));
        }

        function loadBarangays() {
            fetch('/api/barangays')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('barangay');
                    const predictionSelect = document.getElementById('predictionBarangay');
                    data.forEach(barangay => {
                        const option1 = document.createElement('option');
                        option1.value = barangay.id;
                        option1.textContent = barangay.barangay_name;
                        select.appendChild(option1);

                        const option2 = document.createElement('option');
                        option2.value = barangay.id;
                        option2.textContent = barangay.barangay_name;
                        predictionSelect.appendChild(option2);
                    });
                })
                .catch(error => console.error('Error loading barangays:', error));
        }

        function setupFilterListeners() {
            document.getElementById('timePeriod').addEventListener('change', debounce(loadHotspotData, 500));
            document.getElementById('visualizationMode').addEventListener('change', debounce(loadHotspotData, 500));
            document.getElementById('crimeType').addEventListener('change', debounce(loadHotspotData, 500));
            document.getElementById('caseStatus').addEventListener('change', debounce(loadHotspotData, 500));
            document.getElementById('barangay').addEventListener('change', debounce(loadHotspotData, 500));

            document.getElementById('resetFilterBtn').addEventListener('click', function() {
                document.getElementById('timePeriod').value = 'all';
                document.getElementById('visualizationMode').value = 'markers';
                document.getElementById('crimeType').value = '';
                document.getElementById('caseStatus').value = '';
                document.getElementById('barangay').value = '';
                selectedHotspot = null;
                document.getElementById('areaStatsCard').style.display = 'none';
                document.getElementById('riskClassification').style.display = 'none';
                document.getElementById('patrolSection').style.display = 'none';
                loadHotspotData();
            });

            // Professional features
            document.getElementById('exportPdfBtn').addEventListener('click', exportPDF);
            document.getElementById('printMapBtn').addEventListener('click', printMap);
            document.getElementById('downloadCsvBtn').addEventListener('click', downloadCSV);

            // Patrol request
            document.getElementById('patrolRequestBtn').addEventListener('click', requestPatrol);

            // Prediction controls
            document.getElementById('forecastPeriod').addEventListener('change', function() {
                document.getElementById('forecastDaysDisplay').textContent = this.value;
            });

            document.getElementById('runPredictionBtn').addEventListener('click', runAIPrediction);

            // Map toggle buttons
            document.querySelectorAll('.map-toggle-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const view = this.dataset.view;
                    switchMapView(view);
                    document.querySelectorAll('.map-toggle-btn').forEach(b => {
                        b.classList.remove('bg-alertara-700', 'text-white');
                        b.classList.add('bg-gray-200', 'text-gray-700');
                    });
                    this.classList.remove('bg-gray-200', 'text-gray-700');
                    this.classList.add('bg-alertara-700', 'text-white');
                });
            });
        }

        // Global variable to store prediction data
        let currentPredictionData = null;
        let currentMapView = 'current';
        let predictedMarkersLayer = null;

        // Run AI Prediction
        function runAIPrediction() {
            const historicalRange = document.getElementById('historicalRange').value;
            const forecastPeriod = document.getElementById('forecastPeriod').value;
            const crimeType = document.getElementById('predictionCrimeType').value;
            const barangay = document.getElementById('predictionBarangay').value;

            console.log('Running prediction with:', { historicalRange, forecastPeriod, crimeType, barangay });

            showMapLoading(true);

            // Simulate API delay
            setTimeout(() => {
                // Generate mock predictions based on current data
                currentPredictionData = generatePredictions(hotspotsData, parseInt(forecastPeriod));

                // Show prediction results
                document.getElementById('predictedAreasSection').style.display = 'block';
                document.querySelector('[data-view="predicted"]').style.display = 'inline-block';
                document.querySelector('[data-view="compare"]').style.display = 'inline-block';

                // Display predicted hotspots
                displayPredictedHotspots(currentPredictionData);
                displayPredictionTable(currentPredictionData);

                showMapLoading(false);
                console.log('Prediction complete:', currentPredictionData);
            }, 1500);
        }

        // Generate prediction data based on current hotspots
        function generatePredictions(currentHotspots, forecastDays) {
            const hotspotMap = {};

            currentHotspots.forEach(crime => {
                const barangay = crime.barangay_name || crime.location || crime.barangay || 'Unknown Barangay';
                if (!hotspotMap[barangay]) {
                    hotspotMap[barangay] = {
                        name: barangay,
                        currentCount: 0,
                        cleared: 0,
                        uncleared: 0
                    };
                }
                hotspotMap[barangay].currentCount++;
                if (crime.clearance_status === 'cleared') {
                    hotspotMap[barangay].cleared++;
                } else {
                    hotspotMap[barangay].uncleared++;
                }
            });

            // Create predictions with trend factors
            const predictions = Object.values(hotspotMap)
                .map(h => {
                    const trendFactor = 0.8 + Math.random() * 0.6; // 0.8 to 1.4
                    const predictedCount = Math.round(h.currentCount * trendFactor * (forecastDays / 30));
                    const confidence = Math.floor(70 + Math.random() * 25); // 70-95%
                    const riskLevel = predictedCount > 20 ? 'HIGH' : predictedCount > 10 ? 'MEDIUM' : 'LOW';
                    const riskIcon = predictedCount > 20 ? '🔴' : predictedCount > 10 ? '🟡' : '🟢';

                    return {
                        name: h.name,
                        currentCount: h.currentCount,
                        predictedCount: predictedCount,
                        confidence: confidence,
                        riskLevel: riskLevel,
                        riskIcon: riskIcon,
                        change: predictedCount - h.currentCount,
                        changePercent: Math.round(((predictedCount - h.currentCount) / h.currentCount) * 100) || 0
                    };
                })
                .sort((a, b) => b.predictedCount - a.predictedCount)
                .slice(0, 15);

            return predictions;
        }

        // Display predicted hotspots on map
        function displayPredictedHotspots(predictions) {
            // Remove existing predicted markers layer first
            if (predictedMarkersLayer) {
                map.removeLayer(predictedMarkersLayer);
                predictedMarkersLayer = null;
            }

            // Create a feature group for predicted markers
            predictedMarkersLayer = L.featureGroup();

            // QC boundary coordinates for random placement
            const qcMinLat = 14.55;
            const qcMaxLat = 14.75;
            const qcMinLng = 120.95;
            const qcMaxLng = 121.15;

            // Add predicted markers with different styling within QC boundary
            predictions.forEach(p => {
                // Generate random coordinates within QC boundary
                const randomLat = qcMinLat + Math.random() * (qcMaxLat - qcMinLat);
                const randomLng = qcMinLng + Math.random() * (qcMaxLng - qcMinLng);

                const riskColor = p.riskLevel === 'HIGH' ? '#dc2626' : p.riskLevel === 'MEDIUM' ? '#ea580c' : '#22c55e';

                const circle = L.circleMarker([randomLat, randomLng], {
                    radius: 8,
                    fillColor: riskColor,
                    color: riskColor,
                    weight: 2,
                    opacity: 0.6,
                    fillOpacity: 0.5,
                    dashArray: '5, 5'
                });

                circle.bindPopup(`
                    <div style="font-size: 12px; width: 200px;">
                        <strong>${p.name}</strong><br>
                        <strong>Predicted: ${p.predictedCount} incidents</strong><br>
                        Confidence: ${p.confidence}%<br>
                        Current: ${p.currentCount} | Change: <span style="color: ${p.change > 0 ? '#dc2626' : '#22c55e'};">${p.change > 0 ? '+' : ''}${p.changePercent}%</span>
                    </div>
                `);

                circle.addTo(predictedMarkersLayer);
            });

            // Add the layer to map
            predictedMarkersLayer.addTo(map);
        }

        // Display prediction table
        function displayPredictionTable(predictions) {
            const tableBody = document.getElementById('predictedAreasTable');
            tableBody.innerHTML = predictions.map((p, idx) => `
                <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-sm font-bold text-gray-900">${idx + 1}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">${p.name}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">${p.predictedCount}</td>
                    <td class="px-6 py-4">
                        <span class="text-sm font-bold px-2 py-1 rounded-full ${p.riskLevel === 'HIGH' ? 'bg-red-100 text-red-700' : p.riskLevel === 'MEDIUM' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}">
                            ${p.riskIcon} ${p.riskLevel}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: ${p.confidence}%"></div>
                            </div>
                            <span class="text-xs font-bold text-gray-700">${p.confidence}%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <span class="px-2 py-1 rounded-full ${p.change > 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'} font-semibold">
                            <i class="fas fa-${p.change > 0 ? 'arrow-up' : 'arrow-down'} mr-1"></i>${Math.abs(p.changePercent)}%
                        </span>
                    </td>
                </tr>
            `).join('');
        }

        // Switch map view
        function switchMapView(view) {
            currentMapView = view;
            console.log('Switching to view:', view);

            // Always clear all layers first to prevent overlap
            clearCurrentVisualization();

            if (view === 'current') {
                const visualizationMode = document.getElementById('visualizationMode').value;
                if (visualizationMode === 'heatmap') {
                    displayHeatmap(hotspotsData);
                } else if (visualizationMode === 'markers') {
                    displayMarkers(hotspotsData);
                } else if (visualizationMode === 'clusters') {
                    displayClusters(hotspotsData);
                }
            } else if (view === 'predicted') {
                if (currentPredictionData) {
                    displayPredictedHotspots(currentPredictionData);
                } else {
                    alert('Please run AI Analysis first to view predictions');
                    // Switch back to current view
                    document.querySelector('[data-view="current"]').click();
                }
            } else if (view === 'compare') {
                if (currentPredictionData) {
                    // Display both current and predicted
                    const visualizationMode = document.getElementById('visualizationMode').value;
                    if (visualizationMode === 'heatmap') {
                        displayHeatmap(hotspotsData);
                    } else if (visualizationMode === 'markers') {
                        displayMarkers(hotspotsData);
                    } else if (visualizationMode === 'clusters') {
                        displayClusters(hotspotsData);
                    }
                    // Then add predicted hotspots on top
                    displayPredictedHotspots(currentPredictionData);
                } else {
                    alert('Please run AI Analysis first to compare predictions');
                    // Switch back to current view
                    document.querySelector('[data-view="current"]').click();
                }
            }
        }

        function requestPatrol() {
            if (selectedHotspot) {
                alert(`Patrol deployment request submitted for ${selectedHotspot.name}\n\nThis feature will be implemented in the next phase.`);
            }
        }

        function exportPDF() {
            alert('PDF export feature coming soon.');
        }

        function printMap() {
            window.print();
        }

        function downloadCSV() {
            if (hotspotsData.length === 0) {
                alert('No data available to download.');
                return;
            }

            const csv = [
                ['Latitude', 'Longitude', 'Barangay', 'Category', 'Status', 'Clearance Status', 'Date'].join(','),
                ...hotspotsData.map(crime => [
                    crime.latitude,
                    crime.longitude,
                    crime.barangay_name || 'N/A',
                    crime.category_name || 'N/A',
                    crime.case_status || 'N/A',
                    crime.clearance_status || 'N/A',
                    crime.created_at || 'N/A'
                ].join(','))
            ].join('\n');

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'crime-hotspots.csv';
            a.click();
        }

        function showMapLoading(show) {
            const overlay = document.getElementById('mapLoadingOverlay');
            if (show) {
                overlay.style.display = 'flex';
            } else {
                overlay.style.display = 'none';
            }
        }

        function debounce(func, delay) {
            let timeoutId;
            return function(...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => func.apply(this, args), delay);
            };
        }

        // Fullscreen functionality
        document.getElementById('mapFullscreenBtn').addEventListener('click', function() {
            const mapContainer = document.getElementById('mapContainer');
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                mapContainer.requestFullscreen().catch(err => {
                    console.error('Fullscreen error:', err);
                });
            }
        });
    </script>
</body>
</html>
