/*
 * Street-Segment Heatmap for Barangay San Agustin.
 *
 * Instead of blurred density blobs, every road segment (one feature in
 * /data/san_agustin_streets.geojson) is coloured by the number of incidents
 * that sit on it. Each incident is assigned to the nearest segment within
 * `snapMeters`; anything further from a street is counted but not drawn.
 *
 * Usage:
 *   const heat = StreetSegmentHeatmap.create(map, { onSegmentClick: fn });
 *   heat.update(incidents);   // [{latitude, longitude, category_name?}, ...]
 *   heat.clear(); heat.remove(); heat.getStats();
 *
 * Shared by the authenticated Crime Mapping page and the public landing map.
 */
(function (global) {
    'use strict';

    const GEOJSON_URL = '/data/san_agustin_streets.geojson';

    // Same scale as the "Crime Intensity Scale" legend on Crime Mapping
    const STOPS = [
        [0.00, [59, 130, 246]],   // #3b82f6 blue
        [0.25, [46, 204, 113]],   // #2ecc71 green
        [0.50, [243, 156, 18]],   // #f39c12 orange
        [0.75, [231, 76, 60]],    // #e74c3c red
        [1.00, [192, 57, 43]],    // #c0392b dark red
    ];
    const EMPTY_COLOR = '#cbd5e1';

    let streetsPromise = null;

    function colorFor(ratio) {
        const r = Math.max(0, Math.min(1, ratio));
        for (let i = 1; i < STOPS.length; i++) {
            if (r <= STOPS[i][0]) {
                const [r0, c0] = STOPS[i - 1];
                const [r1, c1] = STOPS[i];
                const t = r1 === r0 ? 0 : (r - r0) / (r1 - r0);
                const c = c0.map((v, k) => Math.round(v + (c1[k] - v) * t));
                return 'rgb(' + c.join(',') + ')';
            }
        }
        return 'rgb(' + STOPS[STOPS.length - 1][1].join(',') + ')';
    }

    function escapeHtml(text) {
        return String(text == null ? '' : text).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    /** Segments: [{ id, name, pts: [[lat,lng],...], bbox: {minLat,maxLat,minLng,maxLng} }] */
    function loadStreets() {
        if (streetsPromise) return streetsPromise;
        streetsPromise = fetch(GEOJSON_URL + '?t=' + Date.now(), { headers: { 'Accept': 'application/json' } })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(geo => {
                const segments = [];
                (geo.features || []).forEach((f, idx) => {
                    const name = String((f.properties || {}).name || '').trim() || 'Unnamed road';
                    const geom = f.geometry || {};
                    const lines = geom.type === 'MultiLineString' ? geom.coordinates : [geom.coordinates || []];
                    lines.forEach((line, li) => {
                        const pts = (line || [])
                            .filter(c => Array.isArray(c) && Number.isFinite(+c[0]) && Number.isFinite(+c[1]))
                            .map(c => [+c[1], +c[0]]);
                        if (pts.length < 2) return;
                        const bbox = { minLat: Infinity, maxLat: -Infinity, minLng: Infinity, maxLng: -Infinity };
                        pts.forEach(p => {
                            bbox.minLat = Math.min(bbox.minLat, p[0]); bbox.maxLat = Math.max(bbox.maxLat, p[0]);
                            bbox.minLng = Math.min(bbox.minLng, p[1]); bbox.maxLng = Math.max(bbox.maxLng, p[1]);
                        });
                        segments.push({ id: idx + ':' + li, name: name, pts: pts, bbox: bbox });
                    });
                });
                return segments;
            })
            .catch(err => { streetsPromise = null; throw err; });
        return streetsPromise;
    }

    // Distance in metres from a point to a polyline (equirectangular, fine at barangay scale)
    function distanceToSegment(lat, lng, pts) {
        const kx = Math.cos(lat * Math.PI / 180) * 111320;
        const ky = 110540;
        const px = lng * kx, py = lat * ky;
        let best = Infinity;
        for (let i = 0; i < pts.length - 1; i++) {
            const ax = pts[i][1] * kx, ay = pts[i][0] * ky;
            const bx = pts[i + 1][1] * kx, by = pts[i + 1][0] * ky;
            const dx = bx - ax, dy = by - ay;
            const len2 = dx * dx + dy * dy;
            const t = len2 > 0 ? Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / len2)) : 0;
            const cx = ax + t * dx, cy = ay + t * dy;
            const d = Math.hypot(px - cx, py - cy);
            if (d < best) best = d;
        }
        return best;
    }

    function create(map, options) {
        const opts = Object.assign({
            pane: 'streetHeatPane',
            paneZIndex: 380,
            snapMeters: 45,
            interactive: true,
            showEmpty: true,
            onSegmentClick: null,
            minWeight: 3,
            maxWeight: 11,
        }, options || {});

        if (!map.getPane(opts.pane)) {
            map.createPane(opts.pane);
            map.getPane(opts.pane).style.zIndex = String(opts.paneZIndex);
        }

        const group = L.layerGroup().addTo(map);
        let stats = { segments: 0, max: 0, matched: 0, unmatched: 0, incidents: 0, top: [] };
        let lastIncidents = [];
        let visible = true;
        let renderToken = 0;

        function assign(segments, incidents) {
            const counts = new Map();   // segment id -> { count, categories: Map, incidents: [] }
            let matched = 0, unmatched = 0;
            const pad = opts.snapMeters / 100000; // ~degrees for the bbox prefilter

            incidents.forEach(inc => {
                const lat = parseFloat(inc.latitude ?? inc.lat);
                const lng = parseFloat(inc.longitude ?? inc.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                let best = null, bestD = Infinity;
                for (const seg of segments) {
                    const b = seg.bbox;
                    if (lat < b.minLat - pad || lat > b.maxLat + pad || lng < b.minLng - pad || lng > b.maxLng + pad) continue;
                    const d = distanceToSegment(lat, lng, seg.pts);
                    if (d < bestD) { bestD = d; best = seg; }
                }
                if (!best || bestD > opts.snapMeters) { unmatched++; return; }
                matched++;
                let entry = counts.get(best.id);
                if (!entry) { entry = { count: 0, categories: new Map(), incidents: [] }; counts.set(best.id, entry); }
                entry.count++;
                entry.incidents.push(inc);
                const cat = String(inc.category_name || inc.category || '').trim();
                if (cat) entry.categories.set(cat, (entry.categories.get(cat) || 0) + 1);
            });

            return { counts, matched, unmatched };
        }

        function tooltipHtml(seg, entry) {
            const count = entry ? entry.count : 0;
            let html = '<div style="font-weight:800;font-size:12px;color:#111827">' + escapeHtml(seg.name) + '</div>'
                + '<div style="font-size:11px;color:#374151;margin-top:2px">'
                + (count === 0 ? 'No recorded crimes on this segment' : count + ' crime' + (count === 1 ? '' : 's') + ' on this segment')
                + '</div>';
            if (entry && entry.categories.size) {
                const top = [...entry.categories.entries()].sort((a, b) => b[1] - a[1]).slice(0, 3);
                html += '<div style="font-size:10.5px;color:#6b7280;margin-top:3px">'
                    + top.map(([c, n]) => escapeHtml(c) + ' ' + n).join(' &middot; ') + '</div>';
            }
            return html;
        }

        function render(segments, assignment) {
            group.clearLayers();
            let max = 0;
            assignment.counts.forEach(e => { if (e.count > max) max = e.count; });

            const perName = new Map();
            segments.forEach(seg => {
                const entry = assignment.counts.get(seg.id) || null;
                const count = entry ? entry.count : 0;
                if (count > 0) perName.set(seg.name, (perName.get(seg.name) || 0) + count);
                if (count === 0 && !opts.showEmpty) return;

                const ratio = max > 0 ? count / max : 0;
                const color = count === 0 ? EMPTY_COLOR : colorFor(ratio);
                const weight = count === 0 ? 2 : opts.minWeight + (opts.maxWeight - opts.minWeight) * Math.sqrt(ratio);

                if (count > 0) {
                    // Soft glow under the segment so hot streets read at any zoom
                    L.polyline(seg.pts, {
                        pane: opts.pane, color: color, weight: weight + 8, opacity: 0.22 + 0.18 * ratio,
                        lineCap: 'round', lineJoin: 'round', interactive: false
                    }).addTo(group);
                }

                const line = L.polyline(seg.pts, {
                    pane: opts.pane, color: color, weight: weight, opacity: count === 0 ? 0.55 : 0.95,
                    lineCap: 'round', lineJoin: 'round', interactive: opts.interactive
                }).addTo(group);

                if (opts.interactive) {
                    line.bindTooltip(tooltipHtml(seg, entry), { sticky: true, direction: 'top', opacity: 0.97, className: 'ssh-tooltip' });
                    line.on('mouseover', () => line.setStyle({ weight: weight + 3 }));
                    line.on('mouseout', () => line.setStyle({ weight: weight }));
                    if (typeof opts.onSegmentClick === 'function') {
                        line.on('click', e => opts.onSegmentClick({
                            name: seg.name, count: count, incidents: entry ? entry.incidents : [],
                            categories: entry ? [...entry.categories.entries()].map(([c, n]) => ({ category: c, count: n })).sort((a, b) => b.count - a.count) : [],
                            latlng: e.latlng
                        }, e));
                    }
                }
            });

            stats = {
                segments: assignment.counts.size,
                max: max,
                matched: assignment.matched,
                unmatched: assignment.unmatched,
                incidents: assignment.matched + assignment.unmatched,
                top: [...perName.entries()].map(([name, count]) => ({ name, count })).sort((a, b) => b.count - a.count).slice(0, 10),
            };
        }

        return {
            layer: group,
            update: function (incidents) {
                lastIncidents = Array.isArray(incidents) ? incidents : [];
                const token = ++renderToken;
                return loadStreets().then(segments => {
                    if (token !== renderToken) return stats;   // superseded by a newer update
                    render(segments, assign(segments, lastIncidents));
                    if (!visible && map.hasLayer(group)) map.removeLayer(group);
                    return stats;
                });
            },
            clear: function () { renderToken++; group.clearLayers(); lastIncidents = []; },
            remove: function () { renderToken++; group.clearLayers(); if (map.hasLayer(group)) map.removeLayer(group); },
            setVisible: function (on) {
                visible = !!on;
                if (visible && !map.hasLayer(group)) group.addTo(map);
                if (!visible && map.hasLayer(group)) map.removeLayer(group);
            },
            getStats: function () { return stats; },
            colorFor: colorFor,
        };
    }

    global.StreetSegmentHeatmap = { create: create, colorFor: colorFor, loadStreets: loadStreets };
})(window);
