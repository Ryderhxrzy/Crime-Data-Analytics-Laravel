/*
 * Google Maps view for the crime maps (default engine).
 *
 * Mounts a Google Map (Hybrid: satellite imagery + roads + street names +
 * place labels) in an overlay on top of a page's existing map container and
 * shows the SAME incidents the page currently holds, read through a callback.
 * Crime records stay in our Laravel backend: only the coordinates needed to
 * draw the markers are handed to the map, exactly as with the Leaflet map.
 *
 *   const gmap = CrimeMapGoogle.create({
 *       wrapper: document.getElementById('mapContainer'),
 *       getIncidents: () => currentData,
 *       getMode: () => document.getElementById('visualizationMode').value,
 *       streetView: true,   // native Pegman with the Google-style inset map
 *   });
 *   gmap.refresh();   // after the page reloads its data
 *
 * Shared helpers (also used by the Add Crime Record page's own Google map):
 *   CrimeMapGoogle.ringsOf(geometry)                -> [[ [lat,lng], ... ], ...]
 *   CrimeMapGoogle.insideRings(rings, lat, lng)     -> boolean
 *   CrimeMapGoogle.outsideMask(map, rings)          -> dims everything outside the rings
 *   CrimeMapGoogle.attachStreetView(google, map, hostEl, mapEl, { inside })
 *   CrimeMapGoogle.switcher({ engines, buttons, defaultEngine, storageKey })
 */
(function (global) {
    'use strict';

    const STREETS_URL = '/data/san_agustin_streets.geojson';
    const BARANGAYS_URL = '/qc_barangays.geojson';
    const SA_CODE = '137404095';
    const SA_CENTER = { lat: 14.7292, lng: 121.0385 };

    const SEVERITY = [
        { min: 15, label: 'Critical', color: '#7f1d1d' },
        { min: 10, label: 'High',     color: '#dc2626' },
        { min: 5,  label: 'Moderate', color: '#f97316' },
        { min: 1,  label: 'Low',      color: '#ca8a04' },
        { min: 0,  label: 'Cleared',  color: '#16a34a' },
    ];
    const severityFor = n => SEVERITY.find(x => n >= x.min) || SEVERITY[SEVERITY.length - 1];
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let cssInjected = false;
    function injectCss() {
        if (cssInjected) return;
        cssInjected = true;
        const st = document.createElement('style');
        st.textContent = `
            .cmg-overlay { position: absolute; inset: 0; z-index: 1100; display: none; background: #e5e7eb; }
            .cmg-overlay.on { display: block; }
            .cmg-map { position: absolute; inset: 0; }
            .cmg-badge { position: absolute; bottom: 24px; left: 10px; z-index: 5; background: rgba(255,255,255,.94); border: 1px solid #e5e7eb; border-radius: 9999px; padding: 3px 10px; font-size: 10.5px; font-weight: 700; color: #374151; }
            .cmg-legend { position: absolute; top: 10px; right: 10px; z-index: 5; background: rgba(255,255,255,.96); border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 12px; font-size: 11px; color: #374151; line-height: 1.7; box-shadow: 0 2px 10px rgba(0,0,0,.18); }
            .cmg-legend b { display: block; font-size: 11px; color: #111827; }
            .cmg-legend span { display: inline-block; width: 20px; height: 5px; border-radius: 3px; margin-right: 7px; vertical-align: middle; }
            /* Street View like Google Maps: the panorama fills the host and the SAME map
               (streets + crime circles) becomes an inset box at the bottom left */
            .cmg-pano { position: absolute; inset: 0; display: none; background: #111; z-index: 3; }
            .cmg-sv-host.cmg-split .cmg-pano { display: block; }
            .cmg-sv-host.cmg-split .cmg-sv-map { top: auto !important; right: auto !important; left: 12px !important; bottom: 12px !important; width: 34% !important; min-width: 240px; height: 38% !important; min-height: 170px; z-index: 8; border: 3px solid #fff; border-radius: 10px; box-shadow: 0 8px 28px rgba(0,0,0,.45); overflow: hidden; transition: width .25s, height .25s; }
            .cmg-sv-host.cmg-split.cmg-inset-big .cmg-sv-map { width: 60% !important; height: 62% !important; }
            .cmg-overlay.cmg-split .cmg-legend, .cmg-overlay.cmg-split .cmg-badge { display: none; }
            .cmg-inset-btn { position: absolute; display: none; z-index: 9; left: calc(12px + 34% - 34px); bottom: calc(12px + 38% - 34px); width: 28px; height: 28px; border-radius: 6px; background: #fff; border: 1px solid #d1d5db; color: #374151; font-size: 12px; cursor: pointer; box-shadow: 0 1px 4px rgba(0,0,0,.25); }
            .cmg-sv-host.cmg-split .cmg-inset-btn { display: block; }
            .cmg-sv-host.cmg-split.cmg-inset-big .cmg-inset-btn { left: calc(12px + 60% - 34px); bottom: calc(12px + 62% - 34px); }
            .cmg-split-hint { position: absolute; top: 10px; left: 50%; transform: translateX(-50%); z-index: 9; background: rgba(17,24,39,.85); color: #fff; border-radius: 9999px; padding: 4px 12px; font-size: 11px; font-weight: 700; display: none; pointer-events: none; }
            .cmg-sv-host.cmg-split .cmg-split-hint { display: block; }
            .cmg-sv-toast { position: absolute; top: 46px; left: 50%; transform: translateX(-50%); z-index: 12; background: #b45309; color: #fff; border-radius: 9999px; padding: 6px 14px; font-size: 12px; font-weight: 700; display: none; box-shadow: 0 6px 20px rgba(0,0,0,.3); pointer-events: none; }
            @media (max-width: 767px) {
                .cmg-sv-host.cmg-split .cmg-sv-map { width: 55% !important; min-width: 160px; height: 34% !important; min-height: 130px; }
                .cmg-inset-btn { display: none !important; }
            }
            .cmg-msg { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; text-align: center; padding: 20px; color: #374151; font-size: 13px; background: #f9fafb; z-index: 4; }
            .cmg-tip { background: #111827; color: #fff; border-radius: 8px; padding: 8px 10px; font-size: 11.5px; line-height: 1.45; }
            .cmg-pop { font-size: 12px; line-height: 1.45; min-width: 190px; color: #111827; }
            .cmg-street-label { color: #fff; font-weight: 800; font-size: 13px; text-shadow: 0 0 4px #000, 0 0 8px #000; }
            .map-engine-btn.on { background: #274d4c !important; color: #fff !important; border-color: #274d4c !important; }
        `;
        document.head.appendChild(st);
    }

    // ------------------------------------------------------------------
    // Shared geometry helpers
    // ------------------------------------------------------------------
    function ringsOf(geom) {
        const rings = [];
        if (!geom) return rings;
        const polys = geom.type === 'MultiPolygon' ? geom.coordinates : (geom.type === 'Polygon' ? [geom.coordinates] : []);
        polys.forEach(poly => (poly || []).forEach(ring => rings.push(ring.map(c => [+c[1], +c[0]]))));
        return rings;
    }

    // Ray casting: inside if the point is inside any ring (holes are negligible here)
    function insideRings(rings, lat, lng) {
        if (!rings || !rings.length) return true;   // boundary not loaded yet: do not block
        return rings.some(ring => {
            let inside = false;
            for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
                const yi = ring[i][0], xi = ring[i][1], yj = ring[j][0], xj = ring[j][1];
                if (((yi > lat) !== (yj > lat)) && (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi)) inside = !inside;
            }
            return inside;
        });
    }

    /**
     * Everything outside the rings is dimmed: the coverage area reads at a
     * glance, and Street View's blue coverage lines never show outside it
     * (the mask sits above Google's coverage layer).
     */
    function outsideMask(map, rings, opacity) {
        const google = global.google;
        if (!google || !rings || !rings.length) return null;
        // Google fills a polygon by winding: the hole must run OPPOSITE to the
        // outer ring. Winding is computed, not assumed. The outer ring is a
        // box around Metro Manila: a ring from lng -180 to 180 collapses
        // because Google joins consecutive points the short way round.
        const signedArea = pts => { let a = 0; for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) a += (pts[j].lng * pts[i].lat) - (pts[i].lng * pts[j].lat); return a / 2; };
        let outer = [{ lat: 16.0, lng: 119.5 }, { lat: 16.0, lng: 122.5 }, { lat: 13.5, lng: 122.5 }, { lat: 13.5, lng: 119.5 }];
        if (signedArea(outer) > 0) outer = outer.reverse();
        const holes = rings.map(r => {
            let ring = r.map(p => ({ lat: p[0], lng: p[1] }));
            if (signedArea(ring) < 0) ring = ring.reverse();
            return ring;
        });
        return new google.maps.Polygon({
            map: map, paths: [outer, ...holes], clickable: false, zIndex: 5,
            strokeWeight: 0, fillColor: '#0b1220', fillOpacity: opacity == null ? 0.72 : opacity,
        });
    }

    /**
     * Street View the way Google Maps does it, on an EXISTING map.
     *
     * The panorama is bound to the map (map.setStreetView) so Pegman, the
     * coverage lines while dragging, navigation and the close button are all
     * Google's own. While it is open the panorama fills hostEl and the map
     * element becomes an inset at the bottom left, keeping every overlay;
     * Google's own Pegman on the inset shows where you stand. Pegman cannot
     * be dropped, or walked, outside opts.inside(lat, lng).
     *
     * Returns { pano, isOpen() }.
     */
    function attachStreetView(google, map, hostEl, mapEl, opts) {
        const o = Object.assign({ inside: () => true, limitMessage: 'Street View is limited to Barangay San Agustin', keepControls: false }, opts || {});
        injectCss();
        hostEl.classList.add('cmg-sv-host');
        mapEl.classList.add('cmg-sv-map');

        const panoEl = document.createElement('div'); panoEl.className = 'cmg-pano';
        const hint = document.createElement('span'); hint.className = 'cmg-split-hint';
        hint.innerHTML = '<i class="fas fa-street-view mr-1"></i>Street View · the inset map follows you';
        const toast = document.createElement('span'); toast.className = 'cmg-sv-toast';
        const insetBtn = document.createElement('button'); insetBtn.type = 'button'; insetBtn.className = 'cmg-inset-btn'; insetBtn.title = 'Expand / shrink the map'; insetBtn.innerHTML = '<i class="fas fa-expand"></i>';
        hostEl.append(panoEl, hint, toast, insetBtn);

        const pano = new google.maps.StreetViewPanorama(panoEl, {
            visible: false, enableCloseButton: true, addressControl: true,
            fullscreenControl: false, motionTracking: false, motionTrackingControl: false,
        });
        map.setStreetView(pano);
        map.setOptions({ streetViewControl: true });

        let lastGood = null, toastTimer = null, restoring = false;
        const savedOptions = {};

        function say(text) {
            toast.textContent = text;
            toast.style.display = 'block';
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => { toast.style.display = 'none'; }, 2800);
        }

        // Google draws the (single) Pegman on the map at the panorama's
        // position and heading; the inset only has to keep it in view.
        function follow() {
            const pos = pano.getPosition();
            if (!pos) return;
            if (!map.getBounds() || !map.getBounds().contains(pos)) map.panTo(pos);
        }

        function enforceBoundary() {
            const pos = pano.getPosition();
            if (!pos || restoring) return true;
            if (o.inside(pos.lat(), pos.lng())) { lastGood = pos; return true; }
            restoring = true;
            say(o.limitMessage);
            if (lastGood) pano.setPosition(lastGood); else pano.setVisible(false);
            setTimeout(() => { restoring = false; }, 0);
            return false;
        }

        function enterSplit() {
            hostEl.classList.add('cmg-split');
            ['mapTypeControl', 'zoomControl'].forEach(k => { savedOptions[k] = map.get(k); });
            if (!o.keepControls) map.setOptions({ mapTypeControl: false, zoomControl: false });
            const c = pano.getPosition() || map.getCenter();
            google.maps.event.trigger(map, 'resize');
            if (c) { map.setCenter(c); if (map.getZoom() < 17) map.setZoom(17); }
            follow();
        }

        function exitSplit() {
            hostEl.classList.remove('cmg-split', 'cmg-inset-big');
            map.setOptions({ mapTypeControl: savedOptions.mapTypeControl !== false, zoomControl: savedOptions.zoomControl !== false, streetViewControl: true });
            const c = lastGood || map.getCenter();
            google.maps.event.trigger(map, 'resize');
            if (c) map.setCenter(c);
            lastGood = null;
        }

        pano.addListener('visible_changed', () => {
            if (pano.getVisible()) { if (enforceBoundary()) enterSplit(); }
            else exitSplit();
        });
        pano.addListener('position_changed', () => { if (pano.getVisible() && enforceBoundary()) follow(); });

        // Clicking the inset moves Pegman there when Street View covers that spot
        const svService = new google.maps.StreetViewService();
        map.addListener('click', e => {
            if (!pano.getVisible()) return;
            if (!o.inside(e.latLng.lat(), e.latLng.lng())) { say(o.limitMessage); return; }
            svService.getPanorama({ location: e.latLng, radius: 40, source: google.maps.StreetViewSource.OUTDOOR }).then(r => {
                if (r && r.data && r.data.location && r.data.location.pano) pano.setPano(r.data.location.pano);
                else say('No Street View at that spot');
            }).catch(() => say('No Street View at that spot'));
        });

        insetBtn.addEventListener('click', () => {
            hostEl.classList.toggle('cmg-inset-big');
            const c = pano.getPosition() || map.getCenter();
            setTimeout(() => { google.maps.event.trigger(map, 'resize'); if (c) map.setCenter(c); }, 260);
        });

        return { pano: pano, isOpen: () => pano.getVisible() };
    }

    // ------------------------------------------------------------------
    // Overlay view for the crime pages
    // ------------------------------------------------------------------
    function create(opts) {
        const o = Object.assign({
            wrapper: null,
            getIncidents: () => [],
            getMode: () => 'markers',
            modeSelect: null,
            showStreets: true,
            showLegend: true,
            zoom: 16,
            mapTypeId: 'hybrid',
            onReady: null,
            // Native Street View (Pegman) on the SAME map instance, with the
            // Google-style inset map. Off by default; Crime Mapping turns it on.
            streetView: false,
        }, opts || {});
        if (!o.wrapper) throw new Error('CrimeMapGoogle: wrapper element required');
        injectCss();
        if (getComputedStyle(o.wrapper).position === 'static') o.wrapper.style.position = 'relative';

        const overlay = document.createElement('div');
        overlay.className = 'cmg-overlay';
        overlay.innerHTML =
            '<div class="cmg-map"></div>' +
            '<span class="cmg-badge"><i class="fas fa-spinner fa-spin mr-1"></i>Loading Google Maps</span>' +
            (o.showLegend && o.showStreets ? '<div class="cmg-legend"><b><i class="fas fa-road" style="color:#274d4c"></i> Street crime level</b>' +
                SEVERITY.map(s => '<div><span style="background:' + s.color + '"></span>' + s.label + '</div>').join('') + '</div>' : '');
        o.wrapper.appendChild(overlay);
        const mapEl = overlay.querySelector('.cmg-map');
        const badge = overlay.querySelector('.cmg-badge');

        let map = null, ready = false, failed = false, shown = false, initPromise = null;
        let streetLayer = null, boundaryLayer = null, heat = null, streetTip = null, infoWin = null, labelMarker = null, mask = null, sv = null;
        let markers = [];
        let streetGeo = null, saBounds = null, pending = null, streetCountsCache = {};
        let saRings = [];

        function showMessage(text) {
            let m = overlay.querySelector('.cmg-msg');
            if (!m) { m = document.createElement('div'); m.className = 'cmg-msg'; overlay.appendChild(m); }
            m.innerHTML = '<div><i class="fas fa-triangle-exclamation text-2xl mb-2 block" style="color:#b45309"></i>' + esc(text) + '</div>';
            badge.innerHTML = '<i class="fas fa-map mr-1"></i>Google Maps unavailable';
        }

        // The map is built once; later show() calls only resize/refresh it.
        function init() {
            if (initPromise) return initPromise;
            if (typeof GoogleMapsLoader === 'undefined') { failed = true; showMessage('The Google Maps loader script is missing.'); return Promise.resolve(false); }
            if (!GoogleMapsLoader.hasKey()) { failed = true; showMessage('Google Maps API key is not configured. Set GOOGLE_MAPS_API_KEY in .env.'); return Promise.resolve(false); }

            initPromise = GoogleMapsLoader.load(['maps', 'visualization']).then(google => {
                map = new google.maps.Map(mapEl, {
                    center: SA_CENTER,
                    zoom: o.zoom,
                    mapTypeId: o.mapTypeId,            // HYBRID: imagery + roads + names + place labels
                    mapTypeControl: true,               // native control: Roadmap / Satellite / Hybrid
                    mapTypeControlOptions: {
                        mapTypeIds: [google.maps.MapTypeId.ROADMAP, google.maps.MapTypeId.SATELLITE, google.maps.MapTypeId.HYBRID],
                        style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
                        position: google.maps.ControlPosition.TOP_LEFT,
                    },
                    streetViewControl: !!o.streetView,  // Pegman only where a page asks for it
                    streetViewControlOptions: { position: google.maps.ControlPosition.RIGHT_BOTTOM },
                    zoomControlOptions: { position: google.maps.ControlPosition.RIGHT_BOTTOM },
                    fullscreenControl: false,           // the pages have their own fullscreen
                    rotateControl: false,
                    tilt: 0,                            // flat imagery, not 45° / 3D tiles
                    clickableIcons: false,              // no Places pop-ups from base map POIs
                    gestureHandling: 'greedy',
                });
                infoWin = new google.maps.InfoWindow({ maxWidth: 300 });
                streetTip = new google.maps.InfoWindow({ disableAutoPan: true, headerDisabled: true, pixelOffset: new google.maps.Size(0, -8) });

                addBoundary(google);
                if (o.showStreets) addStreets(google);
                if (o.streetView) sv = attachStreetView(google, map, overlay, mapEl, { inside: (lat, lng) => insideRings(saRings, lat, lng) });
                ready = true;
                badge.innerHTML = '<i class="fab fa-google mr-1"></i>Google Maps';
                setIncidents(pending || o.getIncidents() || []);
                pending = null;
                if (typeof o.onReady === 'function') o.onReady(map, google);
                return true;
            }).catch(err => {
                failed = true;
                console.warn('Google Maps unavailable:', err);
                showMessage('Google Maps could not be loaded: ' + (err && err.message ? err.message : err));
                return false;
            });
            return initPromise;
        }

        function addBoundary(google) {
            boundaryLayer = new google.maps.Data({ map: map });
            fetch(BARANGAYS_URL + '?t=' + Date.now()).then(r => r.json()).then(geo => {
                boundaryLayer.addGeoJson(geo);
                boundaryLayer.setStyle(f => {
                    const sa = String(f.getProperty('code') || '') === SA_CODE;
                    return { clickable: false, strokeColor: sa ? '#5eead4' : '#8fb3b0', strokeWeight: sa ? 3 : 0.8, strokeOpacity: sa ? 1 : 0.6, fillColor: sa ? '#5eead4' : '#f2f9f8', fillOpacity: sa ? 0.10 : 0.04, zIndex: sa ? 2 : 1 };
                });
                const sa = (geo.features || []).find(f => String((f.properties || {}).code || '') === SA_CODE);
                if (!sa) return;
                saRings = ringsOf(sa.geometry);
                mask = outsideMask(map, saRings);
                const b = new google.maps.LatLngBounds();
                (function walk(c) { if (typeof c[0] === 'number') b.extend({ lat: c[1], lng: c[0] }); else c.forEach(walk); })(sa.geometry.coordinates);
                saBounds = b;
                labelMarker = new google.maps.Marker({
                    map: map, position: b.getCenter(), clickable: false, zIndex: 3,
                    icon: { path: 'M 0 0', scale: 0 },
                    label: { text: 'San Agustin', className: 'cmg-street-label' },
                });
                map.fitBounds(b, 12);
                // Roomy restriction: the barangay and enough of its surroundings
                const ne = b.getNorthEast(), sw = b.getSouthWest();
                const padLat = (ne.lat() - sw.lat()) * 1.5, padLng = (ne.lng() - sw.lng()) * 1.5;
                map.setOptions({ restriction: { latLngBounds: { north: ne.lat() + padLat, south: sw.lat() - padLat, east: ne.lng() + padLng, west: sw.lng() - padLng }, strictBounds: false } });
            }).catch(err => console.warn('Google view: barangay boundaries not loaded', err));
        }

        // Per-street counts from the incidents themselves (their `street` field)
        function streetCounts(incidents) {
            const counts = {};
            incidents.forEach(i => {
                const s = String(i.street || (i.address ? i.address.split(',')[0] : '') || '').trim();
                if (!s) return;
                const k = s.toLowerCase();
                counts[k] = counts[k] || { count: 0, cats: {} };
                counts[k].count++;
                const c = String(i.category_name || '').trim();
                if (c) counts[k].cats[c] = (counts[k].cats[c] || 0) + 1;
            });
            return counts;
        }

        function styleStreets() {
            if (!streetLayer) return;
            const mode = String(o.getMode() || 'markers');
            streetLayer.setStyle(f => {
                const name = String(f.getProperty('name') || '').trim();
                const st = streetCountsCache[name.toLowerCase()];
                const count = st ? st.count : 0;
                const sev = severityFor(count);
                const bold = mode === 'street-heatmap' && count > 0;
                return { strokeColor: sev.color, strokeOpacity: count > 0 ? 0.95 : 0.7, strokeWeight: bold ? 4 + Math.min(8, count / 2) : (count > 0 ? 4 : 2.5), zIndex: 10 + count };
            });
        }

        function addStreets(google) {
            streetLayer = new google.maps.Data({ map: map });
            fetch(STREETS_URL + '?t=' + Date.now(), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(geo => {
                streetGeo = geo;
                streetLayer.addGeoJson(geo);
                styleStreets();
                // A street is several geojson features (one per OSM way), so
                // hover highlights EVERY feature sharing the name, not just the
                // piece under the cursor — same as the Leaflet street layer.
                const sameStreet = name => { const out = []; streetLayer.forEach(ft => { if (String(ft.getProperty('name') || '').trim() === name) out.push(ft); }); return out; };
                streetLayer.addListener('mouseover', e => {
                    const f = e.feature;
                    const name = String(f.getProperty('name') || '').trim();
                    const st = streetCountsCache[name.toLowerCase()];
                    const count = st ? st.count : 0;
                    const sev = severityFor(count);
                    const top = st ? Object.entries(st.cats).sort((a, b) => b[1] - a[1])[0] : null;
                    sameStreet(name).forEach(ft => streetLayer.overrideStyle(ft, { strokeWeight: 8, strokeOpacity: 1, zIndex: 90 }));
                    streetTip.setContent('<div class="cmg-tip"><div style="font-weight:700;margin-bottom:2px">' + esc(name) + '</div>' +
                        '<div style="margin-bottom:3px"><span style="display:inline-block;width:28px;height:5px;border-radius:3px;background:' + sev.color + ';vertical-align:middle"></span> <span style="font-weight:700;color:' + sev.color + '">' + sev.label + '</span></div>' +
                        (count > 0 ? '<div>' + count + ' crime' + (count === 1 ? '' : 's') + (top ? ' · mostly ' + esc(top[0]) : '') + '</div>' : '<div>No recorded crimes — cleared</div>') + '</div>');
                    streetTip.setPosition(e.latLng);
                    streetTip.open({ map: map });
                });
                streetLayer.addListener('mousemove', e => streetTip.setPosition(e.latLng));
                streetLayer.addListener('mouseout', e => {
                    sameStreet(String(e.feature.getProperty('name') || '').trim()).forEach(ft => streetLayer.revertStyle(ft));
                    streetTip.close();
                });
            }).catch(err => console.warn('Google view: streets not loaded', err));
        }

        function clearMarkers() { markers.forEach(m => m.setMap(null)); markers = []; }

        function incidentPopup(i) {
            return '<div class="cmg-pop">' +
                '<div style="font-weight:800;margin-bottom:2px">' + esc(i.incident_title || i.title || i.category_name || 'Incident') + '</div>' +
                '<div style="display:flex;align-items:center;gap:6px;margin-bottom:6px"><span style="width:10px;height:10px;border-radius:9999px;background:' + esc(i.color_code || '#274d4c') + ';display:inline-block"></span><span style="font-weight:700;color:#374151">' + esc(i.category_name || 'Unknown') + '</span></div>' +
                (i.incident_code ? '<div style="font-family:monospace;font-size:11px;color:#6b7280">' + esc(i.incident_code) + '</div>' : '') +
                (i.street ? '<div><i class="fas fa-road" style="color:#274d4c;width:14px"></i> ' + esc(i.street) + '</div>' : '') +
                (i.incident_date ? '<div><i class="far fa-calendar" style="color:#274d4c;width:14px"></i> ' + esc(i.incident_date) + (i.incident_time ? ' ' + esc(i.incident_time) : '') + '</div>' : '') +
                (i.status ? '<div><i class="fas fa-flag" style="color:#274d4c;width:14px"></i> ' + esc(String(i.status).replace(/_/g, ' ')) + (i.clearance_status ? ' · ' + esc(i.clearance_status) : '') + '</div>' : '') +
                '</div>';
        }

        function setIncidents(incidents) {
            incidents = Array.isArray(incidents) ? incidents : [];
            if (!ready) { pending = incidents; return; }
            const google = global.google;
            const mode = String(o.getMode() || 'markers');
            streetCountsCache = streetCounts(incidents);
            styleStreets();

            clearMarkers();
            if (heat) { heat.setMap(null); heat = null; }

            const points = [];
            incidents.forEach(i => {
                const lat = parseFloat(i.latitude ?? i.lat), lng = parseFloat(i.longitude ?? i.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                points.push({ lat, lng, i });
            });

            if (mode === 'heatmap' && google.maps.visualization) {
                heat = new google.maps.visualization.HeatmapLayer({
                    map: map, radius: 28, opacity: 0.8,
                    data: points.map(p => new google.maps.LatLng(p.lat, p.lng)),
                    gradient: ['rgba(59,130,246,0)', '#3b82f6', '#2ecc71', '#f39c12', '#e74c3c', '#c0392b'],
                });
            } else if (mode !== 'street-heatmap') {
                points.forEach(p => {
                    const m = new google.maps.Marker({
                        map: map, position: { lat: p.lat, lng: p.lng }, optimized: true, zIndex: 100,
                        title: p.i.incident_title || p.i.category_name || '',
                        icon: { path: google.maps.SymbolPath.CIRCLE, scale: 6, fillColor: p.i.color_code || '#274d4c', fillOpacity: 0.95, strokeColor: '#ffffff', strokeWeight: 1.5 },
                    });
                    m.addListener('click', () => { if (sv && sv.isOpen()) return; infoWin.setContent(incidentPopup(p.i)); infoWin.open({ map: map, anchor: m }); });
                    markers.push(m);
                });
            }
            const modeLabel = { markers: 'markers', heatmap: 'heat map', 'street-heatmap': 'street segments', clusters: 'markers' }[mode] || mode;
            badge.innerHTML = '<i class="fab fa-google mr-1"></i>Google Maps · ' + modeLabel + ' · ' + incidents.length + ' incident' + (incidents.length === 1 ? '' : 's');
        }

        const api = {
            kind: 'google',
            show() {
                shown = true;
                overlay.classList.add('on');
                init().then(ok => { if (ok && map) { global.google.maps.event.trigger(map, 'resize'); setIncidents(o.getIncidents() || []); } });
            },
            hide() { shown = false; overlay.classList.remove('on'); },
            toggle() { shown ? api.hide() : api.show(); },
            refresh() { if (shown && ready) setIncidents(o.getIncidents() || []); },
            resize() { if (map) global.google.maps.event.trigger(map, 'resize'); },
            isShown: () => shown,
            isFailed: () => failed,
            getMap: () => map,
            fitStreet(name) {
                if (!map || !streetGeo) return;
                const b = new global.google.maps.LatLngBounds();
                (streetGeo.features || []).filter(f => String((f.properties || {}).name || '').trim() === name).forEach(f => {
                    (function walk(c) { if (typeof c[0] === 'number') b.extend({ lat: c[1], lng: c[0] }); else c.forEach(walk); })(f.geometry.coordinates);
                });
                if (!b.isEmpty()) map.fitBounds(b, 80);
            },
        };

        if (o.modeSelect) o.modeSelect.addEventListener('change', () => { if (shown) setTimeout(api.refresh, 0); });
        window.addEventListener('resize', () => { if (shown) api.resize(); });
        document.addEventListener('fullscreenchange', () => setTimeout(() => { if (shown) api.resize(); }, 150));
        return api;
    }

    /**
     * Ties a page's map engines to a button group and remembers the choice.
     */
    function switcher(cfg) {
        const engines = cfg.engines || {};
        const buttons = cfg.buttons || {};
        const key = cfg.storageKey || 'crimeMapEngine';
        let current = null;

        function activate(name) {
            Object.keys(engines).forEach(k => { if (k !== name && engines[k]) engines[k].hide(); });
            if (engines[name]) engines[name].show();
            Object.keys(buttons).forEach(k => { if (buttons[k]) buttons[k].classList.toggle('on', k === name); });
            current = name;
            try { localStorage.setItem(key, name); } catch (e) {}
            if (typeof cfg.onChange === 'function') cfg.onChange(name);
        }

        Object.keys(buttons).forEach(k => { if (buttons[k]) buttons[k].addEventListener('click', () => activate(k)); });

        let start = cfg.defaultEngine || 'google';
        try { const saved = localStorage.getItem(key); if (saved && (saved === '2d' || engines[saved])) start = saved; } catch (e) {}
        if (start !== '2d' && !engines[start]) start = '2d';
        activate(start);

        // A Google engine that fails (no key, blocked) drops the page to classic 2D
        if (start === 'google' && engines.google && engines.google.isFailed) {
            setTimeout(() => { if (engines.google.isFailed() && current === 'google') activate('2d'); }, 1500);
        }
        return { activate, current: () => current };
    }

    global.CrimeMapGoogle = { create, switcher, ringsOf, insideRings, outsideMask, attachStreetView, injectCss };
})(window);
