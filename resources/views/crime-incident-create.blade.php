@extends('layouts.app')
@section('title', 'Add Crime Record')

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <!-- The same base map Crime Mapping uses (2D fallback): tiles, zoom limits, San Agustin boundary -->
    <script src="{{ asset('js/crime-map-base.js') }}?v={{ filemtime(public_path('js/crime-map-base.js')) }}"></script>
    <!-- Google Maps (default engine, Hybrid imagery): loaded once via the official bootstrap loader -->
    <script src="{{ asset('js/google-maps-loader.js') }}?v={{ filemtime(public_path('js/google-maps-loader.js')) }}"></script>
    <script src="{{ asset('js/crime-map-google.js') }}?v={{ filemtime(public_path('js/crime-map-google.js')) }}"></script>
    <!-- 3D engine: MapLibre GL with free OpenFreeMap vector tiles (no API key) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.min.js"></script>
    <style>
        #addCrimeMap { height: 520px; width: 100%; border-radius: 12px; z-index: 1; position: relative; overflow: hidden; }
        #addCrimeMap .engine-pane { position: absolute; inset: 0; display: none; }
        #addCrimeMap .engine-pane.on { display: block; }
        .gm-street-label { color: #fff; font-weight: 800; font-size: 13px; text-shadow: 0 0 4px #000, 0 0 8px #000; }
        .gm-tip { background: #111827; color: #fff; border-radius: 8px; padding: 8px 10px; font-size: 11.5px; line-height: 1.45; }
        .map-tools { position: absolute; top: 10px; left: 10px; z-index: 5; display: flex; gap: 6px; }
        .map-tool-btn { background: #fff; border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 10px; font-size: 11.5px; font-weight: 700; color: #374151; cursor: pointer; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
        .map-tool-btn.on { background: #274d4c; color: #fff; border-color: #274d4c; }
        .map-engine-badge { position: absolute; bottom: 10px; left: 10px; z-index: 5; background: rgba(255,255,255,.92); border: 1px solid #e5e7eb; border-radius: 9999px; padding: 3px 9px; font-size: 10.5px; font-weight: 700; color: #374151; }
        .maplibregl-ctrl-attrib { font-size: 10px; }
        .sa-street-tip .maplibregl-popup-content { background: #111827; color: #fff; border-radius: 8px; padding: 8px 10px; font-size: 11.5px; line-height: 1.45; box-shadow: 0 6px 20px rgba(0,0,0,.35); }
        .sa-street-tip .maplibregl-popup-tip { border-top-color: #111827; border-bottom-color: #111827; }
        /* Name label on the isolated barangay (same as Crime Mapping) */
        .brgy-label-selected { background: transparent; border: none; box-shadow: none; color: #123332; font-size: 13px; font-weight: 800; text-shadow: 0 0 4px #fff, 0 0 8px #fff, 0 1px 2px #fff; white-space: nowrap; }
        .brgy-label-selected::before { display: none; }
        .field-label { display: block; font-size: 13px; font-weight: 600; color: #111827; margin-bottom: 4px; }
        .field-input { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; background: #fff; }
        .field-input:focus { outline: none; border-color: #3a6b6a; box-shadow: 0 0 0 3px rgba(58,107,106,.15); }
        .field-hint { font-size: 11.5px; color: #6b7280; margin-top: 4px; }
        .req { color: #dc2626; }
        .step { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 9999px; background: #3a6b6a; color: #fff; font-size: 12px; font-weight: 800; margin-right: 8px; }
        .pin-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border-radius: 9999px; font-size: 11.5px; font-weight: 700; }
        .toggle { position: relative; width: 40px; height: 22px; border-radius: 9999px; background: #d1d5db; transition: background .2s; cursor: pointer; flex-shrink: 0; }
        .toggle::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 9999px; background: #fff; transition: left .2s; }
        .toggle.on { background: #3a6b6a; }
        .toggle.on::after { left: 21px; }
        .crime-marker { width: 30px; height: 30px; border-radius: 50% 50% 50% 0; background: #dc2626; border: 3px solid #fff; box-shadow: 0 3px 10px rgba(0,0,0,.35); transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; }
        .crime-marker i { transform: rotate(45deg); color: #fff; font-size: 12px; }
        .cat-dot { width: 10px; height: 10px; border-radius: 9999px; display: inline-block; }
    </style>
@endpush

@section('content')
<div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
    <!-- Page Header -->
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                    <i class="fas fa-plus-circle mr-3" style="color: #274d4c;"></i>Add Crime Record
                </h1>
                <p class="text-gray-600 mt-1 text-sm lg:text-base">
                    Enter a crime manually. Pick the street, then drag the pin along it to the exact spot.
                </p>
            </div>
            <a href="{{ route('mapping') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 text-sm font-semibold">
                <i class="fas fa-map"></i>Open Crime Mapping
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 flex items-start gap-3">
            <i class="fas fa-circle-check mt-0.5"></i>
            <div>
                <p class="font-semibold">{{ session('success') }}</p>
                @if (session('saved_incident'))
                    @php($si = session('saved_incident'))
                    <p class="mt-1 text-xs text-green-700">{{ $si['code'] }} &middot; {{ $si['category'] }} &middot; {{ $si['title'] }} &middot; {{ $si['street'] }}</p>
                @endif
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <i class="fas fa-triangle-exclamation mr-2"></i>{{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('crime-incident.store') }}" id="addCrimeForm">
        @csrf
        <input type="hidden" name="placement" id="placement" value="{{ old('placement', 'street') }}">

        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
            <!-- Left: details -->
            <div class="xl:col-span-2 space-y-6">
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center"><span class="step">1</span>What happened</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="field-label">Crime category <span class="req">*</span></label>
                            <select name="crime_category_id" id="categorySelect" class="field-input" required>
                                <option value="">Select a category</option>
                                @foreach ($categories as $c)
                                    <option value="{{ $c['id'] }}" data-color="{{ $c['color'] }}" {{ (string) old('crime_category_id') === (string) $c['id'] ? 'selected' : '' }}>{{ $c['name'] }}</option>
                                @endforeach
                            </select>
                            <div class="flex flex-wrap gap-1.5 mt-2" id="categoryChips">
                                @foreach ($categories->take(9) as $c)
                                    <button type="button" class="pin-chip border border-gray-200 bg-white text-gray-700 hover:bg-gray-50" data-cat="{{ $c['id'] }}">
                                        <span class="cat-dot" style="background: {{ $c['color'] }}"></span>{{ $c['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div id="streetDetectedWrap" class="rounded-lg border border-alertara-200 bg-alertara-50/40 p-3">
                            <label class="field-label"><i class="fas fa-road mr-1 text-alertara-700"></i>Street <span class="req">*</span> <span class="text-[10px] font-semibold text-gray-500 ml-1">from the pin coordinates</span></label>
                            <input type="text" id="streetDetected" class="field-input bg-white font-semibold text-gray-900" readonly tabindex="-1"
                                   value="{{ old('street') }}" placeholder="Place the pin on the map">
                            <p class="field-hint" id="streetDetectedHint">Move the pin on the map; the nearest San Agustin street fills in automatically.</p>
                        </div>
                        <div>
                            <label class="field-label">Title <span class="req">*</span></label>
                            <input type="text" name="incident_title" class="field-input" required maxlength="255" value="{{ old('incident_title') }}" placeholder="e.g. Cellphone snatching near the corner store">
                        </div>
                        <div>
                            <label class="field-label">Description <span class="req">*</span></label>
                            <textarea name="incident_description" class="field-input" rows="4" required maxlength="5000" placeholder="What was reported, by whom, and what was taken or damaged.">{{ old('incident_description') }}</textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="field-label">Date <span class="req">*</span></label>
                                <input type="date" name="incident_date" class="field-input" required max="{{ now()->toDateString() }}" value="{{ old('incident_date', now()->toDateString()) }}">
                            </div>
                            <div>
                                <label class="field-label">Time <span class="req">*</span></label>
                                <input type="time" name="incident_time" class="field-input" required value="{{ old('incident_time', now()->format('H:i')) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center"><span class="step">3</span>Case details</h2>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="field-label">Status <span class="req">*</span></label>
                                <select name="status" class="field-input" required>
                                    @foreach ($statuses as $st)
                                        <option value="{{ $st }}" {{ old('status', 'reported') === $st ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $st)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Clearance <span class="req">*</span></label>
                                <select name="clearance_status" id="clearanceSelect" class="field-input" required>
                                    <option value="uncleared" {{ old('clearance_status', 'uncleared') === 'uncleared' ? 'selected' : '' }}>Uncleared</option>
                                    <option value="cleared" {{ old('clearance_status') === 'cleared' ? 'selected' : '' }}>Cleared</option>
                                </select>
                            </div>
                        </div>
                        <div id="clearanceDateWrap" class="hidden">
                            <label class="field-label">Clearance date</label>
                            <input type="date" name="clearance_date" class="field-input" max="{{ now()->toDateString() }}" value="{{ old('clearance_date') }}">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="field-label">Victims</label>
                                <input type="number" name="victim_count" class="field-input" min="0" max="999" value="{{ old('victim_count', 1) }}">
                            </div>
                            <div>
                                <label class="field-label">Suspects</label>
                                <input type="number" name="suspect_count" class="field-input" min="0" max="999" value="{{ old('suspect_count', 1) }}">
                            </div>
                        </div>
                        <div>
                            <label class="field-label">Modus operandi</label>
                            <input type="text" name="modus_operandi" class="field-input" maxlength="2000" value="{{ old('modus_operandi') }}" placeholder="e.g. Riding-in-tandem, grabbed the bag from behind">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="field-label">Weather</label>
                                <select name="weather_condition" class="field-input">
                                    <option value="">Not recorded</option>
                                    @foreach ($weather as $w)
                                        <option value="{{ $w }}" {{ old('weather_condition') === $w ? 'selected' : '' }}>{{ $w }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Assigned officer</label>
                                <input type="text" name="assigned_officer" class="field-input" maxlength="150" value="{{ old('assigned_officer') }}" placeholder="e.g. PO2 Reyes">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: location -->
            <div class="xl:col-span-3 space-y-6">
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center"><span class="step">2</span>Where it happened</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="field-label">Barangay <span class="req">*</span></label>
                            <select name="barangay_id" id="barangaySelect" class="field-input" required>
                                @foreach ($barangays as $b)
                                    <option value="{{ $b['id'] }}" data-name="{{ $b['name'] }}" {{ (string) old('barangay_id', $defaultBarangay) === (string) $b['id'] ? 'selected' : '' }}>{{ $b['name'] }}</option>
                                @endforeach
                            </select>
                            <p class="field-hint">Defaults to San Agustin, where every record in this system sits.</p>
                        </div>
                        <div id="streetJumpWrap">
                            <label class="field-label">Jump to a street <span class="text-[10px] font-semibold text-gray-500 ml-1">filter</span></label>
                            <select id="streetJump" class="field-input">
                                <option value="">Choose a street to move the pin there</option>
                                <optgroup label="Streets with recorded crimes">
                                    @foreach ($activeStreets as $st)
                                        <option value="{{ $st['name'] }}">{{ $st['name'] }} ({{ $st['count'] }} recorded)</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Other streets (no crime yet)">
                                    @foreach ($otherStreets as $st)
                                        <option value="{{ $st['name'] }}">{{ $st['name'] }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                            <p class="field-hint">Moves the pin to that street and zooms in. The saved street is always the one under the pin.</p>
                        </div>
                        <div id="streetTextWrap" class="hidden">
                            <label class="field-label">Street <span class="req">*</span></label>
                            <input type="text" id="streetText" class="field-input" maxlength="150" placeholder="Street name" value="{{ old('street') }}">
                            <p class="field-hint">Outside San Agustin the street is typed and the pin can be placed anywhere.</p>
                        </div>
                        <input type="hidden" name="street" id="streetInput" value="{{ old('street') }}">
                        <script type="application/json" id="activeStreetsJson">@json(collect($activeStreets)->pluck('name')->values())</script>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="pin-chip bg-red-50 text-red-700 border border-red-200"><i class="fas fa-location-dot"></i><span id="pinStreetLabel">No street detected yet</span></span>
                            <span class="pin-chip bg-gray-100 text-gray-700" id="pinModeLabel"><i class="fas fa-road"></i>Pin snaps to the nearest street</span>
                        </div>
                        <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer select-none">
                            <span>Free placement</span>
                            <span class="toggle" id="freeToggle" role="switch" aria-checked="false"></span>
                        </label>
                    </div>

                    <div style="position: relative;">
                        <div id="addCrimeMap">
                            <div id="addCrimeMapGoogle" class="engine-pane"></div>
                            <div id="addCrimeMap3d" class="engine-pane"></div>
                            <div id="addCrimeMap2d" class="engine-pane"></div>
                        </div>
                        <div class="map-tools" style="left: auto; right: 10px; top: 56px;">
                            <button type="button" class="map-tool-btn engine-btn" data-engine="google" title="Google Maps (satellite + roads)"><i class="fab fa-google mr-1"></i>Google</button>
                            <button type="button" class="map-tool-btn engine-btn" data-engine="3d" title="3D map"><i class="fas fa-cube mr-1"></i>3D</button>
                            <button type="button" class="map-tool-btn engine-btn" data-engine="2d" title="Classic 2D map"><i class="fas fa-map mr-1"></i>2D</button>
                            <button type="button" id="tiltToggle" class="map-tool-btn on hidden"><i class="fas fa-cube mr-1"></i>3D view</button>
                        </div>
                        <span id="mapEngineBadge" class="map-engine-badge"><i class="fas fa-spinner fa-spin mr-1"></i>Loading map</span>
                    </div>
                    <p id="boundaryNote" class="hidden mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        <i class="fas fa-triangle-exclamation mr-1"></i>The pin is outside the Barangay San Agustin outline. Records outside the coverage area will not group with the San Agustin streets.
                    </p>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                        <div>
                            <label class="field-label">Latitude</label>
                            <input type="text" name="latitude" id="latInput" class="field-input font-mono text-xs" required value="{{ old('latitude') }}" readonly>
                        </div>
                        <div>
                            <label class="field-label">Longitude</label>
                            <input type="text" name="longitude" id="lngInput" class="field-input font-mono text-xs" required value="{{ old('longitude') }}" readonly>
                        </div>
                        <div class="sm:col-span-2 flex items-end">
                            <button type="button" id="recenterBtn" class="px-3 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50 w-full">
                                <i class="fas fa-crosshairs mr-1"></i>Snap the pin to the nearest street
                            </button>
                        </div>
                    </div>
                    <p class="field-hint mt-2"><i class="fas fa-hand-pointer mr-1"></i>Drag the pin or click the map. While the pin is locked to the street it slides along that street only. Streets are coloured by their crime level, the same scale as Crime Mapping; hover one for its count. Pick a street and the map shows that street alone, in yellow. The teal outline is Barangay San Agustin. In 3D, drag with the right mouse button (or hold Ctrl) to tilt and rotate; buildings rise as you zoom in.</p>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs text-gray-500"><i class="fas fa-shield-halved mr-1"></i>Saved records are audited and appear on Crime Mapping, hotspots, trends and alerts immediately.</p>
                    <div class="flex gap-2">
                        <a href="{{ route('crime-incident.create') }}" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                        <button type="submit" id="submitBtn" class="px-5 py-2.5 bg-alertara-700 text-white rounded-lg text-sm font-semibold hover:bg-alertara-800 flex items-center gap-2">
                            <i class="fas fa-save"></i>Save crime record
                        </button>
                    </div>
                </div>

                @if ($recentManual->isNotEmpty())
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-200 bg-gray-50 text-sm font-bold text-gray-900">
                            <i class="fas fa-clock-rotate-left mr-2 text-alertara-700"></i>Recently added by hand
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($recentManual as $r)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $r->incident_code }}</td>
                                        <td class="px-5 py-2.5 text-gray-900 font-medium">{{ $r->incident_title }}</td>
                                        <td class="px-5 py-2.5 text-gray-600 text-xs">{{ $r->category_name }}</td>
                                        <td class="px-5 py-2.5 text-gray-600 text-xs">{{ trim(explode(',', (string) $r->address_details)[0]) }}</td>
                                        <td class="px-5 py-2.5 text-gray-500 text-xs text-right whitespace-nowrap">{{ $r->incident_date?->format('M j, Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </form>

    @include('partials.san-agustin-streets')
</div>
@endsection

@push('scripts')
<script>
(function () {
    const STREETS_URL = '/data/san_agustin_streets.geojson';
    const BARANGAYS_URL = '/qc_barangays.geojson';
    const SA_CODE = '137404095';
    const SA_CENTER = [14.7292, 121.0385];   // [lat, lng]
    // Free vector tiles, no API key (OpenFreeMap, OpenMapTiles schema)
    const VECTOR_STYLE = 'https://tiles.openfreemap.org/styles/liberty';
    const STREET_STATS_URL = @json(route('pattern-detection.street-stats'));
    // Same crime-level scale as Crime Mapping's street layer
    const SEVERITY = [
        { min: 15, label: 'Critical', color: '#7f1d1d' },
        { min: 10, label: 'High',     color: '#dc2626' },
        { min: 5,  label: 'Moderate', color: '#f97316' },
        { min: 1,  label: 'Low',      color: '#ca8a04' },
        { min: 0,  label: 'Cleared',  color: '#16a34a' },
    ];
    const severityFor = n => SEVERITY.find(x => n >= x.min) || SEVERITY[SEVERITY.length - 1];
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let streetStats = {};   // name -> {count, top_category, peak_hours}

    const streetDetected = document.getElementById('streetDetected');
    const streetDetectedHint = document.getElementById('streetDetectedHint');
    const streetText = document.getElementById('streetText');
    let detectedStreet = streetDetected.value.trim();   // filled from the pin's coordinates
    const NEAR_STREET_METERS = 120;                     // beyond this the pin is "not on a street"
    const streetInput = document.getElementById('streetInput');
    const barangaySelect = document.getElementById('barangaySelect');
    const latInput = document.getElementById('latInput');
    const lngInput = document.getElementById('lngInput');
    const placement = document.getElementById('placement');
    const freeToggle = document.getElementById('freeToggle');
    const pinStreetLabel = document.getElementById('pinStreetLabel');
    const pinModeLabel = document.getElementById('pinModeLabel');
    const engineBadge = document.getElementById('mapEngineBadge');

    let streetLines = {};   // lower name -> [[ [lat,lng], ... ], ...]
    let streetGeo = null;   // raw geojson, reused by the 3D engine
    let free = placement.value === 'free';
    let saBounds = null;    // {minLat,maxLat,minLng,maxLng}
    let engine = null;      // the active map engine (3D MapLibre or 2D Leaflet)

    const activeStreets = new Set((JSON.parse(document.getElementById('activeStreetsJson').textContent || '[]')).map(n => String(n).toLowerCase()));

    // ------------------------------------------------------------------
    // Form helpers shared by both engines
    // ------------------------------------------------------------------
    function isSanAgustin() {
        const opt = barangaySelect.options[barangaySelect.selectedIndex];
        return (opt?.dataset.name || '').trim().toLowerCase() === 'san agustin';
    }

    function currentStreet() {
        return isSanAgustin() ? detectedStreet : streetText.value.trim();
    }

    function setCoords(lat, lng) {
        latInput.value = Number(lat).toFixed(8);
        lngInput.value = Number(lng).toFixed(8);
    }

    // Nearest street to a point: name, distance in metres and the closest
    // point on it. Longitude is scaled by cos(latitude) so distances compare.
    function nearestStreet(lat, lng) {
        const kx = Math.cos(lat * Math.PI / 180);
        let best = null, bestD = Infinity;
        Object.keys(streetLines).forEach(key => {
            streetLines[key].forEach(pts => {
                if (pts.length === 1) {
                    const d = (pts[0][0] - lat) ** 2 + ((pts[0][1] - lng) * kx) ** 2;
                    if (d < bestD) { bestD = d; best = { key: key, point: pts[0] }; }
                    return;
                }
                for (let i = 0; i < pts.length - 1; i++) {
                    const ax = pts[i][1] * kx, ay = pts[i][0], bx = pts[i + 1][1] * kx, by = pts[i + 1][0];
                    const px = lng * kx, py = lat;
                    const dx = bx - ax, dy = by - ay, len2 = dx * dx + dy * dy;
                    const t = len2 > 0 ? Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / len2)) : 0;
                    const cx = ax + t * dx, cy = ay + t * dy;
                    const d = (px - cx) ** 2 + (py - cy) ** 2;
                    if (d < bestD) { bestD = d; best = { key: key, point: [cy, cx / kx] }; }
                }
            });
        });
        if (!best) return null;
        return { name: streetNames[best.key] || best.key, point: best.point, meters: Math.sqrt(bestD) * 111320 };
    }
    const streetNames = {};   // lower -> display name

    // Nearest point on the nearest street
    function snap(lat, lng) {
        const n = nearestStreet(lat, lng);
        return n ? n.point : [lat, lng];
    }

    function streetMidpoint(name) {
        const lines = streetLines[name.toLowerCase()];
        if (!lines || !lines.length) return null;
        let longest = lines[0], bestLen = -1;
        lines.forEach(pts => {
            let len = 0;
            for (let i = 0; i < pts.length - 1; i++) len += Math.hypot(pts[i + 1][0] - pts[i][0], pts[i + 1][1] - pts[i][1]);
            if (len > bestLen) { bestLen = len; longest = pts; }
        });
        if (longest.length === 1) return longest[0];
        let total = 0; const seg = [];
        for (let i = 0; i < longest.length - 1; i++) { const l = Math.hypot(longest[i + 1][0] - longest[i][0], longest[i + 1][1] - longest[i][1]); seg.push(l); total += l; }
        let half = total / 2;
        for (let i = 0; i < seg.length; i++) {
            if (half <= seg[i]) { const t = seg[i] > 0 ? half / seg[i] : 0; return [longest[i][0] + (longest[i + 1][0] - longest[i][0]) * t, longest[i][1] + (longest[i + 1][1] - longest[i][1]) * t]; }
            half -= seg[i];
        }
        return longest[longest.length - 1];
    }

    function streetBounds(name) {
        const lines = streetLines[name.toLowerCase()];
        if (!lines || !lines.length) return null;
        const b = { minLat: Infinity, maxLat: -Infinity, minLng: Infinity, maxLng: -Infinity };
        lines.forEach(pts => pts.forEach(p => {
            b.minLat = Math.min(b.minLat, p[0]); b.maxLat = Math.max(b.maxLat, p[0]);
            b.minLng = Math.min(b.minLng, p[1]); b.maxLng = Math.max(b.maxLng, p[1]);
        }));
        return b;
    }

    function boundsOfGeometry(geom, b) {
        b = b || { minLat: Infinity, maxLat: -Infinity, minLng: Infinity, maxLng: -Infinity };
        const walk = c => {
            if (typeof c[0] === 'number') {
                b.minLng = Math.min(b.minLng, c[0]); b.maxLng = Math.max(b.maxLng, c[0]);
                b.minLat = Math.min(b.minLat, c[1]); b.maxLat = Math.max(b.maxLat, c[1]);
            } else c.forEach(walk);
        };
        if (geom && geom.coordinates) walk(geom.coordinates);
        return b;
    }

    // Free placement can wander past the teal outline; say so rather than block it
    function warnOutsideBoundary(lat, lng) {
        const note = document.getElementById('boundaryNote');
        if (!note) return;
        const outside = saBounds && isSanAgustin() &&
            (lat < saBounds.minLat || lat > saBounds.maxLat || lng < saBounds.minLng || lng > saBounds.maxLng);
        note.classList.toggle('hidden', !outside);
    }

    // The street is read from the pin: nearest San Agustin street to the
    // coordinates, shown read-only on the form. Too far from any street
    // leaves it blank so the record cannot be saved off-street.
    function detectAt(lat, lng) {
        if (!isSanAgustin()) return;
        const n = nearestStreet(lat, lng);
        const ok = n && n.meters <= NEAR_STREET_METERS;
        detectedStreet = ok ? n.name : '';
        streetDetected.value = detectedStreet;
        const jump = document.getElementById('streetJump');
        if (jump && jump.value !== detectedStreet) jump.value = detectedStreet;
        streetInput.value = detectedStreet;
        pinStreetLabel.textContent = detectedStreet || 'No street detected yet';
        if (ok) {
            streetDetectedHint.innerHTML = '<i class="fas fa-circle-check mr-1 text-emerald-600"></i>' + esc(n.name) + ' is ' + Math.round(n.meters) + ' m from the pin' + (activeStreets.has(n.name.toLowerCase()) ? ' · has recorded crimes' : ' · no crime recorded yet');
        } else if (n) {
            streetDetectedHint.innerHTML = '<i class="fas fa-triangle-exclamation mr-1 text-amber-600"></i>Nearest street is ' + esc(n.name) + ', ' + Math.round(n.meters) + ' m away. Move the pin closer to a street.';
        } else {
            streetDetectedHint.textContent = 'Move the pin on the map; the nearest San Agustin street fills in automatically.';
        }
        engine.highlightStreet(detectedStreet || null);
    }

    // The pin was moved by the user (drag or click): snap unless free, then record
    function userPlaced(lat, lng) {
        const s = free || !isSanAgustin() ? [lat, lng] : snap(lat, lng);
        engine.setPin(s[0], s[1], false);
        setCoords(s[0], s[1]);
        warnOutsideBoundary(s[0], s[1]);
        detectAt(s[0], s[1]);
    }

    function placePin(lat, lng, pan) {
        engine.setPin(lat, lng, pan);
        setCoords(lat, lng);
        detectAt(lat, lng);
    }

    function setFree(on) {
        free = on;
        placement.value = on ? 'free' : 'street';
        freeToggle.classList.toggle('on', on);
        freeToggle.setAttribute('aria-checked', on ? 'true' : 'false');
        pinModeLabel.innerHTML = on
            ? '<i class="fas fa-arrows-up-down-left-right"></i>Pin can go anywhere'
            : '<i class="fas fa-road"></i>Pin snaps to the nearest street';
        pinModeLabel.className = 'pin-chip ' + (on ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700');
        if (!on && isSanAgustin() && latInput.value) {
            const s = snap(+latInput.value, +lngInput.value); placePin(s[0], s[1], false);
        }
    }

    function onBarangayChange() {
        const sa = isSanAgustin();
        document.getElementById('streetDetectedWrap').classList.toggle('hidden', !sa);
        document.getElementById('streetJumpWrap').classList.toggle('hidden', !sa);
        document.getElementById('streetTextWrap').classList.toggle('hidden', sa);
        streetText.required = !sa;
        engine.setStreetsVisible(sa);
        if (!sa) {
            setFree(true);
            streetInput.value = streetText.value.trim();
            pinStreetLabel.textContent = streetInput.value || 'No street chosen';
            engine.highlightStreet(null);
        } else {
            setFree(false);
            if (latInput.value && lngInput.value) userPlaced(+latInput.value, +lngInput.value);
            else if (Object.keys(streetLines).length) { const s = snap(SA_CENTER[0], SA_CENTER[1]); placePin(s[0], s[1], true); }
        }
    }

    // Every street, with its crime count and colour attached as properties
    function colouredStreetGeo() {
        return {
            type: 'FeatureCollection',
            features: ((streetGeo && streetGeo.features) || []).map(f => {
                const name = String((f.properties || {}).name || '').trim();
                const st = streetStats[name];
                const count = st ? st.count : 0;
                const sev = severityFor(count);
                return { type: 'Feature', geometry: f.geometry, properties: {
                    name: name, count: count, color: sev.color, level: sev.label,
                    active: activeStreets.has(name.toLowerCase()) ? 1 : 0,
                    top: st && st.top_category ? st.top_category : '',
                    peaks: st && st.peak_hours ? st.peak_hours.join(', ') : ''
                } };
            })
        };
    }

    function streetTipHtml(pr) {
        const count = +pr.count || 0;
        return '<div style="font-weight:700;margin-bottom:2px;">' + esc(pr.name) + '</div>' +
            '<div style="margin-bottom:3px;"><span style="display:inline-block;width:28px;height:5px;border-radius:3px;background:' + esc(pr.color) + ';vertical-align:middle;"></span>' +
            ' <span style="font-weight:700;color:' + esc(pr.color) + ';">' + esc(pr.level) + '</span></div>' +
            (count > 0
                ? '<div>' + count + ' crime' + (count === 1 ? '' : 's') + (pr.top ? ' · mostly ' + esc(pr.top) : '') + '</div>' +
                  (pr.peaks ? '<div style="color:#c4b5fd;">Peak hours: ' + esc(pr.peaks) + '</div>' : '')
                : '<div>No recorded crimes — cleared</div>') +
            '<div style="margin-top:3px;color:#93c5fd;font-weight:600;"><i class="fas fa-hand-pointer"></i> Click to pick this street</div>';
    }

    // ------------------------------------------------------------------
    // 3D engine: MapLibre GL (vector tiles, tilt, extruded buildings)
    // ------------------------------------------------------------------
    function create3dEngine(onFail) {
        const map = new maplibregl.Map({
            container: 'addCrimeMap3d',
            style: VECTOR_STYLE,
            center: [SA_CENTER[1], SA_CENTER[0]],
            zoom: 16.2,
            pitch: 58,
            bearing: -18,
            minZoom: 12,
            maxZoom: 19.5,
            attributionControl: { compact: true },
            antialias: true,
        });
        let failed = false, loaded = false;
        const fail = err => { if (failed || loaded) return; failed = true; console.warn('3D map unavailable:', err); try { map.remove(); } catch (e) {} onFail(); };
        map.on('error', e => { if (!loaded) fail(e && e.error ? e.error.message : e); });
        setTimeout(() => { if (!loaded) fail('style did not load in time'); }, 12000);

        map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');

        // 3D / 2D toggle
        const tiltBtn = document.getElementById('tiltToggle');
        let tilted = true;
        const setTilt = on => {
            tilted = on;
            map.easeTo({ pitch: on ? 58 : 0, bearing: on ? -18 : 0, duration: 700 });
            tiltBtn.innerHTML = on ? '<i class="fas fa-cube mr-1"></i>3D view' : '<i class="fas fa-map mr-1"></i>2D view';
            tiltBtn.classList.toggle('on', on);
        };
        tiltBtn.addEventListener('click', () => setTilt(!tilted));
        tiltBtn.classList.remove('hidden');

        // Pin
        const el = document.createElement('div');
        el.innerHTML = '<div class="crime-marker"><i class="fas fa-exclamation"></i></div>';
        el.style.width = '30px'; el.style.height = '30px';
        const marker = new maplibregl.Marker({ element: el, draggable: true, anchor: 'bottom' });
        let pinOnMap = false;
        marker.on('dragend', () => { const p = marker.getLngLat(); userPlaced(p.lat, p.lng); });

        map.on('click', e => {
            userPlaced(e.lngLat.lat, e.lngLat.lng);
        });

        map.on('load', () => {
            loaded = true;
            if (engineBadge) engineBadge.innerHTML = '<i class="fas fa-cube mr-1"></i>3D map';

            // Extruded buildings, unless the style already draws them
            const has3d = map.getStyle().layers.some(l => l.type === 'fill-extrusion');
            if (!has3d && map.getSource('openmaptiles')) {
                const labelLayer = map.getStyle().layers.find(l => l.type === 'symbol' && l.layout && l.layout['text-field']);
                map.addLayer({
                    id: 'sa-buildings-3d', type: 'fill-extrusion', source: 'openmaptiles', 'source-layer': 'building', minzoom: 14,
                    paint: {
                        'fill-extrusion-color': ['interpolate', ['linear'], ['coalesce', ['get', 'render_height'], 6], 0, '#e8edf0', 20, '#cfd8dc', 60, '#b0bec5'],
                        'fill-extrusion-height': ['interpolate', ['linear'], ['zoom'], 14, 0, 15.5, ['coalesce', ['get', 'render_height'], 6]],
                        'fill-extrusion-base': ['coalesce', ['get', 'render_min_height'], 0],
                        'fill-extrusion-opacity': 0.82,
                    }
                }, labelLayer ? labelLayer.id : undefined);
            }

            // Barangay boundaries: neighbours faint, San Agustin in brand teal
            fetch(BARANGAYS_URL + '?t=' + Date.now()).then(r => r.json()).then(geo => {
                map.addSource('brgys', { type: 'geojson', data: geo });
                map.addLayer({ id: 'brgy-fill', type: 'fill', source: 'brgys', paint: { 'fill-color': ['case', ['==', ['to-string', ['get', 'code']], SA_CODE], '#bfe5de', '#f2f9f8'], 'fill-opacity': ['case', ['==', ['to-string', ['get', 'code']], SA_CODE], 0.18, 0.10] } }, map.getLayer('sa-buildings-3d') ? 'sa-buildings-3d' : (map.getLayer('building-3d') ? 'building-3d' : undefined));
                map.addLayer({ id: 'brgy-line', type: 'line', source: 'brgys', paint: { 'line-color': ['case', ['==', ['to-string', ['get', 'code']], SA_CODE], '#274d4c', '#8fb3b0'], 'line-width': ['case', ['==', ['to-string', ['get', 'code']], SA_CODE], 3, 0.8], 'line-opacity': ['case', ['==', ['to-string', ['get', 'code']], SA_CODE], 1, 0.7] } });
                const sa = (geo.features || []).find(f => String((f.properties || {}).code || '') === SA_CODE);
                if (sa) {
                    saBounds = boundsOfGeometry(sa.geometry);
                    const label = document.createElement('div'); label.className = 'brgy-label-selected'; label.textContent = 'San Agustin';
                    new maplibregl.Marker({ element: label, anchor: 'center' }).setLngLat([(saBounds.minLng + saBounds.maxLng) / 2, (saBounds.minLat + saBounds.maxLat) / 2]).addTo(map);
                    const padLat = (saBounds.maxLat - saBounds.minLat) * 0.8, padLng = (saBounds.maxLng - saBounds.minLng) * 0.8;
                    map.setMaxBounds([[saBounds.minLng - padLng, saBounds.minLat - padLat], [saBounds.maxLng + padLng, saBounds.maxLat + padLat]]);
                    if (!pinOnMap) map.fitBounds([[saBounds.minLng, saBounds.minLat], [saBounds.maxLng, saBounds.maxLat]], { padding: 24, pitch: 58, bearing: -18, duration: 0 });
                }
            }).catch(err => console.warn('Barangay boundaries not loaded:', err));

            // Streets: active (with records) stronger, selected street on top
            if (streetGeo) addStreets();
        });

        function addStreets() {
            if (!map.getSource('sa-streets')) {
                // Every street, coloured by crime level. Once a street is picked
                // the filters below leave only that street on the map.
                map.addSource('sa-streets', { type: 'geojson', data: colouredStreetGeo() });
                map.addLayer({ id: 'sa-streets-casing', type: 'line', source: 'sa-streets', layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': '#1e293b', 'line-width': ['case', ['==', ['get', 'active'], 1], 6, 4], 'line-opacity': 0.45 } });
                map.addLayer({ id: 'sa-streets-line', type: 'line', source: 'sa-streets', layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': ['get', 'color'], 'line-width': ['case', ['==', ['get', 'active'], 1], 3, 2], 'line-opacity': 0.95 } });
                map.addLayer({ id: 'sa-streets-hover', type: 'line', source: 'sa-streets', filter: ['==', ['get', 'name'], '__none__'], layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': ['get', 'color'], 'line-width': 6, 'line-opacity': 1 } });
                map.addLayer({ id: 'sa-streets-selected', type: 'line', source: 'sa-streets', filter: ['==', ['get', 'name'], '__none__'], layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': '#111827', 'line-width': 8, 'line-opacity': 0.95 } });
                map.addLayer({ id: 'sa-streets-selected-inner', type: 'line', source: 'sa-streets', filter: ['==', ['get', 'name'], '__none__'], layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': '#facc15', 'line-width': 4, 'line-opacity': 1 } });
                map.addLayer({ id: 'sa-streets-labels', type: 'symbol', source: 'sa-streets', minzoom: 15.5, layout: { 'symbol-placement': 'line', 'text-field': ['get', 'name'], 'text-size': 11, 'text-font': ['Noto Sans Regular'] }, paint: { 'text-color': '#111827', 'text-halo-color': '#ffffff', 'text-halo-width': 1.6 } });
                // Hover: thicker line + the same tooltip Crime Mapping shows
                const tip = new maplibregl.Popup({ closeButton: false, closeOnClick: false, offset: 12, className: 'sa-street-tip' });
                map.on('mousemove', 'sa-streets-line', e => {
                    const f = e.features && e.features[0];
                    if (!f) return;
                    map.getCanvas().style.cursor = 'pointer';
                    map.setFilter('sa-streets-hover', ['==', ['get', 'name'], f.properties.name]);
                    tip.setLngLat(e.lngLat).setHTML(streetTipHtml(f.properties)).addTo(map);
                });
                map.on('mouseleave', 'sa-streets-line', () => {
                    map.getCanvas().style.cursor = '';
                    map.setFilter('sa-streets-hover', ['==', ['get', 'name'], '__none__']);
                    tip.remove();
                });
                // Clicking an active street picks it in the form
                map.on('click', 'sa-streets-line', e => {
                    if (!isSanAgustin()) return;
                    userPlaced(e.lngLat.lat, e.lngLat.lng);
                });
            } else {
                map.getSource('sa-streets').setData(colouredStreetGeo());
            }
            if (pendingHighlight !== null) highlightStreet(pendingHighlight);
        }
        let pendingHighlight = null;
        function highlightStreet(name) {
            if (!map.getLayer('sa-streets-selected')) { pendingHighlight = name; return; }
            const only = ['==', ['get', 'name'], name || '__none__'];
            // Every street stays visible; the detected one is highlighted on top
            const shown = ['!=', ['get', 'name'], '__none__'];
            ['sa-streets-casing', 'sa-streets-line', 'sa-streets-labels'].forEach(id => map.setFilter(id, shown));
            map.setFilter('sa-streets-selected', only);
            map.setFilter('sa-streets-selected-inner', only);
        }

        return {
            kind: '3d',
            ready: () => { if (loaded && streetGeo) addStreets(); },
            setPin(lat, lng, pan) {
                marker.setLngLat([lng, lat]);
                if (!pinOnMap) { marker.addTo(map); pinOnMap = true; }
                if (pan) map.easeTo({ center: [lng, lat], duration: 500 });
            },
            highlightStreet: highlightStreet,
            fitStreet(name) {
                const b = streetBounds(name);
                if (!b) return;
                map.fitBounds([[b.minLng, b.minLat], [b.maxLng, b.maxLat]], { padding: 90, maxZoom: 18, pitch: tilted ? 58 : 0, bearing: tilted ? -18 : 0, duration: 800 });
            },
            setStreetsVisible(on) {
                ['sa-streets-casing', 'sa-streets-line', 'sa-streets-selected', 'sa-streets-selected-inner', 'sa-streets-labels'].forEach(id => {
                    if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', on ? 'visible' : 'none');
                });
            },
            resize: () => map.resize(),
        };
    }

    // ------------------------------------------------------------------
    // Google Maps engine (default): Hybrid imagery + roads + street names.
    // Same behaviour as the other engines: streets coloured by crime level
    // with hover, the picked street alone, a draggable pin that snaps.
    // ------------------------------------------------------------------
    function createGoogleEngine(onFail) {
        const el = document.getElementById('addCrimeMapGoogle');
        // The map gets its own child so the Street View inset can shrink it
        // while the panorama fills the pane (same as Crime Mapping).
        const mapEl = document.createElement('div');
        mapEl.style.cssText = 'position:absolute;inset:0;';
        el.appendChild(mapEl);
        let map = null, ready = false, marker = null, streetsLayer = null, boundaryLayer = null, tip = null, sv = null, saRings = [];
        let selected = null, streetsVisible = true, streetsAdded = false, pendingPin = null, pendingFit = null;
        if (engineBadge) engineBadge.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Loading Google Maps';

        if (typeof GoogleMapsLoader === 'undefined' || !GoogleMapsLoader.hasKey()) {
            setTimeout(() => onFail('Google Maps API key is not configured (GOOGLE_MAPS_API_KEY in .env)'), 0);
        } else {
            GoogleMapsLoader.load(['maps']).then(google => {
                map = new google.maps.Map(mapEl, {
                    center: { lat: SA_CENTER[0], lng: SA_CENTER[1] }, zoom: 17,
                    mapTypeId: google.maps.MapTypeId.HYBRID,
                    mapTypeControl: true,
                    mapTypeControlOptions: { mapTypeIds: [google.maps.MapTypeId.ROADMAP, google.maps.MapTypeId.SATELLITE, google.maps.MapTypeId.HYBRID], style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR, position: google.maps.ControlPosition.TOP_LEFT },
                    streetViewControl: true, fullscreenControl: false, rotateControl: false, tilt: 0,
                    streetViewControlOptions: { position: google.maps.ControlPosition.RIGHT_BOTTOM },
                    zoomControlOptions: { position: google.maps.ControlPosition.RIGHT_BOTTOM },
                    clickableIcons: false, gestureHandling: 'greedy',
                });
                tip = new google.maps.InfoWindow({ disableAutoPan: true, headerDisabled: true, pixelOffset: new google.maps.Size(0, -8) });
                // Native Pegman + Google-style inset map, limited to the barangay
                if (typeof CrimeMapGoogle !== 'undefined') {
                    sv = CrimeMapGoogle.attachStreetView(google, map, el, mapEl, { inside: (lat, lng) => CrimeMapGoogle.insideRings(saRings, lat, lng) });
                }

                // Barangay boundaries: San Agustin outlined, neighbours faint
                boundaryLayer = new google.maps.Data({ map: map });
                fetch(BARANGAYS_URL + '?t=' + Date.now()).then(r => r.json()).then(geo => {
                    boundaryLayer.addGeoJson(geo);
                    boundaryLayer.setStyle(f => {
                        const sa = String(f.getProperty('code') || '') === SA_CODE;
                        return { clickable: false, strokeColor: sa ? '#5eead4' : '#8fb3b0', strokeWeight: sa ? 3 : 0.8, strokeOpacity: sa ? 1 : 0.6, fillColor: sa ? '#5eead4' : '#f2f9f8', fillOpacity: sa ? 0.10 : 0.04, zIndex: sa ? 2 : 1 };
                    });
                    const sa = (geo.features || []).find(f => String((f.properties || {}).code || '') === SA_CODE);
                    if (!sa) return;
                    saBounds = boundsOfGeometry(sa.geometry);
                    if (typeof CrimeMapGoogle !== 'undefined') {
                        saRings = CrimeMapGoogle.ringsOf(sa.geometry);
                        CrimeMapGoogle.outsideMask(map, saRings);   // outside dimmed, inside clear
                    }
                    const b = new google.maps.LatLngBounds({ lat: saBounds.minLat, lng: saBounds.minLng }, { lat: saBounds.maxLat, lng: saBounds.maxLng });
                    new google.maps.Marker({ map: map, position: b.getCenter(), clickable: false, icon: { path: 'M 0 0', scale: 0 }, label: { text: 'San Agustin', className: 'gm-street-label' } });
                    const padLat = (saBounds.maxLat - saBounds.minLat) * 1.2, padLng = (saBounds.maxLng - saBounds.minLng) * 1.2;
                    map.setOptions({ restriction: { latLngBounds: { north: saBounds.maxLat + padLat, south: saBounds.minLat - padLat, east: saBounds.maxLng + padLng, west: saBounds.minLng - padLng }, strictBounds: false } });
                    if (!marker && !pendingFit) map.fitBounds(b, 12);
                }).catch(err => console.warn('Barangay boundaries not loaded:', err));

                streetsLayer = new google.maps.Data({ map: map });
                map.addListener('click', e => {
                    if (sv && sv.isOpen()) return;
                            userPlaced(e.latLng.lat(), e.latLng.lng());
                });

                ready = true;
                if (engineBadge) { engineBadge.innerHTML = '<i class="fab fa-google mr-1"></i>Google Maps · Hybrid'; engineBadge.title = 'Google Maps JavaScript API'; }
                addStreets();
                if (pendingPin) { api.setPin(pendingPin[0], pendingPin[1], true); pendingPin = null; }
                if (pendingFit) { api.fitStreet(pendingFit); pendingFit = null; }
            }).catch(err => onFail(err && err.message ? err.message : String(err)));
        }

        function styleStreets() {
            if (!streetsLayer) return;
            streetsLayer.setStyle(f => {
                const name = String(f.getProperty('name') || '').trim();
                const st = streetStats[name];
                const count = st ? st.count : 0;
                const sev = severityFor(count);
                const active = activeStreets.has(name.toLowerCase());
                const isSel = !!selected && name === selected;
                return {
                    visible: streetsVisible,
                    clickable: true,
                    strokeColor: isSel ? '#facc15' : sev.color,
                    strokeWeight: isSel ? 6 : (active ? 4 : 2.5),
                    strokeOpacity: 0.95,
                    zIndex: isSel ? 50 : 10 + count,
                };
            });
        }

        function addStreets() {
            if (!ready || !streetGeo || streetsAdded) return;
            streetsAdded = true;
            streetsLayer.addGeoJson(streetGeo);
            styleStreets();
            const propsOf = f => {
                const name = String(f.getProperty('name') || '').trim();
                const st = streetStats[name]; const count = st ? st.count : 0; const sev = severityFor(count);
                return { name, count, color: sev.color, level: sev.label, active: activeStreets.has(name.toLowerCase()) ? 1 : 0, top: st && st.top_category ? st.top_category : '', peaks: st && st.peak_hours ? st.peak_hours.join(', ') : '' };
            };
            // Whole street on hover: every feature with the same name, not one segment
            const sameStreet = name => { const out = []; streetsLayer.forEach(ft => { if (String(ft.getProperty('name') || '').trim() === name) out.push(ft); }); return out; };
            streetsLayer.addListener('mouseover', e => {
                sameStreet(String(e.feature.getProperty('name') || '').trim()).forEach(ft => streetsLayer.overrideStyle(ft, { strokeWeight: 8, strokeOpacity: 1, zIndex: 90 }));
                tip.setContent('<div class="gm-tip">' + streetTipHtml(propsOf(e.feature)) + '</div>');
                tip.setPosition(e.latLng);
                tip.open({ map: map });
            });
            streetsLayer.addListener('mousemove', e => tip.setPosition(e.latLng));
            streetsLayer.addListener('mouseout', e => {
                sameStreet(String(e.feature.getProperty('name') || '').trim()).forEach(ft => streetsLayer.revertStyle(ft));
                tip.close();
            });
            streetsLayer.addListener('click', e => {
                if (!isSanAgustin() || (sv && sv.isOpen())) return;
                userPlaced(e.latLng.lat(), e.latLng.lng());
            });
        }

        const PIN = 'M 0,0 C -2,-20 -10,-22 -10,-30 A 10,10 0 1,1 10,-30 C 10,-22 2,-20 0,0 z';
        const api = {
            kind: 'google',
            ready: addStreets,
            setPin(lat, lng, pan) {
                if (!ready) { pendingPin = [lat, lng]; return; }
                const google = window.google;
                if (!marker) {
                    marker = new google.maps.Marker({
                        map: map, position: { lat, lng }, draggable: true, zIndex: 1000, title: 'Drag to the exact spot',
                        icon: { path: PIN, fillColor: '#dc2626', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2, scale: 1.1, anchor: new google.maps.Point(0, 0) },
                    });
                    marker.addListener('dragend', () => { const p = marker.getPosition(); userPlaced(p.lat(), p.lng()); });
                } else marker.setPosition({ lat, lng });
                if (pan) map.panTo({ lat, lng });
            },
            highlightStreet(name) { selected = name || null; styleStreets(); },
            fitStreet(name) {
                if (!ready) { pendingFit = name; return; }
                const b = streetBounds(name);
                if (b) map.fitBounds(new window.google.maps.LatLngBounds({ lat: b.minLat, lng: b.minLng }, { lat: b.maxLat, lng: b.maxLng }), 80);
            },
            setStreetsVisible(on) { streetsVisible = on; styleStreets(); },
            resize() { if (map) window.google.maps.event.trigger(map, 'resize'); },
        };
        return api;
    }

    // ------------------------------------------------------------------
    // 2D engine: the Leaflet map shared with Crime Mapping (fallback)
    // ------------------------------------------------------------------
    function create2dEngine() {
        document.getElementById('tiltToggle')?.classList.add('hidden');
        if (engineBadge) engineBadge.innerHTML = '<i class="fas fa-map mr-1"></i>2D map';

        const map = createCrimeMap('addCrimeMap2d', { center: SA_CENTER, zoom: 16 });
        CrimeMapBase.drawSanAgustinBoundary(map, { lockView: true }).then(function (b) {
            if (b && b.bounds) saBounds = { minLat: b.bounds.getSouth(), maxLat: b.bounds.getNorth(), minLng: b.bounds.getWest(), maxLng: b.bounds.getEast() };
            if (marker) map.panTo(marker.getLatLng());
        });
        // The shared street layer from Crime Mapping: every street coloured by
        // crime level, hover tooltips. Once a street is picked, the other
        // streets are lifted off the map until the pick is cleared.
        if (typeof saStreetsAttach === 'function') {
            saStreetsAttach(map);
            if (typeof saStreetsSetVisible === 'function') saStreetsSetVisible(true);
        }
        function showOnly(name) {
            const groups = (typeof saStreetGroupsAll !== 'undefined' && saStreetGroupsAll) || null;
            const layer = (typeof saStreetLayer !== 'undefined' && saStreetLayer) || null;
            if (!groups || !layer) return;
            Object.keys(groups).forEach(k => {
                const keep = !name || k === name;
                groups[k].casing.concat(groups[k].inner).forEach(l => {
                    if (keep) { if (!layer.hasLayer(l)) layer.addLayer(l); }
                    else if (layer.hasLayer(l)) layer.removeLayer(l);
                });
            });
        }
        const icon = L.divIcon({ className: '', html: '<div class="crime-marker"><i class="fas fa-exclamation"></i></div>', iconSize: [30, 30], iconAnchor: [15, 30] });
        let marker = null;
        map.on('click', e => {
            userPlaced(e.latlng.lat, e.latlng.lng);
        });
        return {
            kind: '2d',
            ready: () => {},
            setPin(lat, lng, pan) {
                if (!marker) {
                    marker = L.marker([lat, lng], { icon: icon, draggable: true, autoPan: true }).addTo(map);
                    marker.on('dragend', () => { const p = marker.getLatLng(); userPlaced(p.lat, p.lng); });
                } else marker.setLatLng([lat, lng]);
                if (pan) map.panTo([lat, lng]);
            },
            highlightStreet(name) {
                if (typeof saStreetsHighlight === 'function') saStreetsHighlight(name ? [name] : []);
                if (typeof ensureSanAgustinStreets === 'function') ensureSanAgustinStreets().then(() => showOnly(null));
            },
            fitStreet: name => { if (typeof saStreetsFitStreet === 'function') saStreetsFitStreet(name); },
            setStreetsVisible: on => { if (typeof saStreetsSetVisible === 'function') saStreetsSetVisible(on); },
            resize: () => map.invalidateSize(),
        };
    }

    // ------------------------------------------------------------------
    // Wiring
    // ------------------------------------------------------------------
    freeToggle.addEventListener('click', () => { if (isSanAgustin()) setFree(!free); });
    document.getElementById('streetJump').addEventListener('change', function () {
        const name = this.value;
        if (!name || !isSanAgustin()) return;
        const mid = streetMidpoint(name);
        if (!mid) return;
        // Snap onto that street, then read the street back from the coordinates
        const s = snap(mid[0], mid[1]);
        placePin(s[0], s[1], true);
        engine.fitStreet(name);
    });
    streetText.addEventListener('input', () => { streetInput.value = streetText.value.trim(); pinStreetLabel.textContent = streetInput.value || 'No street chosen'; });
    barangaySelect.addEventListener('change', onBarangayChange);
    document.getElementById('recenterBtn').addEventListener('click', () => {
        if (!isSanAgustin() || !latInput.value) return;
        const s = snap(+latInput.value, +lngInput.value);
        placePin(s[0], s[1], true);
    });

    document.querySelectorAll('#categoryChips [data-cat]').forEach(btn => btn.addEventListener('click', () => {
        document.getElementById('categorySelect').value = btn.dataset.cat;
        document.querySelectorAll('#categoryChips [data-cat]').forEach(b => b.classList.remove('ring-2', 'ring-alertara-500'));
        btn.classList.add('ring-2', 'ring-alertara-500');
    }));

    const clearanceSelect = document.getElementById('clearanceSelect');
    const clearanceWrap = document.getElementById('clearanceDateWrap');
    const syncClearance = () => clearanceWrap.classList.toggle('hidden', clearanceSelect.value !== 'cleared');
    clearanceSelect.addEventListener('change', syncClearance); syncClearance();

    document.getElementById('addCrimeForm').addEventListener('submit', function (e) {
        streetInput.value = currentStreet();
        if (!streetInput.value) { e.preventDefault(); alert(isSanAgustin() ? 'Place the pin on a San Agustin street so the street can be detected.' : 'Please type the street where the crime happened.'); return; }
        if (!latInput.value || !lngInput.value) { e.preventDefault(); alert('Please place the pin on the map.'); return; }
        const btn = document.getElementById('submitBtn'); btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Saving...';
    });

    function startForm() {
        onBarangayChange();
        // Restore a previous attempt (validation error) exactly where it was
        if (latInput.value && lngInput.value) placePin(+latInput.value, +lngInput.value, true);
        setTimeout(() => engine.resize(), 200);
    }

    // ------------------------------------------------------------------
    // Engine switching. Every engine lives in its own pane inside #addCrimeMap
    // and is built once; switching shows one pane and re-applies the form
    // state (street, pin) to it. Google Maps is the default.
    // ------------------------------------------------------------------
    const engines = {};
    const ENGINE_KEY = 'addCrimeMapEngine';
    let engineName = null;

    function showPane(name) {
        ['google', '3d', '2d'].forEach(n => document.getElementById('addCrimeMap' + (n === 'google' ? 'Google' : n)).classList.toggle('on', n === name));
        document.querySelectorAll('.engine-btn').forEach(b => b.classList.toggle('on', b.dataset.engine === name));
        document.getElementById('tiltToggle').classList.toggle('hidden', name !== '3d');
    }

    function buildEngine(name) {
        if (name === 'google') return createGoogleEngine(reason => {
            console.warn('Google Maps unavailable, using the 3D map:', reason);
            const why = document.getElementById('map3dReason');
            if (why) { why.textContent = 'Google Maps unavailable: ' + reason; why.classList.remove('hidden'); }
            if (engineName === 'google') switchEngine(typeof maplibregl !== 'undefined' ? '3d' : '2d');
        });
        if (name === '3d') {
            if (typeof maplibregl === 'undefined') return null;
            return create3dEngine(reason => {
                console.warn('3D map unavailable, using the 2D map:', reason);
                if (engineName === '3d') switchEngine('2d', reason);
            });
        }
        return create2dEngine(name === '2d' ? undefined : name);
    }

    function switchEngine(name, reason) {
        if (!engines[name]) {
            try { engines[name] = buildEngine(name); } catch (err) { console.warn('Engine ' + name + ' failed:', err); engines[name] = null; }
            if (!engines[name]) { if (name !== '2d') return switchEngine('2d', reason); return; }
        }
        engineName = name;
        engine = engines[name];
        try { localStorage.setItem(ENGINE_KEY, name); } catch (e) {}
        showPane(name);
        setTimeout(() => {
            engine.resize();
            if (streetGeo) engine.ready();
            engine.setStreetsVisible(isSanAgustin());
            const name2 = currentStreet();
            if (isSanAgustin()) engine.highlightStreet(name2 || null);
            if (latInput.value && lngInput.value) engine.setPin(+latInput.value, +lngInput.value, true);
            else if (name2 && isSanAgustin()) engine.fitStreet(name2);
        }, 50);
    }

    document.querySelectorAll('.engine-btn').forEach(b => b.addEventListener('click', () => switchEngine(b.dataset.engine)));

    function boot() {
        let start = 'google';
        try { const saved = localStorage.getItem(ENGINE_KEY); if (saved === '3d' || saved === '2d' || saved === 'google') start = saved; } catch (e) {}
        switchEngine(start);

        // Street geometry for snapping, midpoints and the street layers
        Promise.all([
            fetch(STREETS_URL + '?t=' + Date.now(), { headers: { 'Accept': 'application/json' } }).then(r => r.json()),
            fetch(STREET_STATS_URL + '?t=' + Date.now(), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => ({ streets: {} }))
        ])
            .then(([geo, stats]) => {
                streetGeo = geo;
                streetStats = (stats && stats.streets) || {};
                (geo.features || []).forEach(f => {
                    const name = String(f.properties?.name || '').trim();
                    if (!name) return;
                    const key = name.toLowerCase();
                    streetNames[key] = name;
                    const coords = f.geometry?.type === 'MultiLineString' ? f.geometry.coordinates : [f.geometry?.coordinates || []];
                    coords.forEach(line => {
                        const pts = line.filter(c => Array.isArray(c) && c.length >= 2).map(c => [+c[1], +c[0]]);
                        if (pts.length) (streetLines[key] ||= []).push(pts);
                    });
                });
                engine.ready();
            })
            .catch(err => console.error('Street geometry failed to load', err))
            .finally(startForm);
    }

    boot();
})();
</script>
@endpush
