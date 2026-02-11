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
</head>
<body class="bg-gray-100">
    <!-- Header Component -->
    @include('components.header')

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

    <!-- Sidebar -->
    @include('components.sidebar')

    <!-- Main Content -->
    <main class="lg:ml-72 ml-0 lg:mt-20 mt-20 min-h-screen bg-gray-100">
        <div class="p-6">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Crime Mapping</h1>
                <p class="text-gray-600 mt-2">Interactive crime data visualization and analysis</p>
            </div>

            <!-- Filter Section -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 hover:border-alertara-300 transition-colors">
                <h2 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-filter mr-2 text-alertara-600"></i>Filter Crime Data
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Visualization Mode -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-chart-pie mr-2"></i>Visualization Mode
                        </label>
                        <select id="visualizationMode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-alertara-600 focus:ring-1 focus:ring-alertara-600">
                            <option value="markers">Individual Markers</option>
                            <option value="heatmap" selected>Heat Map</option>
                            <option value="clusters">Cluster View</option>
                        </select>
                    </div>

                    <!-- Time Period -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-2"></i>Time Period
                        </label>
                        <select id="timePeriod" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-alertara-600 focus:ring-1 focus:ring-alertara-600">
                            <option value="30">Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="180">Last 6 Months</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>

                    <!-- Crime Type -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-tags mr-2"></i>Crime Type
                        </label>
                        <select id="crimeType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-alertara-600 focus:ring-1 focus:ring-alertara-600">
                            <option value="">All Types</option>
                        </select>
                    </div>

                    <!-- Case Status -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-clipboard-check mr-2"></i>Case Status
                        </label>
                        <select id="caseStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-alertara-600 focus:ring-1 focus:ring-alertara-600">
                            <option value="">All Status</option>
                            <option value="cleared">Cleared</option>
                            <option value="uncleared">Uncleared</option>
                        </select>
                    </div>

                    <!-- Barangay -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-map-pin mr-2"></i>Barangay
                        </label>
                        <select id="barangay" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-alertara-600 focus:ring-1 focus:ring-alertara-600">
                            <option value="">All Barangays</option>
                        </select>
                    </div>
                </div>

                <!-- Reset Button & Loading Indicator -->
                <div class="mt-4 flex gap-3">
                    <button id="resetFilterBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-redo mr-2"></i>Reset Filter
                    </button>
                    <span id="loadingIndicator" class="hidden flex items-center text-sm text-gray-600">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Loading map data...
                    </span>
                </div>
            </div>

            <!-- Map Container -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 hover:border-alertara-300 transition-colors" style="position: relative; z-index: 1;">
                <h2 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-map mr-2 text-alertara-600"></i>Crime Incident Map
                </h2>
                <div id="map" style="height: 600px; border-radius: 0.5rem; border: 1px solid #e5e7eb; position: relative; z-index: 1; overflow: hidden;"></div>

                <!-- Crime Density Scale (Below Map) -->
                <div style="margin-top: 16px; background: rgba(255, 255, 255, 0.98); border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);">
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <!-- Gradient Bar -->
                        <div style="flex: 1; display: flex; flex-direction: column; gap: 4px;">
                            <div style="height: 28px; border-radius: 4px; background: linear-gradient(90deg, #3b82f6 0%, #2ecc71 25%, #f39c12 50%, #e74c3c 75%, #c0392b 100%); border: 1px solid rgba(0, 0, 0, 0.1);"></div>
                            <div style="display: flex; justify-content: space-between; padding: 0 4px;">
                                <span style="font-size: 12px; font-weight: 600; color: #666;">Low (1-2)</span>
                                <span style="font-size: 12px; font-weight: 600; color: #666;">Medium (5)</span>
                                <span style="font-size: 12px; font-weight: 600; color: #666;">High (10+, max. 2)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        // State variables
        let heatmapLayer = null;
        let markerClusterGroup = null;
        let markerLayer = null;
        let currentVisualizationMode = 'heatmap';
        let boundaryLayer = null;
        let filterTimeout = null;
        let qcBounds = null;
        let map = null;

        // Initialize map with QC boundary constraints
        function initializeMap() {
            // Create map with QC coordinates
            map = L.map('map', {
                minZoom: 10,
                maxZoom: 25,
                zoomControl: true,
                scrollWheelZoom: true,
                bounceAtZoomLimits: true,
                inertia: true,
                inertiaDeceleration: 3000,
                inertiaMaxSpeed: 1500,
                easeLinearity: 0.25
            }).setView([14.6349, 121.0446], 12);

            // Add base layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 25,
                minZoom: 10
            }).addTo(map);

            // Load QC boundary first to get bounds
            loadQCBoundary();
        }

        // Load QC boundary from GeoJSON and apply constraints
        function loadQCBoundary() {
            fetch('/qc_map.geojson')
                .then(response => response.json())
                .then(data => {
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
                            layer.bindPopup('<p class="font-semibold">Quezon City</p><p class="text-xs text-gray-600">Coverage Area - Restricted to this boundary</p>');

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
                    
                    // Set map view to fit QC bounds with padding and zoom in
                    map.fitBounds(qcBounds, {
                        padding: [50, 50],
                        maxZoom: 22
                    });

                    // Apply boundary constraints
                    applyBoundaryConstraints();

                    // After boundary is loaded, load other data
                    loadCrimeCategories();
                    loadBarangays();
                    setupAutoFilter();
                    loadCrimeData();
                })
                .catch(error => {
                    console.error('Error loading QC boundary:', error);
                    // If boundary fails to load, set default bounds for QC
                    qcBounds = L.latLngBounds(
                        L.latLng(14.50, 120.90), // SW corner
                        L.latLng(14.80, 121.20)  // NE corner
                    );
                    map.fitBounds(qcBounds);
                    applyBoundaryConstraints();
                    
                    // Load other data
                    loadCrimeCategories();
                    loadBarangays();
                    setupAutoFilter();
                    loadCrimeData();
                });
        }

        // Apply boundary constraints to prevent panning outside
        function applyBoundaryConstraints() {
            if (!qcBounds || !map) return;

            // Set max bounds to QC boundary with some padding
            const paddedBounds = qcBounds.pad(0.02); // Add 2% padding
            
            // Set maximum bounds to restrict panning
            map.setMaxBounds(paddedBounds);

            // Add event listener to keep map within bounds
            map.on('drag', function() {
                if (!qcBounds.contains(map.getCenter())) {
                    map.panInsideBounds(qcBounds, { 
                        animate: true,
                        duration: 0.25,
                        easeLinearity: 0.25
                    });
                }
            });

            // Handle zoom events to ensure we stay within bounds
            map.on('zoomend', function() {
                const currentBounds = map.getBounds();
                
                // If map is showing area outside QC bounds at high zoom, adjust
                if (!qcBounds.contains(currentBounds) && map.getZoom() > 15) {
                    map.fitBounds(qcBounds, {
                        padding: [20, 20],
                        maxZoom: map.getZoom()
                    });
                }
            });

            // Handle resize events
            map.on('resize', function() {
                // Ensure map stays within bounds on resize
                if (!qcBounds.contains(map.getCenter())) {
                    map.panInsideBounds(qcBounds);
                }
            });
        }

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
                categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.category_name;
                    crimeTypeSelect.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading crime categories:', error);
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

        // Load and display crime data
        async function loadCrimeData() {
            const loadingIndicator = document.getElementById('loadingIndicator');
            loadingIndicator.classList.remove('hidden');

            try {
                const timePeriod = document.getElementById('timePeriod').value;
                const visualizationMode = document.getElementById('visualizationMode').value;
                const crimeType = document.getElementById('crimeType').value;
                const caseStatus = document.getElementById('caseStatus').value;
                const barangay = document.getElementById('barangay').value;

                // Build query parameters
                const params = new URLSearchParams();
                params.append('range', timePeriod);
                if (crimeType) params.append('crime_type', crimeType);
                if (caseStatus) params.append('status', caseStatus);
                if (barangay) params.append('barangay', barangay);

                const response = await fetch(`/api/crime-heatmap?${params}`);
                const data = await response.json();

                // Filter data to only show points within QC bounds
                const filteredData = data.filter(incident => {
                    if (!qcBounds) return true;
                    return qcBounds.contains([incident.latitude, incident.longitude]);
                });

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
                alert('Failed to load crime data. Please try again.');
            } finally {
                loadingIndicator.classList.add('hidden');
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
        }

        // Display heatmap
        function displayHeatmap(data) {
            // Check if heatmap plugin is loaded
            if (typeof L.heatLayer !== 'function') {
                console.warn('Leaflet heatmap plugin not loaded');
                // Wait a moment for the script to load
                setTimeout(() => {
                    if (typeof L.heatLayer === 'function') {
                        console.log('Heatmap plugin loaded after waiting');
                        displayHeatmap(data);
                    } else {
                        console.warn('Heatmap plugin still not available, falling back to markers');
                        displayMarkers(data);
                    }
                }, 500);
                return;
            }

            const heatmapPoints = data.map(incident => [
                incident.latitude,
                incident.longitude,
                0.7 // intensity
            ]);

            if (heatmapPoints.length > 0) {
                heatmapLayer = L.heatLayer(heatmapPoints, {
                    radius: 40,
                    blur: 20,
                    maxZoom: 18,
                    minOpacity: 0.3,
                    gradient: {
                        0.0: 'rgba(59, 130, 246, 0.4)',
                        0.25: 'rgba(46, 204, 113, 0.5)',
                        0.5: 'rgba(243, 156, 18, 0.6)',
                        0.75: 'rgba(231, 76, 60, 0.7)',
                        1.0: 'rgba(192, 57, 43, 0.8)'
                    }
                }).addTo(map);
            }
        }

        // Display individual markers
        function displayMarkers(data) {
            markerLayer = L.featureGroup();

            data.forEach(incident => {
                // Ensure marker is within QC bounds
                if (qcBounds && !qcBounds.contains([incident.latitude, incident.longitude])) {
                    return;
                }

                const marker = L.circleMarker([incident.latitude, incident.longitude], {
                    radius: 6,
                    fillColor: '#274d4c',
                    color: '#274d4c',
                    weight: 2,
                    opacity: 0.8,
                    fillOpacity: 0.7
                });

                marker.bindPopup(`
                    <div class="p-2">
                        <p class="font-semibold text-sm">${incident.incident_title || 'Crime Incident'}</p>
                        <p class="text-xs text-gray-600">Date: ${new Date(incident.incident_date).toLocaleDateString()}</p>
                    </div>
                `);

                marker.addTo(markerLayer);
            });

            markerLayer.addTo(map);
        }

        // Display cluster view
        function displayClusters(data) {
            markerLayer = L.featureGroup();

            data.forEach(incident => {
                // Ensure marker is within QC bounds
                if (qcBounds && !qcBounds.contains([incident.latitude, incident.longitude])) {
                    return;
                }

                const marker = L.marker([incident.latitude, incident.longitude]);
                marker.bindPopup(`
                    <div class="p-2">
                        <p class="font-semibold text-sm">${incident.incident_title || 'Crime Incident'}</p>
                        <p class="text-xs text-gray-600">Date: ${new Date(incident.incident_date).toLocaleDateString()}</p>
                    </div>
                `);
                marker.addTo(markerLayer);
            });

            markerLayer.addTo(map);
        }

        // Update statistics (commented out since we removed the cards)
        function updateStatistics(data) {
            // Statistics section removed
        }

        // Auto-filter on dropdown change with debouncing
        function setupAutoFilter() {
            const filterElements = [
                'visualizationMode',
                'timePeriod',
                'crimeType',
                'caseStatus',
                'barangay'
            ];

            filterElements.forEach(elementId => {
                document.getElementById(elementId).addEventListener('change', function() {
                    // Clear previous timeout
                    if (filterTimeout) {
                        clearTimeout(filterTimeout);
                    }
                    // Debounce the filter request (500ms)
                    filterTimeout = setTimeout(() => {
                        loadCrimeData();
                    }, 500);
                });
            });
        }

        // Reset filters
        document.getElementById('resetFilterBtn').addEventListener('click', function() {
            document.getElementById('visualizationMode').value = 'heatmap';
            document.getElementById('timePeriod').value = '30';
            document.getElementById('crimeType').value = '';
            document.getElementById('caseStatus').value = '';
            document.getElementById('barangay').value = '';
            loadCrimeData();
        });

        // Fit map to QC boundary when needed
        function fitToQCBoundary() {
            if (qcBounds) {
                map.fitBounds(qcBounds, { 
                    padding: [50, 50],
                    maxZoom: 18
                });
            }
        }

        // Add button to reset view to QC boundary (optional - can be added to UI)
        function addResetViewButton() {
            const resetButton = L.control({position: 'topright'});
            resetButton.onAdd = function() {
                const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                div.innerHTML = `
                    <a href="#" title="Reset view to QC boundary" style="display: block; padding: 8px; background: white; border-radius: 4px; border: 2px solid rgba(0,0,0,0.2);">
                        <i class="fas fa-expand" style="color: #274d4c;"></i>
                    </a>
                `;
                div.onclick = function(e) {
                    e.preventDefault();
                    fitToQCBoundary();
                    return false;
                };
                return div;
            };
            resetButton.addTo(map);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initializeMap();
            // Add reset view button after map is initialized
            setTimeout(addResetViewButton, 1000);
        });

        // Handle window resize to maintain boundary constraints
        window.addEventListener('resize', function() {
            if (map) {
                map.invalidateSize();
                if (qcBounds) {
                    map.panInsideBounds(qcBounds);
                }
            }
        });
    </script>
</body>
</html>