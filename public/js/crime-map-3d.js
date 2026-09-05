/*
 * 3D view for the crime maps.
 *
 * Every map page keeps its Leaflet (2D) map. This module mounts a MapLibre GL
 * map in an overlay on top of the same container and shows the SAME incidents
 * the page currently holds, so a "3D" button can flip between the two without
 * touching the page's filters or data flow.
 *
 *   const view3d = CrimeMap3D.create({
 *       wrapper: document.getElementById('mapContainer'),   // positioned box holding the 2D map
 *       getIncidents: () => currentData,                     // the page's filtered incidents
 *       toggleButton: document.getElementById('map3dBtn'),   // optional: wired automatically
 *   });
 *   view3d.refresh();   // call after the page reloads its data
 *
 * Tiles: OpenFreeMap vector tiles (free, no API key). Buildings come extruded
 * from the tile style itself. Streets are coloured by the crime count found
 * in the incidents' `street` field, on the same scale as Crime Mapping.
 */
(function (global) {
    'use strict';

    const VECTOR_STYLE = 'https://tiles.openfreemap.org/styles/liberty';
    const STREETS_URL = '/data/san_agustin_streets.geojson';
    const BARANGAYS_URL = '/qc_barangays.geojson';
    const SA_CODE = '137404095';
    const SA_CENTER = [121.0385, 14.7292];   // [lng, lat]

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
            .cm3d-overlay { position: absolute; inset: 0; z-index: 1100; display: none; background: #e5e7eb; }
            .cm3d-overlay.on { display: block; }
            .cm3d-map { position: absolute; inset: 0; }
            .cm3d-badge { position: absolute; bottom: 10px; left: 10px; z-index: 5; background: rgba(255,255,255,.94); border: 1px solid #e5e7eb; border-radius: 9999px; padding: 3px 10px; font-size: 10.5px; font-weight: 700; color: #374151; }
            .cm3d-tools { position: absolute; top: 10px; left: 10px; z-index: 5; display: flex; gap: 6px; }
            .cm3d-tool { background: #fff; border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 10px; font-size: 11.5px; font-weight: 700; color: #374151; cursor: pointer; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
            .cm3d-tool.on { background: #274d4c; color: #fff; border-color: #274d4c; }
            .cm3d-legend { position: absolute; bottom: 10px; right: 10px; z-index: 5; background: rgba(255,255,255,.96); border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 12px; font-size: 11px; color: #374151; line-height: 1.7; box-shadow: 0 2px 10px rgba(0,0,0,.18); }
            .cm3d-legend b { display: block; font-size: 11px; color: #111827; }
            .cm3d-legend span { display: inline-block; width: 20px; height: 5px; border-radius: 3px; margin-right: 7px; vertical-align: middle; }
            .cm3d-tip .maplibregl-popup-content { background: #111827; color: #fff; border-radius: 8px; padding: 8px 10px; font-size: 11.5px; line-height: 1.45; box-shadow: 0 6px 20px rgba(0,0,0,.35); }
            .cm3d-tip .maplibregl-popup-tip { border-top-color: #111827; border-bottom-color: #111827; }
            .cm3d-pop .maplibregl-popup-content { border-radius: 10px; padding: 10px 12px; font-size: 12px; line-height: 1.45; box-shadow: 0 8px 24px rgba(0,0,0,.25); min-width: 200px; }
            .cm3d-label { background: transparent; color: #123332; font-size: 13px; font-weight: 800; text-shadow: 0 0 4px #fff, 0 0 8px #fff, 0 1px 2px #fff; white-space: nowrap; pointer-events: none; }
            .cm3d-msg { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; text-align: center; padding: 20px; color: #374151; font-size: 13px; background: #f9fafb; z-index: 4; }
        `;
        document.head.appendChild(st);
    }

    function create(opts) {
        const o = Object.assign({
            wrapper: null,
            getIncidents: () => [],
            toggleButton: null,
            showStreets: true,
            showLegend: true,
            pitch: 58,
            bearing: -18,
            // 'markers' | 'heatmap' | 'street-heatmap' | 'clusters' (clusters draw as markers in 3D)
            getMode: () => 'markers',
            modeSelect: null,
        }, opts || {});
        if (!o.wrapper) throw new Error('CrimeMap3D: wrapper element required');
        injectCss();

        if (getComputedStyle(o.wrapper).position === 'static') o.wrapper.style.position = 'relative';

        const overlay = document.createElement('div');
        overlay.className = 'cm3d-overlay';
        overlay.innerHTML =
            '<div class="cm3d-map"></div>' +
            '<div class="cm3d-tools"><button type="button" class="cm3d-tool on cm3d-tilt"><i class="fas fa-cube mr-1"></i>Tilt on</button></div>' +
            '<span class="cm3d-badge"><i class="fas fa-spinner fa-spin mr-1"></i>Loading 3D</span>' +
            (o.showLegend && o.showStreets ? '<div class="cm3d-legend"><b><i class="fas fa-road" style="color:#274d4c"></i> Street crime level</b>' +
                SEVERITY.map(s => '<div><span style="background:' + s.color + '"></span>' + s.label + '</div>').join('') + '</div>' : '');
        o.wrapper.appendChild(overlay);

        const mapEl = overlay.querySelector('.cm3d-map');
        const badge = overlay.querySelector('.cm3d-badge');
        const tiltBtn = overlay.querySelector('.cm3d-tilt');

        let map = null, styleReady = false, failed = false, shown = false, tilted = true;
        let streetGeo = null, saBounds = null, pendingIncidents = null;
        let hoverTip = null, clickPop = null;

        function showMessage(text) {
            let m = overlay.querySelector('.cm3d-msg');
            if (!m) { m = document.createElement('div'); m.className = 'cm3d-msg'; overlay.appendChild(m); }
            m.innerHTML = '<div><i class="fas fa-triangle-exclamation text-2xl mb-2 block" style="color:#b45309"></i>' + esc(text) + '</div>';
        }

        function init() {
            if (map || failed) return;
            if (typeof maplibregl === 'undefined') {
                failed = true;
                badge.innerHTML = '<i class="fas fa-map mr-1"></i>3D unavailable';
                showMessage('The 3D map library did not load (cdnjs.cloudflare.com blocked?). The 2D map still works.');
                return;
            }
            try {
                map = new maplibregl.Map({
                    container: mapEl, style: VECTOR_STYLE, center: SA_CENTER, zoom: 15.6,
                    pitch: o.pitch, bearing: o.bearing, minZoom: 11, maxZoom: 19.5,
                    attributionControl: { compact: true }, antialias: true,
                });
            } catch (e) {
                failed = true;
                badge.innerHTML = '<i class="fas fa-map mr-1"></i>3D unavailable';
                showMessage('This browser cannot run the 3D map (WebGL is off). The 2D map still works.');
                return;
            }
            map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');
            map.on('error', e => {
                if (styleReady) return;
                if (e && (e.tile || e.sourceId || e.source)) return;   // a single tile error is not a failure
                failed = true;
                badge.innerHTML = '<i class="fas fa-map mr-1"></i>3D unavailable';
                showMessage('The 3D map style could not be loaded from tiles.openfreemap.org. Check the network or proxy. The 2D map still works.');
            });
            setTimeout(() => { if (!styleReady && !failed) { failed = true; badge.innerHTML = '<i class="fas fa-map mr-1"></i>3D unavailable'; showMessage('The 3D map took too long to load. Check the network or proxy.'); } }, 20000);

            hoverTip = new maplibregl.Popup({ closeButton: false, closeOnClick: false, offset: 12, className: 'cm3d-tip' });
            clickPop = new maplibregl.Popup({ closeButton: true, offset: 14, className: 'cm3d-pop', maxWidth: '300px' });

            map.once('style.load', () => {
                styleReady = true;
                badge.innerHTML = '<i class="fas fa-cube mr-1"></i>3D map';
                addBoundary();
                if (o.showStreets) addStreets();
                addIncidentLayers();
                if (pendingIncidents) { setIncidents(pendingIncidents); pendingIncidents = null; }
                else setIncidents(o.getIncidents() || []);
            });

            tiltBtn.addEventListener('click', () => {
                tilted = !tilted;
                map.easeTo({ pitch: tilted ? o.pitch : 0, bearing: tilted ? o.bearing : 0, duration: 700 });
                tiltBtn.classList.toggle('on', tilted);
                tiltBtn.innerHTML = tilted ? '<i class="fas fa-cube mr-1"></i>Tilt on' : '<i class="fas fa-map mr-1"></i>Tilt off';
            });
        }

        function beforeBuildings() {
            return map.getLayer('building-3d') ? 'building-3d' : undefined;
        }

        function addBoundary() {
            fetch(BARANGAYS_URL + '?t=' + Date.now()).then(r => r.json()).then(geo => {
                if (!map) return;
                const isSA = ['==', ['to-string', ['get', 'code']], SA_CODE];
                map.addSource('cm3d-brgys', { type: 'geojson', data: geo });
                map.addLayer({ id: 'cm3d-brgy-fill', type: 'fill', source: 'cm3d-brgys', paint: { 'fill-color': ['case', isSA, '#bfe5de', '#f2f9f8'], 'fill-opacity': ['case', isSA, 0.18, 0.10] } }, beforeBuildings());
                map.addLayer({ id: 'cm3d-brgy-line', type: 'line', source: 'cm3d-brgys', paint: { 'line-color': ['case', isSA, '#274d4c', '#8fb3b0'], 'line-width': ['case', isSA, 3, 0.8], 'line-opacity': ['case', isSA, 1, 0.7] } });
                const sa = (geo.features || []).find(f => String((f.properties || {}).code || '') === SA_CODE);
                if (!sa) return;
                const b = { minLat: Infinity, maxLat: -Infinity, minLng: Infinity, maxLng: -Infinity };
                (function walk(c) { if (typeof c[0] === 'number') { b.minLng = Math.min(b.minLng, c[0]); b.maxLng = Math.max(b.maxLng, c[0]); b.minLat = Math.min(b.minLat, c[1]); b.maxLat = Math.max(b.maxLat, c[1]); } else c.forEach(walk); })(sa.geometry.coordinates);
                saBounds = b;
                const label = document.createElement('div'); label.className = 'cm3d-label'; label.textContent = 'San Agustin';
                new maplibregl.Marker({ element: label, anchor: 'center' }).setLngLat([(b.minLng + b.maxLng) / 2, (b.minLat + b.maxLat) / 2]).addTo(map);
                const padLat = (b.maxLat - b.minLat) * 0.8, padLng = (b.maxLng - b.minLng) * 0.8;
                map.setMaxBounds([[b.minLng - padLng, b.minLat - padLat], [b.maxLng + padLng, b.maxLat + padLat]]);
                map.fitBounds([[b.minLng, b.minLat], [b.maxLng, b.maxLat]], { padding: 24, pitch: tilted ? o.pitch : 0, bearing: tilted ? o.bearing : 0, duration: 0 });
            }).catch(err => console.warn('3D: barangay boundaries not loaded', err));
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

        function colouredStreets(incidents) {
            const counts = streetCounts(incidents);
            return {
                type: 'FeatureCollection',
                features: ((streetGeo && streetGeo.features) || []).map(f => {
                    const name = String((f.properties || {}).name || '').trim();
                    const st = counts[name.toLowerCase()];
                    const count = st ? st.count : 0;
                    const sev = severityFor(count);
                    const top = st ? Object.entries(st.cats).sort((a, b) => b[1] - a[1])[0] : null;
                    return { type: 'Feature', geometry: f.geometry, properties: { name, count, color: sev.color, level: sev.label, top: top ? top[0] : '' } };
                })
            };
        }

        function addStreets() {
            fetch(STREETS_URL + '?t=' + Date.now(), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(geo => {
                if (!map) return;
                streetGeo = geo;
                map.addSource('cm3d-streets', { type: 'geojson', data: colouredStreets(o.getIncidents() || []) });
                map.addLayer({ id: 'cm3d-streets-casing', type: 'line', source: 'cm3d-streets', layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': '#1e293b', 'line-width': ['case', ['>', ['get', 'count'], 0], 6, 4], 'line-opacity': 0.45 } });
                map.addLayer({ id: 'cm3d-streets-line', type: 'line', source: 'cm3d-streets', layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': ['get', 'color'], 'line-width': ['case', ['>', ['get', 'count'], 0], 3, 2], 'line-opacity': 0.95 } });
                map.addLayer({ id: 'cm3d-streets-hover', type: 'line', source: 'cm3d-streets', filter: ['==', ['get', 'name'], '__none__'], layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': ['get', 'color'], 'line-width': 6, 'line-opacity': 1 } });
                map.addLayer({ id: 'cm3d-streets-labels', type: 'symbol', source: 'cm3d-streets', minzoom: 15.5, layout: { 'symbol-placement': 'line', 'text-field': ['get', 'name'], 'text-size': 11, 'text-font': ['Noto Sans Regular'] }, paint: { 'text-color': '#111827', 'text-halo-color': '#ffffff', 'text-halo-width': 1.6 } });
                // Incidents must stay above the streets
                ['cm3d-heat', 'cm3d-incidents-halo', 'cm3d-incidents'].forEach(id => { if (map.getLayer(id)) map.moveLayer(id); });
                applyMode();

                map.on('mousemove', 'cm3d-streets-line', e => {
                    const f = e.features && e.features[0];
                    if (!f) return;
                    if (map.queryRenderedFeatures(e.point, { layers: ['cm3d-incidents'] }).length) return;   // incident wins
                    map.getCanvas().style.cursor = 'default';
                    map.setFilter('cm3d-streets-hover', ['==', ['get', 'name'], f.properties.name]);
                    const p = f.properties, c = +p.count || 0;
                    hoverTip.setLngLat(e.lngLat).setHTML(
                        '<div style="font-weight:700;margin-bottom:2px">' + esc(p.name) + '</div>' +
                        '<div style="margin-bottom:3px"><span style="display:inline-block;width:28px;height:5px;border-radius:3px;background:' + esc(p.color) + ';vertical-align:middle"></span> <span style="font-weight:700;color:' + esc(p.color) + '">' + esc(p.level) + '</span></div>' +
                        (c > 0 ? '<div>' + c + ' crime' + (c === 1 ? '' : 's') + (p.top ? ' · mostly ' + esc(p.top) : '') + '</div>' : '<div>No recorded crimes — cleared</div>')
                    ).addTo(map);
                });
                map.on('mouseleave', 'cm3d-streets-line', () => {
                    map.setFilter('cm3d-streets-hover', ['==', ['get', 'name'], '__none__']);
                    hoverTip.remove();
                });
            }).catch(err => console.warn('3D: streets not loaded', err));
        }

        function incidentsGeo(incidents) {
            return {
                type: 'FeatureCollection',
                features: incidents.map(i => {
                    const lat = parseFloat(i.latitude ?? i.lat), lng = parseFloat(i.longitude ?? i.lng);
                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
                    return { type: 'Feature', geometry: { type: 'Point', coordinates: [lng, lat] }, properties: {
                        id: i.id ?? '', title: i.incident_title || i.title || '', category: i.category_name || i.category || 'Unknown',
                        color: i.color_code || i.color || '#274d4c', date: i.incident_date || '', time: i.incident_time || '',
                        street: i.street || '', status: i.status || '', clearance: i.clearance_status || '', code: i.incident_code || '',
                    } };
                }).filter(Boolean)
            };
        }

        function addIncidentLayers() {
            map.addSource('cm3d-incidents', { type: 'geojson', data: incidentsGeo([]) });
            // Heat map mode: density from the same points, same colour ramp as the 2D heat map
            map.addLayer({ id: 'cm3d-heat', type: 'heatmap', source: 'cm3d-incidents', layout: { visibility: 'none' }, paint: {
                'heatmap-weight': 0.8,
                'heatmap-intensity': ['interpolate', ['linear'], ['zoom'], 13, 0.8, 17, 2.2],
                'heatmap-radius': ['interpolate', ['linear'], ['zoom'], 13, 14, 16, 30, 18, 46],
                'heatmap-opacity': 0.82,
                'heatmap-color': ['interpolate', ['linear'], ['heatmap-density'], 0, 'rgba(59,130,246,0)', 0.15, '#3b82f6', 0.35, '#2ecc71', 0.55, '#f39c12', 0.75, '#e74c3c', 1, '#c0392b'],
            } });
            map.addLayer({ id: 'cm3d-incidents-halo', type: 'circle', source: 'cm3d-incidents', paint: { 'circle-radius': ['interpolate', ['linear'], ['zoom'], 13, 5, 16, 9, 18, 13], 'circle-color': ['get', 'color'], 'circle-opacity': 0.25, 'circle-blur': 0.6 } });
            map.addLayer({ id: 'cm3d-incidents', type: 'circle', source: 'cm3d-incidents', paint: { 'circle-radius': ['interpolate', ['linear'], ['zoom'], 13, 3, 16, 5.5, 18, 8], 'circle-color': ['get', 'color'], 'circle-stroke-color': '#ffffff', 'circle-stroke-width': 1.5, 'circle-opacity': 0.95 } });
            map.on('mouseenter', 'cm3d-incidents', () => { map.getCanvas().style.cursor = 'pointer'; });
            map.on('mouseleave', 'cm3d-incidents', () => { map.getCanvas().style.cursor = ''; });
            map.on('click', 'cm3d-incidents', e => {
                const f = e.features && e.features[0];
                if (!f) return;
                const p = f.properties;
                clickPop.setLngLat(f.geometry.coordinates).setHTML(
                    '<div style="font-weight:800;color:#111827;margin-bottom:2px">' + esc(p.title || p.category) + '</div>' +
                    '<div style="display:flex;align-items:center;gap:6px;margin-bottom:6px"><span style="width:10px;height:10px;border-radius:9999px;background:' + esc(p.color) + ';display:inline-block"></span><span style="font-weight:700;color:#374151">' + esc(p.category) + '</span></div>' +
                    (p.code ? '<div style="font-family:monospace;font-size:11px;color:#6b7280">' + esc(p.code) + '</div>' : '') +
                    (p.street ? '<div style="color:#374151"><i class="fas fa-road" style="color:#274d4c;width:14px"></i> ' + esc(p.street) + '</div>' : '') +
                    (p.date ? '<div style="color:#374151"><i class="far fa-calendar" style="color:#274d4c;width:14px"></i> ' + esc(p.date) + (p.time ? ' ' + esc(p.time) : '') + '</div>' : '') +
                    (p.status ? '<div style="color:#374151"><i class="fas fa-flag" style="color:#274d4c;width:14px"></i> ' + esc(String(p.status).replace(/_/g, ' ')) + (p.clearance ? ' · ' + esc(p.clearance) : '') + '</div>' : '')
                ).addTo(map);
            });
        }

        function applyMode() {
            if (!map || !styleReady) return 'markers';
            const mode = String(o.getMode() || 'markers');
            const vis = (id, on) => { if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', on ? 'visible' : 'none'); };
            const points = mode === 'markers' || mode === 'clusters';
            vis('cm3d-incidents', points); vis('cm3d-incidents-halo', points);
            vis('cm3d-heat', mode === 'heatmap');
            if (map.getLayer('cm3d-streets-line')) {
                map.setPaintProperty('cm3d-streets-line', 'line-width', mode === 'street-heatmap'
                    ? ['case', ['>', ['get', 'count'], 0], ['interpolate', ['linear'], ['get', 'count'], 1, 4, 15, 10], 2]
                    : ['case', ['>', ['get', 'count'], 0], 3, 2]);
                map.setPaintProperty('cm3d-streets-casing', 'line-width', mode === 'street-heatmap'
                    ? ['case', ['>', ['get', 'count'], 0], ['interpolate', ['linear'], ['get', 'count'], 1, 8, 15, 16], 4]
                    : ['case', ['>', ['get', 'count'], 0], 6, 4]);
            }
            return mode;
        }

        function setIncidents(incidents) {
            incidents = Array.isArray(incidents) ? incidents : [];
            if (!map || !styleReady) { pendingIncidents = incidents; return; }
            const src = map.getSource('cm3d-incidents');
            if (src) src.setData(incidentsGeo(incidents));
            const streets = map.getSource('cm3d-streets');
            if (streets && streetGeo) streets.setData(colouredStreets(incidents));
            const mode = applyMode();
            const modeLabel = { markers: 'markers', heatmap: 'heat map', 'street-heatmap': 'street segments', clusters: 'markers' }[mode] || mode;
            badge.innerHTML = '<i class="fas fa-cube mr-1"></i>3D · ' + modeLabel + ' · ' + incidents.length + ' incident' + (incidents.length === 1 ? '' : 's');
        }

        const api = {
            show() {
                shown = true;
                overlay.classList.add('on');
                init();
                if (map) { map.resize(); setIncidents(o.getIncidents() || []); }
                if (o.toggleButton) { o.toggleButton.classList.add('on'); o.toggleButton.innerHTML = '<i class="fas fa-map"></i><span class="hidden sm:inline ml-1">2D</span>'; o.toggleButton.title = 'Back to the 2D map'; }
            },
            hide() {
                shown = false;
                overlay.classList.remove('on');
                if (o.toggleButton) { o.toggleButton.classList.remove('on'); o.toggleButton.innerHTML = '<i class="fas fa-cube"></i><span class="hidden sm:inline ml-1">3D</span>'; o.toggleButton.title = 'Switch to the 3D map'; }
            },
            toggle() { shown ? api.hide() : api.show(); },
            refresh() { if (shown && map) setIncidents(o.getIncidents() || []); },
            resize() { if (map) map.resize(); },
            isShown: () => shown,
        };

        if (o.toggleButton) {
            o.toggleButton.addEventListener('click', api.toggle);
            api.hide();
        }
        if (o.modeSelect) o.modeSelect.addEventListener('change', () => { if (shown) setTimeout(api.refresh, 0); });
        window.addEventListener('resize', () => { if (shown && map) map.resize(); });
        document.addEventListener('fullscreenchange', () => setTimeout(() => { if (shown && map) map.resize(); }, 150));

        return api;
    }

    global.CrimeMap3D = { create: create };
})(window);
