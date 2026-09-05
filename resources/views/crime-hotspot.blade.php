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
    <!-- 3D view: MapLibre GL with free OpenFreeMap vector tiles (no API key) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.min.js"></script>
    <script src="{{ asset('js/crime-map-3d.js') }}?v={{ filemtime(public_path('js/crime-map-3d.js')) }}"></script>
    <meta name="google-maps-key" content="{{ config('services.google_maps.key') }}">
    <!-- Google Maps (default map engine): loaded once via the official bootstrap loader -->
    <script src="{{ asset('js/google-maps-loader.js') }}?v={{ filemtime(public_path('js/google-maps-loader.js')) }}"></script>
    <script src="{{ asset('js/crime-map-google.js') }}?v={{ filemtime(public_path('js/crime-map-google.js')) }}"></script>
    <style>
        .map-3d-btn.on { background: #274d4c !important; color: #fff !important; border-color: #274d4c !important; }
    </style>

    <style>
        /* Barangay name on the polygon - same treatment as the crime map */
        .brgy-label-selected {
            background: transparent;
            border: none;
            box-shadow: none;
            color: #123332;
            font-size: 13px;
            font-weight: 800;
            text-shadow: 0 0 4px #fff, 0 0 8px #fff, 0 1px 2px #fff;
            white-space: nowrap;
        }
        .brgy-label-selected::before { display: none; }
    </style>

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
                                <option value="heatmap">Heat Map (density)</option>
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
                            @php($sanAgustin = $barangays->first(fn ($item) => mb_strtolower(trim($item->barangay_name)) === 'san agustin'))
                            <select id="barangay" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="{{ $sanAgustin?->id ?? '' }}" selected>San Agustin</option>
                                <option value="">All Barangays</option>
                            </select>
                        </div>

                        <!-- Street checkbox dropdown -->
                        <div class="relative" id="streetFilterDropdown">
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Street(s)</label>
                            <button type="button" id="streetDropdownButton" aria-expanded="false"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white flex items-center justify-between text-left">
                                <span id="streetDropdownLabel">All streets</span>
                                <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                            </button>
                            <div id="streetDropdownPanel" class="hidden absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg p-2">
                                <div id="streetCheckboxList" class="max-h-56 overflow-y-auto space-y-1 p-1"></div>
                                <button type="button" id="removeStreetSelectionBtn" class="hidden mt-2 w-full px-3 py-2 text-sm font-semibold text-red-700 border border-red-200 rounded-lg hover:bg-red-50">
                                    <i class="fas fa-xmark mr-1"></i>Remove selection
                                </button>
                            </div>
                            <select id="streets" multiple class="hidden" aria-hidden="true" tabindex="-1"></select>
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

                <!-- Streets are the unit of analysis here: click one to break it down. -->
                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-purple-200 bg-purple-50 p-3">
                    <i class="fas fa-shield-halved text-purple-700"></i>
                    <p class="flex-1 text-sm text-purple-900">
                        <span class="font-bold">Click a street</span> on the map or in the ranking for its breakdown and prevention plan.
                    </p>
                    <button type="button" id="zoomToStreetsBtn"
                            class="px-3 py-1.5 bg-purple-700 text-white rounded-lg hover:bg-purple-800 transition-colors text-xs font-bold flex items-center gap-2">
                        <i class="fas fa-magnifying-glass-location"></i>Zoom to San Agustin
                    </button>
                    <label class="flex items-center gap-2 text-xs font-semibold text-purple-900">
                        <input type="checkbox" id="toggleStreetLayer" checked>
                        Show streets
                    </label>
                    <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-xs font-semibold ml-auto" role="group" aria-label="Map engine">
                        <button id="mapGoogleBtn" type="button" class="map-engine-btn px-3 py-1.5 bg-white text-gray-700 hover:bg-gray-50 flex items-center gap-1" title="Google Maps (satellite + roads)"><i class="fab fa-google"></i><span class="hidden sm:inline">Google</span></button>
                        <button id="map2dBtn" type="button" class="map-engine-btn px-3 py-1.5 bg-white text-gray-700 hover:bg-gray-50 flex items-center gap-1 border-l border-gray-300" title="Classic 2D map (street modal, heat map)"><i class="fas fa-map"></i><span class="hidden sm:inline">Classic</span></button>
                        <button id="map3dBtn" type="button" class="map-engine-btn px-3 py-1.5 bg-white text-gray-700 hover:bg-gray-50 flex items-center gap-1 border-l border-gray-300" title="3D map"><i class="fas fa-cube"></i><span class="hidden sm:inline">3D</span></button>
                    </div>
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
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0; display: flex; align-items: center; gap: 6px;">
                                    <i class="fas fa-list-ol text-red-600"></i>Top 10 Hotspot Streets
                                    <i class="fas fa-circle-info text-gray-400 cursor-help" style="font-size: 12px;"
                                       title="Risk score out of 100 = 40% volume + 20% density (crimes per 100 m) + 25% severity + 15% trend"></i>
                                    <span style="margin-left: auto; display: flex; gap: 8px; font-size: 10px; font-weight: 600; color: #6b7280;">
                                        <span><i class="fas fa-layer-group mr-1"></i>incidents</span>
                                        <span><i class="fas fa-arrow-trend-up mr-1"></i>trend</span>
                                        <span><i class="fas fa-clock mr-1"></i>peak</span>
                                    </span>
                                </h3>
                            </div>
                            <div id="topHotspots" style="overflow-y: auto; max-height: 380px;">
                                <!-- Will be populated by JavaScript -->
                                <div style="padding: 20px; text-align: center; color: #999;">
                                    <i class="fas fa-spinner fa-spin text-2xl"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Selected street breakdown -->
                        <div id="streetAnalysisCard" class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm" style="display: none;">
                            <div style="padding: 14px 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; display: flex; align-items: center; gap: 8px;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0; flex: 1;">
                                    <i class="fas fa-location-crosshairs mr-2 text-purple-700"></i><span id="streetAnalysisName">Street</span>
                                </h3>
                                <button type="button" id="clearStreetSelection" title="Clear selection"
                                        style="background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 14px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="streetAnalysisBody" style="padding: 14px 16px; max-height: 460px; overflow-y: auto;"></div>
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
                                <i class="fas fa-fire mr-1"></i>Critical/High-Risk Streets
                            </p>
                            <p class="text-2xl font-bold text-red-700" id="highRiskCount">0</p>
                            <div class="mt-2 h-1.5 w-40 max-w-full rounded-full bg-white/70 overflow-hidden"><div id="highRiskBar" class="h-full rounded-full" style="width:0%;background:#dc2626;"></div></div>
                            <p class="text-[11px] mt-1 opacity-80" id="highRiskBarLabel">&nbsp;</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-yellow-900 mb-1">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Medium-Risk Streets
                            </p>
                            <p class="text-2xl font-bold text-yellow-700" id="mediumRiskCount">0</p>
                            <div class="mt-2 h-1.5 w-40 max-w-full rounded-full bg-white/70 overflow-hidden"><div id="mediumRiskBar" class="h-full rounded-full" style="width:0%;background:#f59e0b;"></div></div>
                            <p class="text-[11px] mt-1 opacity-80" id="mediumRiskBarLabel">&nbsp;</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-green-900 mb-1">
                                <i class="fas fa-check-circle mr-1"></i>Low-Risk Streets
                            </p>
                            <p class="text-2xl font-bold text-green-700" id="lowRiskCount">0</p>
                            <div class="mt-2 h-1.5 w-40 max-w-full rounded-full bg-white/70 overflow-hidden"><div id="lowRiskBar" class="h-full rounded-full" style="width:0%;background:#16a34a;"></div></div>
                            <p class="text-[11px] mt-1 opacity-80" id="lowRiskBarLabel">&nbsp;</p>
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
                            <div class="mt-2 flex h-1.5 w-40 max-w-full rounded-full bg-white/70 overflow-hidden" id="totalIncidentsBar"></div>
                            <p class="text-[11px] mt-1 text-blue-700 opacity-80" id="totalIncidentsLabel">&nbsp;</p>
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
                    <div id="trendAnalysis">
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="text-[11px] font-bold text-gray-500 uppercase">Overall Trend</div>
                                <div id="overallTrend" class="text-sm font-bold text-gray-900 mt-1">
                                    <i class="fas fa-arrows-left-right mr-1 text-gray-500"></i>—
                                </div>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="text-[11px] font-bold text-gray-500 uppercase">Highest Risk Street</div>
                                <div id="highestDensity" class="text-sm font-bold text-gray-900 mt-1 truncate">—</div>
                            </div>
                        </div>

                        <!-- The rates drawn, not just quoted: clearance, day vs night, risk mix -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="text-[11px] font-bold text-gray-500 uppercase mb-1">Case clearance</div>
                                <div style="position: relative; height: 120px;">
                                    <canvas id="clearanceCanvas"></canvas>
                                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                                        <span id="clearanceRate" class="text-lg font-bold text-green-600 leading-none">0%</span>
                                        <span class="text-[10px] text-gray-500">cleared</span>
                                    </div>
                                </div>
                                <div class="text-[11px] text-gray-600 text-center mt-1"><span id="unsolvedCount" class="font-bold text-red-600">0</span> unsolved cases</div>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="text-[11px] font-bold text-gray-500 uppercase mb-1">Day vs night</div>
                                <div style="position: relative; height: 120px;">
                                    <canvas id="dayNightCanvas"></canvas>
                                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                                        <span id="nightPercent" class="text-lg font-bold text-indigo-600 leading-none">0%</span>
                                        <span class="text-[10px] text-gray-500">at night</span>
                                    </div>
                                </div>
                                <div class="text-[11px] text-gray-600 text-center mt-1">6 PM – 6 AM counts as night</div>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-lg p-3 mt-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[11px] font-bold text-gray-500 uppercase">Street risk mix</span>
                                <span id="riskMixTotal" class="text-[11px] text-gray-500">0 streets</span>
                            </div>
                            <div id="riskMixBar" class="flex h-3 rounded-full overflow-hidden bg-gray-100"></div>
                            <div id="riskMixLegend" class="flex flex-wrap gap-x-3 gap-y-1 mt-2 text-[11px] text-gray-600"></div>
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

            <!-- Date & time trend -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-900 mb-1">
                        <i class="fas fa-chart-line mr-2 text-alertara-700"></i>12-Month Trend
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">Incidents per month across the barangay</p>
                    <div id="monthlyTrendChart" style="position: relative; height: 300px;">
                        <canvas id="monthlyTrendCanvas"></canvas>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-900 mb-1">
                        <i class="fas fa-clock mr-2 text-alertara-700"></i>Time of Day
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">
                        When crime happens - busiest window: <span id="peakPeriodLabel" class="font-bold text-gray-900">--</span>
                    </p>
                    <div id="hourlyChart" style="position: relative; height: 300px;">
                        <canvas id="hourlyCanvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Ranking & weekday charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-900 mb-1">
                        <i class="fas fa-ranking-star mr-2 text-alertara-700"></i>Risk Score by Street
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">Top 10 streets, composite score out of 100 - bar colour is the risk level</p>
                    <div id="riskScoreChart" style="position: relative; height: 300px;">
                        <canvas id="riskScoreCanvas"></canvas>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-900 mb-1">
                        <i class="fas fa-calendar-week mr-2 text-alertara-700"></i>Day of Week
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Incidents per weekday - busiest: <span id="peakDayLabel" class="font-bold text-gray-900">--</span>
                    </p>
                    <div id="weekdayChart" style="position: relative; height: 300px;">
                        <canvas id="weekdayCanvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Analytical Insights Section: findings on the left, the charts
                 that back them on the right -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-6 shadow-sm mt-6">
                <h3 class="text-lg font-bold text-purple-900 mb-4">
                    <i class="fas fa-lightbulb mr-2"></i>Analytical Insights & Recommendations
                </h3>
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <div id="aiInsights">
                        <!-- Populated by JavaScript -->
                    </div>
                    <div class="space-y-4">
                        <div class="bg-white border border-purple-200 rounded-lg p-4">
                            <div class="text-sm font-bold text-gray-900 mb-0.5">
                                <i class="fas fa-arrow-trend-up mr-1 text-purple-700"></i>Rising vs falling streets
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Change vs the previous period. Red bars are rising, green are falling.</p>
                            <div style="position: relative; height: 220px;">
                                <canvas id="trendByStreetCanvas"></canvas>
                            </div>
                        </div>
                        <div class="bg-white border border-purple-200 rounded-lg p-4">
                            <div class="text-sm font-bold text-gray-900 mb-0.5">
                                <i class="fas fa-folder-open mr-1 text-purple-700"></i>Cleared vs open cases by street
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Where the unsolved cases pile up.</p>
                            <div style="position: relative; height: 220px;">
                                <canvas id="clearanceByStreetCanvas"></canvas>
                            </div>
                        </div>
                    </div>
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

            // Google Maps (default, Hybrid imagery) and the 3D view over the
            // same container, both fed by currentData
            const engines = {};
            if (typeof CrimeMapGoogle !== 'undefined') {
                try {
                    window.crimeMapGoogle = engines.google = CrimeMapGoogle.create({
                        wrapper: document.getElementById('mapContainer'),
                        getIncidents: () => currentData,
                        getMode: () => document.getElementById('visualizationMode').value,
                        modeSelect: document.getElementById('visualizationMode'),
                    });
                } catch (e) { console.warn('Google Maps view unavailable:', e); }
            }
            if (typeof CrimeMap3D !== 'undefined') {
                try {
                    window.crimeMap3D = engines['3d'] = CrimeMap3D.create({
                        wrapper: document.getElementById('mapContainer'),
                        getIncidents: () => currentData,
                        getMode: () => document.getElementById('visualizationMode').value,
                        modeSelect: document.getElementById('visualizationMode'),
                    });
                } catch (e) { console.warn('3D view unavailable:', e); }
            }
            if (typeof CrimeMapGoogle !== 'undefined') {
                CrimeMapGoogle.switcher({
                    engines: engines,
                    buttons: { google: document.getElementById('mapGoogleBtn'), '2d': document.getElementById('map2dBtn'), '3d': document.getElementById('map3dBtn') },
                    defaultEngine: 'google',
                    storageKey: 'crimeHotspotEngine',
                });
            }

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

            // Keep incident symbols unobtrusive while viewing the whole
            // barangay, then let them grow as the user zooms into a street.
            map.on('zoomend', updateVisualizationScale);

            console.log('Map initialized');
        }

        // Boundaries, drawn exactly like the crime map: a thin unfilled outline
        // for Quezon City, every one of its 142 barangays outlined thinly for
        // context, and San Agustin marked active on top. All of it is
        // non-interactive and lives in a pane BELOW the street lines, so it can
        // never swallow a street hover or click.
        const STYLE_QC_OUTLINE  = { color: '#274d4c', weight: 1.25, opacity: 0.8, fill: false };
        const STYLE_BRGY_IDLE   = { color: '#5b8f8c', weight: 0.65, opacity: 0.65, fillColor: '#e8f5f3', fillOpacity: 0.15 };
        const STYLE_BRGY_ACTIVE = { color: '#274d4c', weight: 1.5,  opacity: 0.9, fillColor: '#9ed4cb', fillOpacity: 0.22 };

        const ACTIVE_BARANGAY = 'san agustin';

        let barangayBoundaryLayer = null;
        let barangayRenderer = null;
        let activeBarangayLayer = null;
        let activeBarangayLabel = null;
        let barangayBounds = null;

        async function loadQCBoundary() {
            const timestamp = Date.now();
            barangayRenderer = barangayRenderer || L.svg({ pane: 'boundaryPane' });

            // 1. Whole-QC outline. fullmapqc.geojson is the PSGC city outline, so
            //    it lines up exactly with the PSGC barangay polygons below.
            try {
                const response = await fetch(`/fullmapqc.geojson?t=${timestamp}`);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();

                if (boundaryLayer) map.removeLayer(boundaryLayer);
                boundaryLayer = L.geoJSON(data, {
                    style: STYLE_QC_OUTLINE,
                    interactive: false,
                    pane: 'boundaryPane',
                    renderer: barangayRenderer
                }).addTo(map);

                qcBounds = boundaryLayer.getBounds();
            } catch (error) {
                console.error('Error loading QC outline:', error);
                qcBounds = L.latLngBounds(L.latLng(14.50, 120.90), L.latLng(14.80, 121.20));
            }

            // 2. The 142 Quezon City barangays. They stay on the map for context
            //    exactly as on the crime map; San Agustin is the active one.
            try {
                const response = await fetch(`/qc_barangays.geojson?t=${timestamp}`);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();

                if (barangayBoundaryLayer) map.removeLayer(barangayBoundaryLayer);
                barangayBoundaryLayer = L.geoJSON(data, {
                    style: (feature) => Object.assign({},
                        String((feature.properties || {}).name || '').trim().toLowerCase() === ACTIVE_BARANGAY
                            ? STYLE_BRGY_ACTIVE
                            : STYLE_BRGY_IDLE),
                    interactive: false,
                    pane: 'boundaryPane',
                    renderer: barangayRenderer,
                    onEachFeature: (feature, layer) => {
                        const name = String((feature.properties || {}).name || '').trim();
                        if (name.toLowerCase() !== ACTIVE_BARANGAY) return;

                        activeBarangayLayer = layer;
                        barangayBounds = layer.getBounds();

                        // Name sits on the polygon, same as the crime map
                        activeBarangayLabel = L.marker(barangayBounds.getCenter(), {
                            interactive: false,
                            icon: L.divIcon({ className: '', html: '' })
                        }).addTo(map);
                        activeBarangayLabel.bindTooltip(name, {
                            permanent: true,
                            direction: 'center',
                            className: 'brgy-label-selected'
                        }).openTooltip();
                    }
                }).addTo(map);

                // Ordering only shuffles layers inside 'boundaryPane'; the street
                // lines and incident markers sit in higher panes regardless.
                if (activeBarangayLayer) activeBarangayLayer.bringToFront();
            } catch (error) {
                console.error('Error loading barangay boundaries:', error);
            }

            map.invalidateSize();

            // Open on the barangay, not the whole city: at citywide zoom the
            // street lines are too small to read, hover or click.
            if (barangayBounds && barangayBounds.isValid()) {
                map.fitBounds(barangayBounds, { padding: [24, 24], animate: false });
            } else if (qcBounds && qcBounds.isValid()) {
                map.fitBounds(qcBounds, { padding: [20, 20], animate: false });
            }

            loadCrimeCategories();
            loadBarangays();
            loadStreets();
            setupFilterListeners();
            loadHotspotData();
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
            const streets = Array.from(document.getElementById('streets').selectedOptions)
                .map(option => option.value);

            const params = new URLSearchParams({
                timePeriod: timePeriod,
                crimeType: crimeType,
                caseStatus: caseStatus,
                barangay: barangay
            });
            streets.forEach(street => params.append('streets[]', street));

            fetch(`/api/crime-hotspots?${params}`)
                .then(response => response.json())
                .then(data => {
                    analyticsData = data;
                    hotspotsData = data.crimes || [];

                    currentData = hotspotsData;
                    currentVisualizationMode = visualizationMode;
                    if (window.crimeMap3D) window.crimeMap3D.refresh();
                    if (window.crimeMapGoogle) window.crimeMapGoogle.refresh();

                    // Clear current visualization
                    clearCurrentVisualization();

                    // Display based on selected mode
                    if (visualizationMode === 'heatmap') {
                        displayHeatmap(hotspotsData);
                    } else {
                        displayMarkers(hotspotsData);
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
                    radius: visualizationRadiusForZoom(),
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
            const radius = markerRadiusForZoom();

            data.forEach(incident => {
                const markerColor = incident.color_code || '#274d4c';

                const marker = L.circleMarker([incident.latitude, incident.longitude], {
                    radius,
                    fillColor: markerColor,
                    color: markerColor,
                    weight: 1.25,
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

        function markerRadiusForZoom() {
            const zoom = map ? map.getZoom() : 16;
            if (zoom <= 14) return 3;
            if (zoom <= 16) return 4;
            return 5;
        }

        function visualizationRadiusForZoom() {
            const zoom = map ? map.getZoom() : 16;
            if (zoom <= 14) return 24;
            if (zoom <= 16) return 32;
            return heatmapRadius;
        }

        function updateVisualizationScale() {
            if (markerLayer) {
                const radius = markerRadiusForZoom();
                markerLayer.eachLayer(marker => marker.setRadius(radius));
            }

            if (heatmapLayer && typeof heatmapLayer.setOptions === 'function') {
                heatmapLayer.setOptions({ radius: visualizationRadiusForZoom() });
            }
        }

        function calculateCrimeWeight(incident) {
            let weight = 0.5;
            if (incident.clearance_status === 'uncleared') {
                weight += 0.5;
            }
            return Math.min(weight * heatmapIntensity, 1.0);
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


            topHotspotsDiv.innerHTML = hotspots.map((h) => {
                const style = RISK_STYLES[h.risk_level] || RISK_STYLES.LOW;
                const color = RISK_BAR_COLOR[h.risk_level] || '#9ca3af';
                const total = Math.max(1, h.incident_count);

                // Crime mix as a stacked bar: one segment per type, in the
                // type's own colour, hover for the count
                const stack = h.categories.map(c =>
                    `<div style="width:${c.count / total * 100}%;background:${c.color};" title="${escapeHtml(c.name)}: ${c.count}"></div>`).join('');
                const pc = h.trend_percent;
                const trendColor = pc > 0 ? '#b91c1c' : pc < 0 ? '#15803d' : '#6b7280';
                const trendIcon = pc > 0 ? 'fa-arrow-trend-up' : pc < 0 ? 'fa-arrow-trend-down' : 'fa-arrows-left-right';

                return `
                <div class="border-b border-gray-100 px-3 py-2.5 hover:bg-gray-50 cursor-pointer transition-colors hotspot-item" data-street="${escapeHtml(h.area_name)}">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center justify-center rounded-lg text-white text-[11px] font-black" style="width:24px;height:24px;background:${color};flex-shrink:0;">${h.rank}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-gray-900 text-sm truncate">${escapeHtml(h.area_name)}</span>
                                <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-full ${style.badge}">${h.risk_level}</span>
                            </div>
                            <div class="flex h-2 rounded-full overflow-hidden bg-gray-100 mt-1.5">${stack}</div>
                            <div class="flex items-center gap-3 mt-1.5 text-[11px] font-semibold text-gray-600">
                                <span title="Incidents"><i class="fas fa-layer-group mr-1 text-gray-400"></i>${h.incident_count}</span>
                                <span title="Trend vs previous period" style="color:${trendColor}"><i class="fas ${trendIcon} mr-1"></i>${pc > 0 ? '+' : ''}${pc}%</span>
                                ${h.peak_period ? `<span title="Peak hours"><i class="fas fa-clock mr-1 text-gray-400"></i>${escapeHtml(h.peak_period)}</span>` : ''}
                                ${h.density_per_100m !== null ? `<span title="Crimes per 100 m" class="hidden xl:inline"><i class="fas fa-compress mr-1 text-gray-400"></i>${h.density_per_100m}</span>` : ''}
                            </div>
                        </div>
                        ${ringSvg(h.risk_score, color, 40, h.risk_score)}
                    </div>
                </div>
            `}).join('');

            topHotspotsDiv.querySelectorAll('.hotspot-item').forEach(item => {
                item.addEventListener('click', function() {
                    selectHotspot(this.dataset.street, hotspotByStreet(this.dataset.street));
                });
            });

            updateKeyMetrics();
            updateTrendAnalysis();
            renderCrimeDistributionChart();
            renderMonthlyTrendChart();
            renderHourlyChart();
            renderWeekdayChart();
            renderRiskScoreChart();
            renderInsightCharts();
            generateInsights();
        }

        // Update key metrics cards from server-computed summary
        function updateKeyMetrics() {
            const summary = analyticsData?.summary;
            if (!summary) return;

            const rc = summary.risk_counts;
            const high = rc.critical + rc.high;
            const streets = high + rc.medium + rc.low;
            const share = (n) => streets ? Math.round(n / streets * 100) : 0;
            document.getElementById('highRiskCount').textContent = high;
            document.getElementById('mediumRiskCount').textContent = rc.medium;
            document.getElementById('lowRiskCount').textContent = rc.low;
            document.getElementById('totalIncidentsCount').textContent = summary.total_incidents;

            // Share of all ranked streets, as a bar under each number
            [['highRiskBar', high], ['mediumRiskBar', rc.medium], ['lowRiskBar', rc.low]].forEach(([id, n]) => {
                document.getElementById(id).style.width = share(n) + '%';
                document.getElementById(id + 'Label').textContent = `${share(n)}% of ${streets} street${streets === 1 ? '' : 's'}`;
            });

            // Incidents split cleared vs unsolved
            const unsolved = summary.unsolved_count || 0;
            const cleared = Math.max(0, summary.total_incidents - unsolved);
            const pct = summary.total_incidents ? Math.round(cleared / summary.total_incidents * 100) : 0;
            document.getElementById('totalIncidentsBar').innerHTML =
                `<div style="width:${pct}%;background:#16a34a;" title="Cleared: ${cleared}"></div><div style="width:${100 - pct}%;background:#ef4444;" title="Unsolved: ${unsolved}"></div>`;
            document.getElementById('totalIncidentsLabel').innerHTML =
                `<span style="color:#15803d;font-weight:700;">${cleared} cleared</span> · <span style="color:#b91c1c;font-weight:700;">${unsolved} open</span>`;
        }

        // Small SVG progress ring with the value in the middle - used wherever
        // a percentage or score used to be printed as text
        function ringSvg(pct, color, size, label, sub) {
            const v = Math.max(0, Math.min(100, Number(pct) || 0));
            return `<svg viewBox="0 0 36 36" width="${size}" height="${size}" style="flex-shrink:0;">
                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#e5e7eb" stroke-width="3.6"/>
                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="${color}" stroke-width="3.6" stroke-linecap="round"
                        stroke-dasharray="${v} 100" transform="rotate(-90 18 18)"/>
                <text x="18" y="${sub ? 17 : 18.6}" text-anchor="middle" dominant-baseline="middle" font-size="${sub ? 8.5 : 9.5}" font-weight="800" fill="#111827">${label}</text>
                ${sub ? `<text x="18" y="24.5" text-anchor="middle" dominant-baseline="middle" font-size="5" font-weight="700" fill="#6b7280">${sub}</text>` : ''}
            </svg>`;
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

            renderClearanceDonut(summary);
            renderDayNightDonut(analyticsData.day_night);
            renderRiskMix(summary.risk_counts);
        }

        // Small doughnuts behind the clearance / night figures, so the rate
        // reads at a glance instead of as a number in a row
        const MINI_DONUT = (labels, values, colors) => ({
            type: 'doughnut',
            data: { labels, datasets: [{ data: values, backgroundColor: colors, borderColor: '#fff', borderWidth: 2 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '70%',
                plugins: { legend: { display: false },
                           tooltip: { callbacks: { label: (it) => ` ${it.label}: ${it.parsed}` } } }
            }
        });

        let clearanceChartInstance = null;
        function renderClearanceDonut(summary) {
            const ctx = document.getElementById('clearanceCanvas');
            if (!ctx) return;
            if (clearanceChartInstance) clearanceChartInstance.destroy();
            const cleared = Math.max(0, (summary.total_incidents || 0) - (summary.unsolved_count || 0));
            clearanceChartInstance = new Chart(ctx, MINI_DONUT(['Cleared', 'Unsolved'], [cleared, summary.unsolved_count || 0], ['#16a34a', '#ef4444']));
        }

        let dayNightChartInstance = null;
        function renderDayNightDonut(dayNight) {
            const ctx = document.getElementById('dayNightCanvas');
            if (!ctx || !dayNight) return;
            if (dayNightChartInstance) dayNightChartInstance.destroy();
            dayNightChartInstance = new Chart(ctx, MINI_DONUT(
                ['Day (6AM-6PM)', 'Night (6PM-6AM)', 'No time recorded'],
                [dayNight.day || 0, dayNight.night || 0, dayNight.unknown_time || 0],
                ['#60a5fa', '#4338ca', '#e5e7eb']));
        }

        // Stacked bar of how many streets sit in each risk band
        function renderRiskMix(counts) {
            const bar = document.getElementById('riskMixBar');
            const legend = document.getElementById('riskMixLegend');
            if (!bar || !counts) return;
            const bands = [
                ['Critical', counts.critical || 0, '#7f1d1d'],
                ['High', counts.high || 0, '#dc2626'],
                ['Medium', counts.medium || 0, '#f59e0b'],
                ['Low', counts.low || 0, '#16a34a'],
            ];
            const total = bands.reduce((sum, b) => sum + b[1], 0);
            document.getElementById('riskMixTotal').textContent = `${total} street${total === 1 ? '' : 's'}`;
            bar.innerHTML = total
                ? bands.filter(b => b[1] > 0).map(b =>
                    `<div title="${b[0]}: ${b[1]}" style="width:${b[1] / total * 100}%;background:${b[2]};"></div>`).join('')
                : '';
            legend.innerHTML = bands.map(b =>
                `<span class="inline-flex items-center gap-1"><span style="width:8px;height:8px;border-radius:9999px;background:${b[2]};display:inline-block;"></span>${b[0]} <b>${b[1]}</b></span>`).join('');
        }

        // Incidents per weekday, busiest day highlighted
        let weekdayChartInstance = null;
        function renderWeekdayChart() {
            const weekday = analyticsData?.weekday;
            const ctx = document.getElementById('weekdayCanvas');
            if (!ctx || !weekday) return;

            document.getElementById('peakDayLabel').textContent = weekday.peak_day || 'no data';
            if (weekdayChartInstance) weekdayChartInstance.destroy();

            const peak = Math.max(...weekday.values);
            weekdayChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: weekday.labels,
                    datasets: [{
                        label: 'Incidents',
                        data: weekday.values,
                        backgroundColor: weekday.values.map(v => v === peak && peak > 0 ? '#274d4c' : '#9ed4cb'),
                        borderRadius: 6,
                        maxBarThickness: 44
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false },
                               tooltip: { callbacks: { label: (it) => `${it.parsed.y} incident(s)` } } },
                    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        // Horizontal ranking of the top 10 streets by composite risk score
        const RISK_BAR_COLOR = { CRITICAL: '#7f1d1d', HIGH: '#dc2626', MEDIUM: '#f59e0b', LOW: '#16a34a' };
        let riskScoreChartInstance = null;
        function renderRiskScoreChart() {
            const hotspots = (analyticsData?.hotspots || []).slice(0, 10);
            const ctx = document.getElementById('riskScoreCanvas');
            if (!ctx) return;
            if (riskScoreChartInstance) riskScoreChartInstance.destroy();
            if (!hotspots.length) return;

            riskScoreChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: hotspots.map(h => h.area_name),
                    datasets: [{
                        label: 'Risk score',
                        data: hotspots.map(h => h.risk_score),
                        backgroundColor: hotspots.map(h => RISK_BAR_COLOR[h.risk_level] || '#9ca3af'),
                        borderRadius: 5,
                        maxBarThickness: 22
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                    onClick: (evt, els) => {
                        if (!els.length) return;
                        const h = hotspots[els[0].index];
                        if (h) selectHotspot(h.area_name, h);
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: {
                            label: (it) => {
                                const h = hotspots[it.dataIndex];
                                return [` Score ${h.risk_score}/100 · ${h.risk_level}`, ` ${h.incident_count} incidents · ${h.trend_percent > 0 ? '+' : ''}${h.trend_percent}% trend`];
                            }
                        } }
                    },
                    scales: {
                        x: { beginAtZero: true, max: 100, grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 } } },
                        y: { grid: { display: false }, ticks: { font: { size: 11 }, autoSkip: false } }
                    }
                }
            });
        }

        // The two charts beside the insights: per-street trend and clearance
        let trendByStreetChartInstance = null;
        let clearanceByStreetChartInstance = null;
        function renderInsightCharts() {
            const hotspots = analyticsData?.hotspots || [];

            const trendCtx = document.getElementById('trendByStreetCanvas');
            if (trendCtx) {
                if (trendByStreetChartInstance) trendByStreetChartInstance.destroy();
                const movers = hotspots.filter(h => h.trend_percent !== 0)
                    .sort((a, b) => Math.abs(b.trend_percent) - Math.abs(a.trend_percent)).slice(0, 8)
                    .sort((a, b) => b.trend_percent - a.trend_percent);
                if (movers.length) {
                    trendByStreetChartInstance = new Chart(trendCtx, {
                        type: 'bar',
                        data: {
                            labels: movers.map(h => h.area_name),
                            datasets: [{
                                data: movers.map(h => h.trend_percent),
                                backgroundColor: movers.map(h => h.trend_percent > 0 ? '#dc2626' : '#16a34a'),
                                borderRadius: 4, maxBarThickness: 18
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                            plugins: { legend: { display: false },
                                       tooltip: { callbacks: { label: (it) => ` ${it.parsed.x > 0 ? '+' : ''}${it.parsed.x}% vs previous period` } } },
                            scales: {
                                x: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 }, callback: (v) => (v > 0 ? '+' : '') + v + '%' } },
                                y: { grid: { display: false }, ticks: { font: { size: 10 }, autoSkip: false } }
                            }
                        }
                    });
                }
            }

            const clrCtx = document.getElementById('clearanceByStreetCanvas');
            if (clrCtx) {
                if (clearanceByStreetChartInstance) clearanceByStreetChartInstance.destroy();
                const top = hotspots.slice(0, 8);
                if (top.length) {
                    clearanceByStreetChartInstance = new Chart(clrCtx, {
                        type: 'bar',
                        data: {
                            labels: top.map(h => h.area_name),
                            datasets: [
                                { label: 'Cleared', data: top.map(h => h.cleared), backgroundColor: '#16a34a', borderRadius: 3, maxBarThickness: 18 },
                                { label: 'Open', data: top.map(h => h.uncleared), backgroundColor: '#ef4444', borderRadius: 3, maxBarThickness: 18 }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
                            scales: {
                                x: { stacked: true, beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { precision: 0, font: { size: 10 } } },
                                y: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 }, autoSkip: false } }
                            }
                        }
                    });
                }
            }
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

            // Each category's own colour, so a crime type looks the same here,
            // on the map, and in the street breakdown.
            const fallback = ['#dc2626', '#ea580c', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9'];
            const colors = distribution.colors?.length
                ? distribution.colors
                : fallback.slice(0, distribution.labels.length);

            crimeDistributionChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: distribution.labels,
                    datasets: [{
                        data: distribution.values,
                        backgroundColor: colors,
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
        // Time-of-day distribution, with the busiest window called out
        let hourlyChartInstance = null;

        function renderHourlyChart() {
            const hourly = analyticsData?.hourly;
            const ctx = document.getElementById('hourlyCanvas');
            if (!ctx || !hourly) return;

            document.getElementById('peakPeriodLabel').textContent = hourly.peak_period || 'no time recorded';

            if (hourlyChartInstance) hourlyChartInstance.destroy();

            // Night hours (6 PM - 6 AM) shaded differently so the pattern reads
            const colors = hourly.values.map((_, hour) =>
                (hour >= 18 || hour < 6) ? '#4338ca' : '#60a5fa');

            hourlyChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: hourly.labels,
                    datasets: [{
                        label: 'Incidents',
                        data: hourly.values,
                        backgroundColor: colors,
                        borderRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (items) => items[0].label,
                                label: (item) => `${item.parsed.y} incident(s)`
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 60, minRotation: 60 } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        function generateInsights() {
            const hotspots = analyticsData?.hotspots || [];
            const summary = analyticsData?.summary;
            const dayNight = analyticsData?.day_night;
            const insightsDiv = document.getElementById('aiInsights');

            if (!hotspots.length || !summary) {
                insightsDiv.innerHTML = `
                    <div class="bg-white border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>No crime data for the selected filters.
                    </div>`;
                return;
            }

            // Each card = one visual (ring / big number) + a few words. The
            // charts to the right carry the detail.
            const tone = {
                danger:  { border: 'border-red-200',    bg: 'bg-red-50',    text: 'text-red-900',    accent: '#dc2626' },
                warning: { border: 'border-amber-200',  bg: 'bg-amber-50',  text: 'text-amber-900',  accent: '#d97706' },
                success: { border: 'border-green-200',  bg: 'bg-green-50',  text: 'text-green-900',  accent: '#16a34a' },
                info:    { border: 'border-blue-200',   bg: 'bg-blue-50',   text: 'text-blue-900',   accent: '#2563eb' },
                night:   { border: 'border-indigo-200', bg: 'bg-indigo-50', text: 'text-indigo-900', accent: '#4338ca' },
            };
            const card = (type, visual, title, sub, extra) => {
                const t = tone[type] || tone.info;
                return `
                    <div class="border ${t.border} ${t.bg} rounded-lg p-3 flex items-center gap-3">
                        <div class="flex-shrink-0">${visual}</div>
                        <div class="min-w-0 flex-1">
                            <div class="font-bold ${t.text} text-sm leading-tight">${title}</div>
                            ${sub ? `<div class="text-[11.5px] ${t.text} opacity-75 mt-0.5 leading-snug">${sub}</div>` : ''}
                            ${extra || ''}
                        </div>
                    </div>`;
            };
            const bigNum = (value, color, icon) => `
                <div class="flex flex-col items-center justify-center rounded-xl bg-white border border-gray-100" style="width:54px;height:54px;">
                    ${icon ? `<i class="fas ${icon}" style="color:${color};font-size:12px;"></i>` : ''}
                    <span class="font-black leading-none" style="color:${color};font-size:${String(value).length > 4 ? 13 : 16}px;">${value}</span>
                </div>`;

            const cards = [];
            const top = hotspots[0];
            const topTone = (top.risk_level === 'CRITICAL' || top.risk_level === 'HIGH') ? 'danger' : 'info';
            cards.push(card(topTone,
                ringSvg(top.risk_score, RISK_BAR_COLOR[top.risk_level] || '#9ca3af', 54, top.risk_score, 'SCORE'),
                `Highest risk: ${escapeHtml(top.area_name)}`,
                `${top.incident_count} incidents · mostly ${escapeHtml(top.top_category)}${top.peak_period ? ` · peak ${escapeHtml(top.peak_period)}` : ''}`,
                `<a href="/pattern-detection" class="inline-flex items-center gap-1 mt-1.5 text-[11px] font-bold text-purple-700 hover:underline"><i class="fas fa-flask"></i>Simulate interventions</a>`));

            const rising = hotspots.filter(h => h.trend_direction === 'increasing');
            const falling = hotspots.filter(h => h.trend_direction === 'decreasing');
            cards.push(card(rising.length ? 'warning' : 'success',
                bigNum(rising.length, rising.length ? '#d97706' : '#16a34a', rising.length ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'),
                rising.length ? `${rising.length} street${rising.length === 1 ? '' : 's'} rising` : 'No street is rising',
                rising.length
                    ? rising.slice(0, 3).map(h => `${escapeHtml(h.area_name)} <b>+${h.trend_percent}%</b>`).join(' · ') + (rising.length > 3 ? ' …' : '')
                    : `${falling.length} falling vs the previous period`));

            if (dayNight) {
                cards.push(card(dayNight.night_percent >= 50 ? 'night' : 'info',
                    ringSvg(dayNight.night_percent, '#4338ca', 54, dayNight.night_percent + '%', 'NIGHT'),
                    dayNight.night_percent >= 50 ? 'Night-heavy pattern' : 'Mostly daytime crime',
                    dayNight.night_percent >= 50 ? 'Lighting and night patrols apply best (6 PM – 6 AM)' : 'Daytime presence and CCTV apply best'));
            }

            cards.push(card(summary.clearance_rate < 50 ? 'warning' : 'success',
                ringSvg(summary.clearance_rate, summary.clearance_rate < 50 ? '#d97706' : '#16a34a', 54, summary.clearance_rate + '%', 'CLEARED'),
                summary.clearance_rate < 50 ? 'Low clearance rate' : 'Good clearance rate',
                `<b>${summary.unsolved_count}</b> unsolved of ${summary.total_incidents} cases`));

            const trend = summary.citywide_trend;
            if (trend.direction !== 'stable') {
                cards.push(card(trend.direction === 'increasing' ? 'danger' : 'success',
                    bigNum(`${trend.percent > 0 ? '+' : ''}${trend.percent}%`, trend.direction === 'increasing' ? '#dc2626' : '#16a34a', trend.direction === 'increasing' ? 'fa-arrow-up' : 'fa-arrow-down'),
                    `Incidents ${trend.direction === 'increasing' ? 'up' : 'down'} overall`,
                    `vs the previous ${trend.window_days} days, all filtered streets`));
            }

            insightsDiv.innerHTML = `<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2 gap-3">${cards.join('')}</div>`;
        }

        // ---------------------------------------------------------------
        // Selecting a street: zoom the map to it and break it down in the
        // panel. Reached from a click on the map or on the ranked list.
        // ---------------------------------------------------------------
        function hotspotByStreet(street) {
            const key = String(street || '').toLowerCase();
            return (analyticsData?.hotspots || [])
                .find(h => String(h.area_name).toLowerCase() === key) || null;
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, c => (
                { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
            ));
        }

        function clearStreetSelection() {
            selectedHotspot = null;
            document.getElementById('streetAnalysisCard').style.display = 'none';
            if (typeof saStreetsHighlight === 'function') saStreetsHighlight(null);
        }

        function selectHotspot(name, hotspot, scrollIntoView = true) {
            try {
                hotspot = hotspot || hotspotByStreet(name);

                const card = document.getElementById('streetAnalysisCard');
                const body = document.getElementById('streetAnalysisBody');
                document.getElementById('streetAnalysisName').textContent = name;
                card.style.display = 'block';

                // Zoom to the street and pick it out from its neighbours
                if (typeof saStreetsFitStreet === 'function') saStreetsFitStreet(name);
                const filteredStreets = Array.from(document.getElementById('streets')?.selectedOptions || [])
                    .map(option => option.value);
                if (typeof saStreetsHighlight === 'function') saStreetsHighlight(filteredStreets.length ? filteredStreets : name);

                if (!hotspot) {
                    body.innerHTML = `
                        <div class="text-sm text-gray-500">
                            No incidents recorded on ${escapeHtml(name)} for the current filters.
                        </div>
                        <button type="button" class="mt-3 w-full px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-semibold open-street-modal" data-street="${escapeHtml(name)}">
                            <i class="fas fa-shield-halved mr-1"></i>Crimes &amp; prevention suggestions
                        </button>`;
                    wireStreetPanelButtons();
                    return;
                }

                selectedHotspot = hotspot;

                const style = RISK_STYLES[hotspot.risk_level] || RISK_STYLES.LOW;
                const pc = hotspot.trend_percent;
                const trendClass = pc > 0 ? 'text-red-600' : pc < 0 ? 'text-green-600' : 'text-gray-500';
                const trendIcon = pc > 0 ? 'fa-arrow-up' : pc < 0 ? 'fa-arrow-down' : 'fa-arrows-left-right';

                // Top 3 types by name, everything else folded into "Others"
                const shown = hotspot.categories.slice(0, 3);
                const otherCount = hotspot.categories.slice(3)
                    .reduce((sum, c) => sum + c.count, 0);

                const badge = (label, count, color) => `
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-gray-700 rounded-full border border-gray-200 bg-white px-2 py-0.5">
                        <span style="width:8px;height:8px;border-radius:9999px;background:${color};display:inline-block;"></span>
                        ${escapeHtml(label)} <b class="text-gray-900">${count}</b>
                    </span>`;

                // Icon tile: one fact, one glance
                const tile = (icon, label, value, color) => `
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-2.5 py-2 min-w-0">
                        <div class="text-[10px] font-bold text-gray-500 uppercase truncate"><i class="fas ${icon} mr-1" style="color:${color || '#9ca3af'}"></i>${label}</div>
                        <div class="text-sm font-bold text-gray-900 truncate mt-0.5">${value}</div>
                    </div>`;

                // Recent incidents as a compact timeline of chips
                const recent = (hotspot.recent_incidents || []).slice(0, 4).map(i => `
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold rounded-full px-2 py-1 border ${i.cleared ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-800'}" title="${escapeHtml(i.category)} · ${escapeHtml(i.date || '')} ${escapeHtml(i.time || '')} · ${i.cleared ? 'cleared' : 'open'}">
                        <i class="fas ${i.cleared ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i>${escapeHtml((i.date || '').slice(5))} · ${escapeHtml(i.category)}
                    </span>`).join('');

                const barColor = RISK_BAR_COLOR[hotspot.risk_level] || '#9ca3af';
                const meter = (label, left, right, leftColor, rightColor, leftLabel, rightLabel) => {
                    const total = left + right;
                    const pct = total ? Math.round(left / total * 100) : 0;
                    return `
                    <div class="py-1.5">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-gray-600">${label}</span>
                            <span class="font-bold"><span style="color:${leftColor}">${left}</span> <span class="text-gray-400">/</span> <span style="color:${rightColor}">${right}</span></span>
                        </div>
                        <div class="flex h-2 rounded-full overflow-hidden bg-gray-100">
                            <div style="width:${pct}%;background:${leftColor};" title="${leftLabel}: ${left}"></div>
                            <div style="width:${100 - pct}%;background:${rightColor};" title="${rightLabel}: ${right}"></div>
                        </div>
                    </div>`;
                };

                body.innerHTML = `
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs font-bold px-2 py-1 rounded-full ${style.badge}">${style.icon} ${hotspot.risk_level} RISK</span>
                        <span class="text-[11px] text-gray-400 font-bold">score ${hotspot.risk_score}/100</span>
                        <span class="ml-auto text-[11px] font-bold text-gray-500">#${hotspot.rank}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="bg-gray-50 rounded-lg p-3 text-center flex flex-col justify-center">
                            <div class="text-3xl font-bold text-gray-900">${hotspot.incident_count}</div>
                            <div class="text-xs text-gray-600">total crime incidents</div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden mt-2" title="Risk score ${hotspot.risk_score}/100">
                                <div class="h-full" style="width:${Math.min(hotspot.risk_score, 100)}%;background:${barColor};"></div>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2" style="position:relative;height:110px;">
                            <canvas id="streetPanelDonut"></canvas>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-1.5 mb-3">
                        ${shown.map(c => badge(c.name, c.count, c.color)).join('')}
                        ${otherCount ? badge('Others', otherCount, '#9ca3af') : ''}
                    </div>

                    <div class="mb-3 border-t border-gray-100 pt-2">
                        ${meter('Cleared vs open', hotspot.cleared, hotspot.uncleared, '#16a34a', '#ef4444', 'Cleared', 'Open')}
                        ${meter('Night vs day', Math.round(hotspot.incident_count * hotspot.night_percent / 100), hotspot.incident_count - Math.round(hotspot.incident_count * hotspot.night_percent / 100), '#4338ca', '#60a5fa', 'Night', 'Day')}
                    </div>

                    <div class="grid grid-cols-2 gap-2 mb-3">
                        ${tile('fa-tag', 'Top crime', escapeHtml(hotspot.top_category ?? 'N/A'), '#dc2626')}
                        ${tile('fa-clock', 'Peak', escapeHtml(hotspot.peak_period ?? '—'), '#4338ca')}
                        ${tile(trendIcon, 'Trend', `<span class="${trendClass}">${pc > 0 ? '+' : ''}${pc}%</span>`, pc > 0 ? '#dc2626' : pc < 0 ? '#16a34a' : '#9ca3af')}
                        ${tile('fa-location-dot', 'Locations', hotspot.affected_locations, '#0ea5e9')}
                        ${tile('fa-compress', 'Per 100 m', hotspot.density_per_100m !== null ? hotspot.density_per_100m : '—', '#f59e0b')}
                        ${tile('fa-ranking-star', 'Rank', `#${hotspot.rank} of ${(analyticsData?.hotspots || []).length}`, '#7c3aed')}
                    </div>

                    ${recent ? `
                        <div class="mb-3">
                            <div class="text-[10px] font-bold text-gray-500 uppercase mb-1">Latest incidents</div>
                            <div class="flex flex-wrap gap-1.5">${recent}</div>
                        </div>` : ''}

                    <button type="button" class="w-full px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-semibold open-street-modal" data-street="${escapeHtml(hotspot.area_name)}">
                        <i class="fas fa-shield-halved mr-1"></i>Crimes &amp; prevention suggestions
                    </button>
                `;

                wireStreetPanelButtons();
                renderStreetPanelDonut(hotspot);
                if (scrollIntoView) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } catch (error) {
                console.error('Error selecting hotspot:', error);
            }
        }

        // Crime-type doughnut in the street panel, same colours as the map
        let streetPanelDonutInstance = null;
        function renderStreetPanelDonut(hotspot) {
            const ctx = document.getElementById('streetPanelDonut');
            if (streetPanelDonutInstance) { streetPanelDonutInstance.destroy(); streetPanelDonutInstance = null; }
            if (!ctx || !hotspot || !hotspot.categories?.length) return;
            const cats = hotspot.categories;
            streetPanelDonutInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: cats.map(c => c.name),
                    datasets: [{ data: cats.map(c => c.count), backgroundColor: cats.map(c => c.color), borderColor: '#fff', borderWidth: 2 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '55%',
                    plugins: { legend: { display: false },
                               tooltip: { callbacks: { label: (it) => ` ${it.label}: ${it.parsed} (${Math.round(it.parsed / hotspot.incident_count * 100)}%)` } } }
                }
            });
        }

        function wireStreetPanelButtons() {
            document.querySelectorAll('.open-street-modal').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (typeof openStreetModal === 'function') {
                        openStreetModal(this.dataset.street, null);
                    }
                });
            });
        }

        // Clicking a street opens its modal - the crimes, the badges and the
        // prevention suggestions - and also fills the side panel, so the
        // breakdown is still there after the modal is closed.
        window.SA_CLICK_HINT = 'Click for the full breakdown &amp; prevention advice';
        window.onSanAgustinStreetClick = function (street, group) {
            selectHotspot(street, hotspotByStreet(street), false);
            if (typeof openStreetModal === 'function') openStreetModal(street, group);
        };

        // Extra badges for the modal header, pulled from this page's hotspot
        // ranking. The partial calls this if the host page defines it.
        window.saExtraStreetPills = function (street) {
            const h = hotspotByStreet(street);
            if (!h) return '';

            const pill = (html, style) =>
                `<span class="sm-pill"${style ? ` style="${style}"` : ''}>${html}</span>`;

            const pills = [];
            if (h.peak_period) {
                pills.push(pill(`<i class="fas fa-clock"></i>Peak ${h.peak_period}`, 'background:#ede9fe;color:#5b21b6;'));
            }

            const pc = h.trend_percent;
            const trendStyle = pc > 0 ? 'background:#fee2e2;color:#b91c1c;'
                             : pc < 0 ? 'background:#dcfce7;color:#15803d;'
                             : 'background:#f3f4f6;color:#374151;';
            const trendIcon = pc > 0 ? 'fa-arrow-up' : pc < 0 ? 'fa-arrow-down' : 'fa-arrows-left-right';
            pills.push(pill(`<i class="fas ${trendIcon}"></i>${pc > 0 ? '+' : ''}${pc}% vs previous`, trendStyle));

            pills.push(pill(`<i class="fas fa-location-dot"></i>${h.affected_locations} affected location${h.affected_locations === 1 ? '' : 's'}`));

            if (h.density_per_100m !== null) {
                pills.push(pill(`<i class="fas fa-compress"></i>${h.density_per_100m} per 100 m`));
            }

            const riskStyle = { CRITICAL: 'background:#7f1d1d;color:#fff;', HIGH: 'background:#fee2e2;color:#b91c1c;',
                                MEDIUM: 'background:#fef3c7;color:#b45309;', LOW: 'background:#dcfce7;color:#15803d;' };
            pills.push(pill(`<i class="fas fa-gauge-high"></i>${h.risk_level} risk · score ${h.risk_score}`,
                            riskStyle[h.risk_level] || ''));

            return pills.join('');
        };

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
                        if (Array.from(select.options).some(option => option.value === String(barangay.id))) return;
                        const option = document.createElement('option');
                        option.value = barangay.id;
                        option.textContent = barangay.barangay_name;
                        select.appendChild(option);
                    });
                    const sanAgustin = data.find(barangay => String(barangay.barangay_name).trim().toLowerCase() === 'san agustin');
                    if (sanAgustin) {
                        select.value = sanAgustin.id;
                        loadHotspotData();
                    }
                })
                .catch(error => console.error('Error loading barangays:', error));
        }

        function selectedStreetNames() {
            return Array.from(document.getElementById('streets').selectedOptions).map(option => option.value);
        }

        function syncStreetFilter() {
            const selected = selectedStreetNames();
            const label = document.getElementById('streetDropdownLabel');
            const removeButton = document.getElementById('removeStreetSelectionBtn');
            label.textContent = selected.length === 0
                ? 'All streets'
                : selected.length === 1 ? selected[0] : `${selected.length} streets selected`;
            removeButton.classList.toggle('hidden', selected.length === 0);
            if (typeof saStreetsHighlight === 'function') saStreetsHighlight(selected);
            loadHotspotData();
        }

        function clearStreetFilter() {
            document.querySelectorAll('.street-filter-checkbox').forEach(checkbox => checkbox.checked = false);
            Array.from(document.getElementById('streets').options).forEach(option => option.selected = false);
            syncStreetFilter();
        }

        function loadStreets() {
            fetch('/pattern-detection/street-stats')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('streets');
                    const list = document.getElementById('streetCheckboxList');
                    const streets = Object.keys(data.streets || {}).sort((a, b) => a.localeCompare(b));
                    select.innerHTML = '';
                    list.innerHTML = '';

                    streets.forEach(street => {
                        const option = new Option(street, street);
                        select.add(option);

                        const label = document.createElement('label');
                        label.className = 'flex items-center gap-2 px-2 py-1.5 rounded cursor-pointer hover:bg-alertara-50 text-sm text-gray-700';
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.value = street;
                        checkbox.className = 'street-filter-checkbox rounded border-gray-300 text-alertara-600 focus:ring-alertara-500';
                        const name = document.createElement('span');
                        name.textContent = street;
                        label.append(checkbox, name);
                        list.appendChild(label);
                    });
                })
                .catch(error => console.error('Error loading streets:', error));
        }
        function setupFilterListeners() {
            document.getElementById('timePeriod').addEventListener('change', debounce(loadHotspotData, 500));
            document.getElementById('visualizationMode').addEventListener('change', debounce(loadHotspotData, 500));
            document.getElementById('crimeType').addEventListener('change', debounce(loadHotspotData, 500));
            document.getElementById('caseStatus').addEventListener('change', debounce(loadHotspotData, 500));
            document.getElementById('barangay').addEventListener('change', debounce(loadHotspotData, 500));
            document.getElementById('streetDropdownButton').addEventListener('click', function () {
                const panel = document.getElementById('streetDropdownPanel');
                panel.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!panel.classList.contains('hidden')));
            });
            document.getElementById('streetCheckboxList').addEventListener('change', function (event) {
                if (!event.target.classList.contains('street-filter-checkbox')) return;
                const option = Array.from(document.getElementById('streets').options)
                    .find(item => item.value === event.target.value);
                if (option) option.selected = event.target.checked;
                syncStreetFilter();
            });
            document.getElementById('removeStreetSelectionBtn').addEventListener('click', clearStreetFilter);
            document.addEventListener('click', function (event) {
                const dropdown = document.getElementById('streetFilterDropdown');
                if (!dropdown.contains(event.target)) {
                    document.getElementById('streetDropdownPanel').classList.add('hidden');
                    document.getElementById('streetDropdownButton').setAttribute('aria-expanded', 'false');
                }
            });

            document.getElementById('resetFilterBtn').addEventListener('click', function() {
                document.getElementById('timePeriod').value = 'all';
                document.getElementById('visualizationMode').value = 'markers';
                document.getElementById('crimeType').value = '';
                document.getElementById('caseStatus').value = '';
                const barangaySelect = document.getElementById('barangay');
                const sanAgustin = Array.from(barangaySelect.options).find(option => option.text.trim().toLowerCase() === 'san agustin');
                barangaySelect.value = sanAgustin ? sanAgustin.value : '';
                selectedHotspot = null;
                clearStreetSelection();
                clearStreetFilter();
            });

            document.getElementById('downloadCsvBtn').addEventListener('click', downloadCSV);
            document.getElementById('clearStreetSelection').addEventListener('click', clearStreetSelection);

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
