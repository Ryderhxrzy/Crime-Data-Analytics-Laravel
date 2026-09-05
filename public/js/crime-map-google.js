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
 *   });
 *   gmap.refresh();   // after the page reloads its data
 *
 * CrimeMapGoogle.switcher() ties the engines of a page (google, 3d, classic
 * 2D) to a group of buttons and remembers the choice.
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
            .cmg-legend { position: absolute; bottom: 24px; right: 10px; z-index: 5; background: rgba(255,255,255,.96); border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 12px; font-size: 11px; color: #374151; line-height: 1.7; box-shadow: 0 2px 10px rgba(0,0,0,.18); }
            .cmg-legend b { display: block; font-size: 11px; color: #111827; }
            .cmg-legend span { display: inline-block; width: 20px; height: 5px; border-radius: 3px; margin-right: 7px; vertical-align: middle; }
            .cmg-msg { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; text-align: center; padding: 20px; color: #374151; font-size: 13px; background: #f9fafb; z-index: 4; }
            .cmg-tip { background: #111827; color: #fff; border-radius: 8px; padding: 8px 10px; font-size: 11.5px; line-height: 1.45; }
            .cmg-pop { font-size: 12px; line-height: 1.45; min-width: 190px; color: #111827; }
            .cmg-street-label { color: #fff; font-weight: 800; font-size: 13px; text-shadow: 0 0 4px #000, 0 0 8px #000; }
            .map-engine-btn.on { background: #274d4c !important; color: #fff !important; border-color: #274d4c !important; }
        `;
        document.head.appendChild(st);
    }

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
        let streetLayer = null, boundaryLayer = null, heat = null, streetTip = null, infoWin = null, labelMarker = null;
        let markers = [];
        let streetGeo = null, saBounds = null, pending = null, streetCountsCache = {};

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
                    streetViewControl: false,           // no Street View
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
                    m.addListener('click', () => { infoWin.setContent(incidentPopup(p.i)); infoWin.open({ map: map, anchor: m }); });
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
     *   CrimeMapGoogle.switcher({
     *       engines: { google: gmap, '3d': view3d },   // each has show()/hide(); '2d' = none shown
     *       buttons: { google: el, '3d': el, '2d': el },
     *       defaultEngine: 'google', storageKey: 'crimeMapEngine'
     *   });
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

    global.CrimeMapGoogle = { create: create, switcher: switcher };
})(window);
