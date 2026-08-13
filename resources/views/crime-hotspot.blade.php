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
    {{-- Needed by the street suggestions partial when saving a report --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

                <!-- Street-level prevention advice lives on this page: clicking a
                     street opens its crimes and generates suggestions for it. -->
                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-purple-200 bg-purple-50 p-3">
                    <i class="fas fa-shield-halved text-purple-700"></i>
                    <p class="flex-1 text-sm text-purple-900">
                        <span class="font-bold">Street prevention suggestions.</span>
                        Click any coloured street on the map to see every crime recorded there and generate
                        prevention suggestions for it — per street, per crime type.
                    </p>
                    <button type="button" id="zoomToStreetsBtn"
                            class="px-3 py-1.5 bg-purple-700 text-white rounded-lg hover:bg-purple-800 transition-colors text-xs font-bold flex items-center gap-2">
                        <i class="fas fa-magnifying-glass-location"></i>Zoom to San Agustin
                    </button>
                    <label class="flex items-center gap-2 text-xs font-semibold text-purple-900">
                        <input type="checkbox" id="toggleStreetLayer" checked>
                        Show streets
                    </label>
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

                        <!-- Export -->
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                            <div style="padding: 14px 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0;">
                                    <i class="fas fa-download mr-2 text-green-600"></i>Export
                                </h3>
                            </div>
                            <div style="padding: 12px;">
                                <button id="downloadCsvBtn" class="w-full px-3 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors text-sm font-semibold flex items-center justify-center gap-2">
                                    <i class="fas fa-download"></i>
                                    <span>Download CSV</span>
                                </button>
                                <p style="font-size: 11px; color: #6b7280; margin: 8px 0 0;">
                                    Every incident behind the current map view, as filtered.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-8">
                <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-red-900 mb-1">
                                <i class="fas fa-fire mr-1"></i>Critical/High-Risk Areas
                            </p>
                            <p class="text-2xl font-bold text-red-700" id="highRiskCount">0</p>
                            <p class="text-xs text-red-600 mt-1">Composite risk score ≥ 45</p>
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
                            <p class="text-xs text-yellow-600 mt-1">Composite risk score 25-44</p>
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
                            <p class="text-xs text-green-600 mt-1">Composite risk score < 25</p>
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
                            <span class="text-sm text-gray-700">Highest Risk Area</span>
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
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-700">Night-time Incidents</span>
                            <span id="nightPercent" class="text-sm font-bold text-indigo-600">0%</span>
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

            <!-- Analytical Insights Section -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-6 shadow-sm mt-6">
                <h3 class="text-lg font-bold text-purple-900 mb-4">
                    <i class="fas fa-lightbulb mr-2"></i>Analytical Insights & Recommendations
                </h3>
                <div id="aiInsights" class="space-y-3">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>
    </main>

    {{-- San Agustin streets + the street suggestion modal. This is the analysis
         page, so the "generate suggestions" flow lives here rather than on the
         crime map. --}}
    @include('partials.san-agustin-streets', ['withSuggestions' => true])

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

            // The QC boundary is a filled polygon covering the whole city. In the
            // default overlay pane it sits ON TOP of the street lines and eats
            // every hover and click meant for them, so it gets its own pane
            // below the streets (360) and the incident markers (400).
            map.createPane('boundaryPane');
            map.getPane('boundaryPane').style.zIndex = 350;

            // San Agustin streets, colour-coded by crime level. Clicking one
            // opens its crimes and the prevention suggestions for that street.
            if (typeof saStreetsAttach === 'function') {
                saStreetsAttach(map);
                saStreetsSetVisible(true);

                const toggle = document.getElementById('toggleStreetLayer');
                if (toggle) {
                    toggle.addEventListener('change', function () {
                        saStreetsSetVisible(this.checked);
                    });
                }

                const zoomBtn = document.getElementById('zoomToStreetsBtn');
                if (zoomBtn) {
                    zoomBtn.addEventListener('click', function () {
                        if (!toggle.checked) {
                            toggle.checked = true;
                            saStreetsSetVisible(true);
                        }
                        saStreetsFitBounds();
                    });
                }
            }

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
                        pane: 'boundaryPane',
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
                    // Every recorded incident is in San Agustin, and the street
                    // lines are unusable at whole-city zoom, so open there.
                    if (typeof saStreetsFitBounds === 'function') saStreetsFitBounds();

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

                    // Every recorded incident is in San Agustin, and the street
                    // lines are unusable at whole-city zoom, so open there.
                    if (typeof saStreetsFitBounds === 'function') saStreetsFitBounds();

                    loadCrimeCategories();
                    loadBarangays();
                    setupFilterListeners();
                    loadHotspotData();
                });
        }

        // Full analytics payload from the server (hotspot ranking, summary, charts)
        let analyticsData = null;

        function loadHotspotData() {
            showMapLoading(true);
            const timePeriod = document.getElementById('timePeriod').value;
            const visualizationMode = document.getElementById('visualizationMode').value;
            const crimeType = document.getElementById('crimeType').value;
            const caseStatus = document.getElementById('caseStatus').value;
            const barangay = document.getElementById('barangay').value;

            const params = new URLSearchParams({
                timePeriod: timePeriod,
                crimeType: crimeType,
                caseStatus: caseStatus,
                barangay: barangay
            });

            fetch(`/api/crime-hotspots?${params}`)
                .then(response => response.json())
                .then(data => {
                    analyticsData = data;
                    hotspotsData = data.crimes || [];

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

        const RISK_STYLES = {
            CRITICAL: { badge: 'bg-red-100 text-red-700', bar: 'bg-red-600', icon: '🔴' },
            HIGH: { badge: 'bg-orange-100 text-orange-700', bar: 'bg-orange-600', icon: '🟠' },
            MEDIUM: { badge: 'bg-yellow-100 text-yellow-700', bar: 'bg-yellow-600', icon: '🟡' },
            LOW: { badge: 'bg-green-100 text-green-700', bar: 'bg-green-600', icon: '🟢' },
        };

        function trendArrow(direction, percent) {
            if (direction === 'increasing') return `<span class="text-red-600"><i class="fas fa-arrow-up"></i> +${percent}%</span>`;
            if (direction === 'decreasing') return `<span class="text-green-600"><i class="fas fa-arrow-down"></i> ${percent}%</span>`;
            return `<span class="text-gray-500"><i class="fas fa-arrows-left-right"></i> stable</span>`;
        }

        function updateTopHotspots() {
            const hotspots = (analyticsData?.hotspots || []).slice(0, 10);
            const topHotspotsDiv = document.getElementById('topHotspots');

            if (!hotspots.length) {
                topHotspotsDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No crime data available</div>';
                updateKeyMetrics();
                return;
            }

            window.hotspotsData = hotspots;

            topHotspotsDiv.innerHTML = hotspots.map((h, idx) => {
                const style = RISK_STYLES[h.risk_level] || RISK_STYLES.LOW;
                return `
                <div class="border-b border-gray-100 p-3 hover:bg-gray-50 cursor-pointer transition-colors hotspot-item" data-index="${idx}">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <div class="font-semibold text-gray-900 text-sm">
                                <span class="text-lg mr-2">${style.icon}</span>${idx + 1}. ${h.area_name}
                            </div>
                            <div class="text-xs text-gray-600 mt-1">
                                <i class="fas fa-exclamation-circle mr-1"></i>${h.incident_count} incident(s)
                                ${h.crime_rate_per_1000 !== null ? ` · ${h.crime_rate_per_1000}/1k residents` : ''}
                                · ${trendArrow(h.trend_direction, h.trend_percent)}
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold px-2 py-1 rounded-full ${style.badge}">${h.risk_level}</span>
                            <div class="text-[10px] text-gray-400 mt-1 font-bold">score ${h.risk_score}</div>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                        <div class="h-full ${style.bar}" style="width: ${Math.min(h.risk_score, 100)}%"></div>
                    </div>
                </div>
            `}).join('');

            topHotspotsDiv.querySelectorAll('.hotspot-item').forEach(item => {
                item.addEventListener('click', function() {
                    const hotspot = window.hotspotsData[parseInt(this.dataset.index)];
                    selectHotspot(hotspot.area_name, hotspot);
                });
            });

            updateKeyMetrics();
            updateTrendAnalysis();
            renderCrimeDistributionChart();
            renderMonthlyTrendChart();
            generateInsights();
        }

        // Update key metrics cards from server-computed summary
        function updateKeyMetrics() {
            const summary = analyticsData?.summary;
            if (!summary) return;

            document.getElementById('highRiskCount').textContent = summary.risk_counts.critical + summary.risk_counts.high;
            document.getElementById('mediumRiskCount').textContent = summary.risk_counts.medium;
            document.getElementById('lowRiskCount').textContent = summary.risk_counts.low;
            document.getElementById('totalIncidentsCount').textContent = summary.total_incidents;
        }

        // Update trend analysis indicators from real server data
        function updateTrendAnalysis() {
            const summary = analyticsData?.summary;
            if (!summary) return;

            document.getElementById('highestDensity').textContent = summary.highest_risk?.area_name ?? 'N/A';
            document.getElementById('clearanceRate').textContent = summary.clearance_rate + '%';
            document.getElementById('unsolvedCount').textContent = summary.unsolved_count;
            document.getElementById('nightPercent').textContent = (analyticsData.day_night?.night_percent ?? 0) + '%';

            const trend = summary.citywide_trend;
            const label = trend.direction.charAt(0).toUpperCase() + trend.direction.slice(1);
            const icon = trend.direction === 'increasing' ? 'arrow-up text-red-600'
                       : trend.direction === 'decreasing' ? 'arrow-down text-green-600'
                       : 'arrows-left-right text-gray-500';
            document.getElementById('overallTrend').innerHTML =
                `<i class="fas fa-${icon} mr-1"></i>${label} ${trend.percent > 0 ? '+' : ''}${trend.percent}% <span class="text-xs text-gray-400 font-normal">(vs prev ${trend.window_days}d)</span>`;
        }

        // Crime Distribution Chart (server-computed distribution)
        let crimeDistributionChartInstance = null;
        function renderCrimeDistributionChart() {
            const distribution = analyticsData?.type_distribution;
            const ctx = document.getElementById('crimeDistributionCanvas');
            if (!ctx || !distribution) return;

            if (crimeDistributionChartInstance) {
                crimeDistributionChartInstance.destroy();
            }

            const colors = ['#dc2626', '#ea580c', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9'];

            crimeDistributionChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: distribution.labels,
                    datasets: [{
                        data: distribution.values,
                        backgroundColor: colors.slice(0, distribution.labels.length),
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

        // Monthly Trend Chart (real 12-month incident counts from the database)
        let monthlyTrendChartInstance = null;
        function renderMonthlyTrendChart() {
            const trends = analyticsData?.monthly_trends;
            const ctx = document.getElementById('monthlyTrendCanvas');
            if (!ctx || !trends) return;

            if (monthlyTrendChartInstance) {
                monthlyTrendChartInstance.destroy();
            }

            monthlyTrendChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trends.labels,
                    datasets: [{
                        label: 'Incidents',
                        data: trends.values,
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
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        }

        // Generate insights from the server-computed analytics (deterministic rules, no randomness)
        function generateInsights() {
            const insights = [];
            const hotspots = analyticsData?.hotspots || [];
            const summary = analyticsData?.summary;
            const dayNight = analyticsData?.day_night;

            if (!hotspots.length || !summary) {
                insights.push({
                    type: 'neutral',
                    icon: 'info-circle',
                    title: 'No Data',
                    description: 'No crime data available for the selected filters.'
                });
            } else {
                const top = hotspots[0];

                // Top risk area, explained via its composite score components
                insights.push({
                    type: top.risk_level === 'CRITICAL' || top.risk_level === 'HIGH' ? 'danger' : 'info',
                    icon: 'fire',
                    title: `Highest Risk: ${top.area_name} (score ${top.risk_score}/100)`,
                    description: `${top.incident_count} incidents${top.crime_rate_per_1000 !== null ? ` (${top.crime_rate_per_1000} per 1,000 residents)` : ''}, severity index ${top.severity_index}/4, mostly ${top.top_category}. <a href="/pattern-detection" class="underline font-semibold">Simulate interventions →</a>`
                });

                // Rising areas
                const rising = hotspots.filter(h => h.trend_direction === 'increasing');
                if (rising.length > 0) {
                    insights.push({
                        type: 'warning',
                        icon: 'arrow-trend-up',
                        title: `${rising.length} Area(s) with Rising Incidents`,
                        description: `${rising.slice(0, 3).map(h => `${h.area_name} (+${h.trend_percent}%)`).join(', ')}${rising.length > 3 ? '…' : ''} vs the previous period. Prioritize preventive deployment before these harden into hotspots.`
                    });
                }

                // Night-time concentration
                if (dayNight && dayNight.night_percent >= 50) {
                    insights.push({
                        type: 'warning',
                        icon: 'moon',
                        title: `${dayNight.night_percent}% of Incidents Happen at Night`,
                        description: 'Night-heavy pattern detected (6PM–6AM). Street lighting and night patrol interventions are most applicable — test them in the Pattern Detection simulator.'
                    });
                }

                // Clearance rate insight (real)
                if (summary.clearance_rate < 50) {
                    insights.push({
                        type: 'warning',
                        icon: 'triangle-exclamation',
                        title: 'Low Clearance Rate',
                        description: `Only ${summary.clearance_rate}% of cases are cleared (${summary.unsolved_count} unsolved). Focus on case resolution strategies.`
                    });
                } else {
                    insights.push({
                        type: 'success',
                        icon: 'check-circle',
                        title: 'Good Clearance Performance',
                        description: `${summary.clearance_rate}% of cases are cleared. Maintain current investigation efforts.`
                    });
                }

                // Citywide trend
                const trend = summary.citywide_trend;
                if (trend.direction !== 'stable') {
                    insights.push({
                        type: trend.direction === 'increasing' ? 'danger' : 'success',
                        icon: trend.direction === 'increasing' ? 'arrow-up' : 'arrow-down',
                        title: `Citywide Incidents ${trend.direction === 'increasing' ? 'Up' : 'Down'} ${Math.abs(trend.percent)}%`,
                        description: `Compared to the previous ${trend.window_days}-day period across all filtered areas.`
                    });
                }
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

            statsCard.style.display = 'block';
            riskCard.style.display = 'block';

            const style = RISK_STYLES[hotspot.risk_level] || RISK_STYLES.LOW;
            const riskDescriptions = {
                CRITICAL: 'Immediate patrol action recommended',
                HIGH: 'Elevated patrol presence recommended',
                MEDIUM: 'Monitor and increase patrols as needed',
                LOW: 'Stable crime levels, routine patrols',
            };

            const pc = hotspot.trend_percent;
            const changeIcon = pc > 0 ? '📈' : pc < 0 ? '📉' : '➖';

            statsDiv.innerHTML = `
                <div class="space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Total Incidents</span>
                        <span class="font-bold text-lg text-gray-900">${hotspot.incident_count}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Crime Rate</span>
                        <span class="font-bold text-sm text-gray-900">${hotspot.crime_rate_per_1000 !== null ? hotspot.crime_rate_per_1000 + ' / 1,000 residents' : 'No population data'}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Severity Index</span>
                        <span class="font-bold text-lg text-gray-900">${hotspot.severity_index}<span class="text-xs text-gray-400 font-normal">/4</span></span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Cleared / Uncleared</span>
                        <span class="font-bold text-sm"><span class="text-green-600">${hotspot.cleared}</span> / <span class="text-red-600">${hotspot.uncleared}</span></span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Most Common Crime</span>
                        <span class="font-bold text-sm text-gray-900">${hotspot.top_category ?? 'N/A'}</span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Night-time Share</span>
                        <span class="font-bold text-sm text-indigo-600">${hotspot.night_percent}%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Change vs Previous</span>
                        <span class="font-bold text-lg ${pc > 0 ? 'text-red-600' : pc < 0 ? 'text-green-600' : 'text-gray-500'}">${changeIcon} ${pc > 0 ? '+' : ''}${pc}%</span>
                    </div>
                    <a href="/pattern-detection" class="block w-full text-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-semibold text-sm">
                        <i class="fas fa-flask mr-2"></i>Simulate Interventions for This Area
                    </a>
                </div>
            `;

            riskBadge.innerHTML = `
                <div class="text-5xl mb-3">${style.icon}</div>
                <div class="text-2xl font-bold text-gray-900 mb-2">${hotspot.risk_level} RISK ZONE</div>
                <div class="text-sm font-bold text-gray-500 mb-2">Composite Risk Score: ${hotspot.risk_score}/100</div>
                <p class="text-sm text-gray-600 mb-4">Score = 50% volume (population-adjusted rate) + 30% severity + 20% trend.</p>
                <div class="text-xs text-gray-500 bg-gray-50 p-3 rounded-lg">
                    <i class="fas fa-info-circle mr-1"></i>${riskDescriptions[hotspot.risk_level]}
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
                    data.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category.id;
                        option.textContent = category.category_name;
                        select.appendChild(option);
                    });
                })
                .catch(error => console.error('Error loading crime categories:', error));
        }

        function loadBarangays() {
            fetch('/api/barangays')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('barangay');
                    data.forEach(barangay => {
                        const option = document.createElement('option');
                        option.value = barangay.id;
                        option.textContent = barangay.barangay_name;
                        select.appendChild(option);
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
                loadHotspotData();
            });

            document.getElementById('downloadCsvBtn').addEventListener('click', downloadCSV);

        }

        // Export the incidents behind the current map view, as filtered
        function downloadCSV() {
            if (!hotspotsData.length) {
                alert('No data available to download.');
                return;
            }

            const cell = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
            const street = (address) => String(address || '').split(',')[0].trim();

            const csv = [
                ['Date', 'Title', 'Category', 'Street', 'Barangay', 'Clearance', 'Latitude', 'Longitude']
                    .map(cell).join(','),
                ...hotspotsData.map(crime => [
                    crime.incident_date, crime.incident_title, crime.category_name,
                    street(crime.location), crime.barangay_name, crime.clearance_status,
                    crime.latitude, crime.longitude
                ].map(cell).join(','))
            ].join('\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `crime-hotspots-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
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
