// Global variables
let map;
let heatLayer;
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

        // Load crime data
        loadCrimeData();

    } catch (error) {
        console.error('Error initializing map:', error);
        showMapError('Failed to initialize map');
    }
}

/**
 * Load Quezon City boundary from GeoJSON file and restrict map to QC area only
 */
function loadQcBoundary() {
    try {
        fetch('/qc_map.geojson')
            .then(response => {
                if (!response.ok) throw new Error('Failed to load GeoJSON');
                return response.json();
            })
            .then(data => {
                // Calculate bounds from GeoJSON to restrict map view
                let bounds = L.geoJSON(data).getBounds();

                // Set max bounds to QC area - users cannot pan outside this area
                map.setMaxBounds(bounds.pad(0.05)); // 5% padding for visual comfort

                // Restrict zoom to reasonable levels for QC
                map.setMinZoom(11);
                map.setMaxZoom(18);

                // Add QC boundary as visual overlay with light, clean styling
                qcGeoJsonLayer = L.geoJSON(data, {
                    style: {
                        color: '#274d4c',           // Theme green border - Quezon City boundary
                        weight: 5,                  // Bold border for clear definition
                        opacity: 1,                 // Full opacity on border
                        fillOpacity: 0.08,          // Very light fill to show QC area clearly
                        fill: true,
                        fillColor: '#e8f5f3',       // Very light green-tinted fill for visibility
                        lineCap: 'round',
                        lineJoin: 'round'
                    },
                    onEachFeature: function(feature, layer) {
                        // Add popup to show QC area info
                        layer.bindPopup('<div style="text-align:center;"><strong style="color:#274d4c;">Quezon City</strong><br><small>Coverage Area - Restricted to this boundary</small></div>');

                        // Add hover effect for interactivity
                        layer.on('mouseover', function() {
                            this.setStyle({
                                weight: 6,
                                opacity: 1,
                                fillOpacity: 0.15,
                                fillColor: '#d0ebe7'
                            });
                            this.openPopup();
                        });
                        layer.on('mouseout', function() {
                            this.setStyle({
                                weight: 5,
                                opacity: 1,
                                fillOpacity: 0.08,
                                fillColor: '#e8f5f3'
                            });
                            this.closePopup();
                        });
                    }
                }).addTo(map);

                console.log('QC boundary loaded - QC area light, outside areas darkened');
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
            ? '/api/crime-heatmap?range=all'
            : `/api/crime-heatmap?range=${dateRange}`;

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

        // Create new heatmap layer with Leaflet.heat - density-based color gradient
        heatLayer = L.heatLayer(heatPoints, {
            radius: 30,
            blur: 40,
            maxZoom: 13,
            minOpacity: 0.2,
            gradient: {
                0.0: '#3498db',      // Blue - Low density
                0.2: '#2ecc71',      // Green - Low-Medium density
                0.4: '#f39c12',      // Orange - Medium density
                0.6: '#e74c3c',      // Red - Medium-High density
                0.8: '#c0392b',      // Dark Red - High density
                1.0: '#8b0000'       // Dark Red - Very High density
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
        // Date range filter
        const dateFilter = document.getElementById('dateRangeFilter');
        if (dateFilter) {
            dateFilter.addEventListener('change', function() {
                loadCrimeData();
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
