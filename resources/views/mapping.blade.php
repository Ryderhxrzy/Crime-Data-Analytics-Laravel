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

        /* Filters float over the map while it is enlarged */
        #mapContainer.map-fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 99998;
            border-radius: 0;
        }
        #mapContainer.map-fullscreen #map { height: 100vh !important; }

        #mapContainer.map-fullscreen #filtersSection {
            position: absolute;
            top: 12px;
            left: 12px;
            right: 68px;
            z-index: 99999;
            max-height: calc(100vh - 24px);
            overflow-y: auto;
            margin: 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        #mapContainer.map-fullscreen #exitFullscreenBtn { display: flex; }
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
                </div>
            </div>


            <!-- Map Container with Right Panel -->
            <div class="bg-white border border-gray-200 rounded-lg p-6" style="position: relative; z-index: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-map mr-2 text-alertara-600"></i>Crime Map
                    </h2>
                    <button id="mapFullscreenBtn" class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 text-sm" title="Toggle Fullscreen Map">
                        <i class="fas fa-expand"></i>
                        <span class="hidden sm:inline">Fullscreen</span>
                    </button>
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
                            <div class="bg-gradient-to-br from-amber-600 to-amber-500 text-white p-4 rounded-lg shadow-sm">
                                <div class="text-xs opacity-90 mb-1">Crimes</div>
                                <div id="statTotalIncident" class="text-2xl font-bold">0</div>
                            </div>
                            <div class="bg-gradient-to-br from-green-600 to-green-500 text-white p-4 rounded-lg shadow-sm">
                                <div class="text-xs opacity-90 mb-1">Cleared Cases</div>
                                <div id="statCleared" class="text-2xl font-bold">0</div>
                            </div>
                            <div class="bg-gradient-to-br from-red-600 to-red-500 text-white p-4 rounded-lg shadow-sm">
                                <div class="text-xs opacity-90 mb-1">Uncleared Cases</div>
                                <div id="statUncleared" class="text-2xl font-bold">0</div>
                            </div>
                            <div class="bg-gradient-to-br from-blue-600 to-blue-500 text-white p-4 rounded-lg shadow-sm col-span-2">
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

                        <!-- BARANGAYS PANEL (for cluster mode) -->
                        <div id="barangaysPanel" style="background: rgba(255, 255, 255, 0.98); border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; display: none; flex-direction: column; flex-grow: 1;">
                            <div style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0 0 10px;">
                                    <i class="fas fa-map-marker-alt mr-2" style="color: #274d4c;"></i>Barangays
                                </h3>
                                <input type="text" id="barangaySearch" placeholder="Search barangay..." style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; box-sizing: border-box;">
                            </div>
                            <div id="barangayList" style="overflow-y: auto; overflow-x: hidden; max-height: 350px; padding: 8px;">
                                <!-- Will be populated by JavaScript -->
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

                    <!-- Info Box -->
                    <div style="margin-top: 12px; padding: 10px; background: #f0f9f8; border-left: 3px solid #274d4c; border-radius: 4px;">
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

    <!-- Street Details Modal (San Agustin street click) -->
    <style>
        #streetModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 99998; padding: 20px; align-items: center; justify-content: center; }
        #streetModal .sm-card { position: relative; background: #fff; border-radius: 16px; width: 100%; max-width: 1180px; height: 92%; display: flex; flex-direction: column; box-shadow: 0 25px 70px rgba(0,0,0,0.35); overflow: hidden; }
        /* Full-width map on top; crimes (left) + AI (right) below, each with
           its own scrollbar. The body itself never scrolls on desktop. */
        #streetModal .sm-body { flex: 1; min-height: 0; display: flex; flex-direction: column; padding: 14px 20px 20px; overflow: hidden; }
        #streetModal .sm-bottom { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(0,1.1fr) minmax(0,1fr); gap: 16px; margin-top: 14px; }
        #streetModal .sm-col { min-width: 0; min-height: 0; overflow-y: auto; padding-right: 4px; }
        /* Pop-out windows: any modal section can detach into its own draggable,
           resizable "browser-like" window (fullscreen on small screens) */
        .sm-popout { display: none; position: fixed; z-index: 100010; width: min(760px, 92vw); height: min(72vh, 660px); background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; box-shadow: 0 25px 70px rgba(0,0,0,0.5); flex-direction: column; overflow: hidden; resize: both; }
        .sm-popout-head { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: #111827; color: #fff; font-size: 12px; font-weight: 700; cursor: move; user-select: none; touch-action: none; flex-shrink: 0; }
        .sm-popout-dock { margin-left: auto; background: rgba(255,255,255,0.15); color: #fff; border: none; border-radius: 8px; padding: 4px 10px; font-size: 11px; font-weight: 700; cursor: pointer; }
        .sm-popout-dock:hover { background: rgba(255,255,255,0.3); }
        .sm-popout-body { flex: 1; min-height: 0; overflow-y: auto; padding: 12px; }
        .sm-popout-body #secMap { height: 100%; display: flex; flex-direction: column; }
        .sm-popout-body #streetModalMap { flex: 1 1 auto; height: auto !important; min-height: 260px; }
        .sm-pop-btn { font-size: 10px; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px 9px; cursor: pointer; }
        .sm-pop-btn:hover { color: #111827; background: #f1f5f9; }
        @media (max-width: 900px) {
            .sm-popout { inset: 0 !important; width: 100% !important; height: 100% !important; border-radius: 0; resize: none; }
        }
        /* Enlarged suggestions: full-width panel + bigger text for readability */
        #streetModal #streetAiCol.sm-ai-big { grid-column: 1 / -1; }
        #streetModal .sm-ai-big #streetAiSummary { font-size: 14px !important; line-height: 1.55 !important; }
        #streetModal .sm-ai-big #streetAiSuggestions,
        #streetModal .sm-ai-big #streetAiSuggestions * { font-size: 13.5px !important; line-height: 1.55 !important; }
        /* Fullscreen mode (expand button) */
        #streetModal.sm-nopad { padding: 0; }
        #streetModal .sm-card.sm-full { max-width: 100%; width: 100%; height: 100%; border-radius: 0; }
        #streetModal .sm-card.sm-full #streetModalMap { height: 45vh; }
        /* Small screens: the whole modal body scrolls as one column */
        @media (max-width: 900px) {
            #streetModal { padding: 10px; }
            #streetModal .sm-card { height: 96%; }
            #streetModal .sm-body { overflow-y: auto; display: block; -webkit-overflow-scrolling: touch; }
            #streetModal .sm-bottom { grid-template-columns: 1fr; }
            #streetModal .sm-col { overflow-y: visible; }
            #streetModal .sm-card:not(.sm-full) #streetModalMap { height: 260px !important; }
        }
        #streetModal .sm-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; background: #f3f4f6; color: #374151; }
        #streetModal .sm-inc-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; cursor: pointer; transition: box-shadow .15s, border-color .15s; }
        #streetModal .sm-inc-card:hover { border-color: #94a3b8; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        /* Collapsible per-street crime group (right column) */
        #streetModal .sm-acc { border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        #streetModal .sm-acc-head { width: 100%; display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #f9fafb; border: none; cursor: pointer; font-size: 12.5px; font-weight: 800; color: #111827; text-align: left; }
        #streetModal .sm-acc-head:hover { background: #f3f4f6; }
        #streetModal .sm-acc-body { padding: 10px; display: grid; gap: 8px; }
        /* Street filter dropdown (header) */
        #streetFilterPanel { display: none; position: absolute; top: calc(100% + 6px); left: 0; z-index: 50; width: 300px; max-width: 80vw; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 12px 35px rgba(0,0,0,0.18); padding: 10px; }
        /* Street name labels on the mini map: subtle for context streets,
           a bold pill for the active/selected ones */
        .street-name-mini { background: rgba(255,255,255,0.75); border: 1px solid rgba(100,116,139,0.55); color: #475569; font-weight: 600; font-size: 9px; padding: 1px 6px; border-radius: 9999px; white-space: nowrap; box-shadow: none; opacity: 0.85; }
        .street-name-mini::before { display: none; }
        .street-name-mini.snm-active { background: rgba(255,255,255,0.97); border: 1.5px solid #111827; color: #111827; font-weight: 800; font-size: 11px; padding: 2px 9px; box-shadow: 0 1px 5px rgba(0,0,0,0.3); opacity: 1; }
    </style>
    <div id="streetModal" onclick="if(event.target === this) closeStreetModal()">
        <div class="sm-card">
            <button onclick="closeStreetModal()" style="position: absolute; top: 14px; right: 14px; background: none; border: none; font-size: 20px; color: #999; cursor: pointer; z-index: 10; width: 32px; height: 32px;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'"><i class="fas fa-times"></i></button>
            <button onclick="toggleStreetModalFullscreen()" title="Toggle fullscreen" style="position: absolute; top: 14px; right: 50px; background: none; border: none; font-size: 16px; color: #999; cursor: pointer; z-index: 10; width: 32px; height: 32px;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'"><i id="streetModalFsIcon" class="fas fa-expand"></i></button>

            <!-- Header -->
            <div style="padding: 18px 20px 12px; border-bottom: 1px solid #e5e7eb;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <span id="streetModalSwatch" style="display: inline-block; width: 34px; height: 8px; border-radius: 4px; background: #64748b;"></span>
                    <h2 id="streetModalName" style="font-size: 18px; font-weight: 800; color: #111; margin: 0;">Street</h2>
                    <span style="font-size: 12px; color: #6b7280;">Barangay San Agustin, Quezon City</span>
                </div>
                <div id="streetModalPills" style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px;">
                    <span class="sm-pill"><i class="fas fa-spinner fa-spin"></i> Loading…</span>
                </div>
            </div>

            <div class="sm-body">
                <!-- MAP SECTION (can pop out into its own window) -->
                <div id="secMap" style="display: flex; flex-direction: column; flex-shrink: 0; min-height: 0;">
                <!-- Street filter dropdown sits ABOVE the map -->
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-shrink: 0;">
                    <span style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase;">
                        <i class="fas fa-map-location-dot mr-1" style="color: #274d4c;"></i>Street map — crimes plotted where they happened
                    </span>
                    <button type="button" class="sm-pop-btn" onclick="popOutSection('map')" title="Open the map in a separate window" style="margin-left: auto;">
                        <i class="fas fa-up-right-from-square"></i>
                    </button>
                    <div id="streetFilterWrap" style="position: relative;">
                        <button id="streetFilterBtn" type="button" onclick="toggleStreetFilterPanel()"
                                style="display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 700; color: #374151; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 10px; padding: 7px 12px; cursor: pointer;">
                            <i class="fas fa-road" style="color: #b45309;"></i>
                            <span id="streetFilterBtnLabel">Streets (1)</span>
                            <i class="fas fa-chevron-down" style="font-size: 10px; color: #9ca3af;"></i>
                        </button>
                        <div id="streetFilterPanel" style="left: auto; right: 0;">
                            <input type="text" id="streetFilterSearch" placeholder="Search street…" oninput="renderStreetFilterList(this.value)"
                                   style="width: 100%; box-sizing: border-box; padding: 6px 9px; margin-bottom: 6px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 12px;">
                            <div id="streetFilterList" style="max-height: 210px; overflow-y: auto; display: grid; gap: 1px;"></div>
                            <div style="display: flex; justify-content: flex-end; margin-top: 6px;">
                                <button type="button" onclick="clearModalStreets()" style="font-size: 10.5px; font-weight: 700; color: #6b7280; background: none; border: none; cursor: pointer; text-decoration: underline;">Keep first street only</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FULL-WIDTH map -->
                <div id="streetModalMap" style="height: 380px; flex-shrink: 0; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden;"></div>
                </div><!-- /secMap -->

                <div class="sm-bottom">
                <!-- BELOW-LEFT: crimes per selected street (collapsible groups, own scrollbar) -->
                <div class="sm-col" id="secCrimes">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <span style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase;">
                            <i class="fas fa-list-ul mr-1" style="color: #274d4c;"></i>Crimes on selected street(s) <span id="streetIncCount" style="color: #274d4c;"></span>
                        </span>
                        <button type="button" class="sm-pop-btn" onclick="popOutSection('crimes')" title="Open the crime list in a separate window" style="margin-left: auto;">
                            <i class="fas fa-up-right-from-square"></i>
                        </button>
                    </div>
                    <div id="streetIncidentList" style="display: grid; gap: 10px; align-content: start;">
                        <div style="font-size: 12px; color: #9ca3af; padding: 12px;"><i class="fas fa-spinner fa-spin mr-1"></i>Loading crimes…</div>
                    </div>
                </div>

                <!-- BELOW-RIGHT: AI suggestions -->
                <div class="sm-col" id="streetAiCol">
                    <!-- AI suggestions (manual — one Gemini call per Analyze click) -->
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap;">
                            <span style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase;">
                                <i class="fas fa-shield-halved mr-1" style="color: #7c3aed;"></i>Prevention suggestions
                            </span>
                            <span id="streetAiRisk" style="display: none; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 9999px;"></span>
                            <button type="button" class="sm-pop-btn" onclick="popOutSection('sugg')" title="Open the suggestions in a separate window" style="margin-left: auto;">
                                <i class="fas fa-up-right-from-square"></i>
                            </button>
                            <button id="streetAiZoomBtn" type="button" onclick="toggleStreetAiSize()" title="Enlarge suggestions for readability"
                                    style="font-size: 11px; font-weight: 700; color: #7c3aed; background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; padding: 5px 10px; cursor: pointer;">
                                <i class="fas fa-magnifying-glass-plus"></i>
                            </button>
                            <button id="streetAiSaveBtn" onclick="saveStreetAi()" style="display: none; font-size: 11px; font-weight: 700; color: #fff; background: #7c3aed; border: none; border-radius: 8px; padding: 5px 12px; cursor: pointer;">
                                <i class="fas fa-floppy-disk mr-1"></i>Save
                            </button>
                        </div>

                        <button id="streetAiAnalyzeBtn" onclick="analyzeStreetAi()" style="width: 100%; font-size: 12.5px; font-weight: 700; color: #fff; background: #7c3aed; border: none; border-radius: 10px; padding: 9px 12px; cursor: pointer; margin-bottom: 8px;">
                            <i class="fas fa-wand-magic-sparkles mr-1"></i><span id="streetAiAnalyzeLabel">Generate suggestions</span>
                        </button>

                        <div id="streetAiPlaceholder" style="border: 1px dashed #d1d5db; background: #f9fafb; border-radius: 10px; padding: 12px; font-size: 11.5px; color: #6b7280; text-align: center;">
                            The system reviews the crimes on every selected street and suggests what to do per street, based on the crime categories most frequently committed there. Press <span style="font-weight: 700; color: #7c3aed;">Generate suggestions</span> — instant, no AI quota used.
                        </div>
                        <div id="streetAiLoading" style="display: none; border: 1px solid #ddd6fe; background: #f5f3ff; border-radius: 10px; padding: 14px; font-size: 12px; color: #6d28d9;">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Analyzing the crimes on the selected street(s)…
                        </div>
                        <div id="streetAiError" style="display: none; border: 1px solid #fecaca; background: #fef2f2; border-radius: 10px; padding: 12px; font-size: 12px; color: #b91c1c;">
                            <span id="streetAiErrorMsg"></span>
                            <button onclick="analyzeStreetAi()" style="margin-left: 8px; font-weight: 700; color: #7c3aed; background: none; border: none; cursor: pointer; text-decoration: underline;">Retry</button>
                        </div>
                        <div id="streetAiResults" style="display: none;">
                            <p id="streetAiSummary" style="font-size: 12.5px; color: #374151; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; margin: 0 0 8px;"></p>
                            <div id="streetAiSuggestions" style="display: grid; gap: 8px;"></div>
                        </div>
                    </div>
                </div>
                </div><!-- /sm-bottom -->
            </div>
        </div>
    </div>

    <script>
        // State variables
        let heatmapLayer = null;
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
        const DEFAULT_BARANGAY = 'San Agustin';
        // Tight padding when a single barangay is isolated. No minimum-zoom floor:
        // 13 of the 142 barangays are large enough that forcing a closer zoom would
        // crop them, and fitBounds already gives the closest view that still shows
        // the whole barangay.
        const BARANGAY_FIT_PADDING = [12, 12];

        // Thin borders, light fills. The active barangay is marked by its border and a
        // faint tint — a heavy fill would wash out the incident circles sitting on it.
        const STYLE_BRGY_IDLE   = { color: '#5b8f8c', weight: 1,   opacity: 0.8, fillColor: '#e8f5f3', fillOpacity: 0.15, dashArray: null };
        const STYLE_BRGY_HOVER  = { color: '#274d4c', weight: 1.5, opacity: 1,   fillColor: '#bde5dd', fillOpacity: 0.30, dashArray: null };
        const STYLE_BRGY_ACTIVE = { color: '#274d4c', weight: 2.5, opacity: 1,   fillColor: '#9ed4cb', fillOpacity: 0.22, dashArray: null };
        let currentData = [];
        let selectedIncidentId = null;
        let pointerMarker = null;
        let selectedIncidentCoords = null;

        // Pagination state variables
        const MAX_VISIBLE_INCIDENTS = 100;
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

        // Reusable base-map component: the SAME map setup is used by the main
        // crime mapping view AND the street modal, so both behave identically
        // (tiles, zoom limits, inertia) and there is one place to fix bugs.
        function createCrimeMap(containerId, opts) {
            const m = L.map(containerId, Object.assign({
                center: [14.6349, 121.0446],
                zoom: 12,
                minZoom: 10,
                maxZoom: 25,
                zoomControl: true,
                scrollWheelZoom: true,
                bounceAtZoomLimits: true,
                inertia: true,
                inertiaDeceleration: 3000,
                inertiaMaxSpeed: 1500,
                easeLinearity: 0.25
            }, opts || {}));

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 25,
                minZoom: 10
            }).addTo(m);

            return m;
        }

        // Initialize map
        function initializeMap() {
            console.log('Initializing map...');

            // Create the map with default QC view (shared component)
            map = createCrimeMap('map');

            // Boundaries get their own pane BELOW the default overlayPane (z-index 400).
            // Polygons and circle markers otherwise share one pane, so highlighting a
            // barangay would draw its fill over the incident circles and swallow their
            // clicks. Separate panes keep the circles visible and clickable always.
            map.createPane('barangayPane');
            map.getPane('barangayPane').style.zIndex = 350;
            barangayRenderer = L.svg({ pane: 'barangayPane' });

            // Street outlines sit above the barangay fills but below the incident
            // circles (overlayPane, 400), so markers stay visible and clickable.
            map.createPane('streetPane');
            map.getPane('streetPane').style.zIndex = 360;

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

            updateSanAgustinStreetVisibility();
        }

        // Frame a single barangay as closely as possible while keeping all of it visible
        function zoomToBarangayBounds(bounds, animate = true) {
            map.fitBounds(bounds, { padding: BARANGAY_FIT_PADDING, animate });
        }

        // ------------------------------------------------------------------
        // San Agustin street layer — every street outlined, hover highlights
        // the whole street and shows its incident stats. Visible only while
        // Barangay San Agustin is the isolated barangay.
        // ------------------------------------------------------------------
        const SA_STREETS_URL = '/data/san_agustin_streets.geojson';
        const SA_STREET_STATS_URL = @json(route('pattern-detection.street-stats'));

        let saStreetLayer = null;       // layer group holding all street polylines
        let saStreetsLoading = null;    // promise guard so we only build once
        let saStreetGroupsAll = null;   // name -> {casing, inner, color}, for the street modal's context view
        let saStreetStatsAll = {};      // name -> stats, shared with the modal's hover tooltips

        const escStreet = s => String(s ?? '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        // Streets are COLOR-CODED by how many crimes they carry. Highest
        // matching threshold wins; the same scale drives the map legend.
        const STREET_SEVERITY = [
            { min: 15, label: 'Critical', range: '15+ crimes',  color: '#7f1d1d' },
            { min: 10, label: 'High',     range: '10-14 crimes', color: '#dc2626' },
            { min: 5,  label: 'Moderate', range: '5-9 crimes',   color: '#f97316' },
            { min: 1,  label: 'Low',      range: '1-4 crimes',   color: '#ca8a04' },
            { min: 0,  label: 'Cleared',  range: 'no crime',     color: '#16a34a' },
        ];
        function severityForCount(n) {
            return STREET_SEVERITY.find(function (s) { return n >= s.min; }) || STREET_SEVERITY[STREET_SEVERITY.length - 1];
        }

        // Legend explaining the color criteria — mounted on whichever map is
        // showing the street layer (main map and the street modal)
        function makeStreetLegend() {
            const ctl = L.control({ position: 'bottomright' });
            ctl.onAdd = function () {
                const div = L.DomUtil.create('div');
                div.style.cssText = 'background:rgba(255,255,255,0.96);border:1px solid #e5e7eb;border-radius:10px;padding:8px 12px;box-shadow:0 2px 10px rgba(0,0,0,0.18);font-size:11px;color:#374151;line-height:1.7;';
                div.innerHTML = '<div style="font-weight:800;font-size:11px;color:#111827;margin-bottom:2px;"><i class="fas fa-road" style="color:#274d4c;"></i> Street crime level</div>' +
                    STREET_SEVERITY.map(function (s) {
                        return '<div style="display:flex;align-items:center;gap:7px;">' +
                            '<span style="display:inline-block;width:20px;height:5px;border-radius:3px;background:' + s.color + ';"></span>' +
                            '<span style="font-weight:700;">' + s.label + '</span>' +
                            '<span style="color:#9ca3af;">' + s.range + '</span>' +
                        '</div>';
                    }).join('');
                return div;
            };
            return ctl;
        }
        let saStreetLegendCtl = null;   // legend instance on the MAIN map

        async function ensureSanAgustinStreets() {
            if (saStreetLayer) return saStreetLayer;
            if (saStreetsLoading) return saStreetsLoading;

            saStreetsLoading = (async () => {
                let geo, stats = {};
                try {
                    // Cache-buster: the geojson gets re-clipped during development,
                    // and a stale cached copy would redraw streets past the boundary
                    const [gRes, sRes] = await Promise.all([
                        fetch(SA_STREETS_URL + '?t=' + Date.now(), { headers: { 'Accept': 'application/json' } }),
                        fetch(SA_STREET_STATS_URL + '?t=' + Date.now(), { headers: { 'Accept': 'application/json' } })
                    ]);
                    geo = await gRes.json();
                    stats = (await sRes.json()).streets || {};
                } catch (e) {
                    console.error('San Agustin street layer failed to load:', e);
                    return null;
                }

                saStreetLayer = L.layerGroup();

                // Group all OSM segments of a street so hover lights up the
                // whole street, not just one piece. The line colour encodes
                // the street's crime level (see STREET_SEVERITY).
                const groups = {};
                (geo.features || []).forEach(f => {
                    const name = f.properties && f.properties.name;
                    if (!name || f.geometry.type !== 'LineString') return;

                    const latlngs = f.geometry.coordinates.map(c => [c[1], c[0]]);
                    const g = groups[name] = groups[name] ||
                        { casing: [], inner: [], color: severityForCount(stats[name] ? stats[name].count : 0).color };

                    // Thin, translucent lines: the faint casing keeps the outline
                    // readable, and the base map's street names show through.
                    // NOT added to the layer here — each street's polylines are
                    // mounted below through one featureGroup, so the group has a
                    // map reference and its tooltip can actually open.
                    g.casing.push(L.polyline(latlngs, { color: '#1e293b', weight: 5, opacity: 0.3, pane: 'streetPane' }));
                    g.inner.push(L.polyline(latlngs, { color: g.color, weight: 2.5, opacity: 0.6, pane: 'streetPane' }));
                });

                // The street modal redraws every street (muted) for context and
                // reuses the same per-street stats for its hover tooltips
                saStreetGroupsAll = groups;
                saStreetStatsAll = stats;

                // Nearest point on the street's polylines to a given lat/lng —
                // anchor of the thin pointer line drawn to each crime dot.
                const nearestOnStreet = (g, lat, lng) => {
                    const cosLat = Math.cos(lat * Math.PI / 180);
                    let best = null, bestD = Infinity;
                    g.inner.forEach(l => {
                        const pts = l.getLatLngs();
                        for (let i = 0; i < pts.length - 1; i++) {
                            const ax = pts[i].lng * cosLat, ay = pts[i].lat;
                            const bx = pts[i + 1].lng * cosLat, by = pts[i + 1].lat;
                            const px = lng * cosLat, py = lat;
                            const dx = bx - ax, dy = by - ay;
                            const lsq = dx * dx + dy * dy;
                            const t = lsq < 1e-18 ? 0 : Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / lsq));
                            const qx = ax + t * dx, qy = ay + t * dy;
                            const d = (px - qx) * (px - qx) + (py - qy) * (py - qy);
                            if (d < bestD) { bestD = d; best = [qy, (qx / cosLat)]; }
                        }
                    });
                    return best;
                };

                Object.entries(groups).forEach(([name, g]) => {
                    const st = stats[name];
                    const sev = severityForCount(st ? st.count : 0);
                    const tip = '<div style="font-weight:700;margin-bottom:2px;">' + escStreet(name) + '</div>' +
                        // colour + severity label so the criteria reads on hover
                        '<div style="margin-bottom:3px;"><span style="display:inline-block;width:28px;height:5px;border-radius:3px;background:' + g.color + ';vertical-align:middle;"></span>' +
                        ' <span style="font-weight:700;color:' + g.color + ';">' + sev.label + '</span></div>' +
                        (st
                            ? '<div>' + st.count + ' crime' + (st.count === 1 ? '' : 's') +
                              (st.top_category ? ' · mostly ' + escStreet(st.top_category) : '') + '</div>' +
                              (st.peak_hours && st.peak_hours.length
                                  ? '<div style="color:#c4b5fd;">Peak hours: ' + st.peak_hours.map(escStreet).join(', ') + '</div>' : '')
                            : '<div>No recorded crimes — cleared</div>') +
                        '<div style="margin-top:3px;color:#93c5fd;font-weight:600;"><i class="fas fa-hand-pointer"></i> Click for full details &amp; AI advice</div>';

                    // No bringToFront() here on purpose: raising the SVG path
                    // while the cursor sits on it re-appends the element, the
                    // pending mouseout never fires, and the tooltip gets stuck.
                    const highlight = on => {
                        g.casing.forEach(l => l.setStyle(on
                            ? { weight: 8, color: '#111827', opacity: 0.85 }
                            : { weight: 5, color: '#1e293b', opacity: 0.3 }));
                        g.inner.forEach(l => l.setStyle(on
                            ? { weight: 4.5, color: g.color, opacity: 1 }
                            : { weight: 2.5, color: g.color, opacity: 0.6 }));
                    };

                    // Thin dashed pointer lines from the street to each of its
                    // crime dots, labelled with the crime, shown only while
                    // hovering. Everything is non-interactive so the pointers
                    // can never steal the mouse and flicker the hover state.
                    let connectors = null;
                    const showConnectors = () => {
                        if (connectors || !st || !st.incidents || !st.incidents.length) return;
                        connectors = L.layerGroup();

                        st.incidents.forEach(inc => {
                            if (!isFinite(inc.lat) || !isFinite(inc.lng)) return;
                            const anchor = nearestOnStreet(g, inc.lat, inc.lng);
                            if (anchor) {
                                L.polyline([anchor, [inc.lat, inc.lng]], {
                                    color: '#111827', weight: 1.2, opacity: 0.85,
                                    dashArray: '4,3', interactive: false
                                }).addTo(connectors);
                            }

                            L.circleMarker([inc.lat, inc.lng], {
                                radius: 4.5, color: '#111827', weight: 1.5,
                                fillColor: g.color, fillOpacity: 1, interactive: false
                            }).addTo(connectors);

                            const label = escStreet(inc.category) + (inc.time ? ' · ' + escStreet(inc.time) : '');
                            L.marker([inc.lat, inc.lng], {
                                interactive: false,
                                icon: L.divIcon({
                                    className: '',
                                    iconSize: null,
                                    html: '<div style="transform:translate(-50%,-165%);display:inline-block;white-space:nowrap;' +
                                          'background:#111827;color:#fff;font-size:10px;font-weight:600;' +
                                          'padding:2px 7px;border-radius:9999px;box-shadow:0 1px 4px rgba(0,0,0,.35);' +
                                          'border:1.5px solid ' + g.color + ';">' + label + '</div>'
                                })
                            }).addTo(connectors);
                        });

                        // Mounted on the street layer, not the map, so switching
                        // barangay mid-hover sweeps the pointers away with it
                        connectors.addTo(saStreetLayer);
                    };
                    const hideConnectors = () => {
                        if (connectors) { saStreetLayer.removeLayer(connectors); connectors = null; }
                    };

                    // ONE tooltip per street bound to a featureGroup that is
                    // itself mounted on the layer — the group then has a map
                    // reference, which sticky tooltips need in order to open.
                    const streetGroup = L.featureGroup(g.casing.concat(g.inner)).addTo(saStreetLayer);
                    streetGroup.bindTooltip(tip, { sticky: true, direction: 'top', opacity: 0.95 });
                    streetGroup.on('mouseover', () => { highlight(true); showConnectors(); });
                    streetGroup.on('mouseout', () => {
                        highlight(false);
                        hideConnectors();
                        streetGroup.closeTooltip();   // never leave it hanging open
                    });
                    // Click opens the street modal: filtered mini-map, full
                    // incident details, and AI suggestions for this street
                    streetGroup.on('click', () => {
                        highlight(false);
                        hideConnectors();
                        streetGroup.closeTooltip();
                        openStreetModal(name, g);
                    });
                });

                return saStreetLayer;
            })();

            return saStreetsLoading;
        }

        function updateSanAgustinStreetVisibility() {
            const select = document.getElementById('barangay');
            if (!select) return;
            const label = (nameByPsgcCode[select.value] ||
                (select.selectedOptions[0] || {}).textContent || '').trim().toLowerCase();

            if (select.value && label === 'san agustin') {
                ensureSanAgustinStreets().then(layer => {
                    // Re-check: the filter may have changed while streets loaded
                    const current = document.getElementById('barangay');
                    const still = (nameByPsgcCode[current.value] ||
                        (current.selectedOptions[0] || {}).textContent || '').trim().toLowerCase();
                    if (layer && current.value && still === 'san agustin' && !map.hasLayer(layer)) {
                        layer.addTo(map);
                        // Color-criteria legend rides along with the street layer
                        if (!saStreetLegendCtl) saStreetLegendCtl = makeStreetLegend();
                        saStreetLegendCtl.addTo(map);
                    }
                });
            } else if (saStreetLayer && map.hasLayer(saStreetLayer)) {
                map.removeLayer(saStreetLayer);
                if (saStreetLegendCtl) saStreetLegendCtl.remove();
            }
        }

        // ------------------------------------------------------------------
        // San Agustin street modal — opened by clicking a street. Shows the
        // street alone on a filtered mini-map with a dot where each crime
        // happened, the full details of every incident, and AI suggestions.
        // ------------------------------------------------------------------
        const SA_STREET_DETAIL_URL = @json(route('pattern-detection.street-detail'));
        const SA_STREET_AI_URL = @json(route('pattern-detection.street-ai-suggest'));
        const SA_AI_SAVE_URL = @json(route('pattern-detection.ai-save'));

        let streetModalMap = null;        // mini Leaflet map, created once (same tile setup as the big map)
        let streetModalBase = null;       // static context: boundary + every street + name labels
        let miniStreets = {};             // street name -> {lines, label, color}
        let streetCrimeLayers = {};       // street name -> layerGroup of its crime dots (cached)
        let streetDetailCache = {};       // street name -> /street-detail payload (cached)
        let modalStreets = [];            // ACTIVE selection; [0] = first-clicked street (locked in)
        let currentStreetModalName = null;// first-clicked street
        let streetModalMarkers = {};      // incident code -> circle marker
        let streetModalSeq = 0;           // stale-async guard
        const accCollapsed = new Set();   // streets whose crime group is collapsed

        // Every street stays marked but subtle — in its own crime-level colour
        // at low opacity, so the color coding reads even before selecting.
        // Only selected streets render at full strength.
        function mutedStyleFor(color) {
            return { color: color || '#64748b', weight: 1.8, opacity: 0.35 };
        }

        // "21:35" -> "9:35 PM" — every displayed time is 12-hour format
        function fmt12h(t) {
            const m = /^(\d{1,2}):(\d{2})/.exec(String(t || ''));
            if (!m) return String(t || '');
            let h = parseInt(m[1], 10);
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return h + ':' + m[2] + ' ' + ampm;
        }

        // Stable colour per crime category (same golden-angle trick as streets)
        function colorForCategory(cat) {
            let h = 0;
            const s = String(cat || 'Uncategorized');
            for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
            return 'hsl(' + Math.round((h * 137.508) % 360) + ', 72%, 40%)';
        }

        // Build the static context ONCE: barangay boundary + every street as a
        // subtle low-opacity gray line (NO name labels — 131 labels were
        // unreadable; only ACTIVE streets get one). The map itself comes from
        // createCrimeMap(), the same component the main crime mapping uses.
        function ensureStreetModalBase() {
            if (streetModalMap) return;

            streetModalMap = createCrimeMap('streetModalMap');
            streetModalBase = L.layerGroup().addTo(streetModalMap);
            makeStreetLegend().addTo(streetModalMap);   // same color criteria as the main map

            // Barangay San Agustin boundary — faint dashed ring for context
            const saCode = Object.keys(nameByPsgcCode || {}).find(function (c) {
                return String(nameByPsgcCode[c] || '').trim().toLowerCase() === 'san agustin';
            });
            if (saCode && barangayRingsByCode[saCode]) {
                barangayRingsByCode[saCode].forEach(function (poly) {
                    poly.forEach(function (ring) {
                        L.polygon(ring.map(function (c) { return [c[1], c[0]]; }), {
                            color: '#274d4c', weight: 2, opacity: 0.75, dashArray: '6,4',
                            fillColor: '#e8f5f3', fillOpacity: 0.08, interactive: false
                        }).addTo(streetModalBase);
                    });
                });
            }

            // Every street: subtle gray line. Hover behaves EXACTLY like the
            // main mapping — the whole street highlights and the same stats
            // tooltip appears. Clicking toggles the street in/out of the
            // selection.
            Object.entries(saStreetGroupsAll || {}).forEach(function (entry) {
                const name = entry[0], g = entry[1];

                const lines = g.inner.map(function (src) {
                    return L.polyline(src.getLatLngs(), mutedStyleFor(g.color));
                });

                // Same grouped hover as the main map: one featureGroup per
                // street so any segment lights up the entire street
                const grp = L.featureGroup(lines).addTo(streetModalBase);
                const st = saStreetStatsAll[name];
                const sev = severityForCount(st ? st.count : 0);
                const tip = '<div style="font-weight:700;margin-bottom:2px;">' + escStreet(name) + '</div>' +
                    '<div style="margin-bottom:3px;"><span style="display:inline-block;width:28px;height:5px;border-radius:3px;background:' + g.color + ';vertical-align:middle;"></span>' +
                    ' <span style="font-weight:700;color:' + sev.color + ';">' + sev.label + '</span></div>' +
                    (st
                        ? '<div>' + st.count + ' crime' + (st.count === 1 ? '' : 's') +
                          (st.top_category ? ' · mostly ' + escStreet(st.top_category) : '') + '</div>' +
                          (st.peak_hours && st.peak_hours.length
                              ? '<div style="color:#c4b5fd;">Peak hours: ' + st.peak_hours.map(escStreet).join(', ') + '</div>' : '')
                        : '<div>No recorded crimes — cleared</div>') +
                    '<div style="margin-top:3px;color:#93c5fd;font-weight:600;"><i class="fas fa-hand-pointer"></i> Click to select / unselect</div>';
                grp.bindTooltip(tip, { sticky: true, direction: 'top', opacity: 0.95 });

                grp.on('mouseover', function () {
                    if (modalStreets.indexOf(name) === -1) {
                        lines.forEach(function (l) { l.setStyle({ color: g.color, weight: 4, opacity: 0.95 }); });
                    }
                });
                grp.on('mouseout', function () {
                    if (modalStreets.indexOf(name) === -1) {
                        lines.forEach(function (l) { l.setStyle(mutedStyleFor(g.color)); });
                    }
                    grp.closeTooltip();   // never leave it hanging open
                });
                grp.on('click', function () {
                    grp.closeTooltip();
                    toggleModalStreet(name);
                });

                miniStreets[name] = { lines: lines, label: null, color: g.color };
            });
        }

        function styleModalStreet(name, active) {
            const ms = miniStreets[name];
            if (!ms) return;

            ms.lines.forEach(function (l) {
                l.setStyle(active ? { color: ms.color, weight: 5, opacity: 1 } : mutedStyleFor(ms.color));
                if (active && l.bringToFront) l.bringToFront();
            });

            // Name label rides the line ONLY while the street is active
            if (active && !ms.label) {
                let longest = null, longestLen = -1;
                ms.lines.forEach(function (l) {
                    const pts = l.getLatLngs();
                    let len = 0;
                    for (let i = 0; i < pts.length - 1; i++) len += pts[i].distanceTo(pts[i + 1]);
                    if (len > longestLen) { longestLen = len; longest = l; }
                });
                if (longest) {
                    const pts = longest.getLatLngs();
                    ms.label = L.tooltip({ permanent: true, direction: 'top', className: 'street-name-mini snm-active', offset: [0, -6], interactive: false })
                        .setLatLng(pts[Math.floor(pts.length / 2)])
                        .setContent(escStreet(name))
                        .addTo(streetModalBase);
                }
            } else if (!active && ms.label) {
                streetModalBase.removeLayer(ms.label);
                ms.label = null;
            }
        }

        // Fetch one street's crimes (server + client cached) and build its dot layer
        async function ensureStreetCrimes(name) {
            if (!streetDetailCache[name]) {
                const res = await fetch(SA_STREET_DETAIL_URL + '?street=' + encodeURIComponent(name),
                    { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.error) throw new Error(data.message || data.error);
                streetDetailCache[name] = data;
            }
            if (!streetCrimeLayers[name]) {
                const layer = L.layerGroup();
                (streetDetailCache[name].incidents || []).forEach(function (inc) {
                    if (!isFinite(inc.lat) || !isFinite(inc.lng)) return;
                    const c = colorForCategory(inc.category);
                    const marker = L.circleMarker([inc.lat, inc.lng], {
                        radius: 8, color: '#111827', weight: 1.5, fillColor: c, fillOpacity: 0.95
                    });
                    marker.bindPopup(
                        '<div style="min-width:190px;">' +
                            '<div style="font-weight:800;font-size:12px;color:' + c + ';">' + escStreet(inc.category) + '</div>' +
                            '<div style="font-weight:700;font-size:12px;margin:2px 0;">' + escStreet(inc.title || inc.code) + '</div>' +
                            '<div style="font-size:11px;color:#4b5563;">' + escStreet(inc.date || '') + (inc.time ? ' · ' + escStreet(fmt12h(inc.time)) : '') + '</div>' +
                            '<div style="font-size:11px;color:#4b5563;">Status: ' + escStreet(inc.status || '—') + '</div>' +
                            '<div style="font-size:10px;color:#9ca3af;font-family:monospace;margin-top:2px;">' + escStreet(inc.code) + '</div>' +
                        '</div>');
                    marker.addTo(layer);
                    streetModalMarkers[inc.code] = marker;
                });
                streetCrimeLayers[name] = layer;
            }
            return streetDetailCache[name];
        }

        async function activateModalStreet(name) {
            styleModalStreet(name, true);
            const seq = streetModalSeq;
            try {
                await ensureStreetCrimes(name);
            } catch (e) {
                console.error('Street detail failed:', e);
            }
            // Only mount if the street is still selected and the modal not reopened
            if (seq !== streetModalSeq || modalStreets.indexOf(name) === -1) return;
            if (streetCrimeLayers[name] && !streetModalMap.hasLayer(streetCrimeLayers[name])) {
                streetCrimeLayers[name].addTo(streetModalMap);
            }
            renderCrimeAccordions();
            updateStreetModalHeader();
        }

        function deactivateModalStreet(name) {
            styleModalStreet(name, false);
            if (streetCrimeLayers[name] && streetModalMap && streetModalMap.hasLayer(streetCrimeLayers[name])) {
                streetModalMap.removeLayer(streetCrimeLayers[name]);
            }
        }

        // Toggle a street from the mini map or the filter dropdown. The FIRST
        // clicked street is locked in — selecting more never removes it.
        // Deliberately NO re-fit here: the map keeps its current view so
        // selecting streets never yanks the focus away.
        function toggleModalStreet(name) {
            const idx = modalStreets.indexOf(name);
            if (idx === -1) {
                modalStreets.push(name);
                activateModalStreet(name);
            } else {
                if (idx === 0) return;               // the first street stays
                modalStreets.splice(idx, 1);
                deactivateModalStreet(name);
            }
            renderCrimeAccordions();
            updateStreetModalHeader();
            renderStreetFilterList(document.getElementById('streetFilterSearch').value);
        }

        // Auto-zoom: frame every ACTIVE street (snap, no animation)
        function fitModalToActive() {
            if (!streetModalMap) return;
            const bounds = L.latLngBounds([]);
            modalStreets.forEach(function (name) {
                const ms = miniStreets[name];
                if (ms) ms.lines.forEach(function (l) { bounds.extend(l.getBounds()); });
            });
            if (bounds.isValid()) {
                streetModalMap.invalidateSize();
                streetModalMap.fitBounds(bounds.pad(0.2), { maxZoom: 18, animate: false });
            }
        }

        function openStreetModal(name, g) {
            streetModalSeq++;
            currentStreetModalName = name;

            document.getElementById('streetModal').style.display = 'flex';
            ensureStreetModalBase();

            // Reset the selection: mute everything, then activate only the
            // clicked street (more can be added via map clicks or the filter)
            modalStreets.slice().forEach(function (n) { deactivateModalStreet(n); });
            modalStreets = [name];
            accCollapsed.clear();

            document.getElementById('streetModalSwatch').style.background =
                (miniStreets[name] || g || {}).color || '#64748b';
            document.getElementById('streetModalPills').innerHTML =
                '<span class="sm-pill"><i class="fas fa-spinner fa-spin"></i> Loading…</span>';
            document.getElementById('streetIncCount').textContent = '';
            document.getElementById('streetIncidentList').innerHTML =
                '<div style="font-size:12px;color:#9ca3af;padding:12px;"><i class="fas fa-spinner fa-spin mr-1"></i>Loading crimes…</div>';
            document.getElementById('streetFilterSearch').value = '';
            document.getElementById('streetFilterPanel').style.display = 'none';

            updateStreetModalHeader();
            renderStreetFilterList('');
            activateModalStreet(name);

            // Auto-zoom to the clicked street; double pass because the modal
            // was hidden a moment ago and the container needs to settle
            setTimeout(fitModalToActive, 80);
            setTimeout(fitModalToActive, 300);

            resetStreetAiSection();   // AI runs ONLY when Analyze is pressed
        }

        // ---------- header: title, aggregate pills, filter button ----------
        function updateStreetModalHeader() {
            const first = modalStreets[0] || '';
            document.getElementById('streetModalName').textContent =
                modalStreets.length > 1 ? first + ' +' + (modalStreets.length - 1) + ' more' : first;
            document.getElementById('streetFilterBtnLabel').textContent = 'Streets (' + modalStreets.length + ')';
            document.getElementById('streetAiAnalyzeLabel').textContent =
                'Generate suggestions (' + modalStreets.length + ' street' + (modalStreets.length === 1 ? '' : 's') + ')';

            // Aggregate the pills over every active street whose data is in
            let total = 0, unresolved = 0, loaded = 0;
            const cats = {};
            modalStreets.forEach(function (n) {
                const d = streetDetailCache[n];
                if (!d) return;
                loaded++;
                total += (d.summary && d.summary.count) || 0;
                unresolved += (d.summary && d.summary.unresolved) || 0;
                Object.entries((d.summary && d.summary.categories) || {}).forEach(function (e) {
                    cats[e[0]] = (cats[e[0]] || 0) + e[1];
                });
            });
            const top = Object.keys(cats).sort(function (a, b) { return cats[b] - cats[a]; })[0];

            const pills = [];
            if (total === 0 && loaded > 0 && loaded === modalStreets.length) {
                pills.push('<span class="sm-pill" style="background:#dcfce7;color:#15803d;"><i class="fas fa-circle-check"></i>Cleared — no crime</span>');
            } else {
                pills.push('<span class="sm-pill"><i class="fas fa-triangle-exclamation" style="color:#b45309;"></i>' +
                    total + ' crime' + (total === 1 ? '' : 's') + '</span>');
            }
            if (top) {
                pills.push('<span class="sm-pill"><span style="width:9px;height:9px;border-radius:50%;background:' +
                    colorForCategory(top) + ';display:inline-block;"></span>Mostly ' + escStreet(top) + '</span>');
            }
            if (unresolved > 0) {
                pills.push('<span class="sm-pill" style="background:#fef2f2;color:#b91c1c;"><i class="fas fa-folder-open"></i>' +
                    unresolved + ' unresolved</span>');
            }
            if (loaded < modalStreets.length) {
                pills.push('<span class="sm-pill"><i class="fas fa-spinner fa-spin"></i> Loading…</span>');
            }
            document.getElementById('streetModalPills').innerHTML = pills.join('');
        }

        // ---------- street filter dropdown (top of the modal) ----------
        function toggleStreetFilterPanel() {
            const p = document.getElementById('streetFilterPanel');
            const show = p.style.display !== 'block';
            p.style.display = show ? 'block' : 'none';
            if (show) {
                renderStreetFilterList(document.getElementById('streetFilterSearch').value);
                document.getElementById('streetFilterSearch').focus();
            }
        }
        document.addEventListener('click', function (e) {
            const wrap = document.getElementById('streetFilterWrap');
            const panel = document.getElementById('streetFilterPanel');
            if (wrap && panel && !wrap.contains(e.target)) {
                panel.style.display = 'none';
            }
        });

        function renderStreetFilterList(filter) {
            const q = String(filter || '').toLowerCase().trim();
            const names = Object.keys(saStreetGroupsAll || {}).sort(function (a, b) { return a.localeCompare(b); });
            const rows = [];

            names.forEach(function (n) {
                const first = n === modalStreets[0];
                const checked = modalStreets.indexOf(n) !== -1;
                if (!checked && q && !n.toLowerCase().includes(q)) return;
                const row = '<label style="display:flex;align-items:center;gap:7px;font-size:12px;color:#374151;cursor:pointer;padding:3px 2px;' + (first ? 'font-weight:700;' : '') + '">' +
                    '<input type="checkbox" class="street-filter-cb" value="' + escStreet(n) + '"' +
                        (checked ? ' checked' : '') + (first ? ' disabled' : '') + '>' +
                    '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + escStreet(n) + '">' + escStreet(n) +
                        (first ? ' <span style="color:#7c3aed;font-size:9px;">(clicked street)</span>' : '') + '</span>' +
                '</label>';
                if (checked) rows.unshift(row); else rows.push(row);
            });

            document.getElementById('streetFilterList').innerHTML =
                rows.join('') || '<div style="font-size:11px;color:#9ca3af;padding:4px 0;">No matching streets.</div>';
        }

        document.getElementById('streetFilterList').addEventListener('change', function (e) {
            const cb = e.target;
            if (!cb.classList || !cb.classList.contains('street-filter-cb')) return;
            toggleModalStreet(cb.value);
        });

        function clearModalStreets() {
            modalStreets.slice(1).forEach(function (n) { deactivateModalStreet(n); });
            modalStreets = modalStreets.slice(0, 1);
            renderCrimeAccordions();
            updateStreetModalHeader();
            renderStreetFilterList(document.getElementById('streetFilterSearch').value);
        }

        function closeStreetModal() {
            dockAllSections();   // pull every popped-out section back first
            const m = document.getElementById('streetModal');
            if (m) m.style.display = 'none';
        }

        // ------------------------------------------------------------------
        // Section pop-outs: the map, the crime list and the suggestions can
        // each detach into their own draggable, resizable window — like
        // separate browser windows. On small screens the window goes
        // fullscreen, which also frees the page from the map's touch-trap.
        // ------------------------------------------------------------------
        const POPOUT_META = {
            map:    { section: 'secMap',      title: 'Street map',                    icon: 'fa-map-location-dot' },
            crimes: { section: 'secCrimes',   title: 'Crimes on selected street(s)',  icon: 'fa-list-ul' },
            sugg:   { section: 'streetAiCol', title: 'Prevention suggestions',        icon: 'fa-shield-halved' }
        };
        const activePopouts = {};   // key -> {win, ph, sec}

        function createPopoutWindow(key) {
            const meta = POPOUT_META[key];
            const win = document.createElement('div');
            win.id = 'popout-' + key;
            win.className = 'sm-popout';
            win.innerHTML =
                '<div class="sm-popout-head">' +
                    '<i class="fas ' + meta.icon + '"></i><span>' + meta.title + '</span>' +
                    '<button type="button" class="sm-popout-dock"><i class="fas fa-window-minimize mr-1"></i>Return to modal</button>' +
                '</div>' +
                '<div class="sm-popout-body"></div>';
            document.body.appendChild(win);

            win.querySelector('.sm-popout-dock').addEventListener('click', function () { dockSection(key); });

            // Drag by the header (desktop; fullscreen on mobile so no-op there)
            const head = win.querySelector('.sm-popout-head');
            let dragging = false, sx = 0, sy = 0, ox = 0, oy = 0;
            head.addEventListener('pointerdown', function (e) {
                if (e.target.closest('button')) return;
                dragging = true;
                sx = e.clientX; sy = e.clientY;
                const r = win.getBoundingClientRect();
                ox = r.left; oy = r.top;
                head.setPointerCapture(e.pointerId);
            });
            head.addEventListener('pointermove', function (e) {
                if (!dragging) return;
                win.style.left = Math.max(0, Math.min(window.innerWidth - 90, ox + e.clientX - sx)) + 'px';
                win.style.top = Math.max(0, Math.min(window.innerHeight - 44, oy + e.clientY - sy)) + 'px';
            });
            head.addEventListener('pointerup', function () { dragging = false; });

            // Resizing the window must redraw the map inside it
            if (window.ResizeObserver) {
                new ResizeObserver(function () {
                    if (key === 'map' && streetModalMap && activePopouts[key]) streetModalMap.invalidateSize();
                }).observe(win);
            }

            const off = { map: 36, crimes: 72, sugg: 108 }[key] || 36;
            win.style.left = off + 'px';
            win.style.top = off + 'px';

            return win;
        }

        function popOutSection(key) {
            const meta = POPOUT_META[key];
            const sec = meta ? document.getElementById(meta.section) : null;
            if (!sec || activePopouts[key]) return;

            const win = document.getElementById('popout-' + key) || createPopoutWindow(key);

            // Leave an invisible placeholder so docking puts the section back
            // in exactly the same spot
            const ph = document.createElement('div');
            ph.style.display = 'none';
            sec.parentNode.insertBefore(ph, sec);
            win.querySelector('.sm-popout-body').appendChild(sec);
            win.style.display = 'flex';
            activePopouts[key] = { win: win, ph: ph, sec: sec };

            if (key === 'map' && streetModalMap) setTimeout(function () { streetModalMap.invalidateSize(); }, 60);
        }

        function dockSection(key) {
            const p = activePopouts[key];
            if (!p) return;
            p.ph.parentNode.insertBefore(p.sec, p.ph);
            p.ph.remove();
            p.win.style.display = 'none';
            delete activePopouts[key];
            if (key === 'map' && streetModalMap) setTimeout(function () { streetModalMap.invalidateSize(); }, 60);
        }

        function dockAllSections() {
            Object.keys(activePopouts).forEach(dockSection);
        }

        // Enlarge/shrink the suggestions panel: full-width + bigger text so
        // it stays readable on small screens
        function toggleStreetAiSize() {
            const col = document.getElementById('streetAiCol');
            if (!col) return;
            const big = col.classList.toggle('sm-ai-big');
            const icon = document.querySelector('#streetAiZoomBtn i');
            if (icon) icon.className = 'fas ' + (big ? 'fa-magnifying-glass-minus' : 'fa-magnifying-glass-plus');
        }

        // Expand/compress the modal to fill the screen. The map needs a size
        // recalc after the container changes, or tiles render half-blank.
        function toggleStreetModalFullscreen() {
            const modal = document.getElementById('streetModal');
            const card = modal ? modal.querySelector('.sm-card') : null;
            if (!modal || !card) return;

            const full = card.classList.toggle('sm-full');
            modal.classList.toggle('sm-nopad', full);

            const icon = document.getElementById('streetModalFsIcon');
            if (icon) icon.className = 'fas ' + (full ? 'fa-compress' : 'fa-expand');

            if (streetModalMap) {
                setTimeout(function () { streetModalMap.invalidateSize(); }, 60);
            }
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeStreetModal();
        });

        // ---------- right column: crimes grouped per street (collapsible) ----------
        function crimeCardHtml(inc) {
            const c = colorForCategory(inc.category);
            const meta = [];
            if (inc.victim_count > 0) meta.push(inc.victim_count + ' victim' + (inc.victim_count === 1 ? '' : 's'));
            if (inc.suspect_count > 0) meta.push(inc.suspect_count + ' suspect' + (inc.suspect_count === 1 ? '' : 's'));
            if (inc.weather) meta.push(escStreet(inc.weather));
            return '<div class="sm-inc-card" data-code="' + escStreet(inc.code) + '">' +
                '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">' +
                    '<span style="font-size:10px;font-weight:800;color:#fff;background:' + c + ';padding:2px 8px;border-radius:9999px;">' + escStreet(inc.category) + '</span>' +
                    '<span style="font-size:10px;color:#9ca3af;font-family:monospace;">' + escStreet(inc.code) + '</span>' +
                    '<span style="margin-left:auto;font-size:10px;font-weight:700;color:' +
                        (['solved','resolved','closed','cleared'].indexOf(String(inc.status || '').toLowerCase()) >= 0 ? '#15803d' : '#b45309') + ';">' +
                        escStreet(String(inc.status || '—').toUpperCase()) + '</span>' +
                '</div>' +
                '<div style="font-size:13px;font-weight:700;color:#111;margin-top:6px;">' + escStreet(inc.title || 'Crime') + '</div>' +
                '<div style="font-size:11px;color:#6b7280;margin-top:2px;"><i class="fas fa-calendar mr-1"></i>' +
                    escStreet(inc.date || '—') + (inc.time ? ' · <i class="fas fa-clock mr-1"></i>' + escStreet(fmt12h(inc.time)) : '') + '</div>' +
                (inc.description ? '<div style="font-size:11.5px;color:#4b5563;margin-top:6px;line-height:1.45;">' + escStreet(inc.description) + '</div>' : '') +
                (inc.modus_operandi ? '<div style="font-size:11px;color:#6b7280;margin-top:4px;"><span style="font-weight:700;">Modus:</span> ' + escStreet(inc.modus_operandi) + '</div>' : '') +
                (meta.length ? '<div style="font-size:11px;color:#6b7280;margin-top:4px;">' + meta.join(' · ') + '</div>' : '') +
                (inc.clearance_status ? '<div style="font-size:11px;color:#6b7280;margin-top:2px;"><span style="font-weight:700;">Clearance:</span> ' +
                    escStreet(inc.clearance_status) + (inc.clearance_date ? ' (' + escStreet(inc.clearance_date) + ')' : '') + '</div>' : '') +
                (inc.assigned_officer ? '<div style="font-size:11px;color:#6b7280;margin-top:2px;"><span style="font-weight:700;">Officer:</span> ' + escStreet(inc.assigned_officer) + '</div>' : '') +
                '<div style="font-size:10.5px;color:#9ca3af;margin-top:4px;"><i class="fas fa-location-dot mr-1"></i>' + escStreet(inc.address || '') + '</div>' +
            '</div>';
        }

        // One collapsible group per selected street, newest selection last
        function renderCrimeAccordions() {
            let grand = 0;
            const html = modalStreets.map(function (n) {
                const ms = miniStreets[n] || { color: '#64748b' };
                const d = streetDetailCache[n];
                const collapsed = accCollapsed.has(n);

                let countChip, bodyHtml;
                if (!d) {
                    countChip = '<i class="fas fa-spinner fa-spin" style="color:#9ca3af;"></i>';
                    bodyHtml = '<div style="font-size:12px;color:#9ca3af;">Loading crimes…</div>';
                } else {
                    const incs = d.incidents || [];
                    grand += incs.length;
                    if (incs.length) {
                        countChip = '<span style="font-size:10px;font-weight:800;background:' + ms.color + ';color:#fff;border-radius:9999px;padding:2px 8px;">' + incs.length + '</span>';
                        bodyHtml = incs.map(crimeCardHtml).join('');
                    } else {
                        // A selected street with zero crimes stays active — it
                        // simply reads as CLEARED
                        countChip = '<span style="font-size:10px;font-weight:800;background:#dcfce7;color:#15803d;border-radius:9999px;padding:2px 8px;"><i class="fas fa-circle-check mr-1"></i>CLEARED</span>';
                        bodyHtml = '<div style="display:flex;align-items:center;gap:8px;font-size:12px;color:#15803d;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px 12px;">' +
                            '<i class="fas fa-circle-check"></i><span><span style="font-weight:700;">No crime on this street — cleared.</span> Keep routine patrol coverage to sustain it.</span></div>';
                    }
                }

                return '<div class="sm-acc">' +
                    '<button type="button" class="sm-acc-head" data-street="' + escStreet(n) + '">' +
                        '<span style="width:14px;height:5px;border-radius:3px;background:' + ms.color + ';flex-shrink:0;"></span>' +
                        '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escStreet(n) + '</span>' +
                        countChip +
                        '<i class="fas fa-chevron-' + (collapsed ? 'down' : 'up') + '" style="font-size:10px;color:#9ca3af;"></i>' +
                    '</button>' +
                    (collapsed ? '' : '<div class="sm-acc-body">' + bodyHtml + '</div>') +
                '</div>';
            }).join('');

            document.getElementById('streetIncCount').textContent = '(' + grand + ')';
            document.getElementById('streetIncidentList').innerHTML =
                html || '<div style="font-size:12px;color:#9ca3af;padding:12px;">No street selected.</div>';
        }

        // One delegated listener covers accordion headers AND crime cards
        document.getElementById('streetIncidentList').addEventListener('click', function (e) {
            const head = e.target.closest('.sm-acc-head');
            if (head) {
                const n = head.dataset.street;
                if (accCollapsed.has(n)) accCollapsed.delete(n); else accCollapsed.add(n);
                renderCrimeAccordions();
                return;
            }
            const card = e.target.closest('.sm-inc-card');
            if (card) {
                const marker = streetModalMarkers[card.dataset.code];
                if (marker) {
                    streetModalMap.setView(marker.getLatLng(), Math.max(streetModalMap.getZoom(), 18));
                    marker.openPopup();
                }
            }
        });

        // ---------- street AI: manual, analyzes the modal's selected streets ----------
        let latestStreetAi = null;         // last successful result, for Save
        let streetAiSeq = 0;               // stale-response guard

        // Fresh AI state each time the modal opens: nothing analyzed yet
        function resetStreetAiSection() {
            latestStreetAi = null;
            streetAiSeq++;

            document.getElementById('streetAiPlaceholder').style.display = 'block';
            document.getElementById('streetAiLoading').style.display = 'none';
            document.getElementById('streetAiError').style.display = 'none';
            document.getElementById('streetAiResults').style.display = 'none';
            document.getElementById('streetAiRisk').style.display = 'none';
            const save = document.getElementById('streetAiSaveBtn');
            save.style.display = 'none';
            save.disabled = false;
            save.style.background = '#7c3aed';
            save.innerHTML = '<i class="fas fa-floppy-disk mr-1"></i>Save';
        }

        async function analyzeStreetAi() {
            const seq = ++streetAiSeq;
            const loading = document.getElementById('streetAiLoading');
            const errBox = document.getElementById('streetAiError');
            const results = document.getElementById('streetAiResults');
            const risk = document.getElementById('streetAiRisk');
            const analyzeBtn = document.getElementById('streetAiAnalyzeBtn');

            // Analyze exactly what is selected in the modal (map + filter)
            const picked = modalStreets.slice(0, 10);
            if (!picked.length && currentStreetModalName) picked.push(currentStreetModalName);
            if (!picked.length) return;

            document.getElementById('streetAiPlaceholder').style.display = 'none';
            document.getElementById('streetAiSaveBtn').style.display = 'none';
            loading.style.display = 'block';
            errBox.style.display = 'none';
            results.style.display = 'none';
            risk.style.display = 'none';
            analyzeBtn.disabled = true;
            analyzeBtn.style.opacity = '0.6';

            try {
                const params = new URLSearchParams();
                picked.forEach(function (s) { params.append('streets[]', s); });
                const res = await fetch(SA_STREET_AI_URL + '?' + params,
                    { headers: { 'Accept': 'application/json' } });

                // Gateway timeouts / proxy errors answer with an HTML page, not
                // JSON — surface a readable error instead of a parse crash
                let data;
                try {
                    data = JSON.parse(await res.text());
                } catch (parseErr) {
                    throw new Error(res.status === 504
                        ? 'The server took too long to respond. Press Retry.'
                        : 'Server error (HTTP ' + res.status + '). Press Retry.');
                }
                if (!data.success) throw new Error(data.error || (res.status === 429
                    ? 'Too many requests — wait a minute and press Retry.' : 'HTTP ' + res.status));
                if (seq !== streetAiSeq) return;   // user switched streets meanwhile

                latestStreetAi = data;
                const a = data.analysis || {};

                const RISK_CHIP = {
                    high:   'background:#fee2e2;color:#b91c1c;',
                    medium: 'background:#fef3c7;color:#b45309;',
                    low:    'background:#dcfce7;color:#15803d;'
                };
                const lvl = String(a.risk_level || 'low').toLowerCase();
                risk.style.cssText = 'display:inline-block;font-size:10px;font-weight:800;padding:2px 8px;border-radius:9999px;' +
                    (RISK_CHIP[lvl] || 'background:#f3f4f6;color:#374151;');
                risk.textContent = lvl.toUpperCase() + ' RISK';

                document.getElementById('streetAiSummary').textContent = a.summary || '';

                const prioStyle = {
                    high:   'background:#fee2e2;color:#b91c1c;',
                    medium: 'background:#fef3c7;color:#b45309;',
                    low:    'background:#f3f4f6;color:#374151;'
                };
                // "Basis — recorded crimes" box: what actually happened, how,
                // and how bad — grounds every suggestion in the real cases
                const SEV_CHIP = {
                    critical: 'background:#7f1d1d;color:#fff;',
                    high:     'background:#fee2e2;color:#b91c1c;',
                    moderate: 'background:#fef3c7;color:#b45309;',
                    low:      'background:#f3f4f6;color:#4b5563;'
                };
                const sevChip = function (sev) {
                    const s2 = String(sev || '').toLowerCase();
                    if (!SEV_CHIP[s2]) return '';
                    return '<span style="font-size:9.5px;font-weight:800;padding:2px 7px;border-radius:9999px;' + SEV_CHIP[s2] + '">' + s2.toUpperCase() + '</span>';
                };
                const evidenceBlock = function (ev) {
                    if (!ev || !ev.cases) return '';
                    const row = function (icon, html) {
                        return '<div style="display:flex;gap:6px;font-size:11px;color:#78350f;line-height:1.5;margin-top:2px;">' +
                            '<i class="fas ' + icon + '" style="color:#d97706;margin-top:2px;flex-shrink:0;"></i><span>' + html + '</span></div>';
                    };
                    return '<div style="margin-top:6px;padding:8px 10px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;">' +
                        '<div style="font-size:9.5px;font-weight:800;color:#92400e;text-transform:uppercase;margin-bottom:3px;"><i class="fas fa-magnifying-glass mr-1"></i>Basis — recorded crimes</div>' +
                        row('fa-hashtag', '<b>' + ev.cases + ' recorded case' + (ev.cases === 1 ? '' : 's') + '</b> (' + ev.share + '% of this street\'s crimes) — severity <b>' + escStreet(String(ev.severity || '').toUpperCase()) + '</b>.') +
                        (ev.modus && ev.modus.length ? row('fa-user-ninja', 'How they were committed: ' + ev.modus.map(escStreet).join('; ') + '.') : '') +
                        (typeof ev.unresolved === 'number' ? row('fa-folder-open', (ev.unresolved > 0
                            ? '<b>' + ev.unresolved + ' of ' + ev.cases + ' still unresolved</b> — follow up with the assigned officers.'
                            : 'All ' + ev.cases + ' cases already resolved.')) : '') +
                        ((ev.busiest_day || ev.latest) ? row('fa-calendar-day',
                            (ev.busiest_day ? 'Most cases fall on <b>' + escStreet(ev.busiest_day) + 's</b>. ' : '') +
                            (ev.latest ? 'Most recent case: <b>' + escStreet(ev.latest) + '</b>.' : '')) : '') +
                        caseLog(ev.cases_list) +
                    '</div>';
                };
                // Every recorded case with its date, day and exact time
                const caseLog = function (cases) {
                    if (!cases || !cases.length) return '';
                    const MAX = 8;
                    const rows = cases.slice(0, MAX).map(function (c) {
                        return '<div style="display:flex;flex-wrap:wrap;align-items:baseline;column-gap:7px;font-size:10.5px;color:#78350f;border-top:1px dashed #fde68a;padding:3px 0;">' +
                            '<span style="font-weight:800;">' + escStreet(c.date || '') + '</span>' +
                            (c.day ? '<span>(' + escStreet(c.day) + ')</span>' : '') +
                            (c.time ? '<span style="font-weight:700;color:#b45309;"><i class="fas fa-clock" style="margin-right:2px;"></i>' + escStreet(c.time) + '</span>' : '') +
                            (c.modus ? '<span style="color:#92400e;">— ' + escStreet(c.modus) + '</span>' : '') +
                            '<span style="margin-left:auto;font-weight:800;color:' + (c.resolved ? '#15803d' : '#b91c1c') + ';">' + (c.resolved ? 'RESOLVED' : 'UNRESOLVED') + '</span>' +
                        '</div>';
                    }).join('');
                    return '<div style="margin-top:5px;">' +
                        '<div style="font-size:9.5px;font-weight:800;color:#92400e;text-transform:uppercase;"><i class="fas fa-list-ul mr-1"></i>Case log (date · day · time)</div>' +
                        rows +
                        (cases.length > MAX ? '<div style="font-size:10px;color:#b45309;padding-top:3px;">+' + (cases.length - MAX) + ' more — press "View crimes" above for the full list.</div>' : '') +
                    '</div>';
                };

                const suggCard = function (s, showStreet) {
                    const imp = s.expected_impact || {};
                    const d = s.details || {};
                    const pct = Number(imp.estimated_change_percent);
                    const pr = String(s.priority || 'low').toLowerCase();
                    return '<div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;">' +
                        '<div style="display:flex;align-items:flex-start;gap:8px;">' +
                            '<div style="font-size:12.5px;font-weight:700;color:#111;flex:1;"><i class="fas fa-shield-halved mr-1" style="color:#7c3aed;"></i>' + escStreet(s.action) + '</div>' +
                            '<span style="flex-shrink:0;font-size:9.5px;font-weight:800;padding:2px 7px;border-radius:9999px;' + (prioStyle[pr] || prioStyle.low) + '">' + pr.toUpperCase() + '</span>' +
                        '</div>' +
                        (showStreet && s.street ? '<div style="font-size:11px;color:#b45309;font-weight:600;margin-top:3px;"><i class="fas fa-road mr-1"></i>' + escStreet(s.street) + '</div>' : '') +
                        (s.time_window ? '<div style="font-size:11px;color:#6d28d9;font-weight:600;margin-top:3px;"><i class="fas fa-clock mr-1"></i>' + escStreet(s.time_window) + '</div>' : '') +
                        (s.rationale ? '<div style="font-size:11.5px;color:#4b5563;margin-top:4px;line-height:1.45;">' + escStreet(s.rationale) + '</div>' : '') +
                        evidenceBlock(d.evidence) +
                        (d.coverage ? '<div style="font-size:11px;color:#374151;margin-top:5px;"><i class="fas fa-location-crosshairs mr-1" style="color:#7c3aed;"></i>' + escStreet(d.coverage) + '</div>' : '') +
                        (d.steps && d.steps.length ? '<div style="margin-top:6px;padding:8px 10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">' +
                            '<div style="font-size:9.5px;font-weight:800;color:#6b7280;text-transform:uppercase;margin-bottom:3px;">How to implement</div>' +
                            d.steps.map(function (st, i2) {
                                return '<div style="display:flex;gap:6px;font-size:11px;color:#4b5563;line-height:1.5;margin-top:2px;">' +
                                    '<span style="flex-shrink:0;font-weight:800;color:#7c3aed;">' + (i2 + 1) + '.</span><span>' + escStreet(st) + '</span></div>';
                            }).join('') + '</div>' : '') +
                        (d.resources ? '<div style="font-size:11px;color:#4b5563;margin-top:5px;"><i class="fas fa-toolbox mr-1" style="color:#7c3aed;"></i><span style="font-weight:700;color:#374151;">Needs:</span> ' + escStreet(d.resources) + '</div>' : '') +
                        (d.lead ? '<div style="font-size:11px;color:#4b5563;margin-top:3px;"><i class="fas fa-user-shield mr-1" style="color:#7c3aed;"></i><span style="font-weight:700;color:#374151;">Lead:</span> ' + escStreet(d.lead) + '</div>' : '') +
                        (d.timeline ? '<div style="font-size:11px;color:#4b5563;margin-top:3px;"><i class="fas fa-calendar-check mr-1" style="color:#7c3aed;"></i><span style="font-weight:700;color:#374151;">Timeline:</span> ' + escStreet(d.timeline) + '</div>' : '') +
                        (d.tips && d.tips.length ? '<div style="margin-top:6px;padding:8px 10px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">' +
                            '<div style="font-size:9.5px;font-weight:800;color:#0369a1;text-transform:uppercase;margin-bottom:3px;"><i class="fas fa-people-roof mr-1"></i>Prevention tips for residents</div>' +
                            d.tips.map(function (tp) {
                                return '<div style="display:flex;gap:6px;font-size:11px;color:#0c4a6e;line-height:1.5;margin-top:2px;">' +
                                    '<i class="fas fa-check" style="color:#0284c7;margin-top:2px;flex-shrink:0;"></i><span>' + escStreet(tp) + '</span></div>';
                            }).join('') + '</div>' : '') +
                        (isFinite(pct) ? '<div style="font-size:11px;font-weight:700;color:' + (pct < 0 ? '#15803d' : '#374151') + ';margin-top:6px;">' +
                            '<i class="fas ' + (pct < 0 ? 'fa-arrow-trend-down' : 'fa-arrows-left-right') + ' mr-1"></i>' +
                            'If implemented: ' + (pct < 0 ? '~' + Math.abs(pct) + '% fewer crimes' : 'stable') +
                            (imp.explanation ? ' — <span style="font-weight:400;color:#6b7280;">' + escStreet(imp.explanation) + '</span>' : '') + '</div>' : '') +
                        (d.kpi ? '<div style="font-size:11px;color:#15803d;font-weight:600;margin-top:3px;"><i class="fas fa-bullseye mr-1"></i>' + escStreet(d.kpi) + '</div>' : '') +
                    '</div>';
                };

                // Rule-engine responses carry one SECTION PER STREET; render
                // each street separately with its own risk chip and summary
                let out;
                if (a.streets && a.streets.length) {
                    out = a.streets.map(function (sec) {
                        const sLvl = String(sec.risk_level || 'low').toLowerCase();

                        // One block PER CRIME TYPE — the counts add up to the
                        // street total, and each type carries its own tailored
                        // suggestion + a toggle listing that type's crimes
                        let body;
                        if (sec.categories && sec.categories.length) {
                            body = sec.categories.map(function (cb) {
                                const cc = colorForCategory(cb.category);
                                return '<div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">' +
                                    '<div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:#fafafa;flex-wrap:wrap;">' +
                                        '<span style="font-size:10px;font-weight:800;color:#fff;background:' + cc + ';padding:2px 8px;border-radius:9999px;">' + escStreet(cb.category) + '</span>' +
                                        sevChip(cb.severity) +
                                        '<span style="font-size:11px;font-weight:700;color:#374151;">' + cb.count + ' of ' + sec.total + ' crime' + (sec.total === 1 ? '' : 's') + ' (' + cb.share + '%)</span>' +
                                        (cb.peak_hours && cb.peak_hours.length ? '<span style="font-size:10.5px;color:#6d28d9;font-weight:600;"><i class="fas fa-clock mr-1"></i>' + cb.peak_hours.map(escStreet).join(', ') + '</span>' : '') +
                                        '<button type="button" class="cat-crimes-toggle" data-street="' + escStreet(sec.street) + '" data-cat="' + escStreet(cb.category) + '"' +
                                            ' style="margin-left:auto;font-size:10px;font-weight:700;color:#7c3aed;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:3px 9px;cursor:pointer;">' +
                                            '<i class="fas fa-list mr-1"></i>View crimes</button>' +
                                    '</div>' +
                                    '<div class="cat-crimes-list" style="display:none;padding:8px 10px;border-bottom:1px solid #f3f4f6;background:#fcfcfd;"></div>' +
                                    '<div style="padding:8px 10px;">' + suggCard(cb.suggestion || {}) + '</div>' +
                                '</div>';
                            }).join('');
                        } else {
                            body = (sec.suggestions || []).map(function (s) { return suggCard(s); }).join('')
                                || '<div style="font-size:11px;color:#9ca3af;">No suggestions.</div>';
                        }

                        return '<div style="border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px;background:#fcfcfd;">' +
                            '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">' +
                                '<span style="width:14px;height:5px;border-radius:3px;background:' + ((miniStreets[sec.street] || {}).color || '#64748b') + ';flex-shrink:0;"></span>' +
                                '<span style="font-size:12.5px;font-weight:800;color:#111;">' + escStreet(sec.street) + '</span>' +
                                '<span style="font-size:9.5px;font-weight:800;padding:2px 7px;border-radius:9999px;' + (RISK_CHIP[sLvl] || '') + '">' + sLvl.toUpperCase() + ' RISK</span>' +
                                (typeof sec.total === 'number' ? '<span style="font-size:10.5px;color:#6b7280;font-weight:700;">' + sec.total + ' total crime' + (sec.total === 1 ? '' : 's') + '</span>' : '') +
                            '</div>' +
                            (sec.summary ? '<div style="font-size:11.5px;color:#4b5563;margin-bottom:8px;">' + escStreet(sec.summary) + '</div>' : '') +
                            '<div style="display:grid;gap:8px;">' + body + '</div>' +
                        '</div>';
                    }).join('');
                } else {
                    // AI-engine (fallback) shape: one flat list, street shown per card
                    out = (a.suggestions || []).map(function (s) { return suggCard(s, true); }).join('');
                }
                document.getElementById('streetAiSuggestions').innerHTML =
                    out || '<div style="font-size:12px;color:#9ca3af;">No suggestions returned.</div>';

                loading.style.display = 'none';
                risk.style.display = 'inline-block';
                results.style.display = 'block';
                document.getElementById('streetAiSaveBtn').style.display = 'inline-block';
            } catch (e) {
                console.error('Street AI failed:', e);
                if (seq === streetAiSeq) {
                    loading.style.display = 'none';
                    document.getElementById('streetAiErrorMsg').textContent = e.message;
                    errBox.style.display = 'block';
                }
            } finally {
                analyzeBtn.disabled = false;
                analyzeBtn.style.opacity = '1';
            }
        }

        // "View crimes" toggle inside a crime-type block: lists that type's
        // actual crimes (from the already-fetched street detail cache)
        document.getElementById('streetAiSuggestions').addEventListener('click', function (e) {
            const btn = e.target.closest('.cat-crimes-toggle');
            if (!btn) return;
            const block = btn.closest('div').parentNode;
            const list = block ? block.querySelector('.cat-crimes-list') : null;
            if (!list) return;

            if (list.style.display !== 'none') {
                list.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-list mr-1"></i>View crimes';
                return;
            }

            const d = streetDetailCache[btn.dataset.street];
            const incs = ((d && d.incidents) || []).filter(function (i) { return i.category === btn.dataset.cat; });
            list.innerHTML = incs.length
                ? '<div style="font-size:9.5px;font-weight:800;color:#6b7280;text-transform:uppercase;margin-bottom:3px;">' +
                      escStreet(btn.dataset.cat) + ' crimes on ' + escStreet(btn.dataset.street) + '</div>' +
                  incs.map(function (i) {
                    const done = ['solved', 'resolved', 'closed', 'cleared'].indexOf(String(i.status || '').toLowerCase()) >= 0;
                    return '<div style="display:flex;gap:8px;align-items:baseline;font-size:11px;color:#4b5563;padding:2.5px 0;flex-wrap:wrap;border-top:1px dashed #f3f4f6;">' +
                        '<span style="font-family:monospace;color:#9ca3af;">' + escStreet(i.code) + '</span>' +
                        '<span style="font-weight:600;color:#111827;">' + escStreet(i.title || 'Crime') + '</span>' +
                        '<span>' + escStreet(i.date || '') + (i.time ? ' · ' + escStreet(fmt12h(i.time)) : '') + '</span>' +
                        '<span style="margin-left:auto;font-weight:700;color:' + (done ? '#15803d' : '#b45309') + ';">' + escStreet(String(i.status || '').toUpperCase()) + '</span>' +
                    '</div>';
                }).join('')
                : '<div style="font-size:11px;color:#9ca3af;">Crime list not loaded yet — this street\'s details are still loading.</div>';
            list.style.display = 'block';
            btn.innerHTML = '<i class="fas fa-chevron-up mr-1"></i>Hide crimes';
        });

        // Save the street AI report to the database — same endpoint and row
        // layout as the pattern-detection Save button
        async function saveStreetAi() {
            if (!latestStreetAi) return;
            const btn = document.getElementById('streetAiSaveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving…';

            try {
                const res = await fetch(SA_AI_SAVE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        meta: latestStreetAi.meta,
                        analysis: latestStreetAi.analysis,
                        data_source: 'real'
                    })
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || ('HTTP ' + res.status));

                btn.style.background = '#16a34a';
                btn.innerHTML = '<i class="fas fa-circle-check mr-1"></i>Saved (' + data.saved_rows + ' rows)';
            } catch (e) {
                console.error('Street AI save failed:', e);
                btn.disabled = false;
                btn.style.background = '#dc2626';
                btn.innerHTML = '<i class="fas fa-triangle-exclamation mr-1"></i>Save failed — retry';
            }
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
                document.getElementById('statTotalIncident').textContent = stats.total_incident ?? 0;
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
                const filteredData = data.filter(incident => {
                    if (!qcBounds) return true;
                    return qcBounds.contains([incident.latitude, incident.longitude]);
                });

                // Store data globally for right panel
                currentData = filteredData;
                selectedIncidentId = null;

                // Update right panel with statistics and incident list
                updateStatistics(filteredData);
                updateIncidentList(filteredData);

                // Update visualization based on selected mode
                currentVisualizationMode = visualizationMode;
                clearCurrentVisualization();

                if (visualizationMode === 'heatmap') {
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

        // Clear current visualization
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
            const barangaysPanel = document.getElementById('barangaysPanel');
            const severityLegend = document.getElementById('severityLegend');

            // Hide all panels first
            incidentsPanel.style.display = 'none';
            heatmapPanel.style.display = 'none';
            barangaysPanel.style.display = 'none';
            severityLegend.style.display = 'none';

            // Show the appropriate panel
            if (mode === 'heatmap') {
                heatmapPanel.style.display = 'flex';
            } else if (mode === 'clusters') {
                barangaysPanel.style.display = 'flex';
                populateBarangaysList();
            } else {
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
                    color: markerColor,
                    weight: markerWeightForZoom(),
                    opacity: 0.8,
                    fillOpacity: 0.7
                });

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

        // Display cluster view - grouped by barangay
        function displayClusters(data) {
            markerLayer = L.featureGroup();
            let barangayGroups = {};

            // Group incidents by barangay
            data.forEach(incident => {
                if (qcBounds && !qcBounds.contains([incident.latitude, incident.longitude])) {
                    return;
                }

                const barangayId = incident.barangay_id || 'unknown';
                const barangayName = incident.location || 'Unknown Barangay';

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

            // Calculate center for each barangay cluster
            Object.keys(barangayGroups).forEach(barangayId => {
                const group = barangayGroups[barangayId];
                const count = group.incidents.length;
                const stats = calculateClusterStats(group.incidents);

                // Center of cluster
                const centerLat = group.totalLat / count;
                const centerLng = group.totalLng / count;

                // Dynamic color based on incident count
                const clusterColor = getClusterColor(count);

                // Create cluster marker (shows count with dynamic color)
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
                            cursor: pointer;
                            transition: all 0.2s;
                        ">
                            ${count}
                        </div>
                    `,
                    iconSize: [40, 40],
                    iconAnchor: [20, 20],
                    popupAnchor: [0, -20]
                });

                const clusterMarker = L.marker([centerLat, centerLng], { icon: clusterIcon });

                // Create comprehensive popup
                const popupContent = `
                    <div style="min-width: 280px; font-family: Arial, sans-serif;">
                        <div style="border-bottom: 2px solid ${clusterColor}; padding-bottom: 8px; margin-bottom: 8px;">
                            <h3 style="margin: 0 0 4px; color: #111; font-size: 14px; font-weight: bold;">${group.name}</h3>
                            <div style="font-size: 12px; color: #666;">Cluster Summary</div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                            <div style="background: #f3f4f6; padding: 8px; border-radius: 4px;">
                                <div style="font-size: 11px; color: #666; font-weight: 600;">Total</div>
                                <div style="font-size: 16px; font-weight: bold; color: ${clusterColor};">${stats.total}</div>
                            </div>
                            <div style="background: #f3f4f6; padding: 8px; border-radius: 4px;">
                                <div style="font-size: 11px; color: #666; font-weight: 600;">Cleared</div>
                                <div style="font-size: 16px; font-weight: bold; color: #16a34a;">${stats.cleared}</div>
                            </div>
                        </div>

                        <div style="background: #fef3c7; padding: 8px; border-radius: 4px; margin-bottom: 10px; border-left: 3px solid #f59e0b;">
                            <div style="font-size: 11px; color: #92400e; font-weight: 600;">Most Common Crime</div>
                            <div style="font-size: 12px; color: #b45309; font-weight: bold;">${stats.mostCommon}</div>
                        </div>

                        <div style="display: flex; gap: 6px;">
                            <button class="cluster-view-list" data-barangay-id="${barangayId}" style="flex: 1; padding: 8px; background: #274d4c; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">
                                <i class="fas fa-list mr-1"></i>View List
                            </button>
                            <button class="cluster-zoom" style="flex: 1; padding: 8px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">
                                <i class="fas fa-search-plus mr-1"></i>Zoom In
                            </button>
                        </div>
                    </div>
                `;

                clusterMarker.bindPopup(popupContent, { maxWidth: 300 });

                // Handle View List button
                clusterMarker.on('popupopen', function() {
                    setTimeout(() => {
                        const popup = this.getPopup();
                        if (popup && popup._contentNode) {
                            const viewListBtn = popup._contentNode.querySelector('.cluster-view-list');
                            const zoomBtn = popup._contentNode.querySelector('.cluster-zoom');

                            if (viewListBtn) {
                                viewListBtn.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    showClusterIncidents(group.incidents, group.name);
                                    clusterMarker.closePopup();
                                });
                            }

                            if (zoomBtn) {
                                zoomBtn.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    const bounds = L.latLngBounds(
                                        group.incidents.map(i => [i.latitude, i.longitude])
                                    );
                                    map.fitBounds(bounds, { padding: [50, 50] });
                                    clusterMarker.closePopup();
                                });
                            }
                        }
                    }, 100);
                });

                clusterMarker.addTo(markerLayer);

                // Add individual markers for this barangay (hidden by default, shown when zoomed in)
                group.incidents.forEach(incident => {
                    const severity = getSeverityLevel(incident);
                    const severityColor = getSeverityIcon(severity);

                    const individualIcon = L.circleMarker([incident.latitude, incident.longitude], {
                        radius: 6,
                        fillColor: severityColor,
                        color: severityColor,
                        weight: 2,
                        opacity: 0.8,
                        fillOpacity: 0.8
                    });

                    // Store zoom level reference for showing/hiding
                    individualIcon._barangayId = barangayId;
                    individualIcon._centerLat = centerLat;
                    individualIcon._centerLng = centerLng;
                    individualIcon._severity = severity;

                    // Create popup for individual marker
                    const markerPopup = `
                        <div style="min-width: 220px; font-family: Arial, sans-serif;">
                            <div style="font-weight: bold; color: #111; margin-bottom: 6px; font-size: 12px;">
                                ${incident.incident_title}
                            </div>
                            <div style="font-size: 11px; color: #666; margin-bottom: 4px;">
                                <i class="fas fa-flag" style="color: ${severityColor}; margin-right: 4px;"></i>
                                <span style="text-transform: capitalize;">${severity}</span>
                            </div>
                            <div style="font-size: 11px; color: #666; margin-bottom: 4px;">
                                📅 ${incident.incident_date}
                            </div>
                            <div style="font-size: 11px; color: #666; margin-bottom: 8px;">
                                ${incident.category_name}
                            </div>
                            <button style="width: 100%; padding: 6px; background: #274d4c; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px; font-weight: 600;">
                                View Details
                            </button>
                        </div>
                    `;

                    individualIcon.bindPopup(markerPopup);

                    individualIcon.on('popupopen', function() {
                        setTimeout(() => {
                            const popup = this.getPopup();
                            if (popup && popup._contentNode) {
                                const button = popup._contentNode.querySelector('button');
                                if (button) {
                                    button.onclick = function(e) {
                                        e.stopPropagation();
                                        openIncidentModal(incident.id);
                                        individualIcon.closePopup();
                                    };
                                }
                            }
                        }, 100);
                    });

                    individualIcon.on('click', function() {
                        // Show popup instead of opening modal directly
                        this.openPopup();
                    });

                    individualIcon.addTo(markerLayer);
                });
            });

            markerLayer.addTo(map);

            // Handle zoom-based cluster/individual marker visibility
            // Remove old zoom handler if it exists to prevent duplicate handlers
            if (clusterZoomHandler) {
                map.off('zoomend', clusterZoomHandler);
            }

            clusterZoomHandler = function() {
                const currentZoom = map.getZoom();
                // Check if markerLayer still exists (in case it was cleared)
                if (!markerLayer) return;

                markerLayer.eachLayer(function(layer) {
                    if (layer instanceof L.Marker && layer.options.icon.options.className === 'cluster-marker') {
                        // Show cluster markers only when zoomed out (zoom < 15)
                        if (currentZoom < 15) {
                            layer.setOpacity(1);
                        } else {
                            layer.setOpacity(0.1);
                        }
                    } else if (layer instanceof L.CircleMarker) {
                        // Show individual markers only when zoomed in (zoom >= 15)
                        if (currentZoom >= 15) {
                            layer.setStyle({fillOpacity: 0.8, opacity: 0.8});
                        } else {
                            layer.setStyle({fillOpacity: 0, opacity: 0});
                        }
                    }
                });
            };

            map.on('zoomend', clusterZoomHandler);

            // Trigger initial zoom-based visibility
            const currentZoom = map.getZoom();
            markerLayer.eachLayer(function(layer) {
                if (layer instanceof L.Marker && layer.options.icon.options.className === 'cluster-marker') {
                    if (currentZoom < 15) {
                        layer.setOpacity(1);
                    } else {
                        layer.setOpacity(0.1);
                    }
                } else if (layer instanceof L.CircleMarker) {
                    if (currentZoom >= 15) {
                        layer.setStyle({fillOpacity: 0.8, opacity: 0.8});
                    } else {
                        layer.setStyle({fillOpacity: 0, opacity: 0});
                    }
                }
            });
        }

        // Auto-filter on dropdown change
        function setupAutoFilter() {
            const filterElements = [
                'visualizationMode',
                'timePeriod',
                'crimeType',
                'caseStatus',
                'clearanceStatus',
                'barangay'
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
            document.getElementById('incidentSearch').value = '';
            applyBarangaySelection();   // zooms back out to the whole city
            loadCrimeData();
        });

        // ---- Enlarge the map, carrying the filters with it ----
        let mapIsFullscreen = false;
        let filtersHomeParent = null;
        let filtersHomeAnchor = null;   // node the filters sat before, so they go back in place

        function setMapFullscreen(on) {
            const container = document.getElementById('mapContainer');
            const filters = document.getElementById('filtersSection');
            const icon = document.querySelector('#mapFullscreenBtn i');
            const label = document.querySelector('#mapFullscreenBtn span');

            if (on === mapIsFullscreen) return;
            mapIsFullscreen = on;

            if (on) {
                filtersHomeParent = filters.parentNode;
                filtersHomeAnchor = filters.nextSibling;
                container.classList.add('map-fullscreen');
                container.appendChild(filters);          // filters float over the map
                if (icon) icon.className = 'fas fa-compress';
                if (label) label.textContent = 'Exit Fullscreen';
                document.body.style.overflow = 'hidden';
            } else {
                container.classList.remove('map-fullscreen');
                if (filtersHomeParent) filtersHomeParent.insertBefore(filters, filtersHomeAnchor);
                if (icon) icon.className = 'fas fa-expand';
                if (label) label.textContent = 'Fullscreen';
                document.body.style.overflow = '';
            }

            // Leaflet needs to re-measure after the container resizes
            setTimeout(() => {
                map.invalidateSize();

                const code = document.getElementById('barangay').value;
                const layer = code ? barangayLayersByCode[code] : null;

                if (layer) {
                    zoomToBarangayBounds(layer.getBounds());
                } else if (qcBounds && qcBounds.isValid()) {
                    map.fitBounds(qcBounds, { padding: [20, 20] });
                }
            }, 200);
        }

        document.getElementById('mapFullscreenBtn').addEventListener('click', () => setMapFullscreen(!mapIsFullscreen));
        document.getElementById('exitFullscreenBtn').addEventListener('click', () => setMapFullscreen(false));
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && mapIsFullscreen) setMapFullscreen(false);
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
        function populateBarangaysList() {
            const barangayList = document.getElementById('barangayList');

            // Group incidents by barangay
            let barangayGroups = {};
            currentData.forEach(incident => {
                const barangayId = incident.barangay_id || 'unknown';
                const barangayName = incident.location || 'Unknown Barangay';

                if (!barangayGroups[barangayId]) {
                    barangayGroups[barangayId] = {
                        name: barangayName,
                        count: 0,
                        incidents: []
                    };
                }

                barangayGroups[barangayId].count++;
                barangayGroups[barangayId].incidents.push(incident);
            });

            // Create HTML for barangays
            let html = '';
            Object.entries(barangayGroups).forEach(([barangayId, group]) => {
                html += `
                    <div class="barangay-item" data-barangay-id="${barangayId}" style="
                        padding: 12px;
                        border-bottom: 1px solid #e5e7eb;
                        cursor: pointer;
                        transition: all 0.2s;
                        background: #f9fafb;
                        margin-bottom: 4px;
                        border-radius: 6px;
                        border-left: 4px solid #274d4c;
                        width: 100%;
                        box-sizing: border-box;
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: start; gap: 8px;">
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="font-size: 13px; font-weight: 600; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <i class="fas fa-map-pin mr-2" style="color: #274d4c;"></i><span class="barangay-name">${group.name}</span>
                                </div>
                                <div style="font-size: 11px; color: #666; margin-top: 4px;">
                                    <i class="fas fa-list mr-1" style="color: #666;"></i>${group.count} crime${group.count !== 1 ? 's' : ''}
                                </div>
                            </div>
                            <div style="background: #274d4c; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: bold; flex-shrink: 0;">${group.count}</div>
                        </div>
                    </div>
                `;
            });

            barangayList.innerHTML = html || '<div style="padding: 20px; text-align: center; color: #999;">No barangays available</div>';

            // Add click handlers
            document.querySelectorAll('.barangay-item').forEach(item => {
                item.addEventListener('click', function() {
                    const barangayId = this.getAttribute('data-barangay-id');
                    zoomToBarangay(barangayId, barangayGroups);
                });

                // Hover effect
                item.addEventListener('mouseover', function() {
                    this.style.background = '#e8f5f3';
                });

                item.addEventListener('mouseout', function() {
                    this.style.background = '#f9fafb';
                });
            });

            // Setup search with highlighting
            const barangaySearch = document.getElementById('barangaySearch');
            if (barangaySearch) {
                barangaySearch.addEventListener('input', function() {
                    const searchQuery = this.value.toLowerCase();
                    document.querySelectorAll('.barangay-item').forEach(item => {
                        const barangayNameSpan = item.querySelector('.barangay-name');
                        const barangayName = barangayNameSpan.textContent;
                        const barangayNameLower = barangayName.toLowerCase();

                        if (barangayNameLower.includes(searchQuery)) {
                            item.style.display = 'block';
                            // Highlight matching text
                            if (searchQuery.length > 0) {
                                const regex = new RegExp(`(${searchQuery})`, 'gi');
                                const highlightedName = barangayName.replace(regex, '<span style="background-color: #fef08a; font-weight: 600;">$1</span>');
                                barangayNameSpan.innerHTML = highlightedName;
                            } else {
                                barangayNameSpan.textContent = barangayName;
                            }
                        } else {
                            item.style.display = 'none';
                            barangayNameSpan.textContent = barangayName;
                        }
                    });
                });
            }
        }

        // Zoom to barangay
        function zoomToBarangay(barangayId, barangayGroups) {
            const group = barangayGroups[barangayId];
            if (!group || group.incidents.length === 0) return;

            // Calculate bounds from all incidents in barangay
            const bounds = L.latLngBounds(
                group.incidents.map(i => [i.latitude, i.longitude])
            );

            // Zoom and center on barangay
            map.fitBounds(bounds, { padding: [50, 50] });
        }

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
                        toggleRightPanel('clusters');
                        populateBarangaysList();
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

        // Update visualization mode and toggle right panel
        document.getElementById('visualizationMode').addEventListener('change', function() {
            const newMode = this.value;
            const crimeIntensityScale = document.getElementById('crimeIntensityScale');

            // Clear arrow pointer when changing views
            clearArrowPointer();

            // Toggle right panel based on mode
            if (newMode === 'heatmap') {
                toggleRightPanel('heatmap');
                clearAreaAnalysis();
                // Show Crime Intensity Scale in heatmap mode
                crimeIntensityScale.style.display = 'block';
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
            if (currentVisualizationMode === 'heatmap') {
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
                if (currentVisualizationMode === 'heatmap') displayHeatmap(currentData);
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
            if (currentVisualizationMode === 'heatmap') displayHeatmap(currentData);
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

    <!-- External Fullscreen JavaScript -->
    @vite(['resources/js/mapping-fullscreen.js', 'resources/js/notification-manager.ts'])

</body>
</html>