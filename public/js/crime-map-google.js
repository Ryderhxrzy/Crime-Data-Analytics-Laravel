/*
 * Google Maps view for the crime maps (default engine).
 *
 * Mounts a Google Map (Hybrid: satellite imagery + roads + street names +
 * place labels) in an overlay on top of a page's existing map container and
 * shows the SAME incidents the page currently holds, read through a callback.
 * Crime records stay in our Laravel backend: only the coordinates needed to
 * draw the markers are handed to the map, exactly as with the Leaflet map.
 *
 * Every view mode the Leaflet map has works here the same way:
 *   markers         one dot per incident
 *   heatmap         density blobs — drawn on our own canvas, because Google's
 *                   visualization.HeatmapLayer is deprecated and no longer loads
 *   street-heatmap  each road segment coloured by the crimes snapped to it
 *                   (nearest segment within 45 m, same rule as the Leaflet one)
 *   clusters        one bubble per street; the crimes behind it appear on zoom-in
 *
 *   const gmap = CrimeMapGoogle.create({
 *       wrapper: document.getElementById('mapContainer'),
 *       getIncidents: () => currentData,
 *       getMode: () => document.getElementById('visualizationMode').value,
 *       getHeatOptions: () => ({ radius: 40, blur: 20, intensity: 1 }),   // page sliders
 *       getWeight: incident => 0..1,                                        // heat weight
 *       onClusterSelect: (streetName, incidents) => {},                    // side panel hook
 *       streetView: true,   // native Pegman with the Google-style inset map
 *   });
 *   gmap.refresh();   // after the page reloads its data or a slider moves
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

    // Same ramp as the "Crime Intensity Scale" legend and the Leaflet layers
    const HEAT_STOPS = [
        [0.00, [59, 130, 246]],   // #3b82f6 blue
        [0.25, [46, 204, 113]],   // #2ecc71 green
        [0.50, [243, 156, 18]],   // #f39c12 orange
        [0.75, [231, 76, 60]],    // #e74c3c red
        [1.00, [192, 57, 43]],    // #c0392b dark red
    ];
    const EMPTY_STREET = '#cbd5e1';
    const SNAP_METERS = 45;

    // Street crime level, same scale as the classic map's street layer.
    // Every mode except street-heatmap colours the roads with this.
    const LEVELS = [
        { min: 15, label: 'Critical', range: '15+ crimes',  color: '#7f1d1d' },
        { min: 10, label: 'High',     range: '10-14 crimes', color: '#dc2626' },
        { min: 5,  label: 'Moderate', range: '5-9 crimes',   color: '#f97316' },
        { min: 1,  label: 'Low',      range: '1-4 crimes',   color: '#ca8a04' },
        { min: 0,  label: 'Cleared',  range: 'no crime',     color: '#16a34a' },
    ];
    const levelFor = n => LEVELS.find(l => n >= l.min) || LEVELS[LEVELS.length - 1];

    // Cluster bubbles, same thresholds as the Leaflet cluster view
    const clusterColor = n => n >= 31 ? '#dc2626' : (n >= 11 ? '#eab308' : '#16a34a');
    // Individual crime dots inside a cluster, same rule as the Leaflet view
    const severityColor = i => { const c = +(i.crime_category_id || 0); return c >= 5 ? '#dc2626' : (c >= 3 ? '#f97316' : '#16a34a'); };

    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const latOf = i => parseFloat(i.latitude ?? i.lat), lngOf = i => parseFloat(i.longitude ?? i.lng);

    function colorFor(ratio) {
        const r = Math.max(0, Math.min(1, ratio));
        for (let i = 1; i < HEAT_STOPS.length; i++) {
            if (r <= HEAT_STOPS[i][0]) {
                const [r0, c0] = HEAT_STOPS[i - 1], [r1, c1] = HEAT_STOPS[i];
                const t = r1 === r0 ? 0 : (r - r0) / (r1 - r0);
                return 'rgb(' + c0.map((v, k) => Math.round(v + (c1[k] - v) * t)).join(',') + ')';
            }
        }
        return 'rgb(' + HEAT_STOPS[HEAT_STOPS.length - 1][1].join(',') + ')';
    }
    const cssGradient = () => 'linear-gradient(90deg,' + HEAT_STOPS.map(s => 'rgb(' + s[1].join(',') + ') ' + Math.round(s[0] * 100) + '%').join(',') + ')';

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
            .cmg-legend { position: absolute; top: 10px; right: 10px; z-index: 5; background: rgba(255,255,255,.96); border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 12px; font-size: 11px; color: #374151; line-height: 1.7; box-shadow: 0 2px 10px rgba(0,0,0,.18); min-width: 150px; }
            .cmg-legend:empty { display: none; }
            .cmg-legend b { display: block; font-size: 11px; color: #111827; }
            .cmg-legend .sw { display: inline-block; width: 20px; height: 6px; border-radius: 3px; margin-right: 7px; vertical-align: middle; }
            .cmg-legend .dot { display: inline-block; width: 12px; height: 12px; border-radius: 9999px; margin-right: 7px; vertical-align: middle; border: 2px solid #fff; box-shadow: 0 0 0 1px #d1d5db; }
            .cmg-legend .bar { height: 8px; border-radius: 4px; margin: 4px 0 2px; }
            .cmg-legend .ends { display: flex; justify-content: space-between; font-size: 10px; color: #6b7280; line-height: 1.2; }
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
            .cmg-pop .row { display: flex; justify-content: space-between; gap: 12px; font-size: 11.5px; padding: 2px 0; color: #4b5563; }
            .cmg-pop .row b { color: #111827; }
            .cmg-pop .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin: 8px 0; }
            .cmg-pop .stat { background: #f3f4f6; border-radius: 6px; padding: 6px; text-align: center; }
            .cmg-pop .stat small { display: block; font-size: 10px; color: #6b7280; font-weight: 600; }
            .cmg-pop .stat strong { font-size: 15px; }
            .cmg-pop .btn { width: 100%; margin-top: 6px; padding: 8px; background: #274d4c; color: #fff; border: 0; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; }
            .cmg-pop .btn:hover { background: #1f3d3c; }
            .cmg-street-label { color: #fff; font-weight: 800; font-size: 13px; text-shadow: 0 0 4px #000, 0 0 8px #000; }
            .cmg-bubble { position: absolute; transform: translate(-50%, -50%); min-width: 44px; padding: 4px 9px; border-radius: 9999px; color: #fff; font-weight: 700; border: 2px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,.4); cursor: pointer; white-space: nowrap; text-align: center; line-height: 1.15; transition: opacity .2s; user-select: none; }
            .cmg-bubble .n { display: block; font-size: 14px; }
            .cmg-bubble .s { display: block; font-size: 8.5px; opacity: .95; max-width: 120px; overflow: hidden; text-overflow: ellipsis; }
            .cmg-bubble.faded { opacity: .15; pointer-events: none; }
            .cmg-bubble.picked { box-shadow: 0 0 0 3px #facc15, 0 4px 12px rgba(0,0,0,.4); }
            .map-engine-btn.on { background: #274d4c !important; color: #fff !important; border-color: #274d4c !important; }
            /* Same arrow the classic map drops over a crime when you hover it in the list */
            .cmg-pointer { position: absolute; width: 40px; height: 50px; transform: translate(-50%, calc(-100% - 15px)); pointer-events: none; z-index: 200; }
            .cmg-pointer > div { width: 40px; height: 50px; background: linear-gradient(135deg, #274d4c 0%, #1a3d3a 100%); clip-path: polygon(50% 0%, 100% 70%, 85% 100%, 50% 85%, 15% 100%, 0% 70%); box-shadow: 0 4px 12px rgba(0,0,0,.3); display: flex; align-items: center; justify-content: center; border: 2px solid #fff; opacity: .9; transform: rotate(180deg); animation: cmgArrowBounce 1.2s ease-in-out infinite; }
            .cmg-pointer i { color: #fff; font-size: 16px; transform: rotate(180deg); }
            @keyframes cmgArrowBounce { 0%, 100% { transform: rotate(180deg) translateY(0) scale(1); } 50% { transform: rotate(180deg) translateY(15px) scale(1.15); } }
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

    // Distance in metres from a point to a polyline (equirectangular, fine at barangay scale)
    function distanceToLine(lat, lng, pts) {
        const kx = Math.cos(lat * Math.PI / 180) * 111320, ky = 110540;
        const px = lng * kx, py = lat * ky;
        let best = Infinity;
        for (let i = 0; i < pts.length - 1; i++) {
            const ax = pts[i][1] * kx, ay = pts[i][0] * ky, bx = pts[i + 1][1] * kx, by = pts[i + 1][0] * ky;
            const dx = bx - ax, dy = by - ay, len2 = dx * dx + dy * dy;
            const t = len2 > 0 ? Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / len2)) : 0;
            const d = Math.hypot(px - (ax + t * dx), py - (ay + t * dy));
            if (d < best) best = d;
        }
        return best;
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
    // Canvas heat layer
    //
    // Google's visualization.HeatmapLayer is deprecated and no longer loads,
    // so the density map is drawn here with the same technique Leaflet.heat
    // uses (simpleheat): every point is a soft grey disc, the stacked alpha
    // is then recoloured through the intensity ramp. Radius, blur and
    // intensity come from the page's sliders through getOptions().
    // ------------------------------------------------------------------
    function makeHeatLayer(google, map, getOptions) {
        const ov = new google.maps.OverlayView();
        let canvas = null, points = [];   // [{ lat, lng, w }]
        let disc = null, discKey = '', ramp = null;

        function gradientRamp() {
            if (ramp) return ramp;
            const c = document.createElement('canvas'); c.width = 1; c.height = 256;
            const ctx = c.getContext('2d');
            const g = ctx.createLinearGradient(0, 0, 0, 256);
            HEAT_STOPS.forEach(s => g.addColorStop(s[0], 'rgb(' + s[1].join(',') + ')'));
            ctx.fillStyle = g; ctx.fillRect(0, 0, 1, 256);
            ramp = ctx.getImageData(0, 0, 1, 256).data;
            return ramp;
        }

        function discFor(r, blur) {
            const key = r + ':' + blur;
            if (disc && discKey === key) return disc;
            const r2 = r + blur;
            const c = document.createElement('canvas'); c.width = c.height = r2 * 2;
            const ctx = c.getContext('2d');
            ctx.shadowOffsetX = ctx.shadowOffsetY = r2 * 2;
            ctx.shadowBlur = blur; ctx.shadowColor = 'black';
            ctx.beginPath(); ctx.arc(-r2, -r2, r, 0, Math.PI * 2, true); ctx.closePath(); ctx.fill();
            disc = c; discKey = key;
            return c;
        }

        ov.onAdd = function () {
            canvas = document.createElement('canvas');
            canvas.style.position = 'absolute';
            canvas.style.pointerEvents = 'none';
            this.getPanes().overlayLayer.appendChild(canvas);
        };
        ov.onRemove = function () { if (canvas && canvas.parentNode) canvas.parentNode.removeChild(canvas); canvas = null; };
        ov.draw = function () {
            const proj = this.getProjection(), bounds = map.getBounds();
            if (!canvas || !proj || !bounds) return;
            const div = map.getDiv(), w = div.offsetWidth, h = div.offsetHeight;
            const sw = proj.fromLatLngToDivPixel(bounds.getSouthWest()), ne = proj.fromLatLngToDivPixel(bounds.getNorthEast());
            const left = Math.min(sw.x, ne.x), top = Math.min(sw.y, ne.y);
            canvas.style.left = left + 'px'; canvas.style.top = top + 'px';
            canvas.width = w; canvas.height = h;

            const o = Object.assign({ radius: 28, blur: 15, intensity: 1, minOpacity: 0.3, opacity: 0.8 }, getOptions() || {});
            const r = Math.max(4, +o.radius || 28), blur = Math.max(1, +o.blur || 15), r2 = r + blur;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, w, h);
            if (!points.length) return;

            const d = discFor(r, blur);
            points.forEach(p => {
                const px = proj.fromLatLngToDivPixel(new google.maps.LatLng(p.lat, p.lng));
                const x = px.x - left, y = px.y - top;
                if (x < -r2 || y < -r2 || x > w + r2 || y > h + r2) return;
                // Same floor Leaflet.heat applies: a lone incident still shows
                ctx.globalAlpha = Math.max(o.minOpacity, Math.min(1, p.w * (+o.intensity || 1)));
                ctx.drawImage(d, x - r2, y - r2);
            });

            // Recolour: the accumulated alpha picks the ramp colour
            const img = ctx.getImageData(0, 0, w, h), px = img.data, rp = gradientRamp();
            for (let i = 0; i < px.length; i += 4) {
                const a = px[i + 3];
                if (!a) continue;
                const j = a * 4;
                px[i] = rp[j]; px[i + 1] = rp[j + 1]; px[i + 2] = rp[j + 2];
            }
            ctx.putImageData(img, 0, 0);
            canvas.style.opacity = String(o.opacity);
        };

        return {
            setPoints(list) { points = list; if (ov.getMap()) ov.draw(); },
            show() { if (!ov.getMap()) ov.setMap(map); else ov.draw(); },
            hide() { if (ov.getMap()) ov.setMap(null); },
            redraw() { if (ov.getMap()) ov.draw(); },
        };
    }

    // ------------------------------------------------------------------
    // HTML bubble marker (cluster labels), positioned by the map projection
    // ------------------------------------------------------------------
    function makeBubble(google, map, position, html, onClick) {
        const ov = new google.maps.OverlayView();
        const el = document.createElement('div');
        el.className = 'cmg-bubble';
        el.innerHTML = html;
        el.addEventListener('click', e => { e.stopPropagation(); if (onClick) onClick(); });
        ov.onAdd = function () { this.getPanes().overlayMouseTarget.appendChild(el); };
        ov.onRemove = function () { if (el.parentNode) el.parentNode.removeChild(el); };
        ov.draw = function () {
            const proj = this.getProjection();
            if (!proj) return;
            const p = proj.fromLatLngToDivPixel(new google.maps.LatLng(position.lat, position.lng));
            el.style.left = p.x + 'px'; el.style.top = p.y + 'px';
        };
        ov.setMap(map);
        return { el, remove: () => ov.setMap(null) };
    }

    // ------------------------------------------------------------------
    // Overlay view for the crime pages
    // ------------------------------------------------------------------
    function create(opts) {
        const o = Object.assign({
            wrapper: null,
            getIncidents: () => [],
            getMode: () => 'markers',
            getHeatOptions: () => ({}),
            getWeight: null,               // incident -> 0..1 heat weight (page's own scale)
            onClusterSelect: null,         // (streetName, incidents) when a cluster is picked
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
            (o.showLegend ? '<div class="cmg-legend"></div>' : '');
        o.wrapper.appendChild(overlay);
        const mapEl = overlay.querySelector('.cmg-map');
        const badge = overlay.querySelector('.cmg-badge');
        const legend = overlay.querySelector('.cmg-legend');

        let map = null, ready = false, failed = false, shown = false, initPromise = null;
        let streetLayer = null, glowLayer = null, boundaryLayer = null, heat = null, streetTip = null, infoWin = null, labelMarker = null, mask = null, sv = null;
        let markers = [], bubbles = [], focusRings = [];
        let streetGeo = null, segments = [], saBounds = null, pending = null;
        let segCounts = new Map(), segMax = 0;     // feature index -> { count, cats, incidents, name }
        let saRings = [], focusedStreet = null, currentMode = 'markers', zoomListener = null, pointer = null;
        let walking = false;   // Street View open: Pegman's street is heavier, nothing else fades

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

            initPromise = GoogleMapsLoader.load(['maps']).then(google => {
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
                infoWin = new google.maps.InfoWindow({ maxWidth: 320 });
                streetTip = new google.maps.InfoWindow({ disableAutoPan: true, headerDisabled: true, pixelOffset: new google.maps.Size(0, -8) });
                heat = makeHeatLayer(google, map, o.getHeatOptions);

                addBoundary(google);
                if (o.showStreets) addStreets(google);
                if (o.streetView) {
                    sv = attachStreetView(google, map, overlay, mapEl, { inside: (lat, lng) => insideRings(saRings, lat, lng) });
                    followPegman(google);
                }
                zoomListener = map.addListener('zoom_changed', applyClusterZoom);
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

        // ---------------- Streets ----------------

        // One entry per geojson feature: its name, polylines and bbox, so an
        // incident can be snapped to the nearest road within SNAP_METERS.
        function parseSegments(geo) {
            return (geo.features || []).map((f, idx) => {
                const name = String((f.properties || {}).name || '').trim() || 'Unnamed road';
                const geom = f.geometry || {};
                const raw = geom.type === 'MultiLineString' ? geom.coordinates : [geom.coordinates || []];
                const bbox = { minLat: Infinity, maxLat: -Infinity, minLng: Infinity, maxLng: -Infinity };
                const lines = raw.map(line => (line || [])
                    .filter(c => Array.isArray(c) && Number.isFinite(+c[0]) && Number.isFinite(+c[1]))
                    .map(c => { const p = [+c[1], +c[0]]; bbox.minLat = Math.min(bbox.minLat, p[0]); bbox.maxLat = Math.max(bbox.maxLat, p[0]); bbox.minLng = Math.min(bbox.minLng, p[1]); bbox.maxLng = Math.max(bbox.maxLng, p[1]); return p; })
                ).filter(l => l.length >= 2);
                return { idx, name, lines, bbox };
            });
        }

        // Nearest segment for every incident: counts per feature index
        function snapIncidents(incidents) {
            const counts = new Map();
            let max = 0;
            const pad = SNAP_METERS / 100000;
            incidents.forEach(inc => {
                const lat = latOf(inc), lng = lngOf(inc);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                let best = null, bestD = Infinity;
                for (const seg of segments) {
                    const b = seg.bbox;
                    if (lat < b.minLat - pad || lat > b.maxLat + pad || lng < b.minLng - pad || lng > b.maxLng + pad) continue;
                    for (const line of seg.lines) {
                        const d = distanceToLine(lat, lng, line);
                        if (d < bestD) { bestD = d; best = seg; }
                    }
                }
                if (!best || bestD > SNAP_METERS) return;
                let e = counts.get(best.idx);
                if (!e) { e = { count: 0, cats: {}, incidents: [], name: best.name }; counts.set(best.idx, e); }
                e.count++;
                e.incidents.push(inc);
                const c = String(inc.category_name || inc.category || '').trim();
                if (c) e.cats[c] = (e.cats[c] || 0) + 1;
                if (e.count > max) max = e.count;
            });
            segCounts = counts;
            segMax = max;

            // A street is several geojson features (one per OSM way). Everything
            // the user sees as "the street" - colour, hover, counts - works on
            // the whole street; only the street-heatmap colouring stays per piece.
            streetTotals = new Map();
            segments.forEach(seg => {
                let t = streetTotals.get(seg.name);
                if (!t) { t = { count: 0, cats: {}, incidents: [] }; streetTotals.set(seg.name, t); }
                const e = counts.get(seg.idx);
                if (!e) return;
                t.count += e.count;
                t.incidents.push(...e.incidents);
                Object.entries(e.cats).forEach(([c, n]) => { t.cats[c] = (t.cats[c] || 0) + n; });
            });
        }

        let streetTotals = new Map();   // street name -> { count, cats, incidents }
        const entryOf = f => segCounts.get(f.getProperty('_idx')) || null;
        const nameOf = f => String(f.getProperty('name') || '').trim() || 'Unnamed road';
        const streetOf = f => streetTotals.get(nameOf(f)) || null;
        const isFocused = f => focusedStreet !== null && nameOf(f) === focusedStreet;
        const sameStreet = name => { const out = []; if (streetLayer) streetLayer.forEach(ft => { if (nameOf(ft) === name) out.push(ft); }); return out; };

        // The roads are always on the map, coloured by crime level like the
        // classic map. Street-heatmap mode swaps that for the intensity
        // gradient with a glow; a focused street (cluster view) lights up.
        function styleStreets() {
            if (!streetLayer) return;
            const mode = currentMode;
            // A focused street keeps its own colour: it is drawn heavier and
            // every other street fades, the same way the classic map does it.
            // In Street View the other roads never fade, so the inset still
            // shows every street around Pegman; the one under him is only heavier.
            const focusing = focusedStreet !== null && !walking;
            streetLayer.setStyle(f => {
                const e = entryOf(f), count = e ? e.count : 0;
                const ratio = segMax > 0 ? count / segMax : 0;
                const hot = isFocused(f), dim = focusing && !hot;
                if (mode === 'street-heatmap') {
                    const w = count === 0 ? 2 : 3 + 8 * Math.sqrt(ratio);
                    return {
                        strokeColor: count === 0 ? EMPTY_STREET : colorFor(ratio),
                        strokeOpacity: dim ? 0.25 : (count === 0 ? 0.55 : 0.95),
                        strokeWeight: hot ? w + 4 : w,
                        zIndex: hot ? 60 : 10 + count, clickable: true,
                    };
                }
                const total = (streetOf(f) || { count: 0 }).count;
                const lv = levelFor(total);
                return {
                    strokeColor: lv.color,
                    strokeOpacity: dim ? 0.25 : (total > 0 ? 0.95 : 0.7),
                    strokeWeight: hot ? 8 : (total > 0 ? 4 : 2.5),
                    zIndex: hot ? 60 : 10 + total, clickable: true,
                };
            });
            glowLayer.setStyle(f => {
                const e = entryOf(f), count = e ? e.count : 0;
                if (mode !== 'street-heatmap' || count === 0) return { visible: false };
                const ratio = segMax > 0 ? count / segMax : 0;
                return { clickable: false, strokeColor: colorFor(ratio), strokeOpacity: 0.22 + 0.18 * ratio, strokeWeight: 11 + 8 * Math.sqrt(ratio), zIndex: 9 };
            });
        }

        function streetTipHtml(f) {
            const name = nameOf(f);
            const st = streetOf(f), total = st ? st.count : 0;
            const seg = entryOf(f), segCount = seg ? seg.count : 0;
            const gradient = currentMode === 'street-heatmap';
            const top = st ? Object.entries(st.cats).sort((a, b) => b[1] - a[1]).slice(0, 3) : [];
            const lv = levelFor(total);
            const swatch = gradient ? (segCount ? colorFor(segMax > 0 ? segCount / segMax : 0) : EMPTY_STREET) : lv.color;
            return '<div class="cmg-tip"><div style="font-weight:800;margin-bottom:2px">' + esc(name) + '</div>' +
                '<div style="margin-bottom:3px"><span style="display:inline-block;width:28px;height:6px;border-radius:3px;background:' + swatch + ';vertical-align:middle"></span> ' +
                (gradient ? '' : '<span style="font-weight:700;color:' + lv.color + '">' + lv.label + '</span> · ') +
                (total ? '<b>' + total + '</b> crime' + (total === 1 ? '' : 's') + ' on this street' : 'No recorded crimes on this street') + '</div>' +
                (gradient && total ? '<div style="opacity:.8">This segment: ' + segCount + '</div>' : '') +
                (top.length ? '<div style="opacity:.8">' + top.map(([c, n]) => esc(c) + ' ' + n).join(' · ') + '</div>' : '') +
                (total ? '<div style="opacity:.6;margin-top:2px">Click for the breakdown</div>' : '') + '</div>';
        }

        function streetPopupHtml(f) {
            const name = nameOf(f);
            const st = streetOf(f), total = st ? st.count : 0;
            const rows = st ? Object.entries(st.cats).sort((a, b) => b[1] - a[1]).slice(0, 6).map(([c, n]) => '<div class="row"><span>' + esc(c) + '</span><b>' + n + '</b></div>').join('') : '';
            return '<div class="cmg-pop"><div style="font-weight:800;font-size:13px">' + esc(name) + '</div>' +
                '<div style="font-size:11.5px;color:#6b7280;margin:2px 0 6px">' + total + ' crime' + (total === 1 ? '' : 's') + ' on this street</div>' +
                (rows || '<div style="font-size:11px;color:#9ca3af">No crimes recorded here</div>') + '</div>';
        }

        function addStreets(google) {
            glowLayer = new google.maps.Data({ map: map });
            streetLayer = new google.maps.Data({ map: map });
            fetch(STREETS_URL + '?t=' + Date.now(), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(geo => {
                streetGeo = geo;
                segments = parseSegments(geo);
                // addGeoJson returns features in file order, so the index ties
                // each drawn feature to its parsed segment
                streetLayer.addGeoJson(geo).forEach((f, i) => f.setProperty('_idx', i));
                glowLayer.addGeoJson(geo).forEach((f, i) => f.setProperty('_idx', i));
                snapIncidents(pending || o.getIncidents() || []);
                styleStreets();
                renderLegend();

                // Hover highlights the WHOLE street, not just the piece under
                // the cursor: a street is many features that share a name.
                streetLayer.addListener('mouseover', e => {
                    const total = (streetOf(e.feature) || { count: 0 }).count;
                    sameStreet(nameOf(e.feature)).forEach(ft => {
                        const c = (entryOf(ft) || { count: 0 }).count;
                        const base = currentMode === 'street-heatmap' ? 3 + 8 * Math.sqrt(segMax ? c / segMax : 0) : (total > 0 ? 4 : 2.5);
                        streetLayer.overrideStyle(ft, { strokeWeight: base + 3, strokeOpacity: 1, zIndex: 90 });
                    });
                    streetTip.setContent(streetTipHtml(e.feature));
                    streetTip.setPosition(e.latLng);
                    streetTip.open({ map: map });
                });
                streetLayer.addListener('mousemove', e => streetTip.setPosition(e.latLng));
                streetLayer.addListener('mouseout', e => {
                    sameStreet(nameOf(e.feature)).forEach(ft => streetLayer.revertStyle(ft));
                    streetTip.close();
                });
                streetLayer.addListener('click', e => {
                    if (sv && sv.isOpen()) return;
                    infoWin.setContent(streetPopupHtml(e.feature));
                    infoWin.setPosition(e.latLng);
                    infoWin.open({ map: map });
                });
            }).catch(err => console.warn('Google view: streets not loaded', err));
        }

        // ---------------- Legend ----------------
        function renderLegend() {
            if (!legend) return;
            const mode = currentMode;
            if (mode === 'street-heatmap') {
                legend.innerHTML = '<b><i class="fas fa-road" style="color:#274d4c"></i> Crimes per street segment</b>' +
                    '<div class="bar" style="background:' + cssGradient() + '"></div>' +
                    '<div class="ends"><span>Fewer</span><span>' + (segMax ? 'Most (' + segMax + ')' : 'Most') + '</span></div>' +
                    '<div style="margin-top:5px"><span class="sw" style="background:' + EMPTY_STREET + '"></span>No recorded crimes</div>';
            } else {
                const levels = '<b><i class="fas fa-road" style="color:#274d4c"></i> Street crime level</b>' +
                    LEVELS.map(l => '<div><span class="sw" style="background:' + l.color + '"></span>' + l.label + ' <span style="color:#9ca3af">· ' + l.range + '</span></div>').join('');
                if (mode === 'heatmap') {
                    legend.innerHTML = '<b><i class="fas fa-fire" style="color:#e74c3c"></i> Crime density</b>' +
                        '<div class="bar" style="background:' + cssGradient() + '"></div>' +
                        '<div class="ends"><span>Low</span><span>High</span></div>' +
                        '<div style="border-top:1px solid #e5e7eb;margin-top:6px;padding-top:6px">' + levels + '</div>';
                } else if (mode === 'clusters') {
                    legend.innerHTML = '<b><i class="fas fa-layer-group" style="color:#274d4c"></i> Crimes per street</b>' +
                        '<div><span class="dot" style="background:#16a34a"></span>1 – 10</div>' +
                        '<div><span class="dot" style="background:#eab308"></span>11 – 30</div>' +
                        '<div><span class="dot" style="background:#dc2626"></span>31 or more</div>' +
                        '<div style="font-size:10px;color:#6b7280;line-height:1.3;margin-top:4px">Zoom in to see the individual crimes.<br>Click a bubble to highlight its street.</div>';
                } else {
                    legend.innerHTML = levels;
                }
            }
        }

        // ---------------- Incidents ----------------
        function clearMarkers() { markers.forEach(m => m.setMap(null)); markers = []; }
        function clearBubbles() { bubbles.forEach(b => b.remove()); bubbles = []; }
        function clearFocus() {
            focusRings.forEach(r => r.setMap(null)); focusRings = [];
            focusedStreet = null;
            bubbles.forEach(b => b.el.classList.remove('picked'));
        }

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

        function addDot(google, p, color, scale) {
            const m = new google.maps.Marker({
                map: map, position: { lat: p.lat, lng: p.lng }, optimized: true, zIndex: 100,
                title: p.i.incident_title || p.i.category_name || '',
                icon: { path: google.maps.SymbolPath.CIRCLE, scale: scale || 6, fillColor: color, fillOpacity: 0.95, strokeColor: '#ffffff', strokeWeight: 1.5 },
            });
            m.addListener('click', () => { if (sv && sv.isOpen()) return; infoWin.setContent(incidentPopup(p.i)); infoWin.open({ map: map, anchor: m }); });
            markers.push(m);
            return m;
        }

        // Cluster view: one bubble per street, like the Leaflet view
        function groupByStreet(points) {
            const groups = {};
            points.forEach(p => {
                const street = String(p.i.street || '').trim() || 'Unnamed location';
                const g = groups[street] || (groups[street] = { name: street, points: [], lat: 0, lng: 0, cats: {}, cleared: 0, open: 0 });
                g.points.push(p); g.lat += p.lat; g.lng += p.lng;
                const c = p.i.category_name || 'Unknown';
                g.cats[c] = (g.cats[c] || 0) + 1;
                if (p.i.clearance_status === 'cleared') g.cleared++; else g.open++;
            });
            return Object.values(groups).map(g => Object.assign(g, { count: g.points.length, lat: g.lat / g.points.length, lng: g.lng / g.points.length })).sort((a, b) => b.count - a.count);
        }

        function clusterPopupHtml(g) {
            const rows = Object.entries(g.cats).sort((a, b) => b[1] - a[1]).slice(0, 5).map(([c, n]) => '<div class="row"><span>' + esc(c) + '</span><b>' + n + '</b></div>').join('');
            const color = clusterColor(g.count);
            return '<div class="cmg-pop" style="min-width:240px">' +
                '<div style="border-bottom:2px solid ' + color + ';padding-bottom:6px;margin-bottom:4px"><div style="font-weight:800;font-size:14px">' + esc(g.name) + '</div>' +
                '<div style="font-size:11px;color:#6b7280">' + esc(g.points[0].i.barangay_name || 'San Agustin') + ' · street cluster</div></div>' +
                '<div class="stats"><div class="stat"><small>Total</small><strong style="color:' + color + '">' + g.count + '</strong></div>' +
                '<div class="stat"><small>Cleared</small><strong style="color:#16a34a">' + g.cleared + '</strong></div>' +
                '<div class="stat"><small>Open</small><strong style="color:#dc2626">' + g.open + '</strong></div></div>' +
                '<div style="font-size:10px;font-weight:800;color:#6b7280;text-transform:uppercase;margin-bottom:2px">Crimes on this street</div>' + rows +
                '<button type="button" class="btn cmg-focus-btn"><i class="fas fa-location-crosshairs mr-1"></i>Highlight this street</button></div>';
        }

        // Frame a street's crimes and ring them; the street line itself lights
        // up when the road exists in the street layer.
        function focusStreet(name, points, fit) {
            const google = global.google;
            clearFocus();
            focusedStreet = name;
            const pts = points || (currentPoints().filter(p => (String(p.i.street || '').trim() || 'Unnamed location') === name));
            const b = new google.maps.LatLngBounds();
            pts.forEach(p => {
                b.extend({ lat: p.lat, lng: p.lng });
                focusRings.push(new google.maps.Marker({
                    map: map, position: { lat: p.lat, lng: p.lng }, clickable: false, zIndex: 95,
                    icon: { path: google.maps.SymbolPath.CIRCLE, scale: 13, fillColor: '#facc15', fillOpacity: 0.25, strokeColor: '#111827', strokeWeight: 2, strokeOpacity: 0.9 },
                }));
            });
            // The road itself frames the view when it is in the street layer;
            // a street with no drawn road falls back to its crimes.
            (streetGeo && streetGeo.features || []).filter(f => (String((f.properties || {}).name || '').trim() || 'Unnamed road') === name).forEach(f => {
                (function walk(c) { if (typeof c[0] === 'number') b.extend({ lat: c[1], lng: c[0] }); else c.forEach(walk); })(f.geometry.coordinates);
            });
            bubbles.forEach(bb => bb.el.classList.toggle('picked', bb.name === name));
            styleStreets();
            if (fit !== false && !b.isEmpty()) { map.fitBounds(b, 90); if (map.getZoom() > 18) map.setZoom(18); }
            if (pts.length && typeof o.onClusterSelect === 'function') o.onClusterSelect(name, pts.map(p => p.i));
        }

        // Bubbles when zoomed out, the crimes behind them when zoomed in
        function applyClusterZoom() {
            if (!map || currentMode !== 'clusters') return;
            const zoomedIn = map.getZoom() >= 17;
            bubbles.forEach(b => b.el.classList.toggle('faded', zoomedIn));
            markers.forEach(m => m.setVisible(zoomedIn));
        }

        let lastPoints = [];
        const currentPoints = () => lastPoints;

        function setIncidents(incidents) {
            incidents = Array.isArray(incidents) ? incidents : [];
            if (!ready) { pending = incidents; return; }
            const google = global.google;
            const mode = currentMode = String(o.getMode() || 'markers');

            clearMarkers(); clearBubbles(); clearFocus(); hidePointer();
            infoWin.close(); streetTip.close();

            const points = [];
            incidents.forEach(i => {
                const lat = latOf(i), lng = lngOf(i);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                points.push({ lat, lng, i });
            });
            lastPoints = points;

            if (segments.length) snapIncidents(incidents);
            styleStreets();

            if (mode === 'heatmap') {
                heat.setPoints(points.map(p => ({ lat: p.lat, lng: p.lng, w: typeof o.getWeight === 'function' ? Math.max(0, Math.min(1, +o.getWeight(p.i) || 0)) : 0.8 })));
                heat.show();
            } else {
                heat.hide();
            }

            if (mode === 'clusters') {
                groupByStreet(points).forEach(g => {
                    const color = clusterColor(g.count);
                    const bubble = makeBubble(google, map, { lat: g.lat, lng: g.lng },
                        '<span class="n">' + g.count + '</span><span class="s">' + esc(g.name) + '</span>',
                        () => {
                            if (sv && sv.isOpen()) return;
                            infoWin.setContent(clusterPopupHtml(g));
                            infoWin.setPosition({ lat: g.lat, lng: g.lng });
                            infoWin.open({ map: map });
                            focusStreet(g.name, g.points, false);
                            google.maps.event.addListenerOnce(infoWin, 'domready', () => {
                                const btn = document.querySelector('.cmg-focus-btn');
                                if (btn) btn.addEventListener('click', () => { infoWin.close(); focusStreet(g.name, g.points, true); });
                            });
                        });
                    bubble.el.style.background = 'linear-gradient(135deg,' + color + ' 0%,' + color + 'dd 100%)';
                    bubble.name = g.name;
                    bubbles.push(bubble);
                    g.points.forEach(p => addDot(google, p, severityColor(p.i), 6));
                });
                applyClusterZoom();
            } else if (mode === 'markers') {
                points.forEach(p => addDot(google, p, p.i.color_code || '#274d4c', 6));
            }

            renderLegend();
            const modeLabel = { markers: 'markers', heatmap: 'heat map', 'street-heatmap': 'street segments', clusters: 'street clusters' }[mode] || mode;
            badge.innerHTML = '<i class="fab fa-google mr-1"></i>Google Maps · ' + modeLabel + ' · ' + incidents.length + ' incident' + (incidents.length === 1 ? '' : 's');
        }

        // While Street View is open, the street Pegman stands on is the
        // active one on the inset map, so the inset reads like the Add Crime
        // page's: you always see which road you are walking. The street that
        // was focused before (a cluster / Top 10 pick) comes back on exit.
        function followPegman(google) {
            let savedFocus = null;
            const nearestStreet = (lat, lng) => {
                let best = null, bestD = 40;   // metres: Pegman is on the road, not near it
                const pad = 60 / 100000;
                for (const seg of segments) {
                    const b = seg.bbox;
                    if (lat < b.minLat - pad || lat > b.maxLat + pad || lng < b.minLng - pad || lng > b.maxLng + pad) continue;
                    for (const line of seg.lines) {
                        const d = distanceToLine(lat, lng, line);
                        if (d < bestD) { bestD = d; best = seg.name; }
                    }
                }
                return best;
            };
            const update = () => {
                const pos = sv.pano.getPosition();
                if (!pos || !sv.isOpen()) return;
                const name = nearestStreet(pos.lat(), pos.lng());
                if (name && name !== focusedStreet) { focusedStreet = name; styleStreets(); }
            };
            sv.pano.addListener('visible_changed', () => {
                if (sv.isOpen()) {
                    if (!walking) { savedFocus = focusedStreet; walking = true; styleStreets(); }
                    update();
                } else if (walking) {
                    walking = false;
                    focusedStreet = savedFocus;
                    savedFocus = null;
                    styleStreets();
                }
            });
            sv.pano.addListener('position_changed', update);
        }

        // Hover arrow over one crime, driven by the page's incident list
        function showPointer(lat, lng) {
            const google = global.google;
            if (!map || !Number.isFinite(lat) || !Number.isFinite(lng)) return;
            hidePointer();
            const ov = new google.maps.OverlayView();
            const el = document.createElement('div');
            el.className = 'cmg-pointer';
            el.innerHTML = '<div><i class="fas fa-location-dot"></i></div>';
            ov.onAdd = function () { this.getPanes().floatPane.appendChild(el); };
            ov.onRemove = function () { if (el.parentNode) el.parentNode.removeChild(el); };
            ov.draw = function () {
                const proj = this.getProjection();
                if (!proj) return;
                const p = proj.fromLatLngToDivPixel(new google.maps.LatLng(lat, lng));
                el.style.left = p.x + 'px'; el.style.top = p.y + 'px';
            };
            ov.setMap(map);
            pointer = ov;
        }
        function hidePointer() { if (pointer) { pointer.setMap(null); pointer = null; } }

        const api = {
            kind: 'google',
            showPointer(lat, lng) { if (ready) showPointer(+lat, +lng); },
            hidePointer,
            show() {
                shown = true;
                overlay.classList.add('on');
                init().then(ok => { if (ok && map) { global.google.maps.event.trigger(map, 'resize'); setIncidents(o.getIncidents() || []); } });
            },
            hide() { shown = false; overlay.classList.remove('on'); },
            toggle() { shown ? api.hide() : api.show(); },
            refresh() { if (shown && ready) setIncidents(o.getIncidents() || []); },
            resize() { if (map) { global.google.maps.event.trigger(map, 'resize'); if (heat) heat.redraw(); } },
            isShown: () => shown,
            isFailed: () => failed,
            getMap: () => map,
            focusStreet(name) { if (ready) focusStreet(name, null, true); },
            clearFocus() { if (ready) { clearFocus(); styleStreets(); } },
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

    global.CrimeMapGoogle = { create, switcher, ringsOf, insideRings, outsideMask, attachStreetView, injectCss, colorFor };
})(window);
