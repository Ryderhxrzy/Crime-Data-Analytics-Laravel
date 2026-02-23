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
                        <i class="fas fa-map mr-2 text-alertara-600"></i>Crime Incident Map
                    </h2>
                    <button id="mapFullscreenBtn" class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 text-sm" title="Toggle Fullscreen Map">
                        <i class="fas fa-expand"></i>
                        <span class="hidden sm:inline">Fullscreen</span>
                    </button>
                </div>

                <!-- Filters Section -->
                <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
                    <div class="mb-4 pb-4 border-b border-gray-200">
                        <h3 class="text-sm font-bold text-gray-900">
                            <i class="fas fa-filter mr-2 text-alertara-700"></i>Map Filters
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
                                <div class="text-xs opacity-90 mb-1">Total Incidents</div>
                                <div id="statTotal" class="text-2xl font-bold">0</div>
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
                                    <i class="fas fa-list mr-2" style="color: #274d4c;"></i>Crime Incidents
                                </h3>
                                <input type="text" id="incidentSearch" placeholder="Search incidents..." style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; box-sizing: border-box;">
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
                                <div style="font-size: 10px; color: #999;">1-5 incidents</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 4px; background: #2ecc71;"></div>
                            <div style="font-size: 11px; color: #555;">
                                <div style="font-weight: 600;">Low-Medium</div>
                                <div style="font-size: 10px; color: #999;">6-15 incidents</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 4px; background: #f39c12;"></div>
                            <div style="font-size: 11px; color: #555;">
                                <div style="font-weight: 600;">Medium density</div>
                                <div style="font-size: 10px; color: #999;">16-30 incidents</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; border-radius: 4px; background: #e74c3c;"></div>
                            <div style="font-size: 11px; color: #555;">
                                <div style="font-weight: 600;">High density</div>
                                <div style="font-size: 10px; color: #999;">31-50 incidents</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; grid-column: 1 / -1;">
                            <div style="width: 20px; height: 20px; border-radius: 4px; background: #c0392b;"></div>
                            <div style="font-size: 11px; color: #555;">
                                <div style="font-weight: 600;">Critical hotspot</div>
                                <div style="font-size: 10px; color: #999;">50+ incidents</div>
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
                                    <div style="font-size: 10px; color: #999;">1-10 incidents</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #eab308 0%, #eab308dd 100%); border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>
                                <div style="font-size: 11px; color: #555;">
                                    <div style="font-weight: 600;">Yellow</div>
                                    <div style="font-size: 10px; color: #999;">11-30 incidents</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #dc2626 0%, #dc2626dd 100%); border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>
                                <div style="font-size: 11px; color: #555;">
                                    <div style="font-weight: 600;">Red</div>
                                    <div style="font-size: 10px; color: #999;">31+ incidents</div>
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

    <script>
        // State variables
        let heatmapLayer = null;
        let markerClusterGroup = null;
        let markerLayer = null;
        let currentVisualizationMode = 'markers';
        let boundaryLayer = null;
        let filterTimeout = null;
        let qcBounds = null;
        let map = null;
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

        // Initialize map
        function initializeMap() {
            console.log('Initializing map...');

            // Create the map with default QC view
            map = L.map('map', {
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
            });

            // Add base layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 25,
                minZoom: 10
            }).addTo(map);

            // Ensure map size is calculated, then load boundary
            setTimeout(() => {
                map.invalidateSize();
                loadQCBoundary();
            }, 50);
        }

        // Load QC boundary from GeoJSON
        function loadQCBoundary() {
            console.log('Loading QC boundary...');
            
            // Add cache busting parameter
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

                    // Fit bounds BEFORE setting max bounds
                    if (qcBounds.isValid()) {
                        console.log('Fitting map to QC bounds...');
                        map.fitBounds(qcBounds, {
                            padding: [20, 20],
                            animate: true
                        });
                    }

                    // Apply boundary constraints AFTER fitting
                    applyBoundaryConstraints();

                    // After boundary is loaded, load other data
                    loadCrimeCategories();
                    loadBarangays();
                    setupAutoFilter();
                    setupZoomScaling();
                    loadCrimeData();
                    loadTotalStats();
                })
                .catch(error => {
                    console.error('Error loading QC boundary:', error);
                    
                    // Fallback: Use default QC bounds
                    qcBounds = L.latLngBounds(
                        L.latLng(14.50, 120.90), // SW corner
                        L.latLng(14.80, 121.20)  // NE corner
                    );
                    
                    console.log('Using default QC bounds:', qcBounds);
                    
                    // Invalidate size and fit to default bounds
                    map.invalidateSize();

                    if (qcBounds.isValid()) {
                        console.log('Fitting map to default bounds...');
                        map.fitBounds(qcBounds, {
                            padding: [20, 20],
                            animate: true
                        });
                    } else {
                        console.log('Setting default view...');
                        map.setView([14.6349, 121.0446], 12);
                    }

                    applyBoundaryConstraints();

                    // Load other data
                    loadCrimeCategories();
                    loadBarangays();
                    setupAutoFilter();
                    setupZoomScaling();
                    loadCrimeData();
                    loadTotalStats();
                });
        }

        // Apply boundary constraints
        function applyBoundaryConstraints() {
            if (!qcBounds || !map) {
                console.log('Cannot apply boundary constraints: qcBounds or map is null');
                return;
            }

            console.log('Applying boundary constraints...');
            
            // Set max bounds to QC boundary with some padding
            const paddedBounds = qcBounds.pad(0.02);
            map.setMaxBounds(paddedBounds);

            // Force a bounds check
            setTimeout(() => {
                if (!qcBounds.contains(map.getCenter())) {
                    console.log('Center outside bounds, adjusting...');
                    map.panInsideBounds(qcBounds, { animate: true });
                }
            }, 500);

            // Event listeners for boundary constraints
            map.on('drag', function() {
                if (!qcBounds.contains(map.getCenter())) {
                    map.panInsideBounds(qcBounds, { 
                        animate: true,
                        duration: 0.25
                    });
                }
            });

            map.on('zoomend', function() {
                const currentBounds = map.getBounds();
                if (!qcBounds.contains(currentBounds) && map.getZoom() > 15) {
                    map.fitBounds(qcBounds, {
                        padding: [20, 20],
                        maxZoom: map.getZoom()
                    });
                }
            });
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

        // Load barangays for Barangay filter
        async function loadBarangays() {
            try {
                const response = await fetch('/api/barangays');
                const barangays = await response.json();

                const barangaySelect = document.getElementById('barangay');
                barangays.forEach(barangay => {
                    const option = document.createElement('option');
                    option.value = barangay.id;
                    option.textContent = barangay.barangay_name;
                    barangaySelect.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading barangays:', error);
            }
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
                
                document.getElementById('statTotal').textContent = stats.total;
                document.getElementById('statCleared').textContent = stats.cleared;
                document.getElementById('statUncleared').textContent = stats.uncleared;
                document.getElementById('statCategories').textContent = stats.categories;
                
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
                const barangay = document.getElementById('barangay').value;

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
                document.getElementById('incidentListContent').innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c; font-size: 12px;">Error loading incidents. Please try again.</div>';
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
                listContent.innerHTML = '<div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">No incidents found</div>';
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
                listContent.innerHTML = '<div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">No matching incidents found</div>';
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
                let highlightedTitle = incident.incident_title || 'Crime Incident';
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
                    throw new Error('Failed to load incident details');
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

                document.getElementById('modalTitle').textContent = incident.incident_title || 'Crime Incident';
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
                alert('Failed to load incident details');
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
                        <div style="font-size: 11px; opacity: 0.9;">Total Incidents</div>
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
                        ${crimeTypeHtml || '<p style="font-size: 11px; color: #999;">No incidents in this area</p>'}
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
                    radius: 6,
                    fillColor: markerColor,
                    color: markerColor,
                    weight: 2,
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
            loadCrimeData();
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
                                    <i class="fas fa-list mr-1" style="color: #666;"></i>${group.count} incident${group.count !== 1 ? 's' : ''}
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
                            <i class="fas fa-building mr-2" style="color: #274d4c;"></i>Incidents in ${clusterName}
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
                    <input type="text" id="incidentSearch" placeholder="Search incidents..." style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; box-sizing: border-box;">
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
                        title = 'New Crime Incident Reported';
                        break;
                    case 'updated':
                        title = 'Crime Incident Updated';
                        break;
                    case 'deleted':
                        title = 'Crime Incident Deleted';
                        break;
                    default:
                        title = 'Crime Incident Notification';
                }
                
                // Use the NotificationManager class
                if (typeof window.NotificationManager !== 'undefined') {
                    window.NotificationManager.showIncidentNotification(title, {
                        incident_title: incident.incident_title || 'Unknown Incident',
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
                            title: 'New Crime Incident Reported',
                            body: 'Test: A new crime incident has been reported in Quezon City',
                            icon: '/images/alertara.png'
                        },
                        {
                            title: 'Crime Incident Updated',
                            body: 'Test: An existing crime incident has been updated',
                            icon: '/images/alertara.png'
                        },
                        {
                            title: 'Crime Incident Deleted',
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