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
    <title>Barangay Mapping - Crime Management System</title>
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
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Barangay Mapping</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Focused crime visualization for a single barangay boundary</p>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2 bg-alertara-50 border border-alertara-200 rounded-lg">
                        <i class="fas fa-map-pin text-alertara-700"></i>
                        <span class="text-sm font-bold text-alertara-800">{{ $barangayName }}, Quezon City</span>
                    </div>
                </div>
            </div>

            <!-- Map Container with Right Panel -->
            <div class="bg-white border border-gray-200 rounded-lg p-6" style="position: relative; z-index: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-map mr-2 text-alertara-600"></i>Barangay Incident Map
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
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
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
                                <button id="resetFilterBtn" class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2 flex-shrink-0" title="Reset filters">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
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

                        <!-- INCIDENTS PANEL -->
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
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Incident Details Modal -->
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
        // Target barangay (from controller)
        const TARGET_BARANGAY_NAME = @json($barangayName);
        const BARANGAY_GEOJSON_URL = @json($barangayGeojson);

        // State variables
        let map = null;
        let heatmapLayer = null;
        let markerLayer = null;
        let boundaryLayer = null;
        let barangayBounds = null;
        let barangayRings = [];      // polygon rings for point-in-polygon test
        let barangayId = null;       // resolved from /api/barangays
        let currentData = [];
        let currentListData = [];
        let currentListPage = 1;
        let selectedIncidentId = null;
        let arrowPointer = null;
        let filterTimeout = null;
        const MAX_VISIBLE_INCIDENTS = 20;

        // Loading overlay functions
        function showMapLoading() {
            const overlay = document.getElementById('mapLoadingOverlay');
            if (overlay) overlay.style.display = 'flex';
        }

        function hideMapLoading() {
            const overlay = document.getElementById('mapLoadingOverlay');
            if (overlay) overlay.style.display = 'none';
        }

        function showIncidentSkeleton() {
            document.getElementById('incidentSkeletonLoader').style.display = 'block';
            document.getElementById('incidentListContent').style.display = 'none';
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

        // ---------- Geometry helpers ----------

        // Collect all rings from a GeoJSON FeatureCollection (Polygon / MultiPolygon)
        function collectRings(geojson) {
            const polygons = [];
            (geojson.features || []).forEach(f => {
                const g = f.geometry;
                if (!g) return;
                if (g.type === 'Polygon') {
                    polygons.push(g.coordinates);
                } else if (g.type === 'MultiPolygon') {
                    g.coordinates.forEach(poly => polygons.push(poly));
                }
            });
            return polygons;
        }

        // Ray-casting test on a single ring of [lng, lat] pairs
        function pointInRing(lng, lat, ring) {
            let inside = false;
            for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
                const xi = ring[i][0], yi = ring[i][1];
                const xj = ring[j][0], yj = ring[j][1];
                const intersect = ((yi > lat) !== (yj > lat)) &&
                    (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi);
                if (intersect) inside = !inside;
            }
            return inside;
        }

        // True when the point is inside the barangay (outer ring, not in a hole)
        function isInsideBarangay(lat, lng) {
            if (!barangayRings.length) return true;
            for (const poly of barangayRings) {
                if (!poly.length) continue;
                if (pointInRing(lng, lat, poly[0])) {
                    let inHole = false;
                    for (let h = 1; h < poly.length; h++) {
                        if (pointInRing(lng, lat, poly[h])) { inHole = true; break; }
                    }
                    if (!inHole) return true;
                }
            }
            return false;
        }

        // ---------- Map ----------

        function initializeMap() {
            map = L.map('map', {
                center: [14.7297, 121.0336],
                zoom: 15,
                minZoom: 13,
                maxZoom: 25,
                zoomControl: true,
                scrollWheelZoom: true,
                bounceAtZoomLimits: true,
                inertia: true,
                inertiaDeceleration: 3000,
                inertiaMaxSpeed: 1500,
                easeLinearity: 0.25
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 25,
                minZoom: 13
            }).addTo(map);

            setTimeout(() => {
                map.invalidateSize();
                loadBarangayBoundary();
            }, 50);
        }

        // Load the single-barangay boundary GeoJSON
        function loadBarangayBoundary() {
            const timestamp = new Date().getTime();
            fetch(`${BARANGAY_GEOJSON_URL}?t=${timestamp}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    console.log('Barangay boundary loaded:', TARGET_BARANGAY_NAME);

                    barangayRings = collectRings(data);

                    if (boundaryLayer) map.removeLayer(boundaryLayer);

                    boundaryLayer = L.geoJSON(data, {
                        style: {
                            color: '#274d4c',
                            weight: 3,
                            opacity: 1,
                            fillColor: '#e8f5f3',
                            fillOpacity: 0.12,
                            lineCap: 'round',
                            lineJoin: 'round'
                        },
                        onEachFeature: function(feature, layer) {
                            layer.bindTooltip(
                                `<strong>${TARGET_BARANGAY_NAME}</strong><br>Quezon City`,
                                { sticky: true }
                            );
                            layer.on('mouseover', function() {
                                this.setStyle({ weight: 4, fillOpacity: 0.2, fillColor: '#d0ebe7' });
                            });
                            layer.on('mouseout', function() {
                                this.setStyle({ weight: 3, fillOpacity: 0.12, fillColor: '#e8f5f3' });
                            });
                        }
                    }).addTo(map);

                    barangayBounds = boundaryLayer.getBounds();
                    map.invalidateSize();

                    if (barangayBounds.isValid()) {
                        map.fitBounds(barangayBounds, { padding: [20, 20], animate: true });
                        // Keep the view locked around the barangay
                        map.setMaxBounds(barangayBounds.pad(0.5));
                        map.setMinZoom(map.getBoundsZoom(barangayBounds) - 1);
                    }

                    loadCrimeCategories();
                    resolveBarangayId().then(() => {
                        setupAutoFilter();
                        loadCrimeData();
                    });
                })
                .catch(error => {
                    console.error('Error loading barangay boundary:', error);
                    map.setView([14.7297, 121.0336], 15);
                    loadCrimeCategories();
                    resolveBarangayId().then(() => {
                        setupAutoFilter();
                        loadCrimeData();
                    });
                });
        }

        // Find the barangay_id that matches the target name so the API can filter server-side
        async function resolveBarangayId() {
            try {
                const response = await fetch('/api/barangays');
                const barangays = await response.json();
                const match = barangays.find(b =>
                    (b.barangay_name || '').trim().toLowerCase() === TARGET_BARANGAY_NAME.trim().toLowerCase()
                );
                barangayId = match ? match.id : null;
                if (!barangayId) {
                    console.warn(`Barangay "${TARGET_BARANGAY_NAME}" not found in database — falling back to boundary filtering only.`);
                }
            } catch (error) {
                console.error('Error resolving barangay id:', error);
                barangayId = null;
            }
        }

        // Load crime categories into the filter dropdown
        async function loadCrimeCategories() {
            try {
                const response = await fetch('/api/crime-categories');
                const categories = await response.json();
                const select = document.getElementById('crimeType');
                categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.category_name;
                    select.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading crime categories:', error);
            }
        }

        // ---------- Data ----------

        async function loadCrimeData() {
            showIncidentSkeleton();
            showMapLoading();

            try {
                const timePeriod = document.getElementById('timePeriod').value;
                const visualizationMode = document.getElementById('visualizationMode').value;
                const crimeType = document.getElementById('crimeType').value;
                const caseStatus = document.getElementById('caseStatus').value;
                const clearanceStatus = document.getElementById('clearanceStatus').value;

                const params = new URLSearchParams();
                params.append('range', timePeriod);
                if (crimeType) params.append('crime_type', crimeType);
                if (caseStatus) params.append('status', caseStatus);
                if (clearanceStatus) params.append('clearance_status', clearanceStatus);
                if (barangayId) params.append('barangay', barangayId);

                const response = await fetch(`/api/crime-heatmap?${params}`);
                const data = await response.json();

                // Keep only points that actually fall inside the barangay polygon
                const filteredData = data.filter(incident =>
                    isInsideBarangay(incident.latitude, incident.longitude)
                );

                currentData = filteredData;
                selectedIncidentId = null;

                updateStatistics(filteredData);
                updateIncidentList(filteredData);

                clearCurrentVisualization();
                if (visualizationMode === 'heatmap') {
                    displayHeatmap(filteredData);
                } else {
                    displayMarkers(filteredData);
                }
            } catch (error) {
                console.error('Error loading crime data:', error);
                document.getElementById('incidentListContent').innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c; font-size: 12px;">Error loading incidents. Please try again.</div>';
                document.getElementById('incidentSkeletonLoader').style.display = 'none';
                document.getElementById('incidentListContent').style.display = 'block';
            } finally {
                hideMapLoading();
            }
        }

        function clearCurrentVisualization() {
            if (heatmapLayer) {
                map.removeLayer(heatmapLayer);
                heatmapLayer = null;
            }
            if (markerLayer) {
                map.removeLayer(markerLayer);
                markerLayer = null;
            }
            clearArrowPointer();
        }

        // Statistics are scoped to this barangay only
        function updateStatistics(data) {
            const cleared = data.filter(i => i.clearance_status === 'cleared').length;
            const uncleared = data.filter(i => i.clearance_status === 'uncleared').length;
            const categories = new Set(data.map(i => i.category_name).filter(Boolean)).size;

            document.getElementById('statTotal').textContent = data.length;
            document.getElementById('statCleared').textContent = cleared;
            document.getElementById('statUncleared').textContent = uncleared;
            document.getElementById('statCategories').textContent = categories;
        }

        function updateIncidentList(data, searchQuery = '') {
            const skeletonLoader = document.getElementById('incidentSkeletonLoader');
            const listContent = document.getElementById('incidentListContent');

            if (data.length === 0) {
                skeletonLoader.style.display = 'none';
                listContent.style.display = 'block';
                listContent.innerHTML = `<div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">No incidents found in ${TARGET_BARANGAY_NAME}</div>`;
                return;
            }

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

            currentListData = filteredData;
            currentListPage = 1;
            renderIncidentPage(searchQuery);

            skeletonLoader.style.display = 'none';
            listContent.style.display = 'block';
        }

        function renderIncidentPage(searchQuery = '') {
            const listContent = document.getElementById('incidentListContent');
            const end = currentListPage * MAX_VISIBLE_INCIDENTS;
            const visible = currentListData.slice(0, end);

            let html = '';
            visible.forEach((incident) => {
                const originalIndex = currentData.indexOf(incident);
                const isSelected = incident.id === selectedIncidentId;
                const bgColor = isSelected ? '#f0f9f8' : 'white';
                const borderColor = isSelected ? '#274d4c' : '#e5e7eb';

                let highlightedTitle = incident.incident_title || 'Crime Incident';
                let highlightedCategory = incident.category_name || 'Unknown';

                if (searchQuery.trim()) {
                    const regex = new RegExp(`(${searchQuery})`, 'gi');
                    highlightedTitle = highlightedTitle.replace(regex, '<span style="background-color: #fef08a; font-weight: 600;">$1</span>');
                    highlightedCategory = highlightedCategory.replace(regex, '<span style="background-color: #fef08a; font-weight: 600;">$1</span>');
                }

                const workflowStatusInfo = getWorkflowStatusInfo(incident.status);
                const clearanceStatusInfo = getClearanceStatusInfo(incident.clearance_status);

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
                                    <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; background-color: ${workflowStatusInfo.bgColor}; color: ${workflowStatusInfo.color}; font-weight: 600; font-size: 10px;">${workflowStatusInfo.text}</span>
                                    <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; background-color: ${clearanceStatusInfo.bgColor}; color: ${clearanceStatusInfo.color}; font-weight: 600; font-size: 10px;">${clearanceStatusInfo.text}</span>
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

        function loadMoreIncidents() {
            currentListPage++;
            renderIncidentPage(document.getElementById('incidentSearch').value);
        }

        // ---------- Visualizations ----------

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

                marker.on('click', function() {
                    openIncidentModal(incident.id);
                });

                marker.addTo(markerLayer);
            });

            markerLayer.addTo(map);
        }

        function displayHeatmap(data) {
            const points = data.map(i => [i.latitude, i.longitude, 0.8]);
            heatmapLayer = L.heatLayer(points, {
                radius: 30,
                blur: 20,
                maxZoom: 18,
                gradient: {
                    0.0: '#3b82f6',
                    0.25: '#2ecc71',
                    0.5: '#f39c12',
                    0.75: '#e74c3c',
                    1.0: '#c0392b'
                }
            }).addTo(map);
        }

        // ---------- Pointer / selection ----------

        function createPointerMarker(lat, lng, incidentId) {
            clearArrowPointer();
            if (!lat || !lng) return;
            arrowPointer = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'incident-arrow-pointer',
                    html: '<div style="font-size: 26px; color: #274d4c; text-shadow: 0 2px 4px rgba(0,0,0,0.35); transform: translateY(-14px);"><i class="fas fa-map-pin"></i></div>',
                    iconSize: [26, 26],
                    iconAnchor: [13, 26]
                }),
                interactive: false,
                zIndexOffset: 1000
            }).addTo(map);
        }

        function clearArrowPointer() {
            if (arrowPointer) {
                map.removeLayer(arrowPointer);
                arrowPointer = null;
            }
        }

        function zoomToIncident(index) {
            const incident = currentData[index];
            if (!incident) return;
            selectedIncidentId = incident.id;
            map.setView([incident.latitude, incident.longitude], 18, { animate: true });
            createPointerMarker(incident.latitude, incident.longitude, incident.id);
            openIncidentModal(incident.id);
        }

        // ---------- Modal ----------

        async function openIncidentModal(incidentId) {
            try {
                const modal = document.getElementById('incidentModal');
                modal.style.display = 'flex';

                const response = await fetch(`/api/crime-incident/${incidentId}`);
                if (!response.ok) throw new Error('Failed to load incident details');
                const incident = await response.json();

                const incidentData = currentData.find(i => i.id === incidentId);
                if (incidentData) {
                    createPointerMarker(incidentData.latitude, incidentData.longitude, incidentId);
                }

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

                const workflowStatusInfo = getWorkflowStatusInfo(incident.status);
                document.getElementById('modalStatus').innerHTML = `
                    <span style="display: inline-block; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white; background-color: ${workflowStatusInfo.color};">
                        ${workflowStatusInfo.text.toUpperCase()}
                    </span>
                `;

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

        function closeIncidentModal() {
            document.getElementById('incidentModal').style.display = 'none';
            selectedIncidentId = null;
            clearArrowPointer();
            updateIncidentList(currentData);
        }

        // ---------- Filters / events ----------

        function setupAutoFilter() {
            ['visualizationMode', 'timePeriod', 'crimeType', 'caseStatus', 'clearanceStatus'].forEach(id => {
                document.getElementById(id).addEventListener('change', () => {
                    clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(loadCrimeData, 150);
                });
            });

            document.getElementById('resetFilterBtn').addEventListener('click', () => {
                document.getElementById('visualizationMode').value = 'markers';
                document.getElementById('timePeriod').value = 'all';
                document.getElementById('crimeType').value = '';
                document.getElementById('caseStatus').value = '';
                document.getElementById('clearanceStatus').value = '';
                document.getElementById('incidentSearch').value = '';
                if (barangayBounds && barangayBounds.isValid()) {
                    map.fitBounds(barangayBounds, { padding: [20, 20] });
                }
                loadCrimeData();
            });

            document.getElementById('incidentSearch').addEventListener('input', (e) => {
                updateIncidentList(currentData, e.target.value);
            });

            document.getElementById('mapFullscreenBtn').addEventListener('click', () => {
                const container = document.getElementById('mapContainer');
                const icon = document.querySelector('#mapFullscreenBtn i');
                const isFullscreen = container.classList.toggle('map-fullscreen');

                if (isFullscreen) {
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

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeIncidentModal();
            });
        }

        document.addEventListener('DOMContentLoaded', initializeMap);
    </script>
</body>
</html>
