@php
    // Per-user defaults from Settings; falls back to the shipped values.
    $prefs = ($preferences ?? null) ?: \App\Models\UserPreference::DEFAULTS;
@endphp

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
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crime Mapping - Crime Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

    <!-- Leaflet Heatmap Plugin - jsDelivr CDN -->
    <script src="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.min.js"></script>
    <!-- Shared base map (tiles, zoom limits) - also used by Add Crime Record -->
    <script src="{{ asset('js/crime-map-base.js') }}?v={{ filemtime(public_path('js/crime-map-base.js')) }}"></script>
    <!-- Street-Segment Heatmap view: road segments coloured by crime count -->
    <script src="{{ asset('js/street-segment-heatmap.js') }}?v={{ filemtime(public_path('js/street-segment-heatmap.js')) }}"></script>
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
        .ssh-tooltip { border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 6px 20px rgba(0,0,0,.18); padding: 7px 10px; }
        .ssh-tooltip::before { display: none; }
    </style>

    <!-- Laravel App - Real-time features disabled -->
    @vite(['resources/js/app.js'])

    <style>
        /* Barangay hover label */
        .brgy-tooltip {
            background: #274d4c;
            border: none;
            border-radius: 6px;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }
        .brgy-tooltip::before { border-top-color: #274d4c; }

        /* Name label on the isolated barangay */
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

        /* Fullscreen: the MAP ALONE fills the whole screen, through the browser's
           own fullscreen mode. Nothing else comes with it - no filters, no
           panels - so the map really is the full screen. */
        #mapContainer:fullscreen,
        #mapContainer:-webkit-full-screen {
            width: 100vw;
            height: 100vh;
            border-radius: 0;
            border: none;
            background: #fff;
        }
        #mapContainer:fullscreen #map,
        #mapContainer:-webkit-full-screen #map { height: 100vh !important; }

        #mapContainer:fullscreen #exitFullscreenBtn,
        #mapContainer:-webkit-full-screen #exitFullscreenBtn {
            display: flex;
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 1200;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        }

        /* Fallback for browsers that refuse the Fullscreen API */
        #mapContainer.map-fullscreen {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            z-index: 99998;
            border-radius: 0;
        }
        #mapContainer.map-fullscreen #map { height: 100vh !important; }
        #mapContainer.map-fullscreen #exitFullscreenBtn {
            display: flex;
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 1200;
        }

        /* ------------------------------------------------------------------
           Map overlays. Everything that floats on the map shares one look:
           a white card, a hairline border, a soft shadow and compact type -
           so the map reads as one surface instead of stacked widgets.
           ------------------------------------------------------------------ */
        .map-streets-card {
            width: 218px;
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.18);
            overflow: hidden;
            font-family: inherit;
            backdrop-filter: blur(2px);
        }
        .map-streets-card .msc-head {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 10px;
            background: #f8fafc;
            border-bottom: 1px solid #eef2f7;
            font-size: 11.5px;
            font-weight: 800;
            color: #111827;
        }
        .map-streets-card .msc-head i { color: #274d4c; }
        .map-streets-card .msc-title { flex: 1; }
        .map-streets-card .msc-count {
            background: #274d4c;
            color: #fff;
            border-radius: 9999px;
            padding: 1px 7px;
            font-size: 10px;
            font-weight: 800;
        }
        .map-streets-card .msc-toggle {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 0 2px;
            font-size: 10px;
        }
        .map-streets-card .msc-toggle:hover { color: #111827; }

        .map-streets-card .msc-body {
            max-height: 232px;
            overflow-y: auto;
        }
        .map-streets-card .msc-row {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            background: none;
            border: none;
            border-left: 3px solid transparent;
            cursor: pointer;
            text-align: left;
            font-size: 11.5px;
            color: #374151;
        }
        .map-streets-card .msc-row:hover { background: #f8fafc; }
        .map-streets-card .msc-row.is-active {
            background: #eef7f6;
            border-left-color: #274d4c;
            font-weight: 700;
            color: #111827;
        }
        .map-streets-card .msc-dot {
            width: 9px;
            height: 9px;
            border-radius: 9999px;
            flex-shrink: 0;
        }
        .map-streets-card .msc-name {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .map-streets-card .msc-badge {
            color: #fff;
            border-radius: 9999px;
            min-width: 22px;
            padding: 1px 6px;
            text-align: center;
            font-size: 10px;
            font-weight: 800;
            flex-shrink: 0;
        }
        .map-streets-card .msc-empty {
            padding: 14px 10px;
            text-align: center;
            font-size: 11px;
            color: #9ca3af;
        }
        .map-streets-card .msc-clear {
            width: 100%;
            padding: 7px;
            background: #f8fafc;
            border: none;
            border-top: 1px solid #eef2f7;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .map-streets-card .msc-clear:hover { color: #b91c1c; background: #fef2f2; }

        /* Leaflet's own furniture, matched to the cards above */
        #map .leaflet-control-zoom a,
        #map .leaflet-bar a {
            border-radius: 8px !important;
            border: 1px solid #e5e7eb !important;
            color: #274d4c;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.10);
        }
        #map .leaflet-bar { border: none; box-shadow: none; }
        #map .leaflet-popup-content-wrapper {
            border-radius: 12px;
            box-shadow: 0 8px 26px rgba(15, 23, 42, 0.22);
        }
        #map .leaflet-popup-tip { box-shadow: none; }
        #map .leaflet-tooltip {
            border-radius: 8px;
            border: none;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.22);
            font-size: 11px;
        }
        #map .leaflet-container { font-family: inherit; }

        /* Cluster bubbles: no boxy background behind the pill */
        .cluster-marker { background: none !important; border: none !important; }

        @media (max-width: 900px) {
            .map-streets-card { width: 168px; }
            .map-streets-card .msc-body { max-height: 168px; }
        }

        #exitFullscreenBtn { display: none; }
    </style>
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
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Crime Mapping</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Interactive crime data visualization and analysis</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('crime-incident.create') }}"
                           class="px-4 py-2 bg-white border border-alertara-300 text-alertara-800 rounded-lg hover:bg-alertara-50 transition-colors flex items-center gap-2 text-sm font-semibold"
                           title="Enter a crime by hand and place it on a street">
                            <i class="fas fa-plus-circle"></i>
                            <span>Add Crime</span>
                        </a>
                        <button id="importReportsBtn" type="button"
                                class="px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors flex items-center gap-2 text-sm font-semibold"
                                title="Pull crime records from the Alertara Reports system">
                            <i class="fas fa-cloud-download-alt"></i>
                            <span>Import from Reports</span>
                        </button>
                    </div>
                </div>
            </div>


            <!-- Map Container with Right Panel -->
            <div class="bg-white border border-gray-200 rounded-lg p-6" style="position: relative; z-index: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-map mr-2 text-alertara-600"></i>Crime Map
                    </h2>
                    <div class="flex items-center gap-2">
                        <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-sm" role="group" aria-label="Map engine">
                            <button id="mapGoogleBtn" type="button" class="map-engine-btn px-3 py-2 bg-white text-gray-700 hover:bg-gray-50 flex items-center gap-1" title="Google Maps (satellite + roads)">
                                <i class="fab fa-google"></i><span class="hidden sm:inline">Google</span>
                            </button>
                            <button id="map2dBtn" type="button" class="map-engine-btn px-3 py-2 bg-white text-gray-700 hover:bg-gray-50 flex items-center gap-1 border-l border-gray-300" title="Classic 2D map (heat map, clusters, street segments)">
                                <i class="fas fa-map"></i><span class="hidden sm:inline">Classic</span>
                            </button>
                            <button id="map3dBtn" type="button" class="map-engine-btn px-3 py-2 bg-white text-gray-700 hover:bg-gray-50 flex items-center gap-1 border-l border-gray-300" title="3D map">
                                <i class="fas fa-cube"></i><span class="hidden sm:inline">3D</span>
                            </button>
                        </div>
                        <button id="mapFullscreenBtn" class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 text-sm" title="Toggle Fullscreen Map">
                            <i class="fas fa-expand"></i>
                            <span class="hidden sm:inline">Fullscreen</span>
                        </button>
                    </div>
                </div>

                <!-- Filters Section (moves into the map while enlarged) -->
                <div id="filtersSection" class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
                    <div class="mb-4 pb-4 border-b border-gray-200 flex items-center justify-between gap-3">
                        <h3 class="text-sm font-bold text-gray-900">
                            <i class="fas fa-filter mr-2 text-alertara-700"></i>Map Filters
                        </h3>
                        <button id="exitFullscreenBtn" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors items-center gap-2 text-xs" title="Exit fullscreen">
                            <i class="fas fa-compress"></i>
                            <span>Exit Fullscreen</span>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-4">
                        <!-- Visualization Mode -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">View Mode</label>
                            <select id="visualizationMode" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="markers" @selected(!in_array($prefs['default_view_mode'], ['street-heatmap', 'heatmap', 'clusters'], true))>Individual Markers</option>
                                <option value="street-heatmap" @selected($prefs['default_view_mode'] === 'street-heatmap')>Street-Segment Heatmap</option>
                                <option value="heatmap" @selected($prefs['default_view_mode'] === 'heatmap')>Heat Map</option>
                                <option value="clusters" @selected($prefs['default_view_mode'] === 'clusters')>Cluster View</option>
                            </select>
                        </div>

                        <!-- Time Period -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Time Period</label>
                            <select id="timePeriod" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="30" @selected((string) $prefs['default_time_period'] === '30')>Last 30 Days</option>
                                <option value="90" @selected((string) $prefs['default_time_period'] === '90')>Last 90 Days</option>
                                <option value="180" @selected((string) $prefs['default_time_period'] === '180')>Last 6 Months</option>
                                <option value="all" @selected((string) $prefs['default_time_period'] === 'all')>All Time</option>
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

                        <!-- Clearance Status -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Clearance Status</label>
                            <select id="clearanceStatus" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="">All Clearance</option>
                                <option value="cleared">Cleared</option>
                                <option value="uncleared">Uncleared</option>
                            </select>
                        </div>

                        <!-- Barangay -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Barangay</label>
                            <select id="barangay" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="">All Barangays</option>
                            </select>
                        </div>

                        <!-- Street -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Street</label>
                            <select id="street" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="">All Streets</option>
                            </select>
                        </div>

                        <!-- Buttons -->
                        <div class="flex items-end gap-2">
                            <button id="resetFilterBtn" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                                <i class="fas fa-redo"></i>Reset
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Map and Right Panel Side-by-Side -->
                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- LEFT: Map -->
                    <div id="mapContainer" class="w-full lg:w-3/5 border border-gray-200 rounded-lg overflow-hidden relative">
                        <div id="map" class="h-96 lg:h-[600px] w-full"></div>

                        <!-- Map Loading Overlay -->
                        <div id="mapLoadingOverlay" class="absolute inset-0 bg-white bg-opacity-95 hidden z-[10000] flex flex-col items-center justify-center gap-4">
                            <div class="text-center">
                                <div class="inline-block mb-3">
                                    <i class="fas fa-spinner fa-spin text-3xl text-alertara-700"></i>
                                </div>
                                <div class="text-sm font-semibold text-gray-900 mb-1">Loading Map Data</div>
                                <div class="text-xs text-gray-600">Processing visualization...</div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Statistics and Incident List -->
                    <div class="w-full lg:w-2/5 flex flex-col gap-4">
                        <!-- Statistics Cards -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-gradient-to-br from-alertara-700 to-alertara-600 text-white p-4 rounded-lg shadow-sm">
                                <div class="text-xs opacity-90 mb-1">Total Crime</div>
                                <div id="statTotalCrime" class="text-2xl font-bold">0</div>
                            </div>
                            <div class="bg-gradient-to-br from-green-600 to-green-500 text-white p-4 rounded-lg shadow-sm">
                                <div class="text-xs opacity-90 mb-1">Cleared Cases</div>
                                <div id="statCleared" class="text-2xl font-bold">0</div>
                            </div>
                            <div class="bg-gradient-to-br from-red-600 to-red-500 text-white p-4 rounded-lg shadow-sm">
                                <div class="text-xs opacity-90 mb-1">Uncleared Cases</div>
                                <div id="statUncleared" class="text-2xl font-bold">0</div>
                            </div>
                            <div class="bg-gradient-to-br from-blue-600 to-blue-500 text-white p-4 rounded-lg shadow-sm">
                                <div class="text-xs opacity-90 mb-1">Categories</div>
                                <div id="statCategories" class="text-2xl font-bold">0</div>
                            </div>
                        </div>

                        <!-- INCIDENTS PANEL (for markers/clusters mode) -->
                        <div id="incidentsPanel" style="background: rgba(255, 255, 255, 0.98); border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; flex-grow: 1;">
                            <div style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0 0 10px;">
                                    <i class="fas fa-list mr-2" style="color: #274d4c;"></i>Crimes
                                </h3>
                                <input type="text" id="incidentSearch" placeholder="Search crimes..." style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; box-sizing: border-box;">
                            </div>
                            <div id="incidentList" style="overflow-y: auto; flex-grow: 1; max-height: 350px;">
                                <!-- Skeleton loading -->
                                <div id="incidentSkeletonLoader" style="padding: 12px; display: none;">
                                    <div style="padding: 12px; border-bottom: 1px solid #e5e7eb; background-color: #f9fafb; border-radius: 4px; margin-bottom: 8px;">
                                        <div style="display: flex; gap: 8px;">
                                            <div style="width: 12px; height: 12px; border-radius: 50%; background-color: #e5e7eb; flex-shrink: 0;"></div>
                                            <div style="flex-grow: 1;">
                                                <div style="height: 12px; background-color: #e5e7eb; border-radius: 4px; margin-bottom: 6px;"></div>
                                                <div style="height: 10px; background-color: #e5e7eb; border-radius: 4px; width: 80%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding: 12px; border-bottom: 1px solid #e5e7eb; background-color: #f9fafb; border-radius: 4px; margin-bottom: 8px;">
                                        <div style="display: flex; gap: 8px;">
                                            <div style="width: 12px; height: 12px; border-radius: 50%; background-color: #e5e7eb; flex-shrink: 0;"></div>
                                            <div style="flex-grow: 1;">
                                                <div style="height: 12px; background-color: #e5e7eb; border-radius: 4px; margin-bottom: 6px;"></div>
                                                <div style="height: 10px; background-color: #e5e7eb; border-radius: 4px; width: 70%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding: 12px; border-bottom: 1px solid #e5e7eb; background-color: #f9fafb; border-radius: 4px;">
                                        <div style="display: flex; gap: 8px;">
                                            <div style="width: 12px; height: 12px; border-radius: 50%; background-color: #e5e7eb; flex-shrink: 0;"></div>
                                            <div style="flex-grow: 1;">
                                                <div style="height: 12px; background-color: #e5e7eb; border-radius: 4px; margin-bottom: 6px;"></div>
                                                <div style="height: 10px; background-color: #e5e7eb; border-radius: 4px; width: 75%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Actual content -->
                                <div id="incidentListContent" style="display: none;">
                                    <!-- Will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>

                        <!-- HEATMAP ANALYSIS PANEL (for heatmap mode) -->
                        <div id="heatmapAnalysisPanel" style="background: rgba(255, 255, 255, 0.98); border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; display: none; flex-direction: column; flex-grow: 1;">
                            <!-- Heatmap Controls -->
                            <div style="padding: 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0 0 14px;">
                                    <i class="fas fa-sliders-h mr-2" style="color: #274d4c;"></i>Heatmap Analysis
                                </h3>

                                <!-- Radius Slider -->
                                <div style="margin-bottom: 14px;">
                                    <label style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; display: block; margin-bottom: 6px;">
                                        <i class="fas fa-expand-alt mr-1" style="color: #274d4c;"></i>Radius
                                    </label>
                                    <input type="range" id="heatmapRadius" min="20" max="100" value="40" style="width: 100%; cursor: pointer;">
                                    <div style="font-size: 11px; color: #999; margin-top: 4px;">
                                        <span id="radiusValue">40</span>m
                                    </div>
                                </div>

                                <!-- Blur Slider -->
                                <div style="margin-bottom: 14px;">
                                    <label style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; display: block; margin-bottom: 6px;">
                                        <i class="fas fa-wind mr-1" style="color: #274d4c;"></i>Blur
                                    </label>
                                    <input type="range" id="heatmapBlur" min="10" max="40" value="20" style="width: 100%; cursor: pointer;">
                                    <div style="font-size: 11px; color: #999; margin-top: 4px;">
                                        <span id="blurValue">20</span>
                                    </div>
                                </div>

                                <!-- Intensity Slider -->
                                <div style="margin-bottom: 14px;">
                                    <label style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; display: block; margin-bottom: 6px;">
                                        <i class="fas fa-fire mr-1" style="color: #274d4c;"></i>Intensity
                                    </label>
                                    <input type="range" id="heatmapIntensity" min="0.5" max="2" step="0.1" value="1" style="width: 100%; cursor: pointer;">
                                    <div style="font-size: 11px; color: #999; margin-top: 4px;">
                                        <span id="intensityValue">1.0</span>x
                                    </div>
                                </div>

                                <!-- Analysis Radius Slider -->
                                <div style="margin-bottom: 14px;">
                                    <label style="font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; display: block; margin-bottom: 6px;">
                                        <i class="fas fa-search-plus mr-1" style="color: #274d4c;"></i>Analysis Radius
                                    </label>
                                    <input type="range" id="analysisRadiusSlider" min="100" max="2000" step="100" value="500" style="width: 100%; cursor: pointer;">
                                    <div style="font-size: 11px; color: #999; margin-top: 4px;">
                                        <span id="analysisRadiusValue">500</span>m
                                    </div>
                                </div>

                                <!-- Reset Button -->
                                <button id="heatmapResetBtn" style="width: 100%; background-color: #274d4c; color: white; border: none; padding: 8px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                    <i class="fas fa-redo mr-2"></i>Reset Controls
                                </button>
                            </div>

                            <!-- Area Analysis Results -->
                            <div id="areaAnalysisResults" style="overflow-y: auto; flex-grow: 1; padding: 16px;">
                                <div style="text-align: center; padding: 40px 20px; color: #999; font-size: 12px;">
                                    <i class="fas fa-info-circle mr-2"></i>Click on the heatmap to analyze a <span id="analysisRadiusDisplay">500</span>m area
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Crime Intensity & Density Scale (Floating Panel) -->
                <div id="crimeIntensityScale" class="mt-4 mb-4 w-full max-h-[400px] overflow-y-auto bg-white bg-opacity-99 border border-gray-200 rounded-lg p-4 hidden" style="z-index: 1;">
                    <h3 class="text-sm font-bold text-gray-900 mb-3">
                        <i class="fas fa-palette mr-2 text-alertara-700"></i>Crime Intensity Scale
                    </h3>

                    <!-- Gradient Bar -->
                    <div style="margin-bottom: 12px;">
                        <div style="height: 32px; border-radius: 6px; background: linear-gradient(90deg, #3b82f6 0%, #2ecc71 25%, #f39c12 50%, #e74c3c 75%, #c0392b 100%); border: 1px solid rgba(0, 0, 0, 0.1); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);"></div>
                        <div style="display: flex; justify-content: space-between; padding: 6px 4px; font-size: 11px; font-weight: 600; color: #666;">
                            <span>Low</span>
                            <span>Medium</span>
                            <span>High</span>
                        </div>
                    </div>

                    <!-- Legend Items with Thresholds -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; border-top: 1px solid #e5e7eb; padding-top: 12px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 4px; background: #3b82f6;"></div>
                            <div style="font-size: 11px; color: #555;">
                                <div style="font-weight: 600;">Low density</div>
                                <div style="font-size: 10px; color: #999;">1-5 crimes</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 4px; background: #2ecc71;"></div>
                            <div style="font-size: 11px; color: #555;">
                                <div style="font-weight: 600;">Low-Medium</div>
                                <div style="font-size: 10px; color: #999;">6-15 crimes</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 4px; background: #f39c12;"></div>
                            <div style="font-size: 11px; color: #555;">
                                <div style="font-weight: 600;">Medium density</div>
                                <div style="font-size: 10px; color: #999;">16-30 crimes</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 4px; background: #e74c3c;"></div>
                            <div style="font-size: 11px; color: #555;">
                                <div style="font-weight: 600;">High density</div>
                                <div style="font-size: 10px; color: #999;">31-50 crimes</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; grid-column: 1 / -1;">
                            <div style="width: 20px; height: 20px; border-radius: 4px; background: #c0392b;"></div>
                            <div style="font-size: 11px; color: #555;">
                                <div style="font-weight: 600;">Critical hotspot</div>
                                <div style="font-size: 10px; color: #999;">50+ crimes</div>
                            </div>
                        </div>
                    </div>

                    <!-- Street-Segment Heatmap: summary + note (default view) -->
                    <div id="streetHeatSummary" style="margin-top: 12px;"></div>
                    <div id="streetHeatNote" style="margin-top: 12px; padding: 10px; background: #f0f9f8; border-left: 3px solid #274d4c; border-radius: 4px;">
                        <p style="font-size: 11px; color: #555; margin: 0; line-height: 1.4;">
                            <i class="fas fa-road mr-1" style="color: #274d4c;"></i>
                            <strong>Street-Segment Heatmap:</strong> each road segment is coloured by the crimes recorded along it, from blue (few) to dark red (most). Grey segments have none. Hover a street for its count, click it for the breakdown.
                        </p>
                    </div>

                    <!-- Info Box (blurred heat map) -->
                    <div id="heatBlobNote" style="margin-top: 12px; padding: 10px; background: #f0f9f8; border-left: 3px solid #274d4c; border-radius: 4px; display: none;">
                        <p style="font-size: 11px; color: #555; margin: 0; line-height: 1.4;">
                            <i class="fas fa-lightbulb mr-1" style="color: #274d4c;"></i>
                            <strong>Weighted by:</strong> Crime severity + clearance status. Uncleared cases increase intensity. Use sliders in heatmap mode to adjust visualization.
                        </p>
                    </div>
                </div>

                <!-- Severity Legend (Cluster View) -->
                <div id="severityLegend" style="background: rgba(255, 255, 255, 0.98); border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); margin-top: 16px; margin-bottom: 16px; display: none;">
                    <!-- Crime Severity Levels -->
                    <div style="margin-bottom: 16px;">
                        <h3 style="font-size: 13px; font-weight: 700; color: #111; margin-bottom: 10px;">
                            <i class="fas fa-flag mr-2" style="color: #274d4c;"></i>Crime Severity Levels
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 16px; height: 16px; border-radius: 50%; background: #dc2626;"></div>
                                <span style="font-size: 12px; color: #555;">Serious</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 16px; height: 16px; border-radius: 50%; background: #f97316;"></div>
                                <span style="font-size: 12px; color: #555;">Moderate</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 16px; height: 16px; border-radius: 50%; background: #16a34a;"></div>
                                <span style="font-size: 12px; color: #555;">Minor</span>
                            </div>
                        </div>
                    </div>

                    <!-- Cluster Color Thresholds -->
                    <div style="border-top: 1px solid #e5e7eb; padding-top: 12px;">
                        <h3 style="font-size: 13px; font-weight: 700; color: #111; margin-bottom: 10px;">
                            <i class="fas fa-layer-group mr-2" style="color: #274d4c;"></i>Cluster Color Scale
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #16a34a 0%, #16a34add 100%); border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>
                                <div style="font-size: 11px; color: #555;">
                                    <div style="font-weight: 600;">Green</div>
                                    <div style="font-size: 10px; color: #999;">1-10 crimes</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #eab308 0%, #eab308dd 100%); border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>
                                <div style="font-size: 11px; color: #555;">
                                    <div style="font-weight: 600;">Yellow</div>
                                    <div style="font-size: 10px; color: #999;">11-30 crimes</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #dc2626 0%, #dc2626dd 100%); border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>
                                <div style="font-size: 11px; color: #555;">
                                    <div style="font-weight: 600;">Red</div>
                                    <div style="font-size: 10px; color: #999;">31+ crimes</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Crime Category Legend -->
                <div id="categoryLegendContainer" style="background: rgba(255, 255, 255, 0.98); border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); margin-top: 16px;">
                    <h3 style="font-size: 14px; font-weight: 700; color: #111; margin-bottom: 12px;">
                        <i class="fas fa-list-ul mr-2" style="color: #274d4c;"></i>Crime Categories
                    </h3>
                    <div id="categoryLegend" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                        <!-- Categories will be populated here by JavaScript -->
                        <div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">Loading categories...</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Fullscreen Map Container -->
    </main>

    <!-- Incident Details Modal (Full Viewport Overlay) -->
    <div id="incidentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.6); z-index: 99999; padding: 20px; align-items: center; justify-content: center;" onclick="if(event.target === this) closeIncidentModal()">
        <div style="position: relative; background: white; border-radius: 16px; max-width: 380px; max-height: 85%; overflow-y: auto; box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35); pointer-events: auto;">
            <!-- Close Button -->
            <button onclick="closeIncidentModal()" style="position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 20px; color: #999; cursor: pointer; z-index: 10; transition: color 0.2s; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'"><i class="fas fa-times"></i></button>

            <!-- Category Badge Header -->
            <div id="modalCategoryBadge" style="padding: 20px 20px 0;">
                <span style="display: inline-block; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; color: white; background-color: #274d4c;">
                    <i class="fas fa-tag mr-2"></i><span id="modalCategoryName">Loading...</span>
                </span>
            </div>

            <!-- Title -->
            <div style="padding: 16px 20px 0;">
                <h2 id="modalTitle" style="font-size: 18px; font-weight: 700; color: #111; margin: 0; line-height: 1.4;">Loading...</h2>
            </div>

            <!-- Details Grid -->
            <div style="padding: 20px;">
                <div style="display: grid; gap: 14px;">
                    <!-- Row 1: Date and Time -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                                <i class="fas fa-calendar mr-1" style="color: #274d4c;"></i>Date
                            </label>
                            <div id="modalDate" style="font-size: 14px; font-weight: 600; color: #222;">—</div>
                        </div>
                        <div>
                            <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                                <i class="fas fa-clock mr-1" style="color: #274d4c;"></i>Time
                            </label>
                            <div id="modalTime" style="font-size: 14px; font-weight: 600; color: #222;">—</div>
                        </div>
                    </div>

                    <!-- Row 2: Location -->
                    <div>
                        <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                            <i class="fas fa-map-marker-alt mr-1" style="color: #274d4c;"></i>Location
                        </label>
                        <div id="modalLocation" style="font-size: 14px; color: #333;">—</div>
                    </div>

                    <!-- Row 3: Address -->
                    <div>
                        <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                            <i class="fas fa-home mr-1" style="color: #274d4c;"></i>Address
                        </label>
                        <div id="modalAddress" style="font-size: 14px; color: #333;">—</div>
                    </div>

                    <!-- Row 4: Status (Workflow) -->
                    <div>
                        <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                            <i class="fas fa-tasks mr-1" style="color: #274d4c;"></i>Case Status
                        </label>
                        <div id="modalStatus">
                            <span style="display: inline-block; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white;">—</span>
                        </div>
                    </div>

                    <!-- Row 5: Clearance Status -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                                <i class="fas fa-check-circle mr-1" style="color: #274d4c;"></i>Clearance
                            </label>
                            <div id="modalClearanceStatus">
                                <span style="display: inline-block; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white;">—</span>
                            </div>
                        </div>
                        <div>
                            <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                                <i class="fas fa-hashtag mr-1" style="color: #274d4c;"></i>Case
                            </label>
                            <div id="modalCaseNumber" style="font-size: 14px; color: #222; font-weight: 600;">—</div>
                        </div>
                    </div>

                    <!-- Row 6: Details -->
                    <div style="border-top: 1px solid #e5e7eb; padding-top: 14px;">
                        <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                            <i class="fas fa-file-alt mr-1" style="color: #274d4c;"></i>Details
                        </label>
                        <div id="modalDetails" style="font-size: 13px; color: #555; line-height: 1.5;">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- No street layer on this page: the map shows barangay boundaries and the
         crimes themselves. Street lines, their crime levels and the prevention
         suggestions all live on the Crime Hotspots page. --}}

    <script>
        // State variables
        let heatmapLayer = null;
        let streetHeatmap = null;       // StreetSegmentHeatmap instance (default view)
        let markerClusterGroup = null;
        let markerLayer = null;
        let currentVisualizationMode = 'markers';
        let boundaryLayer = null;       // whole-QC outline
        let filterTimeout = null;
        let qcBounds = null;
        let map = null;

        // Barangay boundary layers, keyed by PSGC code so they line up with the
        // barangay filter (which is driven by /api/qc-barangays).
        let barangayBoundaryLayer = null;
        let barangayLayersByCode = {};
        let barangayRingsByCode = {};
        let selectedBarangayLabel = null;
        let barangayRenderer = null;    // renderer bound to the low-z barangay pane

        // Barangay the map opens on
        const DEFAULT_BARANGAY = @json($prefs['default_barangay'] ?: 'San Agustin');
        // Tight padding when a single barangay is isolated. No minimum-zoom floor:
        // 13 of the 142 barangays are large enough that forcing a closer zoom would
        // crop them, and fitBounds already gives the closest view that still shows
        // the whole barangay.
        const BARANGAY_FIT_PADDING = [12, 12];

        // Thin borders, light fills. The active barangay is marked by its border and a
        // faint tint — a heavy fill would wash out the incident circles sitting on it.
        const STYLE_BRGY_IDLE   = { color: '#8fb3b0', weight: 0.8, opacity: 0.7, fillColor: '#f2f9f8', fillOpacity: 0.10, dashArray: null };
        const STYLE_BRGY_HOVER  = { color: '#274d4c', weight: 1.5, opacity: 1,   fillColor: '#bde5dd', fillOpacity: 0.30, dashArray: null };
        const STYLE_BRGY_ACTIVE = { color: '#274d4c', weight: 2.5, opacity: 1,   fillColor: '#bfe5de', fillOpacity: 0.16, dashArray: null };
        let currentData = [];
        let selectedIncidentId = null;
        let pointerMarker = null;
        let selectedIncidentCoords = null;

        // Pagination state variables
        const MAX_VISIBLE_INCIDENTS = @json((int) $prefs['rows_per_page']);
        let currentListData = [];
        let currentListPage = 1;
        let searchTimeout = null;

        // Store cluster zoom handler to remove old listeners
        let clusterZoomHandler = null;
        let highlightCircle = null;

        // Heatmap analysis state
        let heatmapRadius = 40;
        let heatmapBlur = 20;
        let heatmapIntensity = 1;
        let analysisRadius = 500; // Customizable analysis radius in meters
        let analysisCircle = null;
        let analysisMarker = null;

        // HTML-escape for anything interpolated into map markup
        const escStreet = value => String(value ?? '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        // Loading overlay functions
        function showMapLoading() {
            const overlay = document.getElementById('mapLoadingOverlay');
            if (overlay) {
                overlay.style.display = 'flex';
            }
        }

        function hideMapLoading() {
            const overlay = document.getElementById('mapLoadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }

        // Helper function to get workflow status color and text
        function getWorkflowStatusInfo(status) {
            const statusMap = {
                'reported': { color: '#3b82f6', text: 'Reported', bgColor: '#dbeafe' },
                'under_investigation': { color: '#f59e0b', text: 'Under Investigation', bgColor: '#fef3c7' },
                'solved': { color: '#10b981', text: 'Solved', bgColor: '#d1fae5' },
                'closed': { color: '#6366f1', text: 'Closed', bgColor: '#e0e7ff' },
                'archived': { color: '#8b5cf6', text: 'Archived', bgColor: '#ede9fe' }
            };
            return statusMap[status] || { color: '#6b7280', text: status || 'Unknown', bgColor: '#f3f4f6' };
        }

        // Helper function to get clearance status color and text
        function getClearanceStatusInfo(clearanceStatus) {
            const statusMap = {
                'cleared': { color: '#10b981', text: 'Cleared', bgColor: '#d1fae5' },
                'uncleared': { color: '#f59e0b', text: 'Uncleared', bgColor: '#fef3c7' }
            };
            return statusMap[clearanceStatus] || { color: '#6b7280', text: clearanceStatus || 'Unknown', bgColor: '#f3f4f6' };
        }

        // createCrimeMap() lives in public/js/crime-map-base.js so the SAME map
        // setup (tiles, zoom limits, inertia) is shared with the street modal
        // and the Add Crime Record form. There is one place to fix bugs.

        // Initialize map
        function initializeMap() {
            console.log('Initializing map...');

            // Create the map with default QC view (shared component)
            map = createCrimeMap('map');

            // Google Maps (default, Hybrid imagery) and the 3D view are mounted
            // over the same container; both show whatever currentData holds,
            // so the filters keep working in every engine.
            const engines = {};
            if (typeof CrimeMapGoogle !== 'undefined') {
                try {
                    window.crimeMapGoogle = engines.google = CrimeMapGoogle.create({
                        wrapper: document.getElementById('mapContainer'),
                        getIncidents: () => currentData,
                        getMode: () => document.getElementById('visualizationMode').value,
                        modeSelect: document.getElementById('visualizationMode'),
                        streetView: true,   // native Pegman / Street View on this map
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
                    storageKey: 'crimeMappingEngine',
                });
            }

            // Boundaries get their own pane BELOW the default overlayPane (z-index 400).
            // Polygons and circle markers otherwise share one pane, so highlighting a
            // barangay would draw its fill over the incident circles and swallow their
            // clicks. Separate panes keep the circles visible and clickable always.
            map.createPane('barangayPane');
            map.getPane('barangayPane').style.zIndex = 350;
            barangayRenderer = L.svg({ pane: 'barangayPane' });

            // Ensure map size is calculated, then load boundary
            setTimeout(() => {
                map.invalidateSize();
                loadQCBoundary();
            }, 50);
        }

        // Load QC boundary from GeoJSON
        async function loadQCBoundary() {
            console.log('Loading QC boundary and barangay boundaries...');
            const timestamp = new Date().getTime();

            // 1. Whole-QC outline. fullmapqc.geojson is the PSGC city outline, so it
            //    lines up exactly with the PSGC barangay polygons loaded below.
            try {
                const response = await fetch(`/fullmapqc.geojson?t=${timestamp}`);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();

                if (boundaryLayer) map.removeLayer(boundaryLayer);
                boundaryLayer = L.geoJSON(data, {
                    style: { color: '#274d4c', weight: 2, opacity: 0.9, fill: false },
                    interactive: false,
                    pane: 'barangayPane',
                    renderer: barangayRenderer
                }).addTo(map);

                qcBounds = boundaryLayer.getBounds();
            } catch (error) {
                console.error('Error loading QC outline:', error);
                qcBounds = L.latLngBounds(L.latLng(14.50, 120.90), L.latLng(14.80, 121.20));
            }

            // 2. The 142 Quezon City barangays
            try {
                const response = await fetch(`/qc_barangays.geojson?t=${timestamp}`);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();

                barangayBoundaryLayer = L.geoJSON(data, {
                    style: () => Object.assign({}, STYLE_BRGY_IDLE),
                    pane: 'barangayPane',
                    renderer: barangayRenderer,
                    onEachFeature: (feature, layer) => {
                        const p = feature.properties || {};
                        const code = String(p.code || '');
                        const name = (p.name || '').trim();
                        if (!code || !name) return;

                        barangayLayersByCode[code] = layer;
                        barangayRingsByCode[code] = ringsOfGeometry(feature.geometry);

                        // Hover -> identify. Tooltips are bound/unbound by
                        // applyBarangaySelection(): they exist ONLY in the
                        // All-Barangays view.
                        layer._brgyName = name;
                        layer.bindTooltip(name, {
                            sticky: true,
                            direction: 'top',
                            className: 'brgy-tooltip'
                        });

                        // Hover-to-identify only works in the All-Barangays view.
                        // With a specific barangay isolated, the neighbours stay
                        // visible for context but do not react to the mouse.
                        layer.on('mouseover', function () {
                            if (document.getElementById('barangay').value) return;
                            this.setStyle(STYLE_BRGY_HOVER);
                            this.bringToFront();
                        });
                        layer.on('mouseout', function () {
                            if (document.getElementById('barangay').value) return;
                            this.setStyle(styleForBarangay(code));
                        });
                        // Click-to-select only works in the All-Barangays view.
                        // Once a barangay is isolated, clicking a neighbour does
                        // nothing — switching is done through the filter dropdown.
                        layer.on('click', function () {
                            if (document.getElementById('barangay').value) return;
                            document.getElementById('barangay').value = code;
                            applyBarangaySelection();
                            loadCrimeData();
                        });
                    }
                }).addTo(map);

                // Keep the city outline drawn over the barangay fills. Sitting under the
                // incident markers is handled by 'barangayPane', not by ordering.
                if (boundaryLayer) boundaryLayer.bringToFront();

                if (!qcBounds || !qcBounds.isValid()) qcBounds = barangayBoundaryLayer.getBounds();
            } catch (error) {
                console.error('Error loading barangay boundaries:', error);
            }

            map.invalidateSize();

            // No fitBounds here on purpose. applyBarangaySelection() below does the
            // framing — to the default barangay, or to the whole city if none is set.
            // Fitting to the city first started an animation that outran the barangay
            // zoom and left the map sitting at city level.
            if (!qcBounds || !qcBounds.isValid()) {
                map.setView([14.6349, 121.0446], 12);
            }

            applyBoundaryConstraints();

            loadCrimeCategories();
            await loadBarangays();

            // Land on the default barangay. Driven off the option labels so it works
            // whether the list came from /api/qc-barangays or the fallback.
            const barangaySelect = document.getElementById('barangay');
            const defaultOption = [...barangaySelect.options].find(
                o => o.value && o.textContent.trim().toLowerCase() === DEFAULT_BARANGAY.toLowerCase()
            );
            if (defaultOption) barangaySelect.value = defaultOption.value;
            else console.warn(`Default barangay "${DEFAULT_BARANGAY}" not found in the filter.`);

            setupAutoFilter();
            setupZoomScaling();
            applyBarangaySelection(false);   // snap straight there, no competing animation
            loadCrimeData();
            loadTotalStats();
        }

        function ringsOfGeometry(geometry) {
            if (!geometry) return [];
            if (geometry.type === 'Polygon') return [geometry.coordinates];
            if (geometry.type === 'MultiPolygon') return geometry.coordinates;
            return [];
        }

        // Style a barangay according to whether it is the filtered one
        function styleForBarangay(code) {
            const selectedCode = document.getElementById('barangay').value;
            return Object.assign({}, code === selectedCode && selectedCode
                ? STYLE_BRGY_ACTIVE
                : STYLE_BRGY_IDLE);
        }

        // The city outline and every barangay stay on the map at all times. Filtering
        // marks one barangay active and zooms to it; it never hides the rest, so you
        // keep the Quezon City boundary and the neighbouring barangays for context.
        function applyBarangaySelection(animate = true) {
            const selectedCode = document.getElementById('barangay').value;

            if (selectedBarangayLabel) {
                map.removeLayer(selectedBarangayLabel);
                selectedBarangayLabel = null;
            }

            if (!barangayBoundaryLayer) return;

            // QC outline is always visible
            if (boundaryLayer && !map.hasLayer(boundaryLayer)) boundaryLayer.addTo(map);

            Object.entries(barangayLayersByCode).forEach(([code, layer]) => {
                if (!barangayBoundaryLayer.hasLayer(layer)) barangayBoundaryLayer.addLayer(layer);
                layer.setStyle(styleForBarangay(code));

                // Identify-on-hover tooltips belong to the All-Barangays view only
                if (selectedCode) {
                    if (layer.getTooltip()) layer.unbindTooltip();
                } else if (!layer.getTooltip() && layer._brgyName) {
                    layer.bindTooltip(layer._brgyName, {
                        sticky: true,
                        direction: 'top',
                        className: 'brgy-tooltip'
                    });
                }
            });

            // Ordering only shuffles layers within 'barangayPane'; the incident circles
            // live in the higher overlayPane and stay above all of this.
            const activeLayer = selectedCode ? barangayLayersByCode[selectedCode] : null;
            if (activeLayer) activeLayer.bringToFront();

            if (selectedCode) {
                const layer = barangayLayersByCode[selectedCode];
                if (layer) {
                    const b = layer.getBounds();
                    selectedBarangayLabel = L.marker(b.getCenter(), {
                        interactive: false,
                        icon: L.divIcon({ className: '', html: '' })
                    }).addTo(map);
                    const select = document.getElementById('barangay');
                    const label = nameByPsgcCode[selectedCode] ||
                        (select.selectedOptions[0] || {}).textContent || '';
                    selectedBarangayLabel.bindTooltip(label, {
                        permanent: true,
                        direction: 'center',
                        className: 'brgy-label-selected'
                    }).openTooltip();

                    zoomToBarangayBounds(b, animate);
                }
            } else if (qcBounds && qcBounds.isValid()) {
                // All Barangays -> frame the whole city again
                map.fitBounds(qcBounds, { padding: [20, 20], animate });
            }

        }

        // Frame a single barangay as closely as possible while keeping all of it visible
        function zoomToBarangayBounds(bounds, animate = true) {
            map.fitBounds(bounds, { padding: BARANGAY_FIT_PADDING, animate });
        }



        // Apply boundary constraints
        function applyBoundaryConstraints() {
            if (!qcBounds || !map) {
                console.log('Cannot apply boundary constraints: qcBounds or map is null');
                return;
            }

            console.log('Applying boundary constraints...');
            
            // Roomy padding so a barangay on the city edge can still be centred.
            // setMaxBounds already stops the user drifting away from Quezon City.
            map.setMaxBounds(qcBounds.pad(0.25));

            // Deliberately no zoomend "snap back to QC" handler here. Zooming into a
            // barangay near the city edge makes the viewport overhang the boundary,
            // which such a handler reads as "out of bounds" and undoes the zoom.
        }

        // Fit map to QC boundary
        function fitToQCBoundary() {
            if (qcBounds && qcBounds.isValid()) {
                console.log('Manual fit to QC boundary');
                map.fitBounds(qcBounds, {
                    padding: [20, 20],
                    animate: true
                });
            }
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing map...');
            initializeMap();
        });

        // Add custom zoom and reset controls
        function addZoomControls() {
            const controlContainer = L.control({position: 'topright'});

            controlContainer.onAdd = function() {
                const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                div.style.display = 'flex';
                div.style.flexDirection = 'column';
                div.style.gap = '5px';

                // Fit Boundary button
                const fitBtn = L.DomUtil.create('a', '', div);
                fitBtn.href = '#';
                fitBtn.title = 'Fit Boundary';
                fitBtn.innerHTML = '<i class="fas fa-plus" style="color: #274d4c; font-size: 14px;"></i>';
                fitBtn.style.display = 'block';
                fitBtn.style.padding = '8px';
                fitBtn.style.background = 'white';
                fitBtn.style.borderRadius = '4px';
                fitBtn.style.border = '2px solid rgba(0,0,0,0.2)';
                fitBtn.style.textAlign = 'center';
                fitBtn.style.cursor = 'pointer';
                fitBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    fitToQCBoundary();
                    return false;
                };

                // Zoom Out button
                const zoomOutBtn = L.DomUtil.create('a', '', div);
                zoomOutBtn.href = '#';
                zoomOutBtn.title = 'Zoom Out';
                zoomOutBtn.innerHTML = '<i class="fas fa-minus" style="color: #274d4c; font-size: 14px;"></i>';
                zoomOutBtn.style.display = 'block';
                zoomOutBtn.style.padding = '8px';
                zoomOutBtn.style.background = 'white';
                zoomOutBtn.style.borderRadius = '4px';
                zoomOutBtn.style.border = '2px solid rgba(0,0,0,0.2)';
                zoomOutBtn.style.textAlign = 'center';
                zoomOutBtn.style.cursor = 'pointer';
                zoomOutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    map.zoomOut();
                    return false;
                };

                // Reset View button
                const resetBtn = L.DomUtil.create('a', '', div);
                resetBtn.href = '#';
                resetBtn.title = 'Reset View';
                resetBtn.innerHTML = '<i class="fas fa-expand" style="color: #274d4c; font-size: 14px;"></i>';
                resetBtn.style.display = 'block';
                resetBtn.style.padding = '8px';
                resetBtn.style.background = 'white';
                resetBtn.style.borderRadius = '4px';
                resetBtn.style.border = '2px solid rgba(0,0,0,0.2)';
                resetBtn.style.textAlign = 'center';
                resetBtn.style.cursor = 'pointer';
                resetBtn.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
                resetBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    fitToQCBoundary();
                    return false;
                };

                return div;
            };

            controlContainer.addTo(map);
        }

        // The rest of your existing functions remain the same...
        // (loadCrimeCategories, loadBarangays, loadCrimeData, etc.)
        // Keep all your existing functions from here...

        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('aside');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        sidebarToggle?.addEventListener('click', function() {
            sidebar?.classList.toggle('-translate-x-full');
            sidebarOverlay?.classList.toggle('hidden');
        });

        sidebarOverlay?.addEventListener('click', function() {
            sidebar?.classList.add('-translate-x-full');
            sidebarOverlay?.classList.add('hidden');
        });

        // Load crime categories for Crime Type filter
        async function loadCrimeCategories() {
            try {
                const response = await fetch('/api/crime-categories');
                const categories = await response.json();

                const crimeTypeSelect = document.getElementById('crimeType');
                const categoryLegend = document.getElementById('categoryLegend');

                // Clear loading message
                categoryLegend.innerHTML = '';

                categories.forEach(category => {
                    // Add to dropdown
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.category_name;
                    crimeTypeSelect.appendChild(option);

                    // Add to legend with color and icon from database
                    const categoryColor = category.color_code || '#274d4c';
                    const categoryIcon = category.icon || 'fa-exclamation-circle';
                    const legendItem = document.createElement('div');
                    legendItem.style.display = 'flex';
                    legendItem.style.alignItems = 'center';
                    legendItem.style.gap = '10px';
                    legendItem.style.padding = '10px';
                    legendItem.style.backgroundColor = 'rgba(0, 0, 0, 0.02)';
                    legendItem.style.borderRadius = '6px';

                    const colorSwatch = document.createElement('div');
                    colorSwatch.style.width = '32px';
                    colorSwatch.style.height = '32px';
                    colorSwatch.style.borderRadius = '6px';
                    colorSwatch.style.backgroundColor = categoryColor;
                    colorSwatch.style.border = '2px solid rgba(0, 0, 0, 0.1)';
                    colorSwatch.style.flexShrink = '0';
                    colorSwatch.style.display = 'flex';
                    colorSwatch.style.alignItems = 'center';
                    colorSwatch.style.justifyContent = 'center';

                    const icon = document.createElement('i');
                    icon.className = `fas ${categoryIcon}`;
                    icon.style.color = 'white';
                    icon.style.fontSize = '14px';
                    colorSwatch.appendChild(icon);

                    const categoryName = document.createElement('span');
                    categoryName.textContent = category.category_name;
                    categoryName.style.fontSize = '13px';
                    categoryName.style.fontWeight = '500';
                    categoryName.style.color = '#333';

                    legendItem.appendChild(colorSwatch);
                    legendItem.appendChild(categoryName);
                    categoryLegend.appendChild(legendItem);
                });

                if (categories.length === 0) {
                    categoryLegend.innerHTML = '<div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">No crime categories found</div>';
                }
            } catch (error) {
                console.error('Error loading crime categories:', error);
                document.getElementById('categoryLegend').innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c; font-size: 12px;">Error loading categories</div>';
            }
        }

        // Barangay filter state.
        // Options come from /api/qc-barangays — the official 142 Quezon City barangays
        // with PSGC codes — so this page matches the Barangay Boundaries page.
        // /api/barangays is NOT used for the options: it lists every barangay on file,
        // including ones outside QC such as Addition Hills.
        let nameByPsgcCode = {};        // PSGC code -> official name

        // Load barangays for Barangay filter
        async function loadBarangays() {
            const barangaySelect = document.getElementById('barangay');

            // Called from both branches of loadQCBoundary, so start from a clean list
            // and keep only the "All Barangays" placeholder.
            while (barangaySelect.options.length > 1) barangaySelect.remove(1);
            nameByPsgcCode = {};

            // The official QC list drives the options. No DB id bridge is needed —
            // the incident table stores barangay_name, so the filter sends the name.
            let qcRows = [];
            try {
                const response = await fetch('/api/qc-barangays');
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                qcRows = await response.json();
            } catch (error) {
                console.warn('/api/qc-barangays unavailable, falling back to /api/barangays:', error.message);
                try {
                    const response = await fetch('/api/barangays');
                    qcRows = (await response.json()).map(b => ({ code: String(b.id), name: b.barangay_name }));
                } catch (e) {
                    console.error('Error loading barangays:', e);
                }
            }

            qcRows.forEach(row => {
                const code = String(row.code || '');
                const name = (row.name || '').trim();
                if (!code || !name) return;

                nameByPsgcCode[code] = name;

                const option = document.createElement('option');
                option.value = code;
                option.textContent = name;
                barangaySelect.appendChild(option);
            });

            console.log(`Barangay filter: ${barangaySelect.options.length - 1} barangays loaded.`);
        }

        // The incident table stores barangay_name as plain text, so the filter sends
        // the barangay NAME rather than any id.
        function resolveBarangayFilterId() {
            const select = document.getElementById('barangay');
            if (!select.value) return null;
            return nameByPsgcCode[select.value] ||
                (select.selectedOptions[0] || {}).textContent.trim() || null;
        }

        // Streets come from the same incident rows displayed on the map. This
        // keeps the dropdown limited to real streets that have data for the
        // currently selected date/category/status/barangay filters.
        function populateStreetFilter(data) {
            const streetSelect = document.getElementById('street');
            const selectedStreet = streetSelect.value;
            const streets = [...new Set((data || [])
                .map(incident => (incident.street || '').trim())
                .filter(Boolean))]
                .sort((a, b) => a.localeCompare(b));

            streetSelect.innerHTML = '<option value="">All Streets</option>';
            streets.forEach(street => {
                const option = document.createElement('option');
                option.value = street;
                option.textContent = street;
                streetSelect.appendChild(option);
            });

            // If another filter removes the selected street, safely return to
            // the complete filtered result instead of showing an empty map.
            streetSelect.value = streets.includes(selectedStreet) ? selectedStreet : '';
        }

        function filterDataByStreet(data) {
            const street = document.getElementById('street').value;
            return street
                ? data.filter(incident => (incident.street || '').trim() === street)
                : data;
        }

        // Debug variables
        let eventCounter = 0;
        let debugVisible = false;

        // Debug panel functionality
        document.addEventListener('DOMContentLoaded', function() {
            const debugBtn = document.getElementById('debugRealtimeBtn');
            const toggleDebugBtn = document.getElementById('toggleDebugBtn');
            const debugPanel = document.getElementById('debugPanel');
            
            if (debugBtn && toggleDebugBtn && debugPanel) {
                // Toggle debug panel
                debugBtn.addEventListener('click', function() {
                    debugVisible = !debugVisible;
                    if (debugPanel) {
                        debugPanel.classList.toggle('hidden');
                    }
                    if (toggleDebugBtn) {
                        toggleDebugBtn.textContent = debugVisible ? 'Hide Debug Panel' : 'Show Debug Panel';
                    }
                    updateDebugInfo();
                });
                
                if (toggleDebugBtn) {
                    toggleDebugBtn.addEventListener('click', function() {
                        debugVisible = false;
                        if (debugPanel) {
                            debugPanel.classList.add('hidden');
                        }
                        toggleDebugBtn.textContent = 'Show Debug Panel';
                    });
                }
            }
        });

        // Update debug information
        function updateDebugInfo() {
            const echoStatus = document.getElementById('echoStatus');
            const pusherStatus = document.getElementById('pusherStatus');
            const channelStatus = document.getElementById('channelStatus');
            const eventCount = document.getElementById('eventCount');
            const lastEvent = document.getElementById('lastEvent');
            const dataCount = document.getElementById('dataCount');
            
            if (echoStatus) {
                echoStatus.textContent = (typeof window.Echo !== 'undefined' && window.Echo) ? '✅ Available' : '❌ Not Available';
                echoStatus.className = (typeof window.Echo !== 'undefined' && window.Echo) ? 'text-green-600' : 'text-red-600';
            }
            
            if (pusherStatus) {
                try {
                    const pusher = window.Echo?.connector?.pusher;
                    const state = pusher?.connection?.state;
                    pusherStatus.textContent = state === 'connected' ? '✅ Connected' : `❌ ${state || 'Unknown'}`;
                    pusherStatus.className = state === 'connected' ? 'text-green-600' : 'text-red-600';
                } catch (error) {
                    pusherStatus.textContent = '❌ Error';
                    pusherStatus.className = 'text-red-600';
                }
            }
            
            if (channelStatus) {
                channelStatus.textContent = '✅ Subscribed to crime-incidents';
                channelStatus.className = 'text-green-600';
            }
            
            if (eventCount) {
                eventCount.textContent = eventCounter;
                eventCount.className = eventCounter > 0 ? 'text-green-600' : 'text-red-600';
            }
            
            if (lastEvent) {
                lastEvent.textContent = eventCounter > 0 ? '✅ Events Received' : '❌ No Events';
                lastEvent.className = eventCounter > 0 ? 'text-green-600' : 'text-red-600';
            }
            
            if (dataCount) {
                dataCount.textContent = currentData.length;
                dataCount.className = currentData.length > 0 ? 'text-green-600' : 'text-red-600';
            }
        }

        // Update debug info every 2 seconds
        setInterval(updateDebugInfo, 2000);

        // Load total statistics (unfiltered)
        async function loadTotalStats() {
            try {
                const response = await fetch('/api/crime-stats');
                const stats = await response.json();
                
                document.getElementById('statTotalCrime').textContent = stats.total_crime ?? 0;
                document.getElementById('statCleared').textContent = stats.cleared ?? 0;
                document.getElementById('statUncleared').textContent = stats.uncleared ?? 0;
                document.getElementById('statCategories').textContent = stats.categories ?? 0;
                
                console.log('Total stats loaded:', stats);
            } catch (error) {
                console.error('Error loading total stats:', error);
            }
        }

        // Load and display crime data
        async function loadCrimeData() {
            const loadingIndicator = document.getElementById('loadingIndicator');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'flex';
            }
            showIncidentSkeleton();
            showMapLoading();

            try {
                const timePeriod = document.getElementById('timePeriod').value;
                const visualizationMode = document.getElementById('visualizationMode').value;
                const crimeType = document.getElementById('crimeType').value;
                const caseStatus = document.getElementById('caseStatus').value;
                const clearanceStatus = document.getElementById('clearanceStatus').value;
                const barangay = resolveBarangayFilterId();

                console.log('loadCrimeData: timePeriod=', timePeriod, 'mode=', visualizationMode, 'type=', crimeType, 'status=', caseStatus, 'clearance=', clearanceStatus, 'barangay=', barangay);

                // Build query parameters
                const params = new URLSearchParams();
                params.append('range', timePeriod);
                if (crimeType) params.append('crime_type', crimeType);
                if (caseStatus) params.append('status', caseStatus);
                if (clearanceStatus) params.append('clearance_status', clearanceStatus);
                if (barangay) params.append('barangay', barangay);

                const url = `/api/crime-heatmap?${params}`;
                console.log('Fetching from:', url);
                const response = await fetch(url);
                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('Data received:', data);

                // Filter data to only show points within QC bounds
                const dataWithinBounds = data.filter(incident => {
                    if (!qcBounds) return true;
                    return qcBounds.contains([incident.latitude, incident.longitude]);
                });

                populateStreetFilter(dataWithinBounds);
                const filteredData = filterDataByStreet(dataWithinBounds);

                // Store data globally for right panel
                currentData = filteredData;
                selectedIncidentId = null;
                if (window.crimeMap3D) window.crimeMap3D.refresh();
                if (window.crimeMapGoogle) window.crimeMapGoogle.refresh();

                // Update right panel with statistics and incident list
                updateStatistics(filteredData);
                updateIncidentList(filteredData);
                renderStreetPanel();
                renderBarangayCrimeLabels(filteredData);

                // Update visualization based on selected mode
                currentVisualizationMode = visualizationMode;
                clearCurrentVisualization();

                if (visualizationMode === 'street-heatmap') {
                    displayStreetHeatmap(filteredData);
                } else if (visualizationMode === 'heatmap') {
                    displayHeatmap(filteredData);
                } else if (visualizationMode === 'markers') {
                    displayMarkers(filteredData);
                } else if (visualizationMode === 'clusters') {
                    displayClusters(filteredData);
                }
            } catch (error) {
                console.error('Error loading crime data:', error);
                document.getElementById('incidentListContent').innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c; font-size: 12px;">Error loading crimes. Please try again.</div>';
                document.getElementById('incidentSkeletonLoader').style.display = 'none';
                document.getElementById('incidentListContent').style.display = 'block';
            } finally {
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none';
                }
                hideMapLoading();
            }
        }

        // ------------------------------------------------------------------
        // "Streets with crime" panel: only streets that actually carry an
        // incident in the current filter, busiest first. Selecting one
        // highlights that street on the map and narrows the crime list.
        // ------------------------------------------------------------------
        let selectedStreet = null;

        let streetsControl = null;
        let streetsControlEl = null;
        let streetsPanelOpen = true;

        // The panel lives ON the map, in the same visual language as the other
        // map overlays: white card, soft shadow, compact type.
        function ensureStreetsControl() {
            if (streetsControl) return;

            streetsControl = L.control({ position: 'bottomright' });
            streetsControl.onAdd = function () {
                const div = L.DomUtil.create('div', 'map-streets-card');
                L.DomEvent.disableClickPropagation(div);
                L.DomEvent.disableScrollPropagation(div);
                streetsControlEl = div;
                return div;
            };
            streetsControl.addTo(map);
        }

        function renderStreetPanel() {
            ensureStreetsControl();
            if (!streetsControlEl) return;

            const groups = groupIncidentsByStreet(currentData || []);

            if (selectedStreet && !groups.some(g => g.name === selectedStreet)) {
                selectedStreet = null;
                if (streetFocusRing) {
                    map.removeLayer(streetFocusRing);
                    streetFocusRing = null;
                }
            }

            const header = `
                <div class="msc-head">
                    <i class="fas fa-road"></i>
                    <span class="msc-title">Streets with crime</span>
                    <span class="msc-count">${groups.length}</span>
                    <button type="button" class="msc-toggle" title="${streetsPanelOpen ? 'Collapse' : 'Expand'}">
                        <i class="fas fa-chevron-${streetsPanelOpen ? 'up' : 'down'}"></i>
                    </button>
                </div>`;

            if (!streetsPanelOpen) {
                streetsControlEl.innerHTML = header;
                wireStreetsControl(groups);
                return;
            }

            const body = groups.length
                ? groups.map(g => {
                    const active = g.name === selectedStreet;
                    const color = getClusterColor(g.count);
                    return `
                        <button type="button" class="msc-row${active ? ' is-active' : ''}" data-street="${escStreet(g.name)}">
                            <span class="msc-dot" style="background:${color};"></span>
                            <span class="msc-name" title="${escStreet(g.name)}">${escStreet(g.name)}</span>
                            <span class="msc-badge" style="background:${color};">${g.count}</span>
                        </button>`;
                  }).join('')
                : '<div class="msc-empty">No streets with recorded crime.</div>';

            streetsControlEl.innerHTML = header +
                `<div class="msc-body">${body}</div>` +
                (selectedStreet
                    ? '<button type="button" class="msc-clear"><i class="fas fa-xmark mr-1"></i>Clear selection</button>'
                    : '');

            wireStreetsControl(groups);
        }

        function wireStreetsControl(groups) {
            const el = streetsControlEl;
            if (!el) return;

            const toggle = el.querySelector('.msc-toggle');
            if (toggle) {
                toggle.addEventListener('click', () => {
                    streetsPanelOpen = !streetsPanelOpen;
                    renderStreetPanel();
                });
            }

            el.querySelectorAll('.msc-row').forEach(row => {
                row.addEventListener('click', function () {
                    const name = this.dataset.street;
                    const group = groups.find(g => g.name === name);
                    if (selectedStreet === name) {
                        clearStreetFocus();
                    } else {
                        focusStreet(name, group ? group.incidents : []);
                    }
                });
            });

            const clear = el.querySelector('.msc-clear');
            if (clear) clear.addEventListener('click', clearStreetFocus);
        }


        // ------------------------------------------------------------------
        // Crime counts sitting on the barangays themselves, so the map reads
        // as "this barangay, this many crimes" without opening anything.
        // ------------------------------------------------------------------
        let barangayCountLayer = null;

        function renderBarangayCrimeLabels(data) {
            if (barangayCountLayer) {
                map.removeLayer(barangayCountLayer);
                barangayCountLayer = null;
            }
            if (!barangayBoundaryLayer) return;

            // Count per barangay name, then label each polygon that has crimes
            const counts = {};
            (data || []).forEach(incident => {
                const name = (incident.barangay_name || incident.location || '').trim();
                if (!name) return;
                counts[name] = (counts[name] || 0) + 1;
            });

            barangayCountLayer = L.layerGroup();

            Object.entries(barangayLayersByCode).forEach(([code, layer]) => {
                const name = layer._brgyName;
                const count = counts[name];
                if (!name || !count) return;   // only barangays that have crime

                const center = layer.getBounds().getCenter();
                L.marker(center, {
                    interactive: false,
                    icon: L.divIcon({
                        className: '',
                        iconSize: null,
                        // Nudged below the centre so it never lands on top of
                        // the selected barangay's own name label
                        html: `<div style="transform:translate(-50%,10px);white-space:nowrap;
                                background:rgba(255,255,255,0.94);border:1.5px solid #274d4c;
                                border-radius:9999px;padding:2px 9px;font-size:11px;font-weight:800;
                                color:#123332;box-shadow:0 1px 5px rgba(0,0,0,.2);">
                                ${escStreet(name)} · ${count} crime${count === 1 ? '' : 's'}</div>`
                    })
                }).addTo(barangayCountLayer);
            });

            barangayCountLayer.addTo(map);
        }

        // Clear current visualization
        function clearCurrentVisualization() {
            if (streetHeatmap) {
                streetHeatmap.remove();
            }
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
            // Remove cluster zoom handler to prevent stale references
            if (clusterZoomHandler) {
                map.off('zoomend', clusterZoomHandler);
                clusterZoomHandler = null;
            }
        }

        // Update statistics on the right panel (filtered data only)
        function updateStatistics(data) {
            // This function now only handles filtered data statistics
            // Total stats are handled by loadTotalStats() function
            console.log('Filtered data count:', data.length);
        }

        // Update incident list on the right panel (with virtual rendering)
        function updateIncidentList(data, searchQuery = '') {
            console.log('updateIncidentList called with data:', data.length, 'items, searchQuery:', searchQuery);
            const skeletonLoader = document.getElementById('incidentSkeletonLoader');
            const listContent = document.getElementById('incidentListContent');

            if (data.length === 0) {
                skeletonLoader.style.display = 'none';
                listContent.style.display = 'block';
                listContent.innerHTML = '<div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">No crimes found</div>';
                return;
            }

            // Filter data based on search query
            let filteredData = data;
            if (searchQuery.trim()) {
                const query = searchQuery.toLowerCase();
                filteredData = data.filter(incident => {
                    const title = (incident.incident_title || '').toLowerCase();
                    const category = (incident.category_name || '').toLowerCase();
                    return title.includes(query) || category.includes(query);
                });
            }

            if (filteredData.length === 0) {
                skeletonLoader.style.display = 'none';
                listContent.style.display = 'block';
                listContent.innerHTML = '<div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">No matching crimes found</div>';
                return;
            }

            // Store filtered data and reset pagination
            currentListData = filteredData;
            currentListPage = 1;

            // Render first page of results
            renderIncidentPage(searchQuery);

            skeletonLoader.style.display = 'none';
            listContent.style.display = 'block';
        }

        // Helper function to render a page of incidents
        function renderIncidentPage(searchQuery = '') {
            const listContent = document.getElementById('incidentListContent');
            const start = 0;
            const end = currentListPage * MAX_VISIBLE_INCIDENTS;
            const visible = currentListData.slice(start, end);

            let html = '';
            visible.forEach((incident) => {
                // Find original index in currentData
                const originalIndex = currentData.indexOf(incident);
                const isSelected = incident.id === selectedIncidentId;
                const bgColor = isSelected ? '#f0f9f8' : 'white';
                const borderColor = isSelected ? '#274d4c' : '#e5e7eb';

                // Highlight matching text if search query exists
                let highlightedTitle = incident.incident_title || 'Crime';
                let highlightedCategory = incident.category_name || 'Unknown';

                if (searchQuery.trim()) {
                    const query = searchQuery;
                    const regex = new RegExp(`(${query})`, 'gi');
                    highlightedTitle = highlightedTitle.replace(regex, '<span style="background-color: #fef08a; font-weight: 600;">$1</span>');
                    highlightedCategory = highlightedCategory.replace(regex, '<span style="background-color: #fef08a; font-weight: 600;">$1</span>');
                }

                html += `
                    <div class="incident-item" data-id="${incident.id}" style="
                        padding: 12px;
                        border-bottom: 1px solid ${borderColor};
                        background-color: ${bgColor};
                        cursor: pointer;
                        transition: all 0.2s;
                        border-left: 3px solid ${isSelected ? incident.color_code : 'transparent'};
                    " onmouseover="this.style.backgroundColor='#f9fafb'; createPointerMarker(${incident.latitude}, ${incident.longitude}, ${incident.id});" onmouseout="this.style.backgroundColor='${bgColor}'; if(selectedIncidentId !== ${incident.id}) { clearArrowPointer(); }" onclick="zoomToIncident(${originalIndex})">
                        <div style="display: flex; gap: 8px; align-items: flex-start;">
                            <div style="
                                width: 12px;
                                height: 12px;
                                border-radius: 50%;
                                background-color: ${incident.color_code};
                                margin-top: 4px;
                                flex-shrink: 0;
                            "></div>
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="font-size: 12px; font-weight: 600; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    ${highlightedTitle}
                                </div>
                                <div style="font-size: 11px; color: #666; margin-top: 2px;">
                                    ${highlightedCategory}
                                </div>
                                <div style="font-size: 11px; color: #999; margin-top: 2px;">
                                    ${new Date(incident.incident_date).toLocaleDateString()}
                                </div>
                                <div style="font-size: 11px; margin-top: 4px; display: flex; gap: 4px;">
                                    ${(() => {
                                        const workflowStatusInfo = getWorkflowStatusInfo(incident.status);
                                        const clearanceStatusInfo = getClearanceStatusInfo(incident.clearance_status);
                                        return `
                                            <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; background-color: ${workflowStatusInfo.bgColor}; color: ${workflowStatusInfo.color}; font-weight: 600; font-size: 10px;">${workflowStatusInfo.text}</span>
                                            <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; background-color: ${clearanceStatusInfo.bgColor}; color: ${clearanceStatusInfo.color}; font-weight: 600; font-size: 10px;">${clearanceStatusInfo.text}</span>
                                        `;
                                    })()}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            // Add "Show More" button if there are more incidents to display
            if (currentListData.length > end) {
                const remaining = currentListData.length - end;
                html += `
                    <div style="padding: 12px; text-align: center; border-top: 1px solid #e5e7eb;">
                        <button onclick="loadMoreIncidents()" style="
                            padding: 8px 16px;
                            background-color: #274d4c;
                            color: white;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 12px;
                            font-weight: 500;
                            transition: background-color 0.2s;
                        " onmouseover="this.style.backgroundColor='#1a3535'" onmouseout="this.style.backgroundColor='#274d4c'">
                            Show More (${remaining} remaining)
                        </button>
                    </div>
                `;
            }

            listContent.innerHTML = html;
        }

        // Load more incidents
        function loadMoreIncidents() {
            currentListPage++;
            renderIncidentPage(document.getElementById('incidentSearch').value);
        }

        // Show skeleton loader
        function showIncidentSkeleton() {
            document.getElementById('incidentSkeletonLoader').style.display = 'block';
            document.getElementById('incidentListContent').style.display = 'none';
        }

        // Zoom to incident and highlight it
        function zoomToIncident(index) {
            if (index < 0 || index >= currentData.length) return;

            const incident = currentData[index];
            selectedIncidentId = incident.id;

            // Zoom to location with optimal zoom level (17-18) for detail visibility
            // This ensures individual markers are clearly visible even in crowded areas
            const optimalZoom = 17;
            map.setView([incident.latitude, incident.longitude], optimalZoom, {
                animate: true,
                duration: 0.6
            });

            // Create pointer marker and highlight circle to show selected incident
            console.log('zoomToIncident: Creating pointer for incident', incident.id);
            createPointerMarker(incident.latitude, incident.longitude, incident.id);

            // Update incident list to show selection
            updateIncidentList(currentData);

            // Open popup if markers are displayed
            if (currentVisualizationMode === 'markers' || currentVisualizationMode === 'clusters') {
                if (markerLayer) {
                    markerLayer.eachLayer(layer => {
                        if (layer.getLatLng().lat === incident.latitude && layer.getLatLng().lng === incident.longitude) {
                            layer.openPopup();
                        }
                    });
                }
            }
        }

        // Create arrow pointer to selected incident
        function createPointerMarker(lat, lng, incidentId) {
            console.log('createPointerMarker called with:', lat, lng, incidentId, 'current mode:', currentVisualizationMode);

            // Don't show arrow in heatmap mode (no individual markers in heatmap)
            if (currentVisualizationMode === 'heatmap') {
                console.log('Arrow not shown - visualization mode is heatmap');
                return;
            }

            // Remove old pointer if exists
            if (pointerMarker) {
                map.removeLayer(pointerMarker);
                console.log('Removed old pointer marker');
            }

            // Create a custom arrow icon with Font Awesome icon
            const arrowIcon = L.divIcon({
                className: 'incident-pointer-arrow',
                html: `
                    <div class="arrow-bounce" style="
                        width: 40px;
                        height: 50px;
                        background: linear-gradient(135deg, #274d4c 0%, #1a3d3a 100%);
                        clip-path: polygon(50% 0%, 100% 70%, 85% 100%, 50% 85%, 15% 100%, 0% 70%);
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border: 2px solid white;
                        position: relative;
                        opacity: 0.9;
                        transform: rotate(180deg);
                    ">
                        <i class="fas fa-location-dot" style="color: white; font-size: 16px; transform: rotate(180deg);"></i>
                    </div>
                `,
                iconSize: [40, 50],
                iconAnchor: [20, -15],
                popupAnchor: [0, 30]
            });

            // Add animation styles
            if (!document.querySelector('style[data-arrow-animation]')) {
                const arrowStyle = document.createElement('style');
                arrowStyle.setAttribute('data-arrow-animation', 'true');
                arrowStyle.textContent = `
                    .arrow-bounce {
                        animation: arrowBounce 1.2s ease-in-out infinite !important;
                    }
                    @keyframes arrowBounce {
                        0%, 100% { transform: translateY(0) scale(1); }
                        50% { transform: translateY(-15px) scale(1.15); }
                    }
                `;
                document.head.appendChild(arrowStyle);
            }

            pointerMarker = L.marker([lat, lng], { icon: arrowIcon }).addTo(map);
            selectedIncidentCoords = [lat, lng];
            console.log('Pointer marker created successfully');
        }

        // Open incident details modal
        async function openIncidentModal(incidentId) {
            try {
                // Show modal while loading
                const modal = document.getElementById('incidentModal');
                modal.style.display = 'flex';

                // Fetch incident details from API
                const response = await fetch(`/api/crime-incident/${incidentId}`);
                if (!response.ok) {
                    throw new Error('Failed to load crime details');
                }
                const incident = await response.json();

                // Find incident coordinates for pointer
                const incidentData = currentData.find(i => i.id === incidentId);
                console.log('Incident data found:', incidentData);
                if (incidentData) {
                    console.log('Creating pointer for incident:', incidentId, 'at coordinates:', incidentData.latitude, incidentData.longitude);
                    createPointerMarker(incidentData.latitude, incidentData.longitude, incidentId);
                } else {
                    console.warn('Incident data not found for ID:', incidentId);
                }

                // Populate modal with data
                const categoryColor = incident.color_code || '#274d4c';
                const categoryIcon = incident.icon || 'fa-exclamation-circle';

                document.getElementById('modalCategoryBadge').innerHTML = `
                    <span style="display: inline-block; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; color: white; background-color: ${categoryColor};">
                        <i class="fas ${categoryIcon} mr-2"></i>${incident.category_name || 'Unknown'}
                    </span>
                `;

                document.getElementById('modalTitle').textContent = incident.incident_title || 'Crime';
                document.getElementById('modalDate').textContent = incident.incident_date || '—';
                document.getElementById('modalTime').textContent = incident.incident_time || '—';
                document.getElementById('modalLocation').textContent = incident.location || '—';
                document.getElementById('modalAddress').textContent = incident.address || '—';

                // Workflow Status badge
                const workflowStatusInfo = getWorkflowStatusInfo(incident.status);
                document.getElementById('modalStatus').innerHTML = `
                    <span style="display: inline-block; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white; background-color: ${workflowStatusInfo.color};">
                        ${workflowStatusInfo.text.toUpperCase()}
                    </span>
                `;

                // Clearance Status badge
                const clearanceStatusInfo = getClearanceStatusInfo(incident.clearance_status);
                document.getElementById('modalClearanceStatus').innerHTML = `
                    <span style="display: inline-block; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white; background-color: ${clearanceStatusInfo.color};">
                        ${clearanceStatusInfo.text.toUpperCase()}
                    </span>
                `;

                document.getElementById('modalCaseNumber').textContent = incident.case_number || '—';
                document.getElementById('modalDetails').textContent = incident.incident_details || 'No additional details';

            } catch (error) {
                console.error('Error opening incident modal:', error);
                document.getElementById('incidentModal').style.display = 'none';
                alert('Failed to load crime details');
            }
        }

        // Close incident details modal
        function closeIncidentModal() {
            document.getElementById('incidentModal').style.display = 'none';
            // Clear selection and arrow when modal closes
            selectedIncidentId = null;
            clearArrowPointer();
            // Refresh incident list to remove selection highlight
            updateIncidentList(currentData);
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('incidentModal');
            if (event.target === modal) {
                closeIncidentModal();
            }
        });

        // Toggle between incidents panel, heatmap analysis panel, and barangays panel
        function toggleRightPanel(mode) {
            const incidentsPanel = document.getElementById('incidentsPanel');
            const heatmapPanel = document.getElementById('heatmapAnalysisPanel');
            const severityLegend = document.getElementById('severityLegend');

            // Hide all panels first
            incidentsPanel.style.display = 'none';
            heatmapPanel.style.display = 'none';
            severityLegend.style.display = 'none';

            // Show the appropriate panel
            if (mode === 'heatmap') {
                heatmapPanel.style.display = 'flex';
            } else {
                // Cluster view is per street; the streets panel sits above the
                // stats and the crime list stays where it is.
                incidentsPanel.style.display = 'flex';
            }
        }

        // Calculate crime weight based on severity and status
        function calculateCrimeWeight(incident) {
            let weight = 0.5; // Base weight

            // Weight by clearance status
            if (incident.clearance_status === 'uncleared') {
                weight += 0.5; // Uncleared crimes have higher weight
            }

            // Weight by crime category severity (can be expanded with more categories)
            if (incident.crime_category_id) {
                // Higher category IDs typically mean more severe crimes
                weight += (incident.crime_category_id % 5) * 0.1;
            }

            return Math.min(weight * heatmapIntensity, 1.0); // Cap at 1.0
        }

        // Generate weighted heatmap data
        function generateWeightedHeatmapData(data) {
            return data.map(incident => [
                incident.latitude,
                incident.longitude,
                calculateCrimeWeight(incident)
            ]);
        }

        // Calculate area analysis (customizable radius)
        function analyzeArea(lat, lng) {
            const incidents = [];
            let crimeTypeCount = {};
            let statusCount = { cleared: 0, uncleared: 0 };

            // Find all incidents within analysis radius
            currentData.forEach(incident => {
                const distance = L.latLng(lat, lng).distanceTo(L.latLng(incident.latitude, incident.longitude));
                if (distance <= analysisRadius) {
                    incidents.push(incident);

                    // Count by crime type
                    const crimeType = incident.category_name || 'Unknown';
                    crimeTypeCount[crimeType] = (crimeTypeCount[crimeType] || 0) + 1;

                    // Count by status
                    if (incident.clearance_status === 'cleared') {
                        statusCount.cleared++;
                    } else {
                        statusCount.uncleared++;
                    }
                }
            });

            // Display results
            displayAreaAnalysis(incidents, crimeTypeCount, statusCount, lat, lng);
        }

        // Display area analysis results
        function displayAreaAnalysis(incidents, crimeTypeCount, statusCount, lat, lng) {
            const resultsDiv = document.getElementById('areaAnalysisResults');

            // Build crime type breakdown HTML
            let crimeTypeHtml = '';
            Object.entries(crimeTypeCount)
                .sort((a, b) => b[1] - a[1])
                .slice(0, 5)
                .forEach(([type, count]) => {
                    crimeTypeHtml += `
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; padding: 6px; background: #f3f4f6; border-radius: 4px;">
                            <span style="font-size: 12px; color: #333;">${type}</span>
                            <span style="font-size: 12px; font-weight: 600; color: #274d4c;">${count}</span>
                        </div>
                    `;
                });

            resultsDiv.innerHTML = `
                <div>
                    <div style="text-align: center; margin-bottom: 16px;">
                        <h4 style="font-size: 13px; font-weight: 700; color: #111; margin: 0 0 4px;">${analysisRadius}m Radius Analysis</h4>
                        <p style="font-size: 11px; color: #666; margin: 0;">Latitude: ${lat.toFixed(6)}<br>Longitude: ${lng.toFixed(6)}</p>
                    </div>

                    <div style="margin-bottom: 14px; padding: 12px; background: linear-gradient(135deg, #274d4c 0%, #3a6b69 100%); border-radius: 6px; color: white;">
                        <div style="font-size: 11px; opacity: 0.9;">Total Crimes</div>
                        <div style="font-size: 24px; font-weight: bold;">${incidents.length}</div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                        <div style="padding: 10px; background: #d4edda; border-radius: 6px; border-left: 3px solid #28a745;">
                            <div style="font-size: 10px; font-weight: 700; color: #155724; text-transform: uppercase;">Cleared</div>
                            <div style="font-size: 18px; font-weight: bold; color: #155724;">${statusCount.cleared}</div>
                        </div>
                        <div style="padding: 10px; background: #f8d7da; border-radius: 6px; border-left: 3px solid #dc3545;">
                            <div style="font-size: 10px; font-weight: 700; color: #721c24; text-transform: uppercase;">Uncleared</div>
                            <div style="font-size: 18px; font-weight: bold; color: #721c24;">${statusCount.uncleared}</div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid #e5e7eb; padding-top: 12px;">
                        <h5 style="font-size: 11px; font-weight: 700; color: #111; margin: 0 0 8px; text-transform: uppercase;">Top Crime Types</h5>
                        ${crimeTypeHtml || '<p style="font-size: 11px; color: #999;">No crimes in this area</p>'}
                    </div>
                </div>
            `;

            // Add analysis circle to map
            if (analysisCircle) map.removeLayer(analysisCircle);
            if (analysisMarker) map.removeLayer(analysisMarker);

            analysisCircle = L.circle([lat, lng], {
                radius: analysisRadius,
                color: '#274d4c',
                weight: 2,
                opacity: 0.7,
                fill: true,
                fillColor: '#274d4c',
                fillOpacity: 0.1
            }).addTo(map);

            analysisMarker = L.marker([lat, lng], {
                icon: L.icon({
                    iconUrl: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSI4IiBmaWxsPSIjMjc0ZDRjIi8+PC9zdmc+',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                })
            }).addTo(map);
        }

        // Clear analysis circle and marker
        function clearAreaAnalysis() {
            if (analysisCircle) {
                map.removeLayer(analysisCircle);
                analysisCircle = null;
            }
            if (analysisMarker) {
                map.removeLayer(analysisMarker);
                analysisMarker = null;
            }
        }

        // Handle map click for heatmap area analysis
        function setupHeatmapClickHandler() {
            // Remove old handler if exists
            map.off('click');

            // Add new handler
            map.on('click', function(e) {
                if (currentVisualizationMode === 'heatmap') {
                    analyzeArea(e.latlng.lat, e.latlng.lng);
                }
            });
        }

        // Adjust heatmap radius based on zoom level (dynamic scaling)
        function setupZoomScaling() {
            // Resize the incident dots in place — cheaper than rebuilding the layer
            map.on('zoomend', function() {
                if (markerLayer) {
                    const radius = markerRadiusForZoom();
                    const weight = markerWeightForZoom();
                    markerLayer.eachLayer(layer => {
                        if (layer.setRadius) layer.setRadius(radius);
                        if (layer.setStyle) layer.setStyle({ weight });
                    });
                }
            });

            map.on('zoomend', function() {
                if (currentVisualizationMode === 'heatmap' && heatmapLayer) {
                    const zoom = map.getZoom();
                    // Smaller radius at higher zoom levels for better detail
                    let scaledRadius = heatmapRadius;
                    if (zoom >= 18) {
                        scaledRadius = heatmapRadius * 0.6;
                    } else if (zoom >= 16) {
                        scaledRadius = heatmapRadius * 0.8;
                    }
                    // Refresh heatmap with scaled radius
                    displayHeatmap(currentData);
                }
            });
        }

        // Display heatmap with weighted intensity and dynamic radius/blur
        // Street-Segment Heatmap: every road segment coloured by the crimes
        // recorded along it. This is the default view of the map.
        function displayStreetHeatmap(data) {
            if (typeof StreetSegmentHeatmap === 'undefined') {
                console.warn('StreetSegmentHeatmap not loaded, falling back to markers');
                displayMarkers(data);
                return;
            }
            if (!streetHeatmap) {
                streetHeatmap = StreetSegmentHeatmap.create(map, {
                    onSegmentClick: function (seg, e) {
                        const cats = seg.categories.slice(0, 4).map(c =>
                            '<div style="display:flex;justify-content:space-between;gap:12px;font-size:11px;color:#374151"><span>' + escStreet(c.category) + '</span><b>' + c.count + '</b></div>'
                        ).join('');
                        L.popup({ maxWidth: 260 })
                            .setLatLng(e.latlng)
                            .setContent(
                                '<div style="font-weight:800;font-size:13px;color:#111827">' + escStreet(seg.name) + '</div>' +
                                '<div style="font-size:11.5px;color:#6b7280;margin:2px 0 6px">' + seg.count + ' crime' + (seg.count === 1 ? '' : 's') + ' on this street segment</div>' +
                                (cats || '<div style="font-size:11px;color:#9ca3af">No crimes recorded here</div>')
                            )
                            .openOn(map);
                    }
                });
            }
            streetHeatmap.setVisible(true);
            streetHeatmap.update(data).then(function (stats) {
                updateStreetHeatmapSummary(stats);
            }).catch(function (err) {
                console.error('Street-segment heatmap failed:', err);
                displayMarkers(data);
            });
        }

        function updateStreetHeatmapSummary(stats) {
            const box = document.getElementById('streetHeatSummary');
            if (!box || !stats) return;
            const top = (stats.top || []).slice(0, 5).map(function (t, i) {
                const ratio = stats.max > 0 ? Math.min(1, t.count / (stats.top[0] ? stats.top[0].count : 1)) : 0;
                return '<div style="display:flex;align-items:center;gap:8px;font-size:11px;color:#374151;margin-top:5px">' +
                    '<span style="width:14px;color:#9ca3af;font-weight:700">' + (i + 1) + '</span>' +
                    '<span style="flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escStreet(t.name) + '</span>' +
                    '<span style="width:70px;height:6px;border-radius:3px;background:#f3f4f6;overflow:hidden"><span style="display:block;height:100%;width:' + Math.round(ratio * 100) + '%;background:' + streetHeatmap.colorFor(ratio) + '"></span></span>' +
                    '<b style="width:22px;text-align:right">' + t.count + '</b></div>';
            }).join('');
            box.innerHTML =
                '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:6px">' +
                    '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:6px"><div style="font-size:9.5px;font-weight:800;color:#6b7280;text-transform:uppercase">Segments hit</div><div style="font-size:16px;font-weight:800;color:#111827">' + stats.segments + '</div></div>' +
                    '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:6px"><div style="font-size:9.5px;font-weight:800;color:#6b7280;text-transform:uppercase">Hottest</div><div style="font-size:16px;font-weight:800;color:#c0392b">' + stats.max + '</div></div>' +
                    '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:6px"><div style="font-size:9.5px;font-weight:800;color:#6b7280;text-transform:uppercase">Off-street</div><div style="font-size:16px;font-weight:800;color:#111827">' + stats.unmatched + '</div></div>' +
                '</div>' +
                (top ? '<div style="font-size:10px;font-weight:800;color:#6b7280;text-transform:uppercase;margin-top:6px">Hottest streets</div>' + top : '');
        }

        function displayHeatmap(data) {
            if (typeof L.heatLayer !== 'function') {
                setTimeout(() => {
                    if (typeof L.heatLayer === 'function') {
                        displayHeatmap(data);
                    } else {
                        displayMarkers(data);
                    }
                }, 500);
                return;
            }

            // Generate weighted heatmap points
            const heatmapPoints = generateWeightedHeatmapData(data);

            if (heatmapPoints.length > 0) {
                // Remove old heatmap layer
                if (heatmapLayer) {
                    map.removeLayer(heatmapLayer);
                }

                // Create new heatmap with current settings
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

                // Setup click handler for heatmap
                setupHeatmapClickHandler();
            }
        }

        // Incident dots shrink as you zoom out so a dense barangay does not turn into
        // one solid blob of overlapping circles.
        function markerRadiusForZoom() {
            const z = map.getZoom();
            if (z <= 12) return 2.5;
            if (z <= 13) return 3;
            if (z <= 14) return 4;
            if (z <= 15) return 5;
            if (z <= 16) return 6;
            return 7;
        }

        function markerWeightForZoom() {
            return map.getZoom() <= 13 ? 1 : 2;
        }

        // Display individual markers
        function displayMarkers(data) {
            markerLayer = L.featureGroup();

            data.forEach(incident => {
                if (qcBounds && !qcBounds.contains([incident.latitude, incident.longitude])) {
                    return;
                }

                // Use color_code from database
                const markerColor = incident.color_code || '#274d4c';

                const marker = L.circleMarker([incident.latitude, incident.longitude], {
                    radius: markerRadiusForZoom(),
                    fillColor: markerColor,
                    color: '#ffffff',
                    weight: markerWeightForZoom(),
                    opacity: 0.95,
                    fillOpacity: 0.92
                });

                // Street and time up front, so hovering a dot already answers
                // "what happened here" without opening anything
                marker.bindTooltip(
                    '<span style="font-weight:700;">' + escStreet(incident.category_name || 'Crime') + '</span>' +
                    (incident.street ? '<br>' + escStreet(incident.street) : '') +
                    '<br><span style="opacity:.8;">' + escStreet(incident.incident_date || '') +
                    (incident.incident_time ? ' · ' + escStreet(incident.incident_time) : '') + '</span>',
                    { direction: 'top', opacity: 0.95 }
                );

                // Open modal on click
                marker.on('click', function() {
                    openIncidentModal(incident.id);
                });

                marker.addTo(markerLayer);
            });

            markerLayer.addTo(map);
        }

        // Get severity level based on crime category (can be customized)
        function getSeverityLevel(incident) {
            // This is a simple example - you can customize based on actual crime categories
            const categoryId = incident.crime_category_id || 0;
            if (categoryId >= 5) return 'serious'; // Red
            if (categoryId >= 3) return 'moderate'; // Orange
            return 'minor'; // Green
        }

        // Get severity icon
        function getSeverityIcon(severity) {
            const colors = {
                'serious': '#dc2626',
                'moderate': '#f97316',
                'minor': '#16a34a'
            };
            return colors[severity] || '#274d4c';
        }

        // Calculate cluster statistics
        function calculateClusterStats(incidents) {
            let crimeTypes = {};
            let statusCount = { 'cleared': 0, 'uncleared': 0 };

            incidents.forEach(i => {
                crimeTypes[i.category_name || 'Unknown'] = (crimeTypes[i.category_name || 'Unknown'] || 0) + 1;
                statusCount[i.clearance_status || 'uncleared']++;
            });

            // Most common crime
            let mostCommon = 'Unknown';
            let maxCount = 0;
            Object.entries(crimeTypes).forEach(([crime, count]) => {
                if (count > maxCount) {
                    maxCount = count;
                    mostCommon = crime;
                }
            });

            return {
                total: incidents.length,
                mostCommon: mostCommon,
                crimeTypes: crimeTypes,
                cleared: statusCount.cleared,
                uncleared: statusCount.uncleared
            };
        }

        // Get dynamic cluster color based on incident count
        function getClusterColor(count) {
            if (count >= 31) return '#dc2626'; // Red
            if (count >= 11) return '#eab308'; // Yellow
            return '#16a34a'; // Green
        }

        // ------------------------------------------------------------------
        // Cluster view - grouped BY STREET. Every incident belongs to one
        // barangay, so the street is the level at which clustering says
        // anything. Selecting a cluster highlights that street on the map
        // itself and zooms to it.
        // ------------------------------------------------------------------
        function groupIncidentsByStreet(data) {
            const groups = {};

            data.forEach(incident => {
                if (qcBounds && !qcBounds.contains([incident.latitude, incident.longitude])) return;

                const street = incident.street || 'Unnamed location';
                if (!groups[street]) {
                    groups[street] = { name: street, incidents: [], totalLat: 0, totalLng: 0 };
                }

                groups[street].incidents.push(incident);
                groups[street].totalLat += parseFloat(incident.latitude);
                groups[street].totalLng += parseFloat(incident.longitude);
            });

            return Object.values(groups)
                .map(g => Object.assign(g, {
                    count: g.incidents.length,
                    centerLat: g.totalLat / g.incidents.length,
                    centerLng: g.totalLng / g.incidents.length,
                    stats: calculateClusterStats(g.incidents)
                }))
                .sort((a, b) => b.count - a.count);
        }

        // Frame a street's crimes and mark them out on the map. With no street
        // lines drawn, the highlight is a ring around the crimes themselves.
        let streetFocusRing = null;

        function focusStreet(name, incidents) {
            selectedStreet = name;
            incidents = incidents && incidents.length
                ? incidents
                : (currentData || []).filter(i => (i.street || 'Unnamed location') === name);

            if (streetFocusRing) {
                map.removeLayer(streetFocusRing);
                streetFocusRing = null;
            }

            if (incidents.length) {
                streetFocusRing = L.layerGroup(
                    incidents.map(i => L.circleMarker([i.latitude, i.longitude], {
                        radius: 13,
                        color: '#111827',
                        weight: 2,
                        opacity: 0.9,
                        fillColor: '#facc15',
                        fillOpacity: 0.25,
                        interactive: false
                    }))
                ).addTo(map);

                map.fitBounds(
                    L.latLngBounds(incidents.map(i => [i.latitude, i.longitude])).pad(0.6),
                    { maxZoom: 18 }
                );

                showClusterIncidents(incidents, name);
            }

            renderStreetPanel();
        }

        function clearStreetFocus() {
            selectedStreet = null;

            if (streetFocusRing) {
                map.removeLayer(streetFocusRing);
                streetFocusRing = null;
            }

            renderStreetPanel();
            updateIncidentList(currentData);
        }

        function displayClusters(data) {
            markerLayer = L.featureGroup();
            const groups = groupIncidentsByStreet(data);

            groups.forEach(group => {
                const count = group.count;
                const stats = group.stats;
                const clusterColor = getClusterColor(count);

                const clusterIcon = L.divIcon({
                    className: 'cluster-marker',
                    html: `
                        <div style="
                            min-width: 44px;
                            padding: 4px 8px;
                            background: linear-gradient(135deg, ${clusterColor} 0%, ${clusterColor}dd 100%);
                            border-radius: 9999px;
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            justify-content: center;
                            color: white;
                            font-weight: bold;
                            border: 2px solid white;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
                            cursor: pointer;
                            white-space: nowrap;
                        ">
                            <span style="font-size: 14px; line-height: 1;">${count}</span>
                            <span style="font-size: 8.5px; font-weight: 700; opacity: .95; line-height: 1.3;">${escStreet(group.name)}</span>
                        </div>
                    `,
                    iconSize: null,
                    iconAnchor: [22, 20],
                    popupAnchor: [0, -20]
                });

                const clusterMarker = L.marker([group.centerLat, group.centerLng], { icon: clusterIcon });

                const typeRows = Object.entries(stats.crimeTypes)
                    .sort((a, b) => b[1] - a[1])
                    .slice(0, 5)
                    .map(([type, n]) => `
                        <div style="display:flex;justify-content:space-between;font-size:11.5px;padding:2px 0;">
                            <span style="color:#4b5563;">${escStreet(type)}</span>
                            <span style="font-weight:700;color:#111;">${n}</span>
                        </div>`).join('');

                clusterMarker.bindPopup(`
                    <div style="min-width: 250px; font-family: Arial, sans-serif;">
                        <div style="border-bottom: 2px solid ${clusterColor}; padding-bottom: 8px; margin-bottom: 8px;">
                            <h3 style="margin: 0 0 2px; color: #111; font-size: 14px; font-weight: bold;">${escStreet(group.name)}</h3>
                            <div style="font-size: 11px; color: #666;">${escStreet(group.incidents[0].barangay_name || 'San Agustin')} · street cluster</div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-bottom: 10px;">
                            <div style="background:#f3f4f6;padding:6px;border-radius:4px;text-align:center;">
                                <div style="font-size:10px;color:#666;font-weight:600;">Total</div>
                                <div style="font-size:15px;font-weight:bold;color:${clusterColor};">${stats.total}</div>
                            </div>
                            <div style="background:#f3f4f6;padding:6px;border-radius:4px;text-align:center;">
                                <div style="font-size:10px;color:#666;font-weight:600;">Cleared</div>
                                <div style="font-size:15px;font-weight:bold;color:#16a34a;">${stats.cleared}</div>
                            </div>
                            <div style="background:#f3f4f6;padding:6px;border-radius:4px;text-align:center;">
                                <div style="font-size:10px;color:#666;font-weight:600;">Open</div>
                                <div style="font-size:15px;font-weight:bold;color:#dc2626;">${stats.uncleared}</div>
                            </div>
                        </div>

                        <div style="margin-bottom:10px;">
                            <div style="font-size:10px;font-weight:800;color:#6b7280;text-transform:uppercase;margin-bottom:2px;">Crimes on this street</div>
                            ${typeRows}
                        </div>

                        <button class="cluster-focus" style="width:100%;padding:8px;background:#274d4c;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">
                            <i class="fas fa-location-crosshairs mr-1"></i>Highlight this street
                        </button>
                    </div>
                `, { maxWidth: 300 });

                clusterMarker.on('popupopen', function () {
                    setTimeout(() => {
                        const popup = this.getPopup();
                        const btn = popup && popup._contentNode
                            ? popup._contentNode.querySelector('.cluster-focus') : null;
                        if (btn) {
                            btn.addEventListener('click', e => {
                                e.stopPropagation();
                                clusterMarker.closePopup();
                                focusStreet(group.name, group.incidents);
                            });
                        }
                    }, 80);
                });

                // Clicking the bubble itself highlights the street too
                clusterMarker.on('click', () => focusStreet(group.name, group.incidents));

                clusterMarker.addTo(markerLayer);

                // The individual crimes behind the cluster, revealed on zoom-in
                group.incidents.forEach(incident => {
                    const severity = getSeverityLevel(incident);
                    const severityColor = getSeverityIcon(severity);

                    const dot = L.circleMarker([incident.latitude, incident.longitude], {
                        radius: 6,
                        fillColor: severityColor,
                        color: severityColor,
                        weight: 2,
                        opacity: 0.8,
                        fillOpacity: 0.8
                    });

                    dot.bindPopup(`
                        <div style="min-width: 220px; font-family: Arial, sans-serif;">
                            <div style="font-weight:bold;color:#111;margin-bottom:6px;font-size:12px;">${escStreet(incident.incident_title)}</div>
                            <div style="font-size:11px;color:#666;margin-bottom:4px;">
                                <i class="fas fa-flag" style="color:${severityColor};margin-right:4px;"></i>
                                <span style="text-transform:capitalize;">${severity}</span>
                            </div>
                            <div style="font-size:11px;color:#666;margin-bottom:4px;">📅 ${escStreet(incident.incident_date)}${incident.incident_time ? ' · ' + escStreet(incident.incident_time) : ''}</div>
                            <div style="font-size:11px;color:#666;margin-bottom:8px;">${escStreet(incident.category_name)}</div>
                            <button style="width:100%;padding:6px;background:#274d4c;color:white;border:none;border-radius:3px;cursor:pointer;font-size:11px;font-weight:600;">View Details</button>
                        </div>
                    `);

                    dot.on('popupopen', function () {
                        setTimeout(() => {
                            const popup = this.getPopup();
                            const button = popup && popup._contentNode
                                ? popup._contentNode.querySelector('button') : null;
                            if (button) {
                                button.onclick = e => {
                                    e.stopPropagation();
                                    openIncidentModal(incident.id);
                                    dot.closePopup();
                                };
                            }
                        }, 80);
                    });

                    dot.addTo(markerLayer);
                });
            });

            markerLayer.addTo(map);

            // Cluster bubbles when zoomed out, individual crimes when zoomed in
            if (clusterZoomHandler) map.off('zoomend', clusterZoomHandler);

            const applyClusterZoom = () => {
                if (!markerLayer) return;
                const zoomedIn = map.getZoom() >= 17;

                markerLayer.eachLayer(layer => {
                    if (layer instanceof L.Marker && layer.options.icon.options.className === 'cluster-marker') {
                        layer.setOpacity(zoomedIn ? 0.15 : 1);
                    } else if (layer instanceof L.CircleMarker) {
                        layer.setStyle(zoomedIn
                            ? { fillOpacity: 0.8, opacity: 0.8 }
                            : { fillOpacity: 0, opacity: 0 });
                    }
                });
            };

            clusterZoomHandler = applyClusterZoom;
            map.on('zoomend', clusterZoomHandler);
            applyClusterZoom();
        }

        // Auto-filter on dropdown change
        function setupAutoFilter() {
            const filterElements = [
                'visualizationMode',
                'timePeriod',
                'crimeType',
                 'caseStatus',
                 'clearanceStatus',
                 'barangay',
                 'street'
            ];

            filterElements.forEach(elementId => {
                document.getElementById(elementId).addEventListener('change', function() {
                    // Redraw the boundary immediately; only the data fetch is debounced
                    if (elementId === 'barangay') applyBarangaySelection();

                    if (filterTimeout) {
                        clearTimeout(filterTimeout);
                    }
                    filterTimeout = setTimeout(() => {
                        loadCrimeData();
                    }, 500);
                });
            });
        }

        // Reset filters
        document.getElementById('resetFilterBtn').addEventListener('click', function() {
            document.getElementById('visualizationMode').value = 'markers';
            document.getElementById('timePeriod').value = 'all';
            document.getElementById('crimeType').value = '';
            document.getElementById('caseStatus').value = '';
            document.getElementById('clearanceStatus').value = '';
            document.getElementById('barangay').value = '';
            document.getElementById('street').value = '';
            document.getElementById('incidentSearch').value = '';
            applyBarangaySelection();   // zooms back out to the whole city
            loadCrimeData();
        });

        // ---- Enlarge the map, carrying the filters with it ----
        // ------------------------------------------------------------------
        // Fullscreen: ONLY the map. The browser's Fullscreen API puts the map
        // container on the actual screen, so nothing from the page comes with
        // it. Filters and panels stay where they are and come back untouched.
        // ------------------------------------------------------------------
        let mapIsFullscreen = false;

        function fullscreenElement() {
            return document.fullscreenElement || document.webkitFullscreenElement || null;
        }

        function syncFullscreenChrome() {
            const container = document.getElementById('mapContainer');
            const icon = document.querySelector('#mapFullscreenBtn i');
            const label = document.querySelector('#mapFullscreenBtn span');

            mapIsFullscreen = fullscreenElement() === container || container.classList.contains('map-fullscreen');

            if (icon) icon.className = mapIsFullscreen ? 'fas fa-compress' : 'fas fa-expand';
            if (label) label.textContent = mapIsFullscreen ? 'Exit Fullscreen' : 'Fullscreen';

            // Leaflet has to re-measure once the container has its new size
            setTimeout(() => {
                map.invalidateSize();

                const code = document.getElementById('barangay').value;
                const layer = code ? barangayLayersByCode[code] : null;

                if (layer) {
                    zoomToBarangayBounds(layer.getBounds(), false);
                } else if (qcBounds && qcBounds.isValid()) {
                    map.fitBounds(qcBounds, { padding: [20, 20], animate: false });
                }
            }, 150);
        }

        function setMapFullscreen(on) {
            const container = document.getElementById('mapContainer');
            const request = container.requestFullscreen || container.webkitRequestFullscreen;
            const exit = document.exitFullscreen || document.webkitExitFullscreen;

            if (on) {
                if (request) {
                    request.call(container).catch(err => {
                        // Blocked (permissions policy, iframe...) - fall back to
                        // a fixed overlay so the button still does something.
                        console.warn('Fullscreen request refused, using overlay:', err);
                        container.classList.add('map-fullscreen');
                        document.body.style.overflow = 'hidden';
                        syncFullscreenChrome();
                    });
                } else {
                    container.classList.add('map-fullscreen');
                    document.body.style.overflow = 'hidden';
                    syncFullscreenChrome();
                }
            } else {
                if (fullscreenElement() && exit) {
                    exit.call(document);
                } else {
                    container.classList.remove('map-fullscreen');
                    document.body.style.overflow = '';
                    syncFullscreenChrome();
                }
            }
        }

        ['fullscreenchange', 'webkitfullscreenchange'].forEach(evt =>
            document.addEventListener(evt, syncFullscreenChrome));

        document.getElementById('mapFullscreenBtn').addEventListener('click', () => setMapFullscreen(!mapIsFullscreen));
        document.getElementById('exitFullscreenBtn').addEventListener('click', () => setMapFullscreen(false));
        document.addEventListener('keydown', e => {
            // The browser handles Escape in real fullscreen; this covers the fallback
            if (e.key === 'Escape' && mapIsFullscreen && !fullscreenElement()) setMapFullscreen(false);
        });

        // Search incident functionality (with debounce to prevent lag on every keystroke)
        try {
            let searchInputElement = document.getElementById('incidentSearch');
            if (searchInputElement) {
                searchInputElement.addEventListener('input', function() {
                    // Clear previous timeout
                    if (searchTimeout) {
                        clearTimeout(searchTimeout);
                    }

                    // Set new timeout for debounced search (300ms)
                    const searchQuery = this.value;
                    searchTimeout = setTimeout(() => {
                        updateIncidentList(currentData, searchQuery);
                    }, 300);
                });
            }
        } catch(e) {
            console.warn('Search input setup failed:', e);
        }

        // Heatmap control sliders
        document.getElementById('heatmapRadius').addEventListener('input', function(e) {
            heatmapRadius = parseInt(e.target.value);
            document.getElementById('radiusValue').textContent = heatmapRadius;
            // Refresh heatmap
            if (currentVisualizationMode === 'heatmap') {
                displayHeatmap(currentData);
            }
        });

        document.getElementById('heatmapBlur').addEventListener('input', function(e) {
            heatmapBlur = parseInt(e.target.value);
            document.getElementById('blurValue').textContent = heatmapBlur;
            // Refresh heatmap
            if (currentVisualizationMode === 'heatmap') {
                displayHeatmap(currentData);
            }
        });

        document.getElementById('heatmapIntensity').addEventListener('input', function(e) {
            heatmapIntensity = parseFloat(e.target.value);
            document.getElementById('intensityValue').textContent = heatmapIntensity.toFixed(1);
            // Refresh heatmap
            if (currentVisualizationMode === 'heatmap') {
                displayHeatmap(currentData);
            }
        });

        // Analysis radius slider
        document.getElementById('analysisRadiusSlider').addEventListener('input', function(e) {
            analysisRadius = parseInt(e.target.value);
            document.getElementById('analysisRadiusValue').textContent = analysisRadius;

            // Update the instruction message display
            const display = document.getElementById('analysisRadiusDisplay');
            if (display) {
                display.textContent = analysisRadius;
            }

            // Update the analysis results heading if it exists
            const resultsDiv = document.getElementById('areaAnalysisResults');
            if (resultsDiv) {
                const heading = resultsDiv.querySelector('h4');
                if (heading) {
                    heading.textContent = analysisRadius + 'm Radius Analysis';
                }
            }

            // If analysis circle exists, update its radius in real-time
            if (analysisCircle) {
                analysisCircle.setRadius(analysisRadius);
            }
        });

        // Reset heatmap controls
        document.getElementById('heatmapResetBtn').addEventListener('click', function() {
            heatmapRadius = 40;
            heatmapBlur = 20;
            heatmapIntensity = 1;
            analysisRadius = 500;

            document.getElementById('heatmapRadius').value = 40;
            document.getElementById('heatmapBlur').value = 20;
            document.getElementById('heatmapIntensity').value = 1;
            document.getElementById('analysisRadiusSlider').value = 500;

            document.getElementById('radiusValue').textContent = '40';
            document.getElementById('blurValue').textContent = '20';
            document.getElementById('intensityValue').textContent = '1.0';
            document.getElementById('analysisRadiusValue').textContent = '500';

            // Refresh heatmap and clear analysis
            if (currentVisualizationMode === 'heatmap') {
                clearAreaAnalysis();
                const display = document.getElementById('analysisRadiusDisplay');
                if (display) {
                    display.textContent = analysisRadius;
                }
                document.getElementById('areaAnalysisResults').innerHTML = '<div style="text-align: center; padding: 40px 20px; color: #999; font-size: 12px;"><i class="fas fa-info-circle mr-2"></i>Click on the heatmap to analyze a <span id="analysisRadiusDisplay">' + analysisRadius + '</span>m area</div>';
                displayHeatmap(currentData);
            }
        });

        // Populate barangays list with incident counts
        // Show cluster incidents in left panel (drill-down mode)
        function showClusterIncidents(incidents, clusterName) {
            // Switch to incidents panel
            toggleRightPanel('incidents');

            // Show severity legend
            document.getElementById('severityLegend').style.display = 'block';

            // Create incident list HTML
            let incidentListHtml = '<div style="padding: 0; width: 100%; box-sizing: border-box;">';

            incidents.forEach(incident => {
                const severity = getSeverityLevel(incident);
                const severityColor = getSeverityIcon(severity);
                const workflowStatusInfo = getWorkflowStatusInfo(incident.status);
                const clearanceStatusInfo = getClearanceStatusInfo(incident.clearance_status);

                incidentListHtml += `
                    <div class="cluster-incident-item" data-incident-id="${incident.id}" style="
                        padding: 12px;
                        border-bottom: 1px solid #e5e7eb;
                        cursor: pointer;
                        transition: all 0.2s;
                        background: #f9fafb;
                        margin-bottom: 4px;
                        border-radius: 6px;
                        border-left: 4px solid ${severityColor};
                        width: 100%;
                        box-sizing: border-box;
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px; gap: 8px;">
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 12px; font-weight: 600; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    ${incident.incident_title}
                                </div>
                            </div>
                            <div style="display: flex; gap: 4px;">
                                <span style="
                                    display: inline-block;
                                    background: ${workflowStatusInfo.bgColor};
                                    color: ${workflowStatusInfo.color};
                                    padding: 2px 6px;
                                    border-radius: 3px;
                                    font-size: 10px;
                                    font-weight: 600;
                                    white-space: nowrap;
                                ">${workflowStatusInfo.text}</span>
                                <span style="
                                    display: inline-block;
                                    background: ${clearanceStatusInfo.bgColor};
                                    color: ${clearanceStatusInfo.color};
                                    padding: 2px 6px;
                                    border-radius: 3px;
                                    font-size: 10px;
                                    font-weight: 600;
                                    white-space: nowrap;
                                ">${clearanceStatusInfo.text}</span>
                            </div>
                        </div>

                        <div style="display: flex; gap: 12px; font-size: 11px; color: #666; margin-bottom: 6px;">
                            <span>
                                <i class="fas fa-flag" style="color: ${severityColor}; margin-right: 3px;"></i>
                                <span style="text-transform: capitalize;">${severity}</span>
                            </span>
                            <span>📅 ${incident.incident_date}</span>
                        </div>

                        <div style="font-size: 11px; color: #555; margin-bottom: 8px; padding: 6px; background: white; border-radius: 4px;">
                            ${incident.category_name}
                        </div>

                        <button style="
                            width: 100%;
                            padding: 6px;
                            background: #274d4c;
                            color: white;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 11px;
                            font-weight: 600;
                            transition: all 0.2s;
                        " onmouseover="this.style.background='#1a3d3a'" onmouseout="this.style.background='#274d4c'">
                            <i class="fas fa-external-link-alt mr-1"></i>View Details
                        </button>
                    </div>
                `;
            });

            incidentListHtml += '</div>';

            // Update incident list content
            const incidentListContent = document.getElementById('incidentListContent');
            incidentListContent.innerHTML = incidentListHtml;
            incidentListContent.style.display = 'block';
            document.getElementById('incidentSkeletonLoader').style.display = 'none';

            // Add click handlers to incident items
            document.querySelectorAll('.cluster-incident-item').forEach(item => {
                item.addEventListener('click', function() {
                    const incidentId = this.getAttribute('data-incident-id');
                    openIncidentModal(incidentId);
                });

                // Hover effect
                item.addEventListener('mouseover', function() {
                    this.style.background = '#e8f5f3';
                });

                item.addEventListener('mouseout', function() {
                    this.style.background = '#f9fafb';
                });
            });

            // Update header to show cluster name with reset button
            const incidentsPanel = document.getElementById('incidentsPanel');
            const headerDiv = incidentsPanel.querySelector('div:first-child');
            if (headerDiv) {
                headerDiv.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0 0 10px;">
                            <i class="fas fa-building mr-2" style="color: #274d4c;"></i>Crimes in ${clusterName}
                        </h3>
                        <button id="resetClusterView" style="
                            padding: 6px 10px;
                            background: #e5e7eb;
                            color: #111;
                            border: 1px solid #d1d5db;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 11px;
                            font-weight: 600;
                            transition: all 0.2s;
                        ">
                            <i class="fas fa-arrow-left mr-1"></i>Back
                        </button>
                    </div>
                    <input type="text" id="incidentSearch" placeholder="Search crimes..." style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; box-sizing: border-box;">
                `;

                // Add reset button handler
                const resetBtn = headerDiv.querySelector('#resetClusterView');
                if (resetBtn) {
                    resetBtn.addEventListener('click', function() {
                        document.getElementById('severityLegend').style.display = 'none';
                        clearStreetFocus();
                    });

                    resetBtn.addEventListener('mouseover', function() {
                        this.style.background = '#d1d5db';
                    });

                    resetBtn.addEventListener('mouseout', function() {
                        this.style.background = '#e5e7eb';
                    });
                }
            }
        }

        // Clear arrow pointer when changing visualization mode
        function clearArrowPointer() {
            if (pointerMarker) {
                map.removeLayer(pointerMarker);
                pointerMarker = null;
                selectedIncidentCoords = null;
            }
        }

        // The intensity scale is shared by both heat views; this swaps its
        // explanatory copy and the per-street summary between them.
        function setStreetHeatLegend(isStreet) {
            const note = document.getElementById('streetHeatNote');
            const heatNote = document.getElementById('heatBlobNote');
            const summary = document.getElementById('streetHeatSummary');
            if (note) note.style.display = isStreet ? 'block' : 'none';
            if (heatNote) heatNote.style.display = isStreet ? 'none' : 'block';
            if (summary) summary.style.display = isStreet ? 'block' : 'none';
        }

        // The map opens in Individual Markers unless the user saved another default
        (function initialiseViewModeUi() {
            const mode = document.getElementById('visualizationMode').value;
            const scale = document.getElementById('crimeIntensityScale');
            if (mode === 'street-heatmap' || mode === 'heatmap') {
                scale.classList.remove('hidden');
                scale.style.display = 'block';
            }
            setStreetHeatLegend(mode === 'street-heatmap');
            if (mode === 'heatmap' && typeof toggleRightPanel === 'function') toggleRightPanel('heatmap');
        })();

        // Update visualization mode and toggle right panel
        document.getElementById('visualizationMode').addEventListener('change', function() {
            const newMode = this.value;
            const crimeIntensityScale = document.getElementById('crimeIntensityScale');

            // Clear arrow pointer when changing views
            clearArrowPointer();

            // Toggle right panel based on mode
            if (newMode === 'street-heatmap') {
                toggleRightPanel('incidents');
                clearAreaAnalysis();
                // The intensity scale doubles as the street-segment legend
                crimeIntensityScale.classList.remove('hidden');
                crimeIntensityScale.style.display = 'block';
                setStreetHeatLegend(true);
            } else if (newMode === 'heatmap') {
                toggleRightPanel('heatmap');
                clearAreaAnalysis();
                // Show Crime Intensity Scale in heatmap mode
                crimeIntensityScale.classList.remove('hidden');
                crimeIntensityScale.style.display = 'block';
                setStreetHeatLegend(false);
            } else if (newMode === 'clusters') {
                toggleRightPanel('clusters');
                clearAreaAnalysis();
                // Show Severity Legend in cluster mode
                document.getElementById('severityLegend').style.display = 'block';
                // Hide Crime Intensity Scale in cluster mode
                crimeIntensityScale.style.display = 'none';
            } else {
                toggleRightPanel('incidents');
                clearAreaAnalysis();
                // Hide Crime Intensity Scale in markers mode
                crimeIntensityScale.style.display = 'none';
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (map) {
                map.invalidateSize();
                if (qcBounds) {
                    map.panInsideBounds(qcBounds);
                }
            }
        });

        // ============================================================
        // REAL-TIME FEATURES ENABLED - PUSHER
        // ============================================================
        console.log('🔌 Real-time features enabled - Using Pusher');

        // Desktop notification function using NotificationManager
        function showDesktopNotification(incident, action = 'created') {
            try {
                let title;
                
                switch(action) {
                    case 'created':
                        title = 'New Crime Reported';
                        break;
                    case 'updated':
                        title = 'Crime Updated';
                        break;
                    case 'deleted':
                        title = 'Crime Deleted';
                        break;
                    default:
                        title = 'Crime Notification';
                }
                
                // Use the NotificationManager class
                if (typeof window.NotificationManager !== 'undefined') {
                    window.NotificationManager.showIncidentNotification(title, {
                        incident_title: incident.incident_title || 'Unknown Crime',
                        category_name: incident.category_name || 'Unknown Category',
                        location: incident.location || incident.barangay_name || 'Unknown Location',
                        id: incident.id,
                        incident_date: incident.incident_date || new Date().toISOString(),
                        status: incident.status || 'reported',
                        clearance_status: incident.clearance_status || 'uncleared'
                    }, action);
                } else {
                    console.warn('NotificationManager not available');
                }
            } catch (error) {
                console.error('Error showing notification:', error);
            }
        }

        // Initialize real-time listeners after DOM is loaded and Echo is ready
        function initializeRealtimeListeners() {
            console.log('🔍 Initializing real-time listeners...');
            console.log('Echo available:', typeof window.Echo !== 'undefined' && window.Echo);
            
            if (typeof window.Echo !== 'undefined' && window.Echo) {
                console.log('🔌 Echo available - Setting up real-time listeners...');
                
                // Add connection debugging
                window.Echo.connector.pusher.connection.bind('connected', function() {
                    console.log('✅ Pusher connected successfully');
                    updateDebugInfo();
                });
                
                window.Echo.connector.pusher.connection.bind('disconnected', function() {
                    console.log('❌ Pusher disconnected - attempting to reconnect...');
                    updateDebugInfo();
                    // Attempt to reconnect after 3 seconds
                    setTimeout(() => {
                        if (window.Echo.connector.pusher.connection.state === 'disconnected') {
                            console.log('🔄 Attempting to reconnect to Pusher...');
                            window.Echo.connector.pusher.connect();
                        }
                    }, 3000);
                });
                
                window.Echo.connector.pusher.connection.bind('error', function(err) {
                    console.error('❌ Pusher connection error:', err);
                    updateDebugInfo();
                    
                    // Handle specific error codes
                    if (err.data && err.data.code) {
                        switch(err.data.code) {
                            case 4201: // Pong reply not received
                                console.log('🔄 Connection timeout - reconnecting...');
                                setTimeout(() => {
                                    window.Echo.connector.pusher.connect();
                                }, 2000);
                                break;
                            case 4000: // Internal client error
                            case 4200: // Application error
                                console.error('💥 Pusher application error - check configuration');
                                break;
                            default:
                                console.log('🔄 Unknown error - attempting reconnection...');
                                setTimeout(() => {
                                    window.Echo.connector.pusher.connect();
                                }, 5000);
                        }
                    }
                });
                
                // Add connection state monitoring
                window.Echo.connector.pusher.connection.bind('state_change', function(states) {
                    console.log('🔄 Pusher connection state changed:', states.current);
                    updateDebugInfo();
                });
                
                const channel = window.Echo.channel('crime-incidents');
                
                // Add channel debugging
                channel.subscribed(function() {
                    console.log('✅ Successfully subscribed to crime-incidents channel');
                    updateDebugInfo();
                });
                
                channel.error(function(err) {
                    console.error('❌ Failed to subscribe to crime-incidents channel:', err);
                    updateDebugInfo();
                });
                
                channel.listen('.incident.created', function(e) {
                    console.log('📍 New incident received:', e);
                    eventCounter++;
                    handleNewIncident(e);
                    showDesktopNotification(e, 'created');
                    updateDebugInfo();
                });
                
                channel.listen('.incident.updated', function(e) {
                    console.log('🔄 Incident updated:', e);
                    eventCounter++;
                    handleUpdatedIncident(e);
                    showDesktopNotification(e, 'updated');
                    updateDebugInfo();
                });

                window.Echo.channel('crime-incidents')
                    .listen('.incident.deleted', function(e) {
                        console.log('🗑️ Incident deleted:', e);
                        eventCounter++;
                        handleDeletedIncident(e.id);
                        showDesktopNotification(e, 'deleted');
                        updateDebugInfo();
                    });
                    
                console.log('✅ Real-time listeners setup complete');
            } else {
                console.warn('⚠️ Echo not available - real-time features disabled');
                console.log('Checking Echo availability:', typeof window.Echo);
                console.log('Window object keys:', Object.keys(window));
                
                // Retry after 2 seconds
                setTimeout(initializeRealtimeListeners, 2000);
            }
        }

        // Initialize when DOM is loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeRealtimeListeners);
        } else {
            initializeRealtimeListeners();
        }

        // Handle new incident added in real-time
        function handleNewIncident(incident) {
            // Add to current data array
            currentData.push(incident);

            // Add marker/point to current visualization
            if (currentVisualizationMode === 'street-heatmap') {
                displayStreetHeatmap(currentData);
            } else if (currentVisualizationMode === 'heatmap') {
                // Re-render heatmap with updated data
                clearCurrentVisualization();
                displayHeatmap(currentData);
            } else if (currentVisualizationMode === 'markers') {
                // Add single marker to existing display
                addSingleMarkerRealtime(incident);
            } else if (currentVisualizationMode === 'clusters') {
                // Re-render clusters (cluster grouping may change)
                clearCurrentVisualization();
                displayClusters(currentData);
            }

            // Update statistics and incident list
            if (window.crimeMap3D) window.crimeMap3D.refresh();
            if (window.crimeMapGoogle) window.crimeMapGoogle.refresh();
            updateStatistics(currentData);
            loadTotalStats(); // Refresh total stats
            currentListData = currentData;
            currentListPage = 1; // Reset to first page
            renderIncidentPage(document.getElementById('incidentSearch').value);
        }

        // Handle updated incident
        function handleUpdatedIncident(incident) {
            const index = currentData.findIndex(i => i.id === incident.id);
            if (index !== -1) {
                currentData[index] = incident;
                // Re-render
                clearCurrentVisualization();
                if (currentVisualizationMode === 'street-heatmap') displayStreetHeatmap(currentData);
                else if (currentVisualizationMode === 'heatmap') displayHeatmap(currentData);
                else if (currentVisualizationMode === 'markers') displayMarkers(currentData);
                else displayClusters(currentData);
                updateStatistics(currentData);
                loadTotalStats(); // Refresh total stats
                renderIncidentPage(document.getElementById('incidentSearch').value);
            }
        }

        // Handle deleted incident
        function handleDeletedIncident(id) {
            currentData = currentData.filter(i => i.id !== id);
            clearCurrentVisualization();
            if (currentVisualizationMode === 'street-heatmap') displayStreetHeatmap(currentData);
            else if (currentVisualizationMode === 'heatmap') displayHeatmap(currentData);
            else if (currentVisualizationMode === 'markers') displayMarkers(currentData);
            else displayClusters(currentData);
            updateStatistics(currentData);
            loadTotalStats(); // Refresh total stats
            renderIncidentPage(document.getElementById('incidentSearch').value);
        }

        // Add a single marker without re-rendering all markers
        function addSingleMarkerRealtime(incident) {
            if (!incident.latitude || !incident.longitude) return;

            // Check if within QC bounds
            if (qcBounds && !qcBounds.contains([incident.latitude, incident.longitude])) {
                return;
            }

            const marker = L.circleMarker(
                [incident.latitude, incident.longitude],
                {
                    radius: 8,
                    fillColor: incident.color_code,
                    color: incident.color_code,
                    weight: 2,
                    opacity: 0.8,
                    fillOpacity: 0.8,
                    className: 'crime-marker'
                }
            );

            marker.bindPopup(`
                <div style="font-size: 12px;">
                    <strong>${incident.incident_title}</strong><br>
                    ${incident.category_name}<br>
                    ${incident.location}<br>
                    <em>${incident.incident_date}</em>
                </div>
            `);

            marker.on('click', function() {
                openIncidentModal(incident.id);
            });

            marker.addTo(markerLayer);
        }

        // Real-time notification functions (disabled - kept for compatibility)
        function showRealtimeNotification(message) {
            console.log('🔌 Real-time notification disabled:', message);
        }

        // CSS animation for notification
        if (!document.getElementById('realtimeNotificationStyle')) {
            const style = document.createElement('style');
            style.id = 'realtimeNotificationStyle';
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                @keyframes pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.7; }
                }
            `;
            document.head.appendChild(style);
        }

        // Test Notification Button Handler
        document.addEventListener('DOMContentLoaded', function() {
            const testBtn = document.getElementById('testNotificationBtn');
            if (testBtn) {
                testBtn.addEventListener('click', function() {
                    // Show a sample notification
                    const sampleNotifications = [
                        {
                            title: 'New Crime Reported',
                            body: 'Test: A new crime incident has been reported in Quezon City',
                            icon: '/images/alertara.png'
                        },
                        {
                            title: 'Crime Updated',
                            body: 'Test: An existing crime incident has been updated',
                            icon: '/images/alertara.png'
                        },
                        {
                            title: 'Crime Deleted',
                            body: 'Test: A crime incident has been removed from the system',
                            icon: '/images/alertara.png'
                        },
                        {
                            title: 'Real-time Notifications Working',
                            body: 'Test: Your real-time notification system is functioning properly',
                            icon: '/images/alertara.png'
                        },
                        {
                            title: 'WebSocket Connection Active',
                            body: 'Test: You are connected to real-time crime data stream',
                            icon: '/images/alertara.png'
                        }
                    ];

                    const randomNotification = sampleNotifications[Math.floor(Math.random() * sampleNotifications.length)];
                    
                    // Check if browser supports notifications
                    if (!('Notification' in window)) {
                        console.log('❌ Browser does not support notifications');
                        alert('Your browser does not support desktop notifications');
                        return;
                    }
                    
                    // Request permission if not granted
                    if (Notification.permission === 'default') {
                        Notification.requestPermission().then(permission => {
                            if (permission === 'granted') {
                                createTestNotification(randomNotification, testBtn);
                            } else {
                                alert('Please allow notifications to test this feature');
                            }
                        });
                    } else if (Notification.permission === 'granted') {
                        createTestNotification(randomNotification, testBtn);
                    } else {
                        alert('Notifications are blocked. Please enable them in your browser settings.');
                    }
                });
            }
        });

        // Create test notification function
        function createTestNotification(notificationData, button) {
            try {
                const notification = new Notification(notificationData.title, {
                    body: notificationData.body,
                    icon: notificationData.icon,
                    tag: 'test-notification-' + Date.now(),
                    requireInteraction: true,
                    silent: false
                });
                
                console.log('✅ Test notification sent:', notificationData);
                
                // Auto-close after 5 seconds
                setTimeout(() => {
                    notification.close();
                }, 5000);
                
                // Click to focus window
                notification.onclick = function() {
                    window.focus();
                    notification.close();
                };
                
                // Change button to indicate success
                button.style.backgroundColor = '#22c55e';
                button.innerHTML = '<i class="fas fa-check mr-2"></i>Notification Sent!';
                
                // Reset button after 2 seconds
                setTimeout(() => {
                    button.style.backgroundColor = '';
                    button.innerHTML = '<i class="fas fa-bell mr-2"></i>Test Notification';
                }, 2000);
                
            } catch (error) {
                console.error('❌ Failed to create test notification:', error);
                alert('Failed to create notification: ' + error.message);
            }
        }
    </script>

    <!-- ============ Import from the Alertara Reports system ============ -->
    <style>
        #importModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 99999; padding: 20px; align-items: center; justify-content: center; }
        #importModal.open { display: flex; }
        .imp-shell { background: #fff; border-radius: 16px; width: min(1180px, 96vw); height: min(88vh, 860px); display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 70px rgba(0,0,0,0.35); }
        .imp-head { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: flex-start; gap: 12px; }
        .imp-title { font-size: 16px; font-weight: 800; color: #111; margin: 0; }
        .imp-sub { font-size: 12px; color: #6b7280; margin: 3px 0 0; }
        .imp-x { margin-left: auto; background: none; border: none; font-size: 18px; color: #9ca3af; cursor: pointer; width: 32px; height: 32px; border-radius: 8px; }
        .imp-x:hover { color: #111; background: #f3f4f6; }
        .imp-bar { padding: 12px 20px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .imp-bar input[type="text"], .imp-bar select, .imp-bar input[list] { padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 12px; background: #fff; }
        .imp-bar label { font-size: 12px; color: #374151; display: inline-flex; align-items: center; gap: 6px; }
        .imp-btn { font-size: 12px; font-weight: 700; border: 1px solid #d1d5db; background: #fff; color: #374151; border-radius: 8px; padding: 7px 12px; cursor: pointer; }
        .imp-btn:hover { background: #f3f4f6; }
        .imp-btn-primary { background: #274d4c; border-color: #274d4c; color: #fff; }
        .imp-btn-primary:hover { background: #214040; }
        .imp-btn-primary:disabled { background: #9ca3af; border-color: #9ca3af; cursor: not-allowed; }
        .imp-body { flex: 1; overflow: auto; }
        .imp-state { padding: 60px 20px; text-align: center; color: #6b7280; font-size: 13px; }
        .imp-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .imp-table th { position: sticky; top: 0; background: #f3f4f6; text-align: left; padding: 9px 10px; font-size: 11px; font-weight: 800; color: #374151; text-transform: uppercase; letter-spacing: .03em; border-bottom: 1px solid #e5e7eb; z-index: 1; white-space: nowrap; }
        .imp-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #111; }
        .imp-table tbody tr:hover { background: #f8fafc; }
        .imp-table tr.is-imported { background: #f9fafb; color: #9ca3af; }
        .imp-table tr.is-imported td { color: #9ca3af; }
        .imp-table tr.needs-street td { background: #fff7ed; }
        .imp-table select, .imp-table input[list] { width: 100%; min-width: 120px; padding: 5px 7px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 11.5px; background: #fff; }
        .imp-code { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 11px; }
        .imp-pill { display: inline-block; padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; }
        .imp-pill-case { background: #e0e7ff; color: #3730a3; }
        .imp-pill-blotter { background: #fef3c7; color: #92400e; }
        .imp-pill-done { background: #dcfce7; color: #166534; }
        .imp-pill-risk { background: #fee2e2; color: #991b1b; }
        .imp-pill-draft { background: #f1f5f9; color: #475569; }
        .imp-muted { color: #9ca3af; }
        .imp-foot { padding: 12px 20px; border-top: 1px solid #e5e7eb; background: #f9fafb; display: flex; align-items: center; gap: 12px; }
        .imp-count { font-size: 12px; color: #374151; font-weight: 700; }
        .imp-note { font-size: 11.5px; color: #b45309; }
        .imp-toast { position: fixed; right: 20px; bottom: 20px; z-index: 100020; background: #111827; color: #fff; padding: 12px 16px; border-radius: 10px; font-size: 12.5px; font-weight: 600; box-shadow: 0 12px 30px rgba(0,0,0,0.3); max-width: 380px; }
        .imp-toast.ok { background: #15803d; }
        .imp-toast.err { background: #b91c1c; }
    </style>

    <div id="importModal" onclick="if (event.target === this) closeImportModal()">
        <div class="imp-shell">
            <div class="imp-head">
                <div>
                    <h3 class="imp-title"><i class="fas fa-cloud-download-alt mr-2" style="color:#274d4c;"></i>Import crime data from Reports</h3>
                    <p class="imp-sub">Records pulled from the Alertara Reports system. Tick the ones you want, choose where each belongs on the map, then insert them.</p>
                </div>
                <button class="imp-x" type="button" onclick="closeImportModal()" title="Close"><i class="fas fa-times"></i></button>
            </div>

            <div class="imp-bar">
                <input type="text" id="impSearch" placeholder="Search reference, type, location..." style="min-width: 220px; flex: 1;">
                <select id="impSource">
                    <option value="">All sources</option>
                    <option value="case">Cases</option>
                    <option value="blotter">Blotters</option>
                </select>
                <label><input type="checkbox" id="impHideImported" checked> Hide already imported</label>
                <label title="Reports still in Draft, or filed without a reference number"><input type="checkbox" id="impHideDrafts" checked> Hide drafts</label>
                <span style="width: 1px; height: 22px; background: #e5e7eb;"></span>
                <input list="impStreetList" id="impBulkStreet" placeholder="Street to place on..." style="min-width: 190px;">
                <button class="imp-btn" type="button" id="impApplyStreet" title="Set this street on every ticked row">Apply to ticked</button>
                <button class="imp-btn" type="button" id="impRefresh" style="margin-left: auto;"><i class="fas fa-sync-alt mr-1"></i>Refresh</button>
            </div>

            <datalist id="impStreetList"></datalist>

            <div class="imp-body">
                <div id="impLoading" class="imp-state"><i class="fas fa-spinner fa-spin mr-2"></i>Reading the Reports system...</div>
                <div id="impEmpty" class="imp-state" style="display: none;"></div>
                <table class="imp-table" id="impTable" style="display: none;">
                    <thead>
                        <tr>
                            <th style="width: 34px;"><input type="checkbox" id="impCheckAll" title="Select all shown"></th>
                            <th>Source</th>
                            <th>Reference</th>
                            <th>Reported type</th>
                            <th style="min-width: 150px;">Save as category</th>
                            <th>Date &amp; time</th>
                            <th>Reported location</th>
                            <th style="min-width: 170px;">Place on street</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="impRows"></tbody>
                </table>
            </div>

            <div class="imp-foot">
                <span class="imp-count" id="impSelected">0 selected</span>
                <span class="imp-note" id="impWarning"></span>
                <button class="imp-btn" type="button" onclick="closeImportModal()" style="margin-left: auto;">Cancel</button>
                <button class="imp-btn imp-btn-primary" type="button" id="impInsert" disabled>
                    <i class="fas fa-database mr-1"></i>Insert selected
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            let impRecords = [];      // everything the feed returned
            let impStreets = [];      // San Agustin street names
            let impCategories = [];   // categories already used by this system
            const impChoice = {};     // code => { street, category, checked }
            let impLoaded = false;

            const $ = (id) => document.getElementById(id);

            function esc(value) {
                return String(value ?? '').replace(/[&<>"']/g, (c) => (
                    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
                ));
            }

            function toast(message, kind) {
                const el = document.createElement('div');
                el.className = 'imp-toast ' + (kind || '');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(() => el.remove(), 5000);
            }

            window.openImportModal = function () {
                $('importModal').classList.add('open');
                if (!impLoaded) loadReports(false);
            };

            window.closeImportModal = function () {
                $('importModal').classList.remove('open');
            };

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && $('importModal').classList.contains('open')) closeImportModal();
            });

            async function loadReports(refresh) {
                impLoaded = true;
                $('impLoading').style.display = 'block';
                $('impEmpty').style.display = 'none';
                $('impTable').style.display = 'none';

                try {
                    const res = await fetch('/mapping/external-crimes' + (refresh ? '?refresh=1' : ''), {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();

                    if (!res.ok || !data.success) throw new Error(data.error || 'Request failed');

                    impRecords = data.records || [];
                    impStreets = data.streets || [];
                    impCategories = data.categories || [];

                    $('impStreetList').innerHTML = impStreets
                        .map((s) => '<option value="' + esc(s) + '"></option>').join('');

                    // Seed each row's choices once, from what the feed suggests.
                    impRecords.forEach((r) => {
                        if (!impChoice[r.code]) {
                            impChoice[r.code] = {
                                street: r.street_hint || '',
                                category: r.category || '',
                                checked: false
                            };
                        }
                    });

                    render();
                } catch (err) {
                    console.error('Reports import: load failed', err);
                    $('impLoading').style.display = 'none';
                    $('impEmpty').style.display = 'block';
                    $('impEmpty').innerHTML = '<i class="fas fa-triangle-exclamation mr-2" style="color:#b91c1c;"></i>'
                        + 'Could not reach the Reports system. Try Refresh in a moment.';
                }
            }

            function visibleRecords() {
                const q = $('impSearch').value.trim().toLowerCase();
                const source = $('impSource').value;
                const hideImported = $('impHideImported').checked;
                const hideDrafts = $('impHideDrafts').checked;

                return impRecords.filter((r) => {
                    if (source && r.source !== source) return false;
                    if (hideImported && r.already_imported) return false;
                    if (hideDrafts && r.draft && !r.already_imported) return false;
                    if (!q) return true;
                    return [r.code, r.type, r.category, r.location, r.title, r.reporter]
                        .filter(Boolean).join(' ').toLowerCase().includes(q);
                });
            }

            function categoryOptions(current) {
                const list = impCategories.slice();
                if (current && !list.some((c) => c.toLowerCase() === current.toLowerCase())) list.unshift(current);
                return list.map((c) => '<option value="' + esc(c) + '"'
                    + (c.toLowerCase() === String(current).toLowerCase() ? ' selected' : '') + '>' + esc(c) + '</option>').join('');
            }

            function render() {
                const rows = visibleRecords();
                $('impLoading').style.display = 'none';

                if (!impRecords.length) {
                    $('impTable').style.display = 'none';
                    $('impEmpty').style.display = 'block';
                    $('impEmpty').innerHTML = '<i class="fas fa-inbox mr-2"></i>The Reports system has no crime data to import right now.';
                    updateFooter();
                    return;
                }

                if (!rows.length) {
                    $('impTable').style.display = 'none';
                    $('impEmpty').style.display = 'block';
                    $('impEmpty').innerHTML = '<i class="fas fa-filter mr-2"></i>Nothing matches these filters. '
                        + impRecords.length + ' record(s) available in total.';
                    updateFooter();
                    return;
                }

                $('impEmpty').style.display = 'none';
                $('impTable').style.display = 'table';

                $('impRows').innerHTML = rows.map((r) => {
                    const choice = impChoice[r.code];
                    const done = r.already_imported;
                    const hasOwnPoint = r.lat !== null && r.lng !== null;
                    const needsStreet = !done && !hasOwnPoint && !choice.street;
                    const when = esc(r.date) + (r.time ? ' <span class="imp-muted">' + esc(r.time.slice(0, 5)) + '</span>' : '');

                    const place = hasOwnPoint
                        ? '<span class="imp-muted" title="This report carries its own coordinates">'
                            + '<i class="fas fa-location-dot mr-1"></i>' + Number(r.lat).toFixed(5) + ', ' + Number(r.lng).toFixed(5) + '</span>'
                        : '<input list="impStreetList" data-code="' + esc(r.code) + '" class="imp-street" '
                            + 'placeholder="Pick a street" value="' + esc(choice.street) + '"' + (done ? ' disabled' : '') + '>';

                    const detail = [
                        r.title, r.description, r.reporter ? 'Reported by: ' + r.reporter : '',
                        r.victim ? 'Victim: ' + r.victim : '', r.suspect ? 'Suspect: ' + r.suspect : '',
                        r.urgency ? 'Urgency: ' + r.urgency : ''
                    ].filter(Boolean).join('\n');

                    return '<tr class="' + (done ? 'is-imported' : '') + (needsStreet ? ' needs-street' : '') + '">'
                        + '<td><input type="checkbox" class="imp-pick" data-code="' + esc(r.code) + '"'
                            + (choice.checked && !done ? ' checked' : '') + (done ? ' disabled' : '') + '></td>'
                        + '<td><span class="imp-pill imp-pill-' + esc(r.source) + '">' + esc(r.source) + '</span></td>'
                        + '<td class="imp-code" title="' + esc(detail) + '">' + esc(r.code)
                            + (r.draft ? ' <span class="imp-pill imp-pill-draft" title="Unfiled in Reports">draft</span>' : '')
                            + (r.high_risk ? ' <span class="imp-pill imp-pill-risk">high risk</span>' : '')
                            + (done ? ' <span class="imp-pill imp-pill-done">in database</span>' : '') + '</td>'
                        + '<td>' + esc(r.type) + '</td>'
                        + '<td><select class="imp-cat" data-code="' + esc(r.code) + '"' + (done ? ' disabled' : '') + '>'
                            + categoryOptions(choice.category) + '</select></td>'
                        + '<td style="white-space: nowrap;">' + when + '</td>'
                        + '<td>' + (r.location ? esc(r.location) : '<span class="imp-muted">not stated</span>') + '</td>'
                        + '<td>' + place + '</td>'
                        + '<td>' + esc(r.raw_status || '—') + ' <span class="imp-muted">&rarr; ' + esc(r.status) + '</span></td>'
                        + '</tr>';
                }).join('');

                const pickable = rows.filter((r) => !r.already_imported);
                $('impCheckAll').checked = pickable.length > 0 && pickable.every((r) => impChoice[r.code].checked);
                updateFooter();
            }

            function selectedCodes() {
                return impRecords
                    .filter((r) => !r.already_imported && impChoice[r.code] && impChoice[r.code].checked)
                    .map((r) => r.code);
            }

            function unplacedCount() {
                return impRecords.filter((r) => {
                    if (r.already_imported || !impChoice[r.code].checked) return false;
                    if (r.lat !== null && r.lng !== null) return false;
                    return !impChoice[r.code].street;
                }).length;
            }

            function updateFooter() {
                const count = selectedCodes().length;
                const unplaced = unplacedCount();

                $('impSelected').textContent = count + ' selected';
                $('impWarning').textContent = unplaced
                    ? unplaced + ' ticked row(s) still need a street before they can go on the map.'
                    : '';
                $('impInsert').disabled = count === 0 || unplaced > 0;
            }

            // ---- events

            $('impSearch').addEventListener('input', render);
            $('impSource').addEventListener('change', render);
            $('impHideImported').addEventListener('change', render);
            $('impHideDrafts').addEventListener('change', render);
            $('impRefresh').addEventListener('click', () => loadReports(true));

            $('impCheckAll').addEventListener('change', function () {
                visibleRecords().forEach((r) => {
                    if (!r.already_imported) impChoice[r.code].checked = this.checked;
                });
                render();
            });

            $('impRows').addEventListener('change', function (e) {
                const el = e.target;
                const code = el.dataset.code;
                if (!code || !impChoice[code]) return;

                if (el.classList.contains('imp-pick')) {
                    impChoice[code].checked = el.checked;
                    updateFooter();
                } else if (el.classList.contains('imp-cat')) {
                    impChoice[code].category = el.value;
                }
            });

            // Street is a free-text datalist input, so track it as it is typed.
            $('impRows').addEventListener('input', function (e) {
                const el = e.target;
                if (!el.classList.contains('imp-street')) return;

                const code = el.dataset.code;
                if (!code || !impChoice[code]) return;

                impChoice[code].street = el.value.trim();
                el.closest('tr').classList.toggle('needs-street', !impChoice[code].street);
                updateFooter();
            });

            $('impApplyStreet').addEventListener('click', function () {
                const street = $('impBulkStreet').value.trim();
                if (!street) {
                    toast('Type or pick a street first.', 'err');
                    return;
                }
                if (!impStreets.some((s) => s.toLowerCase() === street.toLowerCase())) {
                    toast('"' + street + '" is not a San Agustin street.', 'err');
                    return;
                }

                const codes = selectedCodes();
                if (!codes.length) {
                    toast('Tick the rows you want to place first.', 'err');
                    return;
                }

                codes.forEach((code) => { impChoice[code].street = street; });
                render();
                toast(codes.length + ' row(s) set to ' + street + '.', 'ok');
            });

            $('impInsert').addEventListener('click', async function () {
                const codes = selectedCodes();
                if (!codes.length) return;

                const items = codes.map((code) => ({
                    code: code,
                    street: impChoice[code].street || null,
                    category: impChoice[code].category || null
                }));

                this.disabled = true;
                const original = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Inserting...';

                try {
                    const res = await fetch('/mapping/external-crimes/import', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ items: items })
                    });
                    const data = await res.json();

                    if (!res.ok || !data.success) throw new Error(data.error || 'Import failed');

                    let message = data.inserted + ' crime record(s) inserted.';
                    if (data.skipped) message += ' ' + data.skipped + ' already existed.';
                    if (data.failed && data.failed.length) {
                        message += ' ' + data.failed.length + ' could not be placed.';
                        console.warn('Reports import: rows skipped', data.failed);
                    }
                    toast(message, data.inserted ? 'ok' : 'err');

                    (data.codes || []).forEach((code) => { if (impChoice[code]) impChoice[code].checked = false; });

                    // Refresh the map and the list side by side.
                    if (typeof loadCrimeData === 'function') loadCrimeData();
                    if (typeof loadTotalStats === 'function') loadTotalStats();
                    await loadReports(false);
                } catch (err) {
                    console.error('Reports import failed', err);
                    toast(err.message || 'The import failed. Nothing was inserted.', 'err');
                } finally {
                    this.disabled = false;
                    this.innerHTML = original;
                    updateFooter();
                }
            });

            document.addEventListener('DOMContentLoaded', function () {
                const btn = $('importReportsBtn');
                if (btn) btn.addEventListener('click', openImportModal);
            });
        })();
    </script>

    <!-- External Fullscreen JavaScript -->
    @vite(['resources/js/mapping-fullscreen.js', 'resources/js/notification-manager.ts'])

</body>
</html>
