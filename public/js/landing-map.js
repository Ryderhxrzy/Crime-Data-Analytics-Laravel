// Global variables
let map;
let heatLayer;
let markersLayer;
let markersGroup = [];
let currentView = 'heatmap';
let qcGeoJsonLayer;

// Initialize map on page load
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    attachEventListeners();
});

/**
 * Initialize Leaflet map with base layers and GeoJSON boundary
 */
function initMap() {
    try {
        // Create map centered on Quezon City
        const qcCenter = [14.6760, 121.0437];
        map = L.map('crimeMap').setView(qcCenter, 12);

        // Add OpenStreetMap tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
            minZoom: 10
        }).addTo(map);

        // Load QC boundary from GeoJSON
        loadQcBoundary();

        // Initialize layer groups
        markersLayer = L.layerGroup().addTo(map);

        // Load crime data
        loadCrimeData();

    } catch (error) {
        console.error('Error initializing map:', error);
        showMapError('Failed to initialize map');
    }
}

/**
 * Load Quezon City boundary from GeoJSON file
 */
function loadQcBoundary() {
    try {
        fetch('/qc_map.geojson')
            .then(response => {
                if (!response.ok) throw new Error('Failed to load GeoJSON');
                return response.json();
            })
            .then(data => {
                qcGeoJsonLayer = L.geoJSON(data, {
                    style: {
                        color: '#274d4c',
                        weight: 3,
                        opacity: 0.8,
                        fillOpacity: 0.05,
                        fill: true
                    }
                }).addTo(map);
            })
            .catch(error => {
                console.warn('Could not load QC boundary GeoJSON:', error);
                // Continue even if boundary fails to load
            });
    } catch (error) {
        console.error('Error loading QC boundary:', error);
    }
}

/**
 * Fetch crime data from API and create heatmap
 */
async function loadCrimeData() {
    const loader = document.getElementById('mapLoader');

    try {
        // Show loader
        if (loader) loader.classList.remove('hidden');

        // Get date range from filter
        const dateRange = document.getElementById('dateRangeFilter').value;
        const apiUrl = dateRange === 'all'
            ? '/api/crime-data?range=all'
            : `/api/crime-data?range=${dateRange}`;

        // Fetch crime data
        const response = await fetch(apiUrl);

        if (!response.ok) {
            throw new Error(`API error: ${response.status}`);
        }

        const data = await response.json();

        if (!Array.isArray(data) || data.length === 0) {
            console.warn('No crime data available');
            showMapError('No crime data available');
            return;
        }

        // Store data globally for marker view
        window.crimeData = data;

        // Create heatmap data points
        const heatPoints = data.map(incident => {
            return [
                parseFloat(incident.lat),
                parseFloat(incident.lng),
                0.5  // Default intensity
            ];
        });

        // Remove old heatmap if exists
        if (heatLayer && map.hasLayer(heatLayer)) {
            map.removeLayer(heatLayer);
        }

        // Create new heatmap layer with Leaflet.heat
        heatLayer = L.heatLayer(heatPoints, {
            radius: 25,
            blur: 35,
            maxZoom: 13,
            minOpacity: 0.3,
            gradient: {
                0.0: 'blue',
                0.25: 'cyan',
                0.5: 'lime',
                0.75: 'yellow',
                1.0: 'red'
            }
        }).addTo(map);

        console.log(`Loaded ${data.length} crime incidents`);

    } catch (error) {
        console.error('Error loading crime data:', error);
        showMapError('Failed to load crime data. Please refresh the page.');
    } finally {
        // Hide loader
        if (loader) loader.classList.add('hidden');
    }
}

/**
 * Switch to heatmap view
 */
function switchToHeatmap() {
    try {
        // Remove markers layer
        markersLayer.clearLayers();

        // Add heatmap if not visible
        if (heatLayer && !map.hasLayer(heatLayer)) {
            map.addLayer(heatLayer);
        }

        // Update button styles
        updateButtonStyles('heatmap');

        currentView = 'heatmap';
        console.log('Switched to heatmap view');

    } catch (error) {
        console.error('Error switching to heatmap view:', error);
    }
}

/**
 * Switch to markers view
 */
function switchToMarkers() {
    try {
        // Remove heatmap if visible
        if (heatLayer && map.hasLayer(heatLayer)) {
            map.removeLayer(heatLayer);
        }

        // Clear existing markers
        markersLayer.clearLayers();
        markersGroup = [];

        // Add markers for each crime incident
        if (!window.crimeData || window.crimeData.length === 0) {
            console.warn('No crime data to display as markers');
            return;
        }

        window.crimeData.forEach(incident => {
            try {
                const lat = parseFloat(incident.lat);
                const lng = parseFloat(incident.lng);

                if (isNaN(lat) || isNaN(lng)) {
                    return; // Skip invalid coordinates
                }

                // Create circle marker
                const marker = L.circleMarker([lat, lng], {
                    radius: 6,
                    fillColor: getCategoryColor(incident.category),
                    color: '#333',
                    weight: 1,
                    opacity: 1,
                    fillOpacity: 0.8
                });

                // Add popup with incident info
                const popupContent = `
                    <div class="text-sm">
                        <strong style="color: #274d4c;">${escapeHtml(incident.category)}</strong><br>
                        <small class="text-gray-600">Date: ${escapeHtml(incident.date)}</small>
                    </div>
                `;

                marker.bindPopup(popupContent);
                marker.addTo(markersLayer);
                markersGroup.push(marker);

            } catch (err) {
                console.warn('Error creating marker:', err);
            }
        });

        // Update button styles
        updateButtonStyles('markers');

        currentView = 'markers';
        console.log(`Added ${markersGroup.length} markers`);

    } catch (error) {
        console.error('Error switching to markers view:', error);
        showMapError('Failed to switch to markers view');
    }
}

/**
 * Get color for crime category
 */
function getCategoryColor(category) {
    const colors = {
        'Theft': '#f59e0b',
        'Robbery': '#ef4444',
        'Assault': '#dc2626',
        'Burglary': '#f97316',
        'Vandalism': '#84cc16',
        'Homicide': '#7f1d1d',
        'Drug Related': '#6366f1',
        'Rape': '#e11d48',
        'Kidnapping': '#a21caf',
        'Unknown': '#6b7280'
    };
    return colors[category] || colors['Unknown'];
}

/**
 * Update button styling
 */
function updateButtonStyles(activeView) {
    const heatmapBtn = document.getElementById('heatmapViewBtn');
    const markersBtn = document.getElementById('markersViewBtn');

    if (activeView === 'heatmap') {
        heatmapBtn.classList.add('bg-alertara-600', 'hover:bg-alertara-700');
        markersBtn.classList.remove('bg-alertara-600', 'hover:bg-alertara-700');
        markersBtn.classList.add('text-gray-300', 'hover:bg-gray-700');
    } else {
        markersBtn.classList.add('bg-alertara-600', 'hover:bg-alertara-700');
        heatmapBtn.classList.remove('bg-alertara-600', 'hover:bg-alertara-700');
        heatmapBtn.classList.add('text-gray-300', 'hover:bg-gray-700');
    }
}

/**
 * Show error message on map
 */
function showMapError(message) {
    const loader = document.getElementById('mapLoader');
    if (loader) {
        loader.innerHTML = `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-5xl mb-4 block"></i>
                <p class="text-white text-lg font-medium">${escapeHtml(message)}</p>
                <button onclick="location.reload()" class="mt-4 px-4 py-2 bg-alertara-600 text-white rounded hover:bg-alertara-700">
                    Retry
                </button>
            </div>
        `;
        loader.classList.remove('hidden');
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, char => map[char]);
}

/**
 * Attach event listeners for interactive controls
 */
function attachEventListeners() {
    try {
        // Heatmap view button
        const heatmapBtn = document.getElementById('heatmapViewBtn');
        if (heatmapBtn) {
            heatmapBtn.addEventListener('click', switchToHeatmap);
        }

        // Markers view button
        const markersBtn = document.getElementById('markersViewBtn');
        if (markersBtn) {
            markersBtn.addEventListener('click', switchToMarkers);
        }

        // Date range filter
        const dateFilter = document.getElementById('dateRangeFilter');
        if (dateFilter) {
            dateFilter.addEventListener('change', function() {
                loadCrimeData();
                // Reload current view
                if (currentView === 'markers') {
                    // Small delay to ensure data is loaded
                    setTimeout(switchToMarkers, 500);
                }
            });
        }

    } catch (error) {
        console.error('Error attaching event listeners:', error);
    }
}

/**
 * Handle responsive map resizing
 */
window.addEventListener('resize', function() {
    if (map) {
        map.invalidateSize();
    }
});

console.log('Landing map script loaded successfully');
