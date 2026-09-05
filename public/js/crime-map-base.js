/*
 * Shared base map for every crime map in the system.
 *
 * createCrimeMap() is the exact setup Crime Mapping uses (OpenStreetMap
 * tiles, zoom limits, inertia). drawSanAgustinBoundary() draws the Quezon
 * City barangays the same way Crime Mapping does when Barangay San Agustin
 * is isolated: neighbours faint for context, San Agustin outlined in brand
 * teal with its name label, the view framed and held on it.
 *
 * Used by: resources/views/mapping.blade.php (Crime Mapping) and
 *          resources/views/crime-incident-create.blade.php (Add Crime Record).
 */
(function (global) {
    'use strict';

    // Barangay San Agustin, Quezon City — 'code' in /qc_barangays.geojson
    const SAN_AGUSTIN_CODE = '137404095';
    const SAN_AGUSTIN_NAME = 'San Agustin';

    // Same styles as Crime Mapping's STYLE_BRGY_IDLE / STYLE_BRGY_ACTIVE
    const STYLE_BRGY_IDLE   = { color: '#8fb3b0', weight: 0.8, opacity: 0.7, fillColor: '#f2f9f8', fillOpacity: 0.10, dashArray: null };
    const STYLE_BRGY_ACTIVE = { color: '#274d4c', weight: 2.5, opacity: 1,   fillColor: '#bfe5de', fillOpacity: 0.16, dashArray: null };
    const STYLE_QC_OUTLINE  = { color: '#274d4c', weight: 2, opacity: 0.9, fill: false };

    // Reusable base-map component: the SAME map setup is used by the main
    // crime mapping view, the street modal and the add-crime form, so they
    // behave identically (tiles, zoom limits, inertia) and there is one
    // place to fix bugs.
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

    /**
     * Draw the barangay boundaries with San Agustin isolated, exactly like
     * Crime Mapping's default view. Resolves with { bounds, layer } for the
     * San Agustin polygon (bounds null if the geojson could not be loaded).
     *
     * opts.lockView (default true): fit and hold the map on the barangay.
     * opts.paneZIndex (default 350): boundaries sit below the streets (360)
     *   and the markers (400) so they never swallow clicks.
     */
    function drawSanAgustinBoundary(map, opts) {
        const o = Object.assign({ lockView: true, paneZIndex: 350, cityOutline: true }, opts || {});

        if (!map.getPane('barangayPane')) {
            map.createPane('barangayPane');
            map.getPane('barangayPane').style.zIndex = String(o.paneZIndex);
        }
        const renderer = L.svg({ pane: 'barangayPane' });
        const stamp = Date.now();

        const cityPromise = !o.cityOutline ? Promise.resolve(null) :
            fetch('/fullmapqc.geojson?t=' + stamp)
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(data => L.geoJSON(data, { style: STYLE_QC_OUTLINE, interactive: false, pane: 'barangayPane', renderer: renderer }).addTo(map))
                .catch(err => { console.warn('QC outline not loaded:', err); return null; });

        const brgyPromise = fetch('/qc_barangays.geojson?t=' + stamp)
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json() })
            .then(data => {
                let active = null;
                const layer = L.geoJSON(data, {
                    pane: 'barangayPane',
                    renderer: renderer,
                    interactive: false,
                    style: f => Object.assign({}, String((f.properties || {}).code || '') === SAN_AGUSTIN_CODE ? STYLE_BRGY_ACTIVE : STYLE_BRGY_IDLE),
                    onEachFeature: (f, l) => { if (String((f.properties || {}).code || '') === SAN_AGUSTIN_CODE) active = l; }
                }).addTo(map);

                if (!active) {
                    console.warn('San Agustin boundary not found in qc_barangays.geojson');
                    return { bounds: null, layer: null, all: layer };
                }
                active.bringToFront();
                const bounds = active.getBounds();

                // Permanent centre label, same treatment as Crime Mapping
                L.marker(bounds.getCenter(), { interactive: false, icon: L.divIcon({ className: '', html: '' }) })
                    .addTo(map)
                    .bindTooltip(SAN_AGUSTIN_NAME, { permanent: true, direction: 'center', className: 'brgy-label-selected' })
                    .openTooltip();

                if (o.lockView) {
                    map.invalidateSize();
                    map.fitBounds(bounds, { padding: [12, 12], animate: false });
                    map.setMaxBounds(bounds.pad(0.8));
                    map.setMinZoom(Math.max(12, map.getZoom() - 2));
                }
                return { bounds: bounds, layer: active, all: layer };
            })
            .catch(err => { console.warn('Barangay boundaries not loaded:', err); return { bounds: null, layer: null, all: null }; });

        return Promise.all([cityPromise, brgyPromise]).then(([city, b]) => {
            if (city && city.bringToFront) city.bringToFront();
            if (b.layer && b.layer.bringToFront) b.layer.bringToFront();
            return b;
        });
    }

    global.createCrimeMap = createCrimeMap;
    global.CrimeMapBase = {
        createCrimeMap: createCrimeMap,
        drawSanAgustinBoundary: drawSanAgustinBoundary,
        SAN_AGUSTIN_CODE: SAN_AGUSTIN_CODE,
        SAN_AGUSTIN_NAME: SAN_AGUSTIN_NAME,
        STYLE_BRGY_IDLE: STYLE_BRGY_IDLE,
        STYLE_BRGY_ACTIVE: STYLE_BRGY_ACTIVE,
    };
})(window);
