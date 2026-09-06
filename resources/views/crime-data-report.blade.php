@extends('layouts.app')

@section('title', 'Crime Data Reports')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    /* Floating hover map */
    #hoverMapPanel {
        position: fixed;
        z-index: 60;
        width: 380px;
        max-width: calc(100vw - 24px);
        display: none;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        box-shadow: 0 12px 32px rgba(0,0,0,.18);
        overflow: hidden;
    }
    #hoverMapPanel .hm-head {
        padding: 7px 10px;
        font-size: 11px;
        font-weight: 800;
        color: #111827;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    #hoverMap { height: 240px; }
    .cd-row:hover { background: #f5f3ff; }
    .cd-row td { vertical-align: top; }
</style>
@endpush

@section('content')
<div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">

    <!-- Page header -->
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                    <i class="fas fa-file-shield text-alertara-700 mr-2"></i>Crime Data Reports
                </h1>
                <p class="text-gray-600 mt-1 text-sm lg:text-base">
                    Every recorded San Agustin crime, by street. Hover a row to see the exact street and spot on the map.
                    Select crimes to save a reusable report or download a PDF — with the map included — whenever someone requests crime data.
                </p>
            </div>
            <a href="{{ route('reports.index') }}"
               class="inline-flex items-center self-start px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-semibold flex-shrink-0">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
            </a>
        </div>
    </div>

    <!-- Saved reports -->
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between gap-2 mb-3">
            <h2 class="text-sm font-bold text-gray-900">
                <i class="fas fa-box-archive text-alertara-600 mr-1.5"></i>Saved Crime Data Reports
            </h2>
            <span id="savedCountBadge" class="text-[11px] font-bold text-gray-400"></span>
        </div>
        <div id="savedReportsBox" class="space-y-2">
            <div class="text-sm text-gray-400"><i class="fas fa-spinner fa-spin mr-1"></i>Loading saved reports…</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-4 bg-white rounded-xl border border-gray-200 p-5">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                <input id="cdSearch" type="text" placeholder="Code, title, street, modus…"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-alertara-500 bg-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Street</label>
                <select id="cdStreet" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                    <option value="">All streets</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Category</label>
                <select id="cdCategory" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                    <option value="">All categories</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                <select id="cdStatus" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                    <option value="">All statuses</option>
                    <option value="resolved">Resolved / solved / closed</option>
                    <option value="unresolved">Unresolved</option>
                </select>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <span id="cdShownCount" class="text-xs font-bold text-gray-500"></span>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <span id="cdSelCount" class="text-xs font-bold text-alertara-700"></span>
                <button id="cdSelectAllBtn" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-xs font-semibold">
                    <i class="fas fa-check-double mr-1"></i>Select shown
                </button>
                <button id="cdClearSelBtn" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-xs font-semibold">
                    Clear
                </button>
                <button id="cdSaveReportBtn" class="px-3 py-1.5 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 text-xs font-semibold">
                    <i class="fas fa-floppy-disk mr-1"></i>Create report from selection
                </button>
                <button id="cdPdfSelBtn" class="px-3 py-1.5 bg-violet-600 text-white rounded-lg hover:bg-violet-700 text-xs font-semibold">
                    <i class="fas fa-file-pdf mr-1"></i>Download PDF (selected)
                </button>
            </div>
        </div>
    </div>

    <!-- Crime table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div id="cdLoading" class="p-8 text-center text-sm text-gray-500">
            <i class="fas fa-spinner fa-spin text-alertara-600 mr-1"></i>Loading crimes…
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm hidden" id="cdTable">
                <thead>
                    <tr class="bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                        <th class="px-3 py-2.5 w-8"></th>
                        <th class="px-3 py-2.5">Code</th>
                        <th class="px-3 py-2.5">Crime</th>
                        <th class="px-3 py-2.5">Street</th>
                        <th class="px-3 py-2.5">Date &amp; Time</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5 text-right">PDF</th>
                    </tr>
                </thead>
                <tbody id="cdTbody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Floating hover map -->
<div id="hoverMapPanel">
    <div class="hm-head">
        <i class="fas fa-road text-alertara-600"></i><span id="hmStreet"></span>
        <span id="hmCat" class="text-[10px] font-bold text-white px-2 py-0.5 rounded-full"></span>
    </div>
    <div id="hoverMap"></div>
</div>

<!-- Save-report modal -->
<div id="cdSaveModal" class="hidden fixed inset-0 z-[70] bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-5">
        <h3 class="text-sm font-bold text-gray-900 mb-3"><i class="fas fa-floppy-disk text-alertara-600 mr-1.5"></i>Save Crime Data Report</h3>
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Title</label>
        <input id="cdReportTitle" type="text" placeholder='e.g. "Resolved robberies — Susano Road"'
               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm mb-3">
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Purpose (optional)</label>
        <input id="cdReportPurpose" type="text" placeholder="e.g. Requested by ABC Homeowners Association"
               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm mb-1">
        <p id="cdModalInfo" class="text-[11px] text-gray-500 mb-4"></p>
        <div class="flex justify-end gap-2">
            <button id="cdModalCancel" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-xs font-semibold hover:bg-gray-50">Cancel</button>
            <button id="cdModalSave" class="px-4 py-2 bg-alertara-700 text-white rounded-lg text-xs font-semibold hover:bg-alertara-800">
                <i class="fas fa-floppy-disk mr-1"></i>Save report
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<!-- Hover map runs on Google Maps (Hybrid); Leaflet stays as the fallback and for the PDF -->
<script src="{{ asset('js/google-maps-loader.js') }}?v={{ filemtime(public_path('js/google-maps-loader.js')) }}"></script>
<script>
(function () {
    'use strict';

    const LIST_URL   = @json(route('reports.crime-data.list'));
    const SAVE_URL   = @json(route('reports.crime-data.save'));
    const SAVED_URL  = @json(route('reports.crime-data.saved'));
    const DELETE_URL = @json(url('reports/crime-data/saved'));
    const STREETS_GEOJSON = @json(asset('data/san_agustin_streets.geojson'));
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const CAT_COLORS = {
        'Theft': '#2563eb', 'Robbery': '#dc2626', 'Assault': '#ea580c',
        'Burglary': '#7c3aed', 'Vehicle Theft': '#0891b2', 'Domestic Violence': '#be185d',
        'Fraud': '#ca8a04', 'Sexual Offense': '#9333ea', 'Homicide': '#111827'
    };
    const DONE = ['solved', 'resolved', 'closed', 'cleared'];
    const isDone = s => DONE.includes(String(s || '').toLowerCase());

    function fmt12h(t) {
        const m = /^(\d{1,2}):(\d{2})/.exec(String(t || ''));
        if (!m) return t || '';
        const h = parseInt(m[1], 10);
        return ((h % 12) || 12) + ':' + m[2] + ' ' + (h >= 12 ? 'PM' : 'AM');
    }

    let allCrimes = [];               // full list from the server
    let shown = [];                   // after filters
    const selected = new Set();       // incident codes
    let streetFeatures = {};          // street name (lower) -> [geojson coords arrays]

    // ------------------------------------------------------------ data load
    async function loadCrimes() {
        try {
            const res = await fetch(LIST_URL, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'HTTP ' + res.status);
            allCrimes = data.incidents || [];
            buildFilterOptions();
            applyFilters();
            $('cdLoading').style.display = 'none';
            $('cdTable').classList.remove('hidden');
        } catch (e) {
            $('cdLoading').innerHTML = '<span class="text-red-600">Could not load crimes — ' + esc(e.message) + '</span>';
        }
    }

    async function loadStreets() {
        try {
            const res = await fetch(STREETS_GEOJSON);
            const geo = await res.json();
            (geo.features || []).forEach(f => {
                const name = (f.properties && f.properties.name || '').trim();
                if (!name || !f.geometry || f.geometry.type !== 'LineString') return;
                const key = name.toLowerCase();
                (streetFeatures[key] = streetFeatures[key] || []).push(f.geometry.coordinates);
            });
        } catch (e) { console.error('streets geojson failed:', e); }
    }

    function buildFilterOptions() {
        const streets = [...new Set(allCrimes.map(c => c.street).filter(Boolean))].sort();
        const cats = [...new Set(allCrimes.map(c => c.category).filter(Boolean))].sort();
        $('cdStreet').innerHTML = '<option value="">All streets</option>' +
            streets.map(s => '<option>' + esc(s) + '</option>').join('');
        $('cdCategory').innerHTML = '<option value="">All categories</option>' +
            cats.map(c => '<option>' + esc(c) + '</option>').join('');
    }

    // ------------------------------------------------------------- filtering
    function applyFilters() {
        const q = $('cdSearch').value.trim().toLowerCase();
        const st = $('cdStreet').value;
        const cat = $('cdCategory').value;
        const status = $('cdStatus').value;

        shown = allCrimes.filter(c => {
            if (st && c.street !== st) return false;
            if (cat && c.category !== cat) return false;
            if (status === 'resolved' && !isDone(c.status)) return false;
            if (status === 'unresolved' && isDone(c.status)) return false;
            if (q) {
                const hay = [c.code, c.title, c.street, c.category, c.modus, c.status].join(' ').toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });

        renderTable();
    }

    function renderTable() {
        $('cdShownCount').textContent = 'Showing ' + shown.length + ' of ' + allCrimes.length + ' crimes';
        $('cdTbody').innerHTML = shown.map(c => {
            const cc = CAT_COLORS[c.category] || '#64748b';
            const done = isDone(c.status);
            return '<tr class="cd-row border-t border-gray-100" data-code="' + esc(c.code) + '">' +
                '<td class="px-3 py-2.5"><input type="checkbox" class="cd-check" data-code="' + esc(c.code) + '"' + (selected.has(c.code) ? ' checked' : '') + '></td>' +
                '<td class="px-3 py-2.5 font-mono text-xs text-gray-500 whitespace-nowrap">' + esc(c.code) + '</td>' +
                '<td class="px-3 py-2.5"><div class="font-semibold text-gray-900">' + esc(c.title || 'Crime') + '</div>' +
                    '<span class="inline-block mt-0.5 text-[10px] font-bold text-white px-2 py-0.5 rounded-full" style="background:' + cc + ';">' + esc(c.category) + '</span></td>' +
                '<td class="px-3 py-2.5 text-gray-700 whitespace-nowrap"><i class="fas fa-road text-gray-300 mr-1"></i>' + esc(c.street || '—') + '</td>' +
                '<td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">' + esc(c.date || '') + (c.time ? '<br><span class="text-xs text-gray-400"><i class="fas fa-clock mr-0.5"></i>' + esc(fmt12h(c.time)) + '</span>' : '') + '</td>' +
                '<td class="px-3 py-2.5 whitespace-nowrap"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full ' +
                    (done ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800') + '">' + esc(String(c.status || '').replace(/_/g, ' ').toUpperCase()) + '</span></td>' +
                '<td class="px-3 py-2.5 text-right"><button class="cd-pdf px-2.5 py-1 bg-violet-50 border border-violet-200 text-violet-700 rounded-lg text-[11px] font-bold hover:bg-violet-100" data-code="' + esc(c.code) + '">' +
                    '<i class="fas fa-file-pdf mr-1"></i>PDF</button></td>' +
            '</tr>';
        }).join('') || '<tr><td colspan="7" class="px-3 py-8 text-center text-sm text-gray-400">No crimes match the filters.</td></tr>';
        updateSelCount();
    }

    ['cdSearch', 'cdStreet', 'cdCategory', 'cdStatus'].forEach(id =>
        $(id).addEventListener(id === 'cdSearch' ? 'input' : 'change', applyFilters));

    // ------------------------------------------------------------- selection
    function updateSelCount() {
        $('cdSelCount').textContent = selected.size ? selected.size + ' selected' : '';
    }
    $('cdTbody').addEventListener('change', e => {
        const cb = e.target.closest('.cd-check');
        if (!cb) return;
        cb.checked ? selected.add(cb.dataset.code) : selected.delete(cb.dataset.code);
        updateSelCount();
    });
    $('cdSelectAllBtn').addEventListener('click', () => {
        shown.forEach(c => selected.add(c.code));
        renderTable();
    });
    $('cdClearSelBtn').addEventListener('click', () => { selected.clear(); renderTable(); });

    // ------------------------------------------------------------- hover map
    // Google Maps (Hybrid imagery, zoomed in on the crime) when the key is
    // configured; otherwise the original Leaflet mini-map.
    let hoverMap = null, hoverLayer = null, hideTimer = null;
    let gHoverMap = null, gHoverShapes = [], gHoverLoading = null, gHoverPending = null;
    const useGoogleHover = typeof GoogleMapsLoader !== 'undefined' && GoogleMapsLoader.hasKey();

    function ensureHoverMap() {
        if (hoverMap) return;
        hoverMap = L.map('hoverMap', { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, boxZoom: false, keyboard: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(hoverMap);
    }

    // One Google map for every hover, built on the first hover only
    function ensureGoogleHoverMap() {
        if (gHoverMap) return Promise.resolve(gHoverMap);
        if (gHoverLoading) return gHoverLoading;
        gHoverLoading = GoogleMapsLoader.load(['maps']).then(google => {
            gHoverMap = new google.maps.Map($('hoverMap'), {
                center: { lat: 14.7292, lng: 121.0385 }, zoom: 18,
                mapTypeId: google.maps.MapTypeId.HYBRID,
                disableDefaultUI: true, gestureHandling: 'none', clickableIcons: false, tilt: 0,
                keyboardShortcuts: false,
            });
            return gHoverMap;
        }).catch(err => { console.warn('Google hover map unavailable, using Leaflet:', err); gHoverLoading = null; return null; });
        return gHoverLoading;
    }

    function drawGoogleHover(crime) {
        const google = window.google;
        gHoverShapes.forEach(sh => sh.setMap(null));
        gHoverShapes = [];
        const bounds = new google.maps.LatLngBounds();

        // The street itself, highlighted (active)
        const segs = streetFeatures[(crime.street || '').toLowerCase()] || [];
        segs.forEach(coords => {
            const path = coords.map(p => ({ lat: p[1], lng: p[0] }));
            path.forEach(pt => bounds.extend(pt));
            gHoverShapes.push(new google.maps.Polyline({ map: gHoverMap, path: path, strokeColor: '#111', strokeOpacity: .45, strokeWeight: 9, zIndex: 1 }));
            gHoverShapes.push(new google.maps.Polyline({ map: gHoverMap, path: path, strokeColor: '#f59e0b', strokeOpacity: .95, strokeWeight: 4, zIndex: 2 }));
        });

        // The crime spot
        if (crime.lat && crime.lng) {
            bounds.extend({ lat: crime.lat, lng: crime.lng });
            gHoverShapes.push(new google.maps.Marker({
                map: gHoverMap, position: { lat: crime.lat, lng: crime.lng }, zIndex: 10, clickable: false,
                icon: { path: google.maps.SymbolPath.CIRCLE, scale: 9, fillColor: CAT_COLORS[crime.category] || '#dc2626', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
            }));
        }

        google.maps.event.trigger(gHoverMap, 'resize');
        // Zoomed in on the crime, with its street still in view
        if (crime.lat && crime.lng) {
            gHoverMap.setCenter({ lat: crime.lat, lng: crime.lng });
            gHoverMap.setZoom(18);
        } else if (!bounds.isEmpty()) {
            gHoverMap.fitBounds(bounds, 20);
        }
    }

    function showHoverMap(crime, rowRect) {
        if (useGoogleHover) {
            showHoverPanel(crime, rowRect);
            gHoverPending = crime;
            ensureGoogleHoverMap().then(m => {
                if (!m) { useGoogleHoverFallback(crime, rowRect); return; }
                if (gHoverPending === crime) drawGoogleHover(crime);
            });
            return;
        }
        showHoverMapLeaflet(crime, rowRect);
    }

    function useGoogleHoverFallback(crime, rowRect) {
        // Google failed to load: hand the panel back to Leaflet for good
        $('hoverMap').innerHTML = '';
        showHoverMapLeaflet(crime, rowRect);
    }

    function showHoverPanel(crime, rowRect) {
        const panel = $('hoverMapPanel');
        $('hmStreet').textContent = crime.street || crime.code;
        const catEl = $('hmCat');
        catEl.textContent = crime.category;
        catEl.style.background = CAT_COLORS[crime.category] || '#64748b';
        const panelW = 380, panelH = 280;
        let left = Math.min(window.innerWidth - panelW - 12, rowRect.right - panelW - 60);
        if (left < 12) left = 12;
        let top = rowRect.top - 40;
        top = Math.max(12, Math.min(top, window.innerHeight - panelH - 12));
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
        panel.style.display = 'block';
    }

    function showHoverMapLeaflet(crime, rowRect) {
        ensureHoverMap();
        const panel = $('hoverMapPanel');

        $('hmStreet').textContent = crime.street || crime.code;
        const catEl = $('hmCat');
        catEl.textContent = crime.category;
        catEl.style.background = CAT_COLORS[crime.category] || '#64748b';

        // Position beside the row (right side if it fits, else left)
        const panelW = 380, panelH = 280;
        let left = Math.min(window.innerWidth - panelW - 12, rowRect.right - panelW - 60);
        if (left < 12) left = 12;
        let top = rowRect.top - 40;
        top = Math.max(12, Math.min(top, window.innerHeight - panelH - 12));
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
        panel.style.display = 'block';

        if (hoverLayer) { hoverLayer.remove(); hoverLayer = null; }
        const group = L.featureGroup();

        // The street itself, highlighted (active)
        const segs = streetFeatures[(crime.street || '').toLowerCase()] || [];
        segs.forEach(coords => {
            const latlngs = coords.map(p => [p[1], p[0]]);
            L.polyline(latlngs, { color: '#111', weight: 8, opacity: .35 }).addTo(group);
            L.polyline(latlngs, { color: '#f59e0b', weight: 4, opacity: .95 }).addTo(group);
        });

        // The crime spot
        if (crime.lat && crime.lng) {
            L.circleMarker([crime.lat, crime.lng], {
                radius: 9, color: '#fff', weight: 2, fillColor: CAT_COLORS[crime.category] || '#dc2626', fillOpacity: 1
            }).addTo(group);
        }

        hoverLayer = group.addTo(hoverMap);
        setTimeout(() => {
            hoverMap.invalidateSize();
            // Auto-zoom to the crime, keeping its street in view
            const b = group.getBounds();
            if (b.isValid()) hoverMap.fitBounds(b.pad(0.15), { maxZoom: 17 });
            if (crime.lat && crime.lng) hoverMap.setView([crime.lat, crime.lng], Math.max(hoverMap.getZoom(), 17));
        }, 30);
    }

    $('cdTbody').addEventListener('mouseover', e => {
        const row = e.target.closest('.cd-row');
        if (!row) return;
        clearTimeout(hideTimer);
        const crime = shown.find(c => c.code === row.dataset.code) || allCrimes.find(c => c.code === row.dataset.code);
        if (crime) showHoverMap(crime, row.getBoundingClientRect());
    });
    $('cdTbody').addEventListener('mouseleave', () => {
        hideTimer = setTimeout(() => { $('hoverMapPanel').style.display = 'none'; }, 250);
    });
    $('hoverMapPanel').addEventListener('mouseenter', () => clearTimeout(hideTimer));
    $('hoverMapPanel').addEventListener('mouseleave', () => { $('hoverMapPanel').style.display = 'none'; });

    // ------------------------------------------------------- save-report flow
    $('cdSaveReportBtn').addEventListener('click', () => {
        if (!selected.size) { alert('Select at least one crime first (checkboxes on the left).'); return; }
        $('cdModalInfo').textContent = selected.size + ' crime' + (selected.size === 1 ? '' : 's') + ' will be saved in this report.';
        $('cdSaveModal').classList.remove('hidden');
        $('cdReportTitle').focus();
    });
    $('cdModalCancel').addEventListener('click', () => $('cdSaveModal').classList.add('hidden'));

    $('cdModalSave').addEventListener('click', async () => {
        const title = $('cdReportTitle').value.trim();
        if (!title) { alert('Please enter a report title.'); return; }
        const btn = $('cdModalSave');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving…';
        try {
            const res = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    title: title,
                    purpose: $('cdReportPurpose').value.trim() || null,
                    incident_codes: [...selected]
                })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'HTTP ' + res.status);
            $('cdSaveModal').classList.add('hidden');
            $('cdReportTitle').value = '';
            $('cdReportPurpose').value = '';
            loadSavedReports();
        } catch (e) {
            alert('Could not save the report: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-floppy-disk mr-1"></i>Save report';
        }
    });

    // --------------------------------------------------------- saved reports
    async function loadSavedReports() {
        try {
            const res = await fetch(SAVED_URL, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            const reports = (data.reports || []);
            $('savedCountBadge').textContent = reports.length ? reports.length + ' saved' : '';
            $('savedReportsBox').innerHTML = reports.length ? reports.map(r =>
                '<div class="flex flex-wrap items-center gap-2 border border-gray-200 rounded-lg px-3 py-2.5">' +
                    '<div class="min-w-0">' +
                        '<div class="text-sm font-bold text-gray-900">' + esc(r.title) + '</div>' +
                        '<div class="text-[11px] text-gray-500">' + r.count + ' crime' + (r.count === 1 ? '' : 's') +
                            (r.purpose ? ' · ' + esc(r.purpose) : '') +
                            (r.created_at ? ' · ' + esc(r.created_at) : '') +
                            (r.created_by ? ' · ' + esc(r.created_by) : '') + '</div>' +
                    '</div>' +
                    '<div class="ml-auto flex items-center gap-1.5">' +
                        '<button class="sr-load px-2.5 py-1 bg-white border border-gray-300 text-gray-700 rounded-lg text-[11px] font-bold hover:bg-gray-50" data-codes="' + esc((r.incident_codes || []).join(',')) + '">' +
                            '<i class="fas fa-eye mr-1"></i>Load</button>' +
                        '<button class="sr-pdf px-2.5 py-1 bg-violet-50 border border-violet-200 text-violet-700 rounded-lg text-[11px] font-bold hover:bg-violet-100" data-codes="' + esc((r.incident_codes || []).join(',')) + '" data-title="' + esc(r.title) + '">' +
                            '<i class="fas fa-file-pdf mr-1"></i>PDF</button>' +
                        '<button class="sr-del px-2.5 py-1 bg-white border border-red-200 text-red-600 rounded-lg text-[11px] font-bold hover:bg-red-50" data-id="' + r.id + '">' +
                            '<i class="fas fa-trash"></i></button>' +
                    '</div>' +
                '</div>').join('')
                : '<div class="text-sm text-gray-400">No saved reports yet — select crimes below and click "Create report from selection".</div>';
        } catch (e) {
            $('savedReportsBox').innerHTML = '<div class="text-sm text-red-600">Could not load saved reports.</div>';
        }
    }

    $('savedReportsBox').addEventListener('click', async e => {
        const load = e.target.closest('.sr-load');
        if (load) {
            selected.clear();
            load.dataset.codes.split(',').filter(Boolean).forEach(c => selected.add(c));
            ['cdSearch', 'cdStreet', 'cdCategory', 'cdStatus'].forEach(id => { $(id).value = ''; });
            applyFilters();
            window.scrollTo({ top: $('cdTable').getBoundingClientRect().top + window.scrollY - 140, behavior: 'smooth' });
            return;
        }
        const pdf = e.target.closest('.sr-pdf');
        if (pdf) {
            openPdf(pdf.dataset.codes.split(',').filter(Boolean), pdf.dataset.title);
            return;
        }
        const del = e.target.closest('.sr-del');
        if (del) {
            if (!confirm('Delete this saved report? The crimes themselves are not affected.')) return;
            try {
                const res = await fetch(DELETE_URL + '/' + del.dataset.id, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'HTTP ' + res.status);
                loadSavedReports();
            } catch (err) { alert('Could not delete: ' + err.message); }
        }
    });

    // --------------------------------------------------------------- PDF
    $('cdTbody').addEventListener('click', e => {
        const btn = e.target.closest('.cd-pdf');
        if (btn) openPdf([btn.dataset.code], null);
    });
    $('cdPdfSelBtn').addEventListener('click', () => {
        if (!selected.size) { alert('Select at least one crime first (checkboxes on the left).'); return; }
        openPdf([...selected], null);
    });

    /**
     * Opens a print-ready window: one section per crime with full details and
     * a live map (street highlighted + exact spot). Once the map tiles load,
     * the print dialog opens — choose "Save as PDF" to download.
     */
    // The report window's own script: the 3D map (OpenFreeMap, same style as the
    // system's 3D view) for the preview AND the PDF picture; hand-drawn
    // OpenStreetMap only when the browser cannot run it.
    const REPORT_SCRIPT = "var reportMaps = [], pending = CRIMES.length, actionsReady = false, map3dOk = false;\nfunction enableActions() { if (actionsReady) return; actionsReady = true; document.getElementById(\"downloadPdf\").disabled = false; document.getElementById(\"printReport\").disabled = false; }\nfunction done() { if (--pending <= 0) { setTimeout(enableActions, 400); } }\nfunction streetSegments(c) { return STREETS[(c.street || \"\").toLowerCase()] || []; }\nfunction crimeColor(c) { return CATC[c.category] || \"#dc2626\"; }\n\n/* ---- The 3D map (same OpenFreeMap style as the system's 3D view), one per crime. ---- */\n/* Drawn with preserveDrawingBuffer so its canvas can be copied straight into the PDF: */\n/* the street line and the marker are rendered by the map itself, so nothing can drift. */\nvar VECTOR_STYLE = \"https://tiles.openfreemap.org/styles/liberty\";\nfunction initReportMaps() {\n    if (typeof maplibregl === \"undefined\") { reportMapsFailed(\"MapLibre failed to load\"); return; }\n    map3dOk = true;\n    CRIMES.forEach(function (c, i) {\n        var el = document.getElementById(\"map\" + i);\n        var segs = streetSegments(c);\n        var center = c.lat && c.lng ? [+c.lng, +c.lat] : (segs[0] && segs[0][0] ? segs[0][0] : [121.0385, 14.7292]);\n        var map;\n        try {\n            map = new maplibregl.Map({\n                container: el, style: VECTOR_STYLE, center: center, zoom: 17.2, pitch: 58, bearing: -18,\n                interactive: false, attributionControl: false, antialias: true, preserveDrawingBuffer: true, pixelRatio: 2\n            });\n        } catch (e) { map3dOk = false; reportMapsFailed(e.message); return; }\n        reportMaps.push(map);\n        map.on(\"load\", function () {\n            map.addSource(\"street\", { type: \"geojson\", data: { type: \"FeatureCollection\", features: segs.map(function (coords) { return { type: \"Feature\", geometry: { type: \"LineString\", coordinates: coords } }; }) } });\n            map.addLayer({ id: \"street-casing\", type: \"line\", source: \"street\", layout: { \"line-cap\": \"round\", \"line-join\": \"round\" }, paint: { \"line-color\": \"#111111\", \"line-width\": 8, \"line-opacity\": .35 } });\n            map.addLayer({ id: \"street-line\", type: \"line\", source: \"street\", layout: { \"line-cap\": \"round\", \"line-join\": \"round\" }, paint: { \"line-color\": \"#f59e0b\", \"line-width\": 4, \"line-opacity\": .95 } });\n            if (c.lat && c.lng) {\n                map.addSource(\"spot\", { type: \"geojson\", data: { type: \"Feature\", geometry: { type: \"Point\", coordinates: [+c.lng, +c.lat] } } });\n                map.addLayer({ id: \"spot-halo\", type: \"circle\", source: \"spot\", paint: { \"circle-radius\": 16, \"circle-color\": crimeColor(c), \"circle-opacity\": .25, \"circle-blur\": .6, \"circle-pitch-alignment\": \"map\" } });\n                map.addLayer({ id: \"spot\", type: \"circle\", source: \"spot\", paint: { \"circle-radius\": 9, \"circle-color\": crimeColor(c), \"circle-stroke-color\": \"#ffffff\", \"circle-stroke-width\": 2 } });\n            } else if (segs.length) {\n                var b = new maplibregl.LngLatBounds(segs[0][0], segs[0][0]);\n                segs.forEach(function (coords) { coords.forEach(function (p) { b.extend(p); }); });\n                map.fitBounds(b, { padding: 30, maxZoom: 17.5, duration: 0 });\n            }\n            map.once(\"idle\", done);\n        });\n        map.on(\"error\", function (e) { if (e && (e.tile || e.sourceId)) return; console.warn(\"3D map error\", e && e.error); });\n    });\n}\nfunction reportMapsFailed(why) {\n    console.warn(\"3D map unavailable:\", why);\n    CRIMES.forEach(function (c, i) { var el = document.getElementById(\"map\" + i); if (el && !el.hasChildNodes()) el.innerHTML = \"<div style=\\\"padding:20px;color:#6b7280;font-size:12px\\\">Map preview unavailable in this browser. The PDF will still include the map.</div>\"; });\n    enableActions();\n}\n\n/* ---- PDF image: the 3D map's own canvas ---- */\nfunction snapshot3d(i) {\n    var map = reportMaps[i];\n    if (!map) return Promise.reject(new Error(\"no 3D map\"));\n    return new Promise(function (res) {\n        var grab = function () { try { res(map.getCanvas().toDataURL(\"image/png\")); } catch (e) { res(null); } };\n        if (map.loaded() && !map.isMoving()) grab(); else map.once(\"idle\", grab);\n    }).then(function (url) { if (!url) throw new Error(\"canvas not readable\"); return url; });\n}\n\n/* ---- Fallback only (3D not available): OpenStreetMap tiles, street and marker drawn by hand ---- */\n/* through the SAME projection, so the line sits exactly on the road. */\nvar TILE_URL = \"https://tile.openstreetmap.org/{z}/{x}/{y}.png\";\nfunction loadImage(url) { return new Promise(function (res, rej) { var img = new Image(); img.crossOrigin = \"anonymous\"; img.onload = function () { res(img); }; img.onerror = function () { rej(new Error(\"image\")); }; img.src = url; }); }\nfunction project(lat, lng, z) { var n = 256 * Math.pow(2, z), s = Math.sin(lat * Math.PI / 180); return { x: (lng + 180) / 360 * n, y: (0.5 - Math.log((1 + s) / (1 - s)) / (4 * Math.PI)) * n }; }\nfunction osmSnapshot(c, w, h) {\n    var z = 17, S = 2, lat = +c.lat, lng = +c.lng;\n    var segs = streetSegments(c);\n    if (!lat || !lng) { var first = segs[0] && segs[0][0]; if (!first) return Promise.reject(new Error(\"no location\")); lng = first[0]; lat = first[1]; }\n    var center = project(lat, lng, z), tl = { x: center.x - w / 2, y: center.y - h / 2 };\n    var toPt = function (la, ln) { var p = project(la, ln, z); return [p.x - tl.x, p.y - tl.y]; };\n    var canvas = document.createElement(\"canvas\"); canvas.width = w * S; canvas.height = h * S;\n    var ctx = canvas.getContext(\"2d\"); ctx.scale(S, S); ctx.fillStyle = \"#e5e7eb\"; ctx.fillRect(0, 0, w, h);\n    var n = Math.pow(2, z), loads = [];\n    for (var tx = Math.floor(tl.x / 256); tx <= Math.floor((tl.x + w) / 256); tx++) {\n        for (var ty = Math.floor(tl.y / 256); ty <= Math.floor((tl.y + h) / 256); ty++) {\n            if (ty < 0 || ty >= n) continue;\n            (function (tx, ty) { var wx = ((tx % n) + n) % n; var url = TILE_URL.replace(\"{z}\", z).replace(\"{x}\", wx).replace(\"{y}\", ty);\n                loads.push(loadImage(url).then(function (img) { ctx.drawImage(img, tx * 256 - tl.x, ty * 256 - tl.y, 256, 256); }).catch(function () {})); })(tx, ty);\n        }\n    }\n    return Promise.all(loads).then(function () {\n        ctx.lineCap = \"round\"; ctx.lineJoin = \"round\";\n        [[\"#111111\", 8, .35], [\"#f59e0b\", 4, .95]].forEach(function (st) {\n            segs.forEach(function (coords) {\n                ctx.beginPath(); coords.forEach(function (p, i) { var pt = toPt(p[1], p[0]); if (i) ctx.lineTo(pt[0], pt[1]); else ctx.moveTo(pt[0], pt[1]); });\n                ctx.strokeStyle = st[0]; ctx.lineWidth = st[1]; ctx.globalAlpha = st[2]; ctx.stroke(); ctx.globalAlpha = 1;\n            });\n        });\n        if (c.lat && c.lng) { var pt = toPt(+c.lat, +c.lng); ctx.beginPath(); ctx.arc(pt[0], pt[1], 9, 0, Math.PI * 2); ctx.fillStyle = crimeColor(c); ctx.fill(); ctx.lineWidth = 2; ctx.strokeStyle = \"#fff\"; ctx.stroke(); }\n        return canvas.toDataURL(\"image/png\");\n    });\n}\n\nfunction snapshotCrime(c, i, w, h) {\n    if (!map3dOk) return osmSnapshot(c, w, h);\n    return snapshot3d(i).catch(function (err) { console.warn(\"3D snapshot failed, drawing OpenStreetMap instead:\", err.message); return osmSnapshot(c, w, h); });\n}\n\ndocument.getElementById(\"printReport\").addEventListener(\"click\", function () { setTimeout(function () { window.print(); }, 250); });\nfunction safeName(v) { return String(v || \"report\").replace(/[^a-z0-9]+/gi, \"-\").replace(/^-+|-+$/g, \"\").toLowerCase() || \"report\"; }\nfunction pdfFilename() { if (CRIMES.length === 1) return safeName(CRIMES[0].street) + \"-\" + safeName(CRIMES[0].code) + \".pdf\"; return \"crime-data-report-\" + new Date().toISOString().slice(0, 10) + \".pdf\"; }\n\ndocument.getElementById(\"downloadPdf\").addEventListener(\"click\", function () {\n    var btn = this;\n    if (!window.html2pdf) { alert(\"PDF generator failed to load. Please try again.\"); return; }\n    btn.disabled = true; btn.textContent = \"Preparing maps\u2026\";\n    var mapEl = document.getElementById(\"map0\"), w = Math.round(mapEl.clientWidth || 640), h = Math.round(mapEl.clientHeight || 300);\n    Promise.all(CRIMES.map(function (c, i) { return snapshotCrime(c, i, w, h); })).then(function (images) {\n        var copy = document.getElementById(\"reportContent\").cloneNode(true);\n        copy.id = \"pdfDownloadContent\"; copy.style.cssText = \"width:190mm;background:#fff;padding:0;margin:0;\";\n        /* The map keeps the aspect of the picture it holds - nothing cropped, nothing squashed */\n        copy.querySelectorAll(\".map\").forEach(function (el, i) {\n            el.innerHTML = \"\"; el.style.height = \"auto\"; el.style.aspectRatio = w + \" / \" + h;\n            var image = document.createElement(\"img\"); image.src = images[i]; image.alt = \"Crime location map\";\n            image.style.cssText = \"display:block;width:100%;height:100%;object-fit:contain;\"; el.appendChild(image);\n        });\n        var staging = document.createElement(\"div\"); staging.style.cssText = \"position:fixed;left:0;top:0;z-index:-1;width:190mm;background:#fff;\"; staging.appendChild(copy); document.body.appendChild(staging);\n        btn.textContent = \"Creating PDF\u2026\";\n        return html2pdf().set({\n            margin: [10, 10, 10, 10], filename: pdfFilename(), image: { type: \"jpeg\", quality: .98 },\n            html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: \"mm\", format: \"a4\", orientation: \"portrait\" },\n            /* css mode only: the legacy mode pads the top of every page with empty space */\n            pagebreak: { mode: [\"css\"], before: \".page-break\", avoid: [\".map\", \"table.details\", \"h2\", \".chips\", \".maplabel\", \"header\"] }\n        }).from(copy).save().then(function () { staging.remove(); btn.textContent = \"Download PDF\"; btn.disabled = false; });\n    }).catch(function (error) {\n        console.error(\"PDF map failed:\", error);\n        alert(\"The map image could not be created, so no PDF was downloaded. Please try again.\");\n        btn.textContent = \"Download PDF\"; btn.disabled = false;\n    });\n});\ninitReportMaps();\nsetTimeout(enableActions, 10000);\n";

    function openPdf(codes, reportTitle) {
        const crimes = codes.map(c => allCrimes.find(x => x.code === c)).filter(Boolean);
        if (!crimes.length) { alert('Crime data is still loading — try again in a moment.'); return; }

        // Only the street geometries these crimes need
        const neededStreets = {};
        crimes.forEach(c => {
            const key = (c.street || '').toLowerCase();
            if (key && streetFeatures[key]) neededStreets[key] = streetFeatures[key];
        });

        const w = window.open('', '_blank');
        if (!w) { alert('Pop-up blocked — allow pop-ups for this site to download the PDF.'); return; }

        const detailRow = (label, val) => val
            ? '<tr><td class="lbl">' + label + '</td><td>' + esc(val) + '</td></tr>'
            : '';

        const sections = crimes.map((c, i) => {
            const done = isDone(c.status);
            return '<div class="crime' + (i > 0 ? ' page-break' : '') + '">' +
                '<h2>' + esc(c.title || 'Crime') + ' <span class="code">' + esc(c.code) + '</span></h2>' +
                '<div class="chips">' +
                    '<span class="chip" style="background:' + (CAT_COLORS[c.category] || '#64748b') + ';">' + esc(c.category) + '</span>' +
                    '<span class="chip ' + (done ? 'ok' : 'warn') + '">' + esc(String(c.status || '').replace(/_/g, ' ').toUpperCase()) + '</span>' +
                '</div>' +
                '<table class="details">' +
                    detailRow('Where', c.address || c.street) +
                    detailRow('Date', c.date) +
                    detailRow('Time', c.time ? fmt12h(c.time) : null) +
                    detailRow('Modus operandi', c.modus) +
                    detailRow('Victims', c.victims ? String(c.victims) : null) +
                    detailRow('Suspects', c.suspects ? String(c.suspects) : null) +
                    detailRow('Weather', c.weather) +
                    detailRow('Assigned officer', c.officer) +
                    detailRow('Clearance', c.clearance ? c.clearance + (c.cleared_on ? ' (' + c.cleared_on + ')' : '') : null) +
                '</table>' +
                (c.description ? '<p class="desc">' + esc(c.description) + '</p>' : '') +
                '<div class="map" id="map' + i + '"></div>' +
                '<p class="maplabel"><i></i>Map: ' + esc(c.street || 'location') + ' — the marker is the exact recorded spot of this crime.</p>' +
            '</div>';
        }).join('');

        w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8">' +
            '<title>' + esc(reportTitle || 'Crime Data Report') + '</title>' +
            '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.min.css">' +
            '<style>' +
                'body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;padding:16px 24px 24px;}' +
                '#reportContent{margin:0;padding:0;}' +
                'header{border-bottom:3px solid #1e3a8a;padding:0 0 10px;margin:0 0 14px;}' +
                'header h1{margin:0;font-size:20px;color:#1e3a8a;}' +
                'header p{margin:4px 0 0;font-size:11px;color:#555;}' +
                '.crime{margin:0 0 22px;}' +
                '.page-break{page-break-before:always;break-before:page;margin-top:0;}' +
                'h2{font-size:15px;margin:0 0 6px;}' +
                '.code{font-family:monospace;font-size:11px;color:#666;font-weight:400;}' +
                '.chips{margin-bottom:8px;}' +
                '.chip{display:inline-block;font-size:10px;font-weight:700;color:#fff;padding:2px 9px;border-radius:999px;margin-right:6px;}' +
                '.chip.ok{background:#15803d;}.chip.warn{background:#b45309;}' +
                'table.details{border-collapse:collapse;width:100%;margin-bottom:8px;}' +
                'table.details td{border:1px solid #ddd;padding:5px 8px;font-size:11.5px;}' +
                'table.details td.lbl{width:130px;font-weight:700;background:#f8fafc;color:#374151;}' +
                '.desc{font-size:11.5px;color:#444;margin:0 0 8px;}' +
                '.map{height:300px;border:1px solid #cbd5e1;border-radius:6px;overflow:hidden;background:#e5e7eb;position:relative;}' +
                '.map canvas{border-radius:6px;}' +
                '.maplabel{font-size:10px;color:#666;margin:4px 0 0;}' +
                'footer{margin-top:18px;border-top:1px solid #ddd;padding-top:8px;font-size:10px;color:#777;}' +
                '#pdfActions{position:sticky;top:0;z-index:10;display:flex;justify-content:flex-end;gap:8px;padding:0 0 12px;background:#fff;}' +
                '#pdfActions button{border:0;border-radius:6px;padding:9px 13px;color:#fff;font-weight:700;cursor:pointer;}' +
                '#downloadPdf{background:#6d28d9;}#printReport{background:#1e3a8a;}#pdfActions button:disabled{opacity:.55;cursor:wait;}' +
                '@media print{#pdfActions{display:none;}.map{height:300px;}}' +
            '</style></head><body>' +
            '<div id="pdfActions"><button id="downloadPdf" type="button" disabled>Download PDF</button><button id="printReport" type="button" disabled>Print</button></div>' +
            '<div id="reportContent">' +
            '<header>' +
                '<h1>' + esc(reportTitle || 'Crime Data Report') + '</h1>' +
                '<p>Barangay San Agustin, Quezon City · ' + crimes.length + ' crime record' + (crimes.length === 1 ? '' : 's') +
                ' · Generated ' + new Date().toLocaleString() + '</p>' +
            '</header>' +
            sections +
            '<footer>Generated by the Crime Data Analytics system. The map shows the street of the crime (highlighted) and the exact recorded spot (marker). For official use.</footer>' +
            '</div>' +
            '<scr' + 'ipt src="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.min.js"><\/scr' + 'ipt>' +
            '<scr' + 'ipt src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"><\/scr' + 'ipt>' +
            '<scr' + 'ipt>' +
            'var CRIMES=' + JSON.stringify(crimes) + ';' +
            'var STREETS=' + JSON.stringify(neededStreets) + ';' +
            'var CATC=' + JSON.stringify(CAT_COLORS) + ';' +
            REPORT_SCRIPT +
            '<\/scr' + 'ipt></body></html>');
        w.document.close();
    }

    // ------------------------------------------------------------------ init
    loadStreets();
    loadCrimes();
    loadSavedReports();
})();
</script>
@endpush
