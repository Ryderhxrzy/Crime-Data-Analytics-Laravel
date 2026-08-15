/*
 * Public crime heatmap.
 *
 * Scope: Barangay San Agustin only. The incident API is backed by the San
 * Agustin table, so the map frames that barangay and highlights it the same
 * way the authenticated crime mapping page does — the selected barangay is
 * drawn in brand teal, its neighbours stay faint for context.
 *
 * This map is heat-only. No incident markers are ever added, so no individual
 * case is identifiable from the public page.
 */

// Barangay San Agustin, Quezon City — 'code' in public/qc_barangays.geojson.
const SAN_AGUSTIN_CODE = '137404095';
const SAN_AGUSTIN_NAME = 'San Agustin';

// Boundary styling, matched to the AlerTaraQC palette.
const STYLE_BRGY_IDLE = {
    color: '#8fb3b0',
    weight: 0.8,
    opacity: 0.7,
    fillColor: '#f2f9f8',
    fillOpacity: 0.10,
};

const STYLE_BRGY_ACTIVE = {
    color: '#2f7d7b',
    weight: 2.5,
    opacity: 1,
    fillColor: '#bfe5de',
    fillOpacity: 0.16,
};

let map;
let heatLayer;
let barangayLayer;
let sanAgustinLayer;
let sanAgustinBounds;

document.addEventListener('DOMContentLoaded', function () {
    initMap();
    attachEventListeners();
});

/**
 * Initialize Leaflet map with the base layer and barangay boundaries
 */
function initMap() {
    try {
        // Opening view; loadBarangayBoundaries() reframes onto San Agustin as
        // soon as the GeoJSON lands.
        map = L.map('crimeMap', { zoomControl: true }).setView([14.6760, 121.0437], 13);

        // Boundaries sit below the heat canvas (overlayPane is 400).
        map.createPane('barangayPane');
        map.getPane('barangayPane').style.zIndex = 350;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
            minZoom: 11
        }).addTo(map);

        loadBarangayBoundaries();
        loadCrimeData();

    } catch (error) {
        console.error('Error initializing map:', error);
        showMapError('Failed to initialize map');
    }
}

/**
 * Draw the Quezon City barangays, highlight San Agustin, and lock the view to it.
 */
function loadBarangayBoundaries() {
    fetch('/qc_barangays.geojson')
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            barangayLayer = L.geoJSON(data, {
                pane: 'barangayPane',
                interactive: false,
                style: function (feature) {
                    const code = String((feature.properties || {}).code || '');
                    return code === SAN_AGUSTIN_CODE
                        ? Object.assign({}, STYLE_BRGY_ACTIVE)
                        : Object.assign({}, STYLE_BRGY_IDLE);
                },
                onEachFeature: function (feature, layer) {
                    const code = String((feature.properties || {}).code || '');
                    if (code === SAN_AGUSTIN_CODE) sanAgustinLayer = layer;
                }
            }).addTo(map);

            if (!sanAgustinLayer) {
                console.warn('San Agustin boundary not found in qc_barangays.geojson');
                return;
            }

            // Keep the highlighted barangay drawn over its neighbours.
            sanAgustinLayer.bringToFront();

            sanAgustinBounds = sanAgustinLayer.getBounds();

            // Permanent centre label, same treatment as the crime mapping page.
            L.marker(sanAgustinBounds.getCenter(), {
                interactive: false,
                icon: L.divIcon({ className: '', html: '' })
            })
                .addTo(map)
                .bindTooltip(SAN_AGUSTIN_NAME, {
                    permanent: true,
                    direction: 'center',
                    className: 'brgy-label-selected'
                })
                .openTooltip();

            // Frame the barangay, then hold the view around it. The padding gives
            // enough of the neighbouring streets for orientation without letting
            // the viewport wander across the city.
            map.fitBounds(sanAgustinBounds, { padding: [16, 16] });
            map.setMaxBounds(sanAgustinBounds.pad(0.8));
            map.setMinZoom(Math.max(12, map.getZoom() - 2));
            map.setMaxZoom(18);
        })
        .catch(error => {
            console.warn('Could not load barangay boundaries:', error);
            // The heatmap still works without the outline, so this is not fatal.
        });
}

/**
 * Fetch incident coordinates and render them as a single heat layer.
 */
async function loadCrimeData() {
    const loader = document.getElementById('mapLoader');

    try {
        if (loader) loader.classList.remove('hidden');

        const dateRange = document.getElementById('dateRangeFilter').value;
        const response = await fetch(`/api/crime-heatmap?range=${encodeURIComponent(dateRange)}`);

        if (!response.ok) throw new Error(`API error: ${response.status}`);

        const data = await response.json();

        if (!Array.isArray(data) || data.length === 0) {
            showMapError('No crime data available for this period.');
            return;
        }

        // The API returns latitude/longitude; older payloads used lat/lng.
        const heatPoints = data
            .map(incident => [
                parseFloat(incident.latitude ?? incident.lat),
                parseFloat(incident.longitude ?? incident.lng),
                0.5
            ])
            .filter(point => Number.isFinite(point[0]) && Number.isFinite(point[1]));

        if (heatPoints.length === 0) {
            showMapError('No mappable incidents for this period.');
            return;
        }

        if (heatLayer && map.hasLayer(heatLayer)) {
            map.removeLayer(heatLayer);
        }

        // Radius/blur are tuned for a single barangay rather than the whole city.
        heatLayer = L.heatLayer(heatPoints, {
            radius: 25,
            blur: 30,
            maxZoom: 17,
            minOpacity: 0.25,
            gradient: {
                0.0: '#3498db',      // Blue - Low density
                0.2: '#2ecc71',      // Green - Low-Medium density
                0.4: '#f39c12',      // Orange - Medium density
                0.6: '#e74c3c',      // Red - Medium-High density
                0.8: '#c0392b',      // Dark Red - High density
                1.0: '#8b0000'       // Dark Red - Very High density
            }
        }).addTo(map);

        console.log(`Loaded ${heatPoints.length} mappable incidents`);

    } catch (error) {
        console.error('Error loading crime data:', error);
        showMapError('Failed to load crime data. Please refresh the page.');
    } finally {
        if (loader) loader.classList.add('hidden');
    }
}

/**
 * Show a message over the map panel
 */
function showMapError(message) {
    const loader = document.getElementById('mapLoader');
    if (loader) {
        // The loader sits on a white panel, so the copy has to be dark to be readable.
        loader.innerHTML = `
            <div class="text-center px-6">
                <i class="fas fa-triangle-exclamation text-3xl mb-3 block" style="color:#b45309"></i>
                <p class="text-base font-medium" style="color:#0b132b">${escapeHtml(message)}</p>
                <button onclick="location.reload()" class="mt-4 rounded-full px-5 py-2.5 text-sm font-semibold text-white" style="background:#2f7d7b">
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
    return String(text).replace(/[&<>"']/g, char => map[char]);
}

/**
 * Attach event listeners for interactive controls
 */
function attachEventListeners() {
    try {
        const dateFilter = document.getElementById('dateRangeFilter');
        if (dateFilter) {
            dateFilter.addEventListener('change', function () {
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
window.addEventListener('resize', function () {
    if (map) map.invalidateSize();
});

console.log('Landing map script loaded successfully');
