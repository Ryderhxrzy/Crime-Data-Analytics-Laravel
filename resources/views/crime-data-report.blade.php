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
    let hoverMap = null, hoverLayer = null, hideTimer = null;

    function ensureHoverMap() {
        if (hoverMap) return;
        hoverMap = L.map('hoverMap', { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, boxZoom: false, keyboard: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(hoverMap);
    }

    function showHoverMap(crime, rowRect) {
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
            '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">' +
            '<style>' +
                'body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:24px;}' +
                'header{border-bottom:3px solid #1e3a8a;padding-bottom:10px;margin-bottom:18px;}' +
                'header h1{margin:0;font-size:20px;color:#1e3a8a;}' +
                'header p{margin:4px 0 0;font-size:11px;color:#555;}' +
                '.crime{margin-bottom:26px;}' +
                '.page-break{page-break-before:always;}' +
                'h2{font-size:15px;margin:0 0 6px;}' +
                '.code{font-family:monospace;font-size:11px;color:#666;font-weight:400;}' +
                '.chips{margin-bottom:8px;}' +
                '.chip{display:inline-block;font-size:10px;font-weight:700;color:#fff;padding:2px 9px;border-radius:999px;margin-right:6px;}' +
                '.chip.ok{background:#15803d;}.chip.warn{background:#b45309;}' +
                'table.details{border-collapse:collapse;width:100%;margin-bottom:8px;}' +
                'table.details td{border:1px solid #ddd;padding:5px 8px;font-size:11.5px;}' +
                'table.details td.lbl{width:130px;font-weight:700;background:#f8fafc;color:#374151;}' +
                '.desc{font-size:11.5px;color:#444;margin:0 0 8px;}' +
                '.map{height:300px;border:1px solid #cbd5e1;border-radius:6px;}' +
                '.maplabel{font-size:10px;color:#666;margin:4px 0 0;}' +
                'footer{margin-top:18px;border-top:1px solid #ddd;padding-top:8px;font-size:10px;color:#777;}' +
                '@media print{.map{height:300px;}}' +
            '</style></head><body>' +
            '<header>' +
                '<h1>' + esc(reportTitle || 'Crime Data Report') + '</h1>' +
                '<p>Barangay San Agustin, Quezon City · ' + crimes.length + ' crime record' + (crimes.length === 1 ? '' : 's') +
                ' · Generated ' + new Date().toLocaleString() + '</p>' +
            '</header>' +
            sections +
            '<footer>Generated by the Crime Data Analytics system. The map shows the street of the crime (highlighted) and the exact recorded spot (marker). For official use.</footer>' +
            '<scr' + 'ipt src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"><\/scr' + 'ipt>' +
            '<scr' + 'ipt>' +
            'var CRIMES=' + JSON.stringify(crimes) + ';' +
            'var STREETS=' + JSON.stringify(neededStreets) + ';' +
            'var CATC=' + JSON.stringify(CAT_COLORS) + ';' +
            'var pending=CRIMES.length;var printed=false;' +
            'function done(){if(--pending<=0&&!printed){printed=true;setTimeout(function(){window.print();},700);}}' +
            'CRIMES.forEach(function(c,i){' +
                'var m=L.map("map"+i,{zoomControl:false,attributionControl:false,dragging:false,scrollWheelZoom:false});' +
                'var t=L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19});' +
                't.on("load",done);t.addTo(m);' +
                'var g=L.featureGroup();' +
                'var segs=STREETS[(c.street||"").toLowerCase()]||[];' +
                'segs.forEach(function(coords){var ll=coords.map(function(p){return [p[1],p[0]];});' +
                    'L.polyline(ll,{color:"#111",weight:8,opacity:.35}).addTo(g);' +
                    'L.polyline(ll,{color:"#f59e0b",weight:4,opacity:.95}).addTo(g);});' +
                'if(c.lat&&c.lng){L.circleMarker([c.lat,c.lng],{radius:9,color:"#fff",weight:2,fillColor:CATC[c.category]||"#dc2626",fillOpacity:1}).addTo(g)' +
                    '.bindTooltip(c.code,{permanent:true,direction:"top",offset:[0,-8]});}' +
                'g.addTo(m);' +
                'var b=g.getBounds();if(b.isValid()){m.fitBounds(b.pad(0.2),{maxZoom:17});}' +
                'if(c.lat&&c.lng){m.setView([c.lat,c.lng],Math.max(m.getZoom(),16));}' +
            '});' +
            'setTimeout(function(){if(!printed){printed=true;window.print();}},6000);' +
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
