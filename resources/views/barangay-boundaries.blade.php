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
    <title>Barangay Boundaries - Crime Management System</title>
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

        /* Selected-barangay label */
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
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Barangay Boundaries</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Quezon City barangay map — hover to identify, filter to isolate</p>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2 bg-alertara-50 border border-alertara-200 rounded-lg">
                        <i class="fas fa-map-pin text-alertara-700"></i>
                        <span id="activeBarangayLabel" class="text-sm font-bold text-alertara-800">Loading...</span>
                    </div>
                </div>
            </div>

            <!-- Map Container with Right Panel -->
            <div class="bg-white border border-gray-200 rounded-lg p-6" style="position: relative; z-index: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-draw-polygon mr-2 text-alertara-600"></i>Barangay Boundary Map
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
                        <!-- Barangay -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Barangay</label>
                            <select id="barangay" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="">All Barangays</option>
                            </select>
                        </div>

                        <!-- Visualization Mode -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">View Mode</label>
                            <select id="visualizationMode" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                <option value="markers" selected>Individual Markers</option>
                                <option value="heatmap">Heat Map</option>
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

                        <!-- Clearance Status + Reset -->
                        <div>
                            <label class="block text-sm font-medium text-alertara-800 mb-2">Clearance Status</label>
                            <div class="flex gap-2">
                                <select id="clearanceStatus" class="flex-1 min-w-0 px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                                    <option value="">All Clearance</option>
                                    <option value="cleared">Cleared</option>
                                    <option value="uncleared">Uncleared</option>
                                </select>
                                <button id="resetFilterBtn" class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center flex-shrink-0" title="Reset filters">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Hint -->
                    <div class="mt-4 pt-4 border-t border-gray-200 flex items-start gap-2 text-xs text-gray-600">
                        <i class="fas fa-lightbulb text-alertara-600 mt-0.5"></i>
                        <span>On <strong>All Barangays</strong>, hover any area to see its name — click it to isolate that barangay.</span>
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

                        <!-- INCIDENTS PANEL -->
                        <div id="incidentsPanel" style="background: rgba(255, 255, 255, 0.98); border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; flex-grow: 1;">
                            <div style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                <h3 style="font-size: 13px; font-weight: 700; color: #111; margin: 0 0 10px;">
                                    <i class="fas fa-list mr-2" style="color: #274d4c;"></i>Crime Incidents
                                </h3>
                                <input type="text" id="incidentSearch" placeholder="Search incidents..." style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; box-sizing: border-box;">
                            </div>
                            <div id="incidentList" style="overflow-y: auto; flex-grow: 1; max-height: 350px;">
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
                                <div id="incidentListContent" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Incident Details Modal -->
    <div id="incidentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.6); z-index: 99999; padding: 20px; align-items: center; justify-content: center;" onclick="if(event.target === this) closeIncidentModal()">
        <div style="position: relative; background: white; border-radius: 16px; max-width: 380px; max-height: 85%; overflow-y: auto; box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35); pointer-events: auto;">
            <button onclick="closeIncidentModal()" style="position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 20px; color: #999; cursor: pointer; z-index: 10; transition: color 0.2s; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'"><i class="fas fa-times"></i></button>

            <div id="modalCategoryBadge" style="padding: 20px 20px 0;">
                <span style="display: inline-block; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; color: white; background-color: #274d4c;">
                    <i class="fas fa-tag mr-2"></i><span id="modalCategoryName">Loading...</span>
                </span>
            </div>

            <div style="padding: 16px 20px 0;">
                <h2 id="modalTitle" style="font-size: 18px; font-weight: 700; color: #111; margin: 0; line-height: 1.4;">Loading...</h2>
            </div>

            <div style="padding: 20px;">
                <div style="display: grid; gap: 14px;">
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

                    <div>
                        <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                            <i class="fas fa-map-marker-alt mr-1" style="color: #274d4c;"></i>Location
                        </label>
                        <div id="modalLocation" style="font-size: 14px; color: #333;">—</div>
                    </div>

                    <div>
                        <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                            <i class="fas fa-home mr-1" style="color: #274d4c;"></i>Address
                        </label>
                        <div id="modalAddress" style="font-size: 14px; color: #333;">—</div>
                    </div>

                    <div>
                        <label style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase; display: block; margin-bottom: 6px;">
                            <i class="fas fa-tasks mr-1" style="color: #274d4c;"></i>Case Status
                        </label>
                        <div id="modalStatus">
                            <span style="display: inline-block; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white;">—</span>
                        </div>
                    </div>

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
        const QC_OUTLINE_URL   = @json($qcOutlineGeojson);
        const QC_BARANGAYS_URL = @json($barangaysGeojson);
        const DEFAULT_BARANGAY = @json($defaultBarangay);

        // ---- Boundary styles ----
        const STYLE_IDLE     = { color: '#5b8f8c', weight: 1,   opacity: 0.85, fillColor: '#e8f5f3', fillOpacity: 0.25 };
        const STYLE_HOVER    = { color: '#274d4c', weight: 3,   opacity: 1,    fillColor: '#9ed4cb', fillOpacity: 0.55 };
        // "Lining" border for the isolated barangay: solid dark outline + dashed accent halo
        const STYLE_SELECTED = { color: '#274d4c', weight: 4,   opacity: 1,    fillColor: '#bde5dd', fillOpacity: 0.35 };
        const STYLE_HALO     = { color: '#f59e0b', weight: 2,   opacity: 0.95, dashArray: '6 5', fill: false };

        // State
        let map = null;
        let heatmapLayer = null, markerLayer = null;
        let qcOutlineLayer = null;      // whole-QC outline (context)
        let barangayLayer = null;       // all barangay polygons
        let selectedHaloLayer = null;   // dashed accent on the isolated barangay
        let selectedLabel = null;       // name label on the isolated barangay
        let barangayLayersByName = {};  // geojson name -> leaflet layer
        let barangayRingsByName = {};   // geojson name -> polygon rings (for point-in-polygon)
        let qcBounds = null;
        // The dropdown is driven by /api/barangays, so its values are DB barangay ids
        let barangayNameById = {};      // DB barangay_id -> barangay_name
        let barangayIdByName = {};      // normalized barangay_name -> DB barangay_id
        let currentData = [], currentListData = [], currentListPage = 1;
        let selectedIncidentId = null, arrowPointer = null, filterTimeout = null;
        const MAX_VISIBLE_INCIDENTS = 20;

        const norm = s => (s || '').trim().toLowerCase();

        // ---- Loading helpers ----
        function showMapLoading() {
            const o = document.getElementById('mapLoadingOverlay');
            if (o) o.style.display = 'flex';
        }
        function hideMapLoading() {
            const o = document.getElementById('mapLoadingOverlay');
            if (o) o.style.display = 'none';
        }
        function showIncidentSkeleton() {
            document.getElementById('incidentSkeletonLoader').style.display = 'block';
            document.getElementById('incidentListContent').style.display = 'none';
        }

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

        function getClearanceStatusInfo(clearanceStatus) {
            const statusMap = {
                'cleared': { color: '#10b981', text: 'Cleared', bgColor: '#d1fae5' },
                'uncleared': { color: '#f59e0b', text: 'Uncleared', bgColor: '#fef3c7' }
            };
            return statusMap[clearanceStatus] || { color: '#6b7280', text: clearanceStatus || 'Unknown', bgColor: '#f3f4f6' };
        }

        // ---- Geometry ----
        function ringsOf(geometry) {
            if (!geometry) return [];
            if (geometry.type === 'Polygon') return [geometry.coordinates];
            if (geometry.type === 'MultiPolygon') return geometry.coordinates;
            return [];
        }

        function pointInRing(lng, lat, ring) {
            let inside = false;
            for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
                const xi = ring[i][0], yi = ring[i][1];
                const xj = ring[j][0], yj = ring[j][1];
                if (((yi > lat) !== (yj > lat)) &&
                    (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi)) inside = !inside;
            }
            return inside;
        }

        function pointInPolygons(lat, lng, polys) {
            for (const poly of polys) {
                if (!poly.length || !pointInRing(lng, lat, poly[0])) continue;
                let inHole = false;
                for (let h = 1; h < poly.length; h++) {
                    if (pointInRing(lng, lat, poly[h])) { inHole = true; break; }
                }
                if (!inHole) return true;
            }
            return false;
        }

        // ---- Map init ----
        function initializeMap() {
            map = L.map('map', {
                center: [14.6760, 121.0437],
                zoom: 12,
                minZoom: 10,
                maxZoom: 25,
                zoomControl: true,
                scrollWheelZoom: true,
                inertia: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 25,
                minZoom: 10
            }).addTo(map);

            setTimeout(() => {
                map.invalidateSize();
                loadBoundaries();
            }, 50);
        }

        async function loadBoundaries() {
            showMapLoading();
            const t = Date.now();

            // Whole-QC outline (visual context behind the barangays)
            try {
                const res = await fetch(`${QC_OUTLINE_URL}?t=${t}`);
                const qc = await res.json();
                qcOutlineLayer = L.geoJSON(qc, {
                    style: { color: '#274d4c', weight: 3, opacity: 0.9, fill: false },
                    interactive: false
                }).addTo(map);
                qcBounds = qcOutlineLayer.getBounds();
            } catch (e) {
                console.error('Error loading QC outline:', e);
            }

            // All barangay polygons
            try {
                const res = await fetch(`${QC_BARANGAYS_URL}?t=${t}`);
                const brgy = await res.json();

                barangayLayer = L.geoJSON(brgy, {
                    style: () => Object.assign({}, STYLE_IDLE),
                    onEachFeature: (feature, layer) => {
                        const name = (feature.properties && feature.properties.name || '').trim();
                        if (!name) return;

                        barangayLayersByName[name] = layer;
                        barangayRingsByName[name] = ringsOf(feature.geometry);

                        // Hover -> identify
                        layer.bindTooltip(name, {
                            sticky: true,
                            direction: 'top',
                            className: 'brgy-tooltip'
                        });

                        layer.on('mouseover', function () {
                            if (currentBarangayName()) return;   // isolated view: no hover swapping
                            this.setStyle(STYLE_HOVER);
                            this.bringToFront();
                        });
                        layer.on('mouseout', function () {
                            if (currentBarangayName()) return;
                            this.setStyle(STYLE_IDLE);
                        });
                        // Click -> isolate this barangay
                        layer.on('click', function () {
                            if (currentBarangayName()) return;
                            const id = barangayIdByName[norm(name)];
                            if (!id) {
                                console.warn(`"${name}" has no matching row in /api/barangays — cannot isolate.`);
                                return;
                            }
                            document.getElementById('barangay').value = id;
                            applyBarangaySelection();
                            loadCrimeData();
                        });
                    }
                }).addTo(map);

                if (!qcBounds || !qcBounds.isValid()) qcBounds = barangayLayer.getBounds();
            } catch (e) {
                console.error('Error loading barangay boundaries:', e);
            }

            await loadCrimeCategories();
            await populateBarangayDropdown();

            // Default selection: San Agustin
            const defaultId = barangayIdByName[norm(DEFAULT_BARANGAY)];
            if (defaultId) document.getElementById('barangay').value = defaultId;

            setupAutoFilter();
            applyBarangaySelection();
            loadCrimeData();
            hideMapLoading();
        }

        // Barangay options come from the QC barangay API, so the option values are
        // the real DB ids the crime endpoints filter on.
        async function populateBarangayDropdown() {
            const select = document.getElementById('barangay');
            try {
                const res = await fetch('/api/barangays');
                const rows = await res.json();

                rows.sort((a, b) => (a.barangay_name || '').localeCompare(b.barangay_name || ''));

                rows.forEach(row => {
                    const name = (row.barangay_name || '').trim();
                    if (!name) return;

                    barangayNameById[String(row.id)] = name;
                    barangayIdByName[norm(name)] = String(row.id);

                    const opt = document.createElement('option');
                    opt.value = row.id;
                    opt.textContent = name;
                    select.appendChild(opt);
                });

                // Surface any name that has no polygon (or vice versa) instead of failing silently
                const noPolygon = rows
                    .map(r => (r.barangay_name || '').trim())
                    .filter(n => n && !Object.keys(barangayLayersByName).some(g => norm(g) === norm(n)));
                const noDbRow = Object.keys(barangayLayersByName)
                    .filter(g => !barangayIdByName[norm(g)]);

                if (noPolygon.length) console.warn('Barangays with no boundary polygon:', noPolygon);
                if (noDbRow.length)   console.warn('Polygons with no /api/barangays row:', noDbRow);
            } catch (e) {
                console.error('Error loading barangays:', e);
            }
        }

        const currentBarangayId = () => document.getElementById('barangay').value;
        const currentBarangayName = () => barangayNameById[currentBarangayId()] || '';

        // Show all barangays (hoverable) or isolate exactly one with a lining border
        function applyBarangaySelection() {
            const selected = currentBarangayName();

            if (selectedHaloLayer) { map.removeLayer(selectedHaloLayer); selectedHaloLayer = null; }
            if (selectedLabel)     { map.removeLayer(selectedLabel);     selectedLabel = null; }

            document.getElementById('activeBarangayLabel').textContent =
                selected ? `${selected}, Quezon City` : 'All Barangays, Quezon City';

            // Toggle through the GeoJSON group so its membership stays consistent
            Object.entries(barangayLayersByName).forEach(([name, layer]) => {
                const isTarget = selected && norm(name) === norm(selected);

                if (!selected || isTarget) {
                    if (!barangayLayer.hasLayer(layer)) barangayLayer.addLayer(layer);
                    layer.setStyle(Object.assign({}, isTarget ? STYLE_SELECTED : STYLE_IDLE));
                    if (isTarget) layer.bringToFront();
                } else {
                    // Isolated mode: drop the rest so they cannot be seen or hovered
                    if (barangayLayer.hasLayer(layer)) barangayLayer.removeLayer(layer);
                }
            });

            if (selected) {
                const key = Object.keys(barangayLayersByName).find(n => norm(n) === norm(selected));
                const layer = key ? barangayLayersByName[key] : null;
                if (layer) {
                    // Dashed accent halo = the "lining" border
                    selectedHaloLayer = L.geoJSON(layer.toGeoJSON(), {
                        style: Object.assign({}, STYLE_HALO),
                        interactive: false
                    }).addTo(map);

                    const b = layer.getBounds();
                    selectedLabel = L.marker(b.getCenter(), {
                        interactive: false,
                        icon: L.divIcon({ className: '', html: '' })
                    }).addTo(map);
                    selectedLabel.bindTooltip(selected, {
                        permanent: true,
                        direction: 'center',
                        className: 'brgy-label-selected'
                    }).openTooltip();

                    map.fitBounds(b, { padding: [30, 30], animate: true });
                }
            } else if (qcBounds && qcBounds.isValid()) {
                map.fitBounds(qcBounds, { padding: [20, 20], animate: true });
            }
        }

        async function loadCrimeCategories() {
            try {
                const res = await fetch('/api/crime-categories');
                const categories = await res.json();
                const select = document.getElementById('crimeType');
                categories.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.category_name;
                    select.appendChild(opt);
                });
            } catch (e) {
                console.error('Error loading crime categories:', e);
            }
        }

        // ---- Crime data ----
        async function loadCrimeData() {
            showIncidentSkeleton();
            showMapLoading();

            try {
                const selectedBrgy = currentBarangayName();
                const params = new URLSearchParams();
                params.append('range', document.getElementById('timePeriod').value);

                const crimeType = document.getElementById('crimeType').value;
                const caseStatus = document.getElementById('caseStatus').value;
                const clearanceStatus = document.getElementById('clearanceStatus').value;
                if (crimeType) params.append('crime_type', crimeType);
                if (caseStatus) params.append('status', caseStatus);
                if (clearanceStatus) params.append('clearance_status', clearanceStatus);

                const brgyId = currentBarangayId();
                if (brgyId) params.append('barangay', brgyId);

                const res = await fetch(`/api/crime-heatmap?${params}`);
                const data = await res.json();

                // Keep points inside the selected polygon (or inside QC when showing all)
                let filtered = data;
                if (selectedBrgy) {
                    const key = Object.keys(barangayRingsByName)
                        .find(n => norm(n) === norm(selectedBrgy));
                    const polys = key ? barangayRingsByName[key] : null;
                    if (polys) {
                        filtered = data.filter(i => pointInPolygons(i.latitude, i.longitude, polys));
                    }
                } else if (qcBounds) {
                    filtered = data.filter(i => qcBounds.contains([i.latitude, i.longitude]));
                }

                currentData = filtered;
                selectedIncidentId = null;

                updateStatistics(filtered);
                updateIncidentList(filtered);

                clearCurrentVisualization();
                if (document.getElementById('visualizationMode').value === 'heatmap') {
                    displayHeatmap(filtered);
                } else {
                    displayMarkers(filtered);
                }
            } catch (e) {
                console.error('Error loading crime data:', e);
                document.getElementById('incidentListContent').innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c; font-size: 12px;">Error loading incidents. Please try again.</div>';
                document.getElementById('incidentSkeletonLoader').style.display = 'none';
                document.getElementById('incidentListContent').style.display = 'block';
            } finally {
                hideMapLoading();
            }
        }

        function clearCurrentVisualization() {
            if (heatmapLayer) { map.removeLayer(heatmapLayer); heatmapLayer = null; }
            if (markerLayer)  { map.removeLayer(markerLayer);  markerLayer = null; }
            clearArrowPointer();
        }

        function updateStatistics(data) {
            document.getElementById('statTotal').textContent = data.length;
            document.getElementById('statCleared').textContent = data.filter(i => i.clearance_status === 'cleared').length;
            document.getElementById('statUncleared').textContent = data.filter(i => i.clearance_status === 'uncleared').length;
            document.getElementById('statCategories').textContent = new Set(data.map(i => i.category_name).filter(Boolean)).size;
        }

        function updateIncidentList(data, searchQuery = '') {
            const skeleton = document.getElementById('incidentSkeletonLoader');
            const listContent = document.getElementById('incidentListContent');
            const where = currentBarangayName() || 'Quezon City';

            if (data.length === 0) {
                skeleton.style.display = 'none';
                listContent.style.display = 'block';
                listContent.innerHTML = `<div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">No incidents found in ${where}</div>`;
                return;
            }

            let filtered = data;
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                filtered = data.filter(i =>
                    (i.incident_title || '').toLowerCase().includes(q) ||
                    (i.category_name || '').toLowerCase().includes(q)
                );
            }

            if (filtered.length === 0) {
                skeleton.style.display = 'none';
                listContent.style.display = 'block';
                listContent.innerHTML = '<div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">No matching incidents found</div>';
                return;
            }

            currentListData = filtered;
            currentListPage = 1;
            renderIncidentPage(searchQuery);

            skeleton.style.display = 'none';
            listContent.style.display = 'block';
        }

        function renderIncidentPage(searchQuery = '') {
            const listContent = document.getElementById('incidentListContent');
            const end = currentListPage * MAX_VISIBLE_INCIDENTS;
            const visible = currentListData.slice(0, end);

            let html = '';
            visible.forEach(incident => {
                const originalIndex = currentData.indexOf(incident);
                const isSelected = incident.id === selectedIncidentId;
                const bgColor = isSelected ? '#f0f9f8' : 'white';
                const borderColor = isSelected ? '#274d4c' : '#e5e7eb';

                let title = incident.incident_title || 'Crime Incident';
                let category = incident.category_name || 'Unknown';
                if (searchQuery.trim()) {
                    const re = new RegExp(`(${searchQuery})`, 'gi');
                    title = title.replace(re, '<span style="background-color: #fef08a; font-weight: 600;">$1</span>');
                    category = category.replace(re, '<span style="background-color: #fef08a; font-weight: 600;">$1</span>');
                }

                const ws = getWorkflowStatusInfo(incident.status);
                const cs = getClearanceStatusInfo(incident.clearance_status);

                html += `
                    <div class="incident-item" data-id="${incident.id}" style="
                        padding: 12px;
                        border-bottom: 1px solid ${borderColor};
                        background-color: ${bgColor};
                        cursor: pointer;
                        transition: all 0.2s;
                        border-left: 3px solid ${isSelected ? incident.color_code : 'transparent'};
                    " onmouseover="this.style.backgroundColor='#f9fafb'; createPointerMarker(${incident.latitude}, ${incident.longitude});" onmouseout="this.style.backgroundColor='${bgColor}'; if(selectedIncidentId !== ${incident.id}) { clearArrowPointer(); }" onclick="zoomToIncident(${originalIndex})">
                        <div style="display: flex; gap: 8px; align-items: flex-start;">
                            <div style="width: 12px; height: 12px; border-radius: 50%; background-color: ${incident.color_code}; margin-top: 4px; flex-shrink: 0;"></div>
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="font-size: 12px; font-weight: 600; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${title}</div>
                                <div style="font-size: 11px; color: #666; margin-top: 2px;">${category}</div>
                                <div style="font-size: 11px; color: #999; margin-top: 2px;">${new Date(incident.incident_date).toLocaleDateString()}</div>
                                <div style="font-size: 11px; margin-top: 4px; display: flex; gap: 4px;">
                                    <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; background-color: ${ws.bgColor}; color: ${ws.color}; font-weight: 600; font-size: 10px;">${ws.text}</span>
                                    <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; background-color: ${cs.bgColor}; color: ${cs.color}; font-weight: 600; font-size: 10px;">${cs.text}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            if (currentListData.length > end) {
                const remaining = currentListData.length - end;
                html += `
                    <div style="padding: 12px; text-align: center; border-top: 1px solid #e5e7eb;">
                        <button onclick="loadMoreIncidents()" style="padding: 8px 16px; background-color: #274d4c; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500;" onmouseover="this.style.backgroundColor='#1a3535'" onmouseout="this.style.backgroundColor='#274d4c'">
                            Show More (${remaining} remaining)
                        </button>
                    </div>
                `;
            }

            listContent.innerHTML = html;
        }

        function loadMoreIncidents() {
            currentListPage++;
            renderIncidentPage(document.getElementById('incidentSearch').value);
        }

        // ---- Visualizations ----
        function displayMarkers(data) {
            markerLayer = L.featureGroup();
            data.forEach(incident => {
                const color = incident.color_code || '#274d4c';
                const marker = L.circleMarker([incident.latitude, incident.longitude], {
                    radius: 6, fillColor: color, color: color, weight: 2, opacity: 0.8, fillOpacity: 0.7
                });
                marker.on('click', () => openIncidentModal(incident.id));
                marker.addTo(markerLayer);
            });
            markerLayer.addTo(map);
        }

        function displayHeatmap(data) {
            heatmapLayer = L.heatLayer(data.map(i => [i.latitude, i.longitude, 0.8]), {
                radius: 30, blur: 20, maxZoom: 18,
                gradient: { 0.0: '#3b82f6', 0.25: '#2ecc71', 0.5: '#f39c12', 0.75: '#e74c3c', 1.0: '#c0392b' }
            }).addTo(map);
        }

        // ---- Pointer / selection ----
        function createPointerMarker(lat, lng) {
            clearArrowPointer();
            if (!lat || !lng) return;
            arrowPointer = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'incident-arrow-pointer',
                    html: '<div style="font-size: 26px; color: #274d4c; text-shadow: 0 2px 4px rgba(0,0,0,0.35); transform: translateY(-14px);"><i class="fas fa-map-pin"></i></div>',
                    iconSize: [26, 26], iconAnchor: [13, 26]
                }),
                interactive: false, zIndexOffset: 1000
            }).addTo(map);
        }

        function clearArrowPointer() {
            if (arrowPointer) { map.removeLayer(arrowPointer); arrowPointer = null; }
        }

        function zoomToIncident(index) {
            const incident = currentData[index];
            if (!incident) return;
            selectedIncidentId = incident.id;
            map.setView([incident.latitude, incident.longitude], 18, { animate: true });
            createPointerMarker(incident.latitude, incident.longitude);
            openIncidentModal(incident.id);
        }

        // ---- Modal ----
        async function openIncidentModal(incidentId) {
            try {
                document.getElementById('incidentModal').style.display = 'flex';

                const res = await fetch(`/api/crime-incident/${incidentId}`);
                if (!res.ok) throw new Error('Failed to load incident details');
                const incident = await res.json();

                const d = currentData.find(i => i.id === incidentId);
                if (d) createPointerMarker(d.latitude, d.longitude);

                document.getElementById('modalCategoryBadge').innerHTML = `
                    <span style="display: inline-block; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; color: white; background-color: ${incident.color_code || '#274d4c'};">
                        <i class="fas ${incident.icon || 'fa-exclamation-circle'} mr-2"></i>${incident.category_name || 'Unknown'}
                    </span>
                `;

                document.getElementById('modalTitle').textContent = incident.incident_title || 'Crime Incident';
                document.getElementById('modalDate').textContent = incident.incident_date || '—';
                document.getElementById('modalTime').textContent = incident.incident_time || '—';
                document.getElementById('modalLocation').textContent = incident.location || '—';
                document.getElementById('modalAddress').textContent = incident.address || '—';

                const ws = getWorkflowStatusInfo(incident.status);
                document.getElementById('modalStatus').innerHTML = `
                    <span style="display: inline-block; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white; background-color: ${ws.color};">${ws.text.toUpperCase()}</span>
                `;

                const cs = getClearanceStatusInfo(incident.clearance_status);
                document.getElementById('modalClearanceStatus').innerHTML = `
                    <span style="display: inline-block; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white; background-color: ${cs.color};">${cs.text.toUpperCase()}</span>
                `;

                document.getElementById('modalCaseNumber').textContent = incident.case_number || '—';
                document.getElementById('modalDetails').textContent = incident.incident_details || 'No additional details';
            } catch (e) {
                console.error('Error opening incident modal:', e);
                document.getElementById('incidentModal').style.display = 'none';
                alert('Failed to load incident details');
            }
        }

        function closeIncidentModal() {
            document.getElementById('incidentModal').style.display = 'none';
            selectedIncidentId = null;
            clearArrowPointer();
            updateIncidentList(currentData);
        }

        // ---- Filters / events ----
        function setupAutoFilter() {
            document.getElementById('barangay').addEventListener('change', () => {
                applyBarangaySelection();
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(loadCrimeData, 150);
            });

            ['visualizationMode', 'timePeriod', 'crimeType', 'caseStatus', 'clearanceStatus'].forEach(id => {
                document.getElementById(id).addEventListener('change', () => {
                    clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(loadCrimeData, 150);
                });
            });

            document.getElementById('resetFilterBtn').addEventListener('click', () => {
                document.getElementById('barangay').value = '';
                document.getElementById('visualizationMode').value = 'markers';
                document.getElementById('timePeriod').value = 'all';
                document.getElementById('crimeType').value = '';
                document.getElementById('caseStatus').value = '';
                document.getElementById('clearanceStatus').value = '';
                document.getElementById('incidentSearch').value = '';
                applyBarangaySelection();
                loadCrimeData();
            });

            document.getElementById('incidentSearch').addEventListener('input', e => {
                updateIncidentList(currentData, e.target.value);
            });

            document.getElementById('mapFullscreenBtn').addEventListener('click', () => {
                const container = document.getElementById('mapContainer');
                const icon = document.querySelector('#mapFullscreenBtn i');
                const isFull = container.classList.toggle('map-fullscreen');
                if (isFull) {
                    container.style.cssText = 'position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 99998; border-radius: 0;';
                    document.getElementById('map').style.height = '100vh';
                    icon.className = 'fas fa-compress';
                } else {
                    container.style.cssText = '';
                    document.getElementById('map').style.height = '';
                    icon.className = 'fas fa-expand';
                }
                setTimeout(() => map.invalidateSize(), 150);
            });

            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') closeIncidentModal();
            });
        }

        document.addEventListener('DOMContentLoaded', initializeMap);
    </script>
</body>
</html>
