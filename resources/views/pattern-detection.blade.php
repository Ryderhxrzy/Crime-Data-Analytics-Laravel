@extends('layouts.app')

@section('title', 'Pattern Detection')

@section('content')
<div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12" id="patternApp">

    <!-- Page Header -->
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Pattern Detection</h1>
                <p class="text-gray-600 mt-1 text-sm lg:text-base">
                    Trends, hotspots, timing, repeat activity and anomalies extracted from recorded incidents.
                </p>
            </div>

            <!-- Simulation toggle -->
            <div class="flex items-center gap-4 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
                <div>
                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Simulation Mode</div>
                    <div id="simStateLabel" class="text-sm font-bold text-gray-700">OFF &mdash; real data only</div>
                </div>
                <button type="button" id="simToggle" role="switch" aria-checked="false"
                        class="relative inline-flex h-7 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-gray-300 transition-colors focus:outline-none focus:ring-2 focus:ring-alertara-500 focus:ring-offset-2"
                        style="width: 3.25rem;">
                    <span id="simKnob" class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 translate-x-0"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Simulation banner (hidden when OFF) -->
    <div id="simBanner" class="hidden mb-6 rounded-xl border-2 border-amber-400 bg-amber-50 p-4">
        <div class="flex items-start gap-3">
            <i class="fas fa-flask text-amber-600 mt-0.5"></i>
            <div class="flex-1">
                <div class="font-bold text-amber-900 text-sm">Simulation is ON &mdash; results are not operational data</div>
                <p class="text-amber-800 text-xs mt-1">
                    Generated records are mixed into the analysis below and marked
                    <span class="inline-block px-1.5 py-0.5 bg-amber-200 text-amber-900 rounded text-[10px] font-bold">SIMULATED</span>.
                    Synthetic records resample the shape of existing data &mdash; they are not a forecast and must not be used for deployment or reporting.
                </p>
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-4">
        <div class="mb-4 pb-4 border-b border-gray-200">
            <h3 class="text-sm font-bold text-gray-900"><i class="fas fa-sliders-h mr-2 text-alertara-700"></i>Analysis Settings</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-alertara-800 mb-2">Analysis Period</label>
                <select id="daysSelect" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 bg-white">
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="180" selected>Last 6 months</option>
                    <option value="365">Last 12 months</option>
                    <option value="730">Last 24 months</option>
                </select>
            </div>
            <div class="flex items-end">
                <button id="runBtn" class="w-full px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors flex items-center justify-center gap-2 font-semibold">
                    <i class="fas fa-play"></i> Run Analysis
                </button>
            </div>
        </div>

        <!-- Scenario controls, only meaningful while simulating -->
        <div id="scenarioPanel" class="hidden mt-4 pt-4 border-t border-gray-200">
            <h4 class="text-xs font-bold text-amber-800 uppercase tracking-wide mb-3">
                <i class="fas fa-flask mr-1"></i>Scenario Configuration
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Simulated volume</label>
                    <select id="volumeMultiplier" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                        <option value="0.25">+25% of real volume</option>
                        <option value="0.5" selected>+50% of real volume</option>
                        <option value="1">+100% of real volume</option>
                        <option value="2">+200% of real volume</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Surge in crime type</label>
                    <select id="surgeCategory" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                        <option value="">None</option>
                        @foreach($crimeCategories as $category)
                            <option value="{{ $category->category_name }}">{{ $category->category_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Time-based spike</label>
                    <select id="timeSpike" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                        <option value="">None</option>
                        <option value="0,5">Early morning (00:00-05:59)</option>
                        <option value="6,11">Morning (06:00-11:59)</option>
                        <option value="12,17">Afternoon (12:00-17:59)</option>
                        <option value="18,23">Evening/Night (18:00-23:59)</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700 pb-2 cursor-pointer">
                        <input type="checkbox" id="locationSurge" class="rounded border-gray-300 text-alertara-700 focus:ring-alertara-500">
                        <span>Concentrate on top hotspot</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div id="loadingState" class="hidden bg-white rounded-xl border border-gray-200 p-12 text-center">
        <i class="fas fa-spinner fa-spin text-3xl text-alertara-700 mb-3"></i>
        <div class="text-sm font-semibold text-gray-900">Analyzing incident patterns&hellip;</div>
    </div>

    <!-- Error -->
    <div id="errorState" class="hidden bg-red-50 border border-red-200 rounded-xl p-6">
        <div class="flex items-start gap-3">
            <i class="fas fa-triangle-exclamation text-red-600 mt-0.5"></i>
            <div>
                <div class="font-bold text-red-900 text-sm">Analysis failed</div>
                <p id="errorMessage" class="text-red-800 text-xs mt-1"></p>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div id="results" class="hidden space-y-6">

        <!-- Data provenance -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-gradient-to-br from-alertara-700 to-alertara-600 text-white p-4 rounded-lg">
                <div class="text-xs opacity-90 mb-1">Real Records</div>
                <div id="statReal" class="text-2xl font-bold">0</div>
            </div>
            <div id="simStatCard" class="bg-gradient-to-br from-amber-600 to-amber-500 text-white p-4 rounded-lg opacity-40">
                <div class="text-xs opacity-90 mb-1">Simulated Records</div>
                <div id="statSimulated" class="text-2xl font-bold">0</div>
            </div>
            <div class="bg-gradient-to-br from-blue-600 to-blue-500 text-white p-4 rounded-lg">
                <div class="text-xs opacity-90 mb-1">Analyzed Total</div>
                <div id="statTotal" class="text-2xl font-bold">0</div>
            </div>
            <div class="bg-gradient-to-br from-gray-700 to-gray-600 text-white p-4 rounded-lg">
                <div class="text-xs opacity-90 mb-1">Period</div>
                <div id="statPeriod" class="text-lg font-bold">&mdash;</div>
            </div>
        </div>

        <!-- Low-confidence warning -->
        <div id="confidenceWarning" class="hidden rounded-xl border border-orange-300 bg-orange-50 p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-circle-info text-orange-600 mt-0.5"></i>
                <p id="confidenceNote" class="text-orange-900 text-xs"></p>
            </div>
        </div>

        <!-- Summary insights -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4"><i class="fas fa-lightbulb mr-2 text-alertara-600"></i>Summary Insights</h2>
            <div id="insightsList" class="space-y-3"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Trends -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900"><i class="fas fa-chart-line mr-2 text-alertara-600"></i>Crime Trends</h2>
                    <span id="trendBadge" class="px-3 py-1 rounded-full text-xs font-bold">&mdash;</span>
                </div>
                <p id="trendExplanation" class="text-xs text-gray-600 mb-4"></p>

                <div class="flex gap-2 mb-3">
                    <button class="trend-tab px-3 py-1 text-xs font-semibold rounded-lg border" data-grain="daily">Daily</button>
                    <button class="trend-tab px-3 py-1 text-xs font-semibold rounded-lg border" data-grain="weekly">Weekly</button>
                    <button class="trend-tab px-3 py-1 text-xs font-semibold rounded-lg border" data-grain="monthly">Monthly</button>
                </div>
                <div id="trendChart" class="overflow-x-auto"></div>
            </div>

            <!-- Crime type distribution -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4"><i class="fas fa-chart-pie mr-2 text-alertara-600"></i>Crime Type Distribution</h2>
                <div id="typeDistribution" class="space-y-2 max-h-96 overflow-y-auto"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Time patterns -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4"><i class="fas fa-clock mr-2 text-alertara-600"></i>Time-Based Patterns</h2>
                <div id="timeSummary" class="grid grid-cols-2 gap-3 mb-5"></div>
                <div class="text-xs font-bold text-gray-500 uppercase mb-2">By hour of day</div>
                <div id="hourChart" class="mb-5"></div>
                <div class="text-xs font-bold text-gray-500 uppercase mb-2">By day of week</div>
                <div id="dowChart"></div>
            </div>

            <!-- Hotspots -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4"><i class="fas fa-location-crosshairs mr-2 text-alertara-600"></i>Ranked Hotspots</h2>
                <div id="hotspotList" class="space-y-2 max-h-[28rem] overflow-y-auto"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Repeat clusters -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2"><i class="fas fa-layer-group mr-2 text-alertara-600"></i>Repeat / Cluster Incidents</h2>
                <p id="clusterRule" class="text-xs text-gray-500 mb-4"></p>
                <div id="clusterList" class="space-y-3 max-h-96 overflow-y-auto"></div>
            </div>

            <!-- Anomalies -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2"><i class="fas fa-wave-square mr-2 text-alertara-600"></i>Anomaly Detection</h2>
                <p id="anomalyRule" class="text-xs text-gray-500 mb-4"></p>
                <div id="anomalyList" class="space-y-2 max-h-96 overflow-y-auto"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const ANALYZE_URL = @json(route('pattern-detection.analyze'));

    let simulationOn = false;
    let latest = null;
    let trendGrain = 'daily';

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const SIM_TAG = '<span class="inline-block px-1.5 py-0.5 bg-amber-200 text-amber-900 rounded text-[10px] font-bold ml-2">SIMULATED</span>';

    // ---------- simulation toggle ----------
    function setSimulation(on) {
        simulationOn = on;
        const toggle = $('simToggle'), knob = $('simKnob');

        toggle.setAttribute('aria-checked', String(on));
        toggle.classList.toggle('bg-amber-500', on);
        toggle.classList.toggle('bg-gray-300', !on);
        knob.classList.toggle('translate-x-6', on);
        knob.classList.toggle('translate-x-0', !on);

        $('simStateLabel').textContent = on ? 'ON — real + simulated' : 'OFF — real data only';
        $('simStateLabel').className = on ? 'text-sm font-bold text-amber-700' : 'text-sm font-bold text-gray-700';

        $('simBanner').classList.toggle('hidden', !on);
        $('scenarioPanel').classList.toggle('hidden', !on);
        $('simStatCard').classList.toggle('opacity-40', !on);
    }

    // ---------- run ----------
    async function run() {
        $('loadingState').classList.remove('hidden');
        $('results').classList.add('hidden');
        $('errorState').classList.add('hidden');

        const params = new URLSearchParams({ days: $('daysSelect').value });

        if (simulationOn) {
            params.set('simulation', '1');
            params.set('volume_multiplier', $('volumeMultiplier').value);

            if ($('surgeCategory').value) {
                params.set('surge_category', $('surgeCategory').value);
                params.set('surge_category_multiplier', '3');
            }
            if ($('timeSpike').value) {
                const [start, end] = $('timeSpike').value.split(',');
                params.set('spike_start_hour', start);
                params.set('spike_end_hour', end);
                params.set('spike_multiplier', '3');
            }
            if ($('locationSurge').checked) {
                params.set('location_surge', '1');
                params.set('location_surge_multiplier', '3');
            }
        }

        try {
            const res = await fetch(ANALYZE_URL + '?' + params, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (data.error) throw new Error(data.message || data.error);

            latest = data;
            render(data);

            $('loadingState').classList.add('hidden');
            $('results').classList.remove('hidden');
        } catch (e) {
            console.error('Pattern detection failed:', e);
            $('loadingState').classList.add('hidden');
            $('errorMessage').textContent = e.message;
            $('errorState').classList.remove('hidden');
        }
    }

    // ---------- render ----------
    function render(d) {
        const m = d.meta;

        $('statReal').textContent = m.real_count.toLocaleString();
        $('statSimulated').textContent = m.simulated_count.toLocaleString();
        $('statTotal').textContent = m.total_count.toLocaleString();
        $('statPeriod').textContent = m.period_days + 'd';

        $('confidenceWarning').classList.toggle('hidden', !m.low_confidence);
        if (m.low_confidence) $('confidenceNote').textContent = m.confidence_note;

        renderInsights(d.insights);
        renderTrend(d.trends);
        renderTypes(d.type_distribution, m.total_count);
        renderTime(d.time_patterns);
        renderHotspots(d.hotspots);
        renderClusters(d.repeat_clusters);
        renderAnomalies(d.anomalies);
    }

    function renderInsights(list) {
        if (!list || !list.length) {
            $('insightsList').innerHTML = '<p class="text-sm text-gray-500">No insights available.</p>';
            return;
        }

        $('insightsList').innerHTML = list.map(i => {
            const lowConf = i.confidence === 'low';
            return '<div class="flex items-start gap-3 p-3 rounded-lg border ' +
                (i.simulated ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50') + '">' +
                '<i class="fas ' + (i.simulated ? 'fa-flask text-amber-600' : 'fa-circle-check text-alertara-600') + ' mt-0.5"></i>' +
                '<div class="flex-1 min-w-0">' +
                    '<div class="text-sm font-semibold text-gray-900">' + esc(i.text) +
                        (i.simulated ? SIM_TAG : '') +
                        (lowConf ? '<span class="inline-block px-1.5 py-0.5 bg-orange-200 text-orange-900 rounded text-[10px] font-bold ml-2">LOW CONFIDENCE</span>' : '') +
                    '</div>' +
                    '<div class="text-xs text-gray-500 mt-1">Based on: ' + esc(i.based_on) + '</div>' +
                '</div></div>';
        }).join('');
    }

    function renderTrend(t) {
        const dir = t.direction;
        const badge = $('trendBadge');
        const styles = {
            increasing: 'bg-red-100 text-red-800',
            decreasing: 'bg-green-100 text-green-800',
            stable: 'bg-gray-100 text-gray-700'
        };
        badge.className = 'px-3 py-1 rounded-full text-xs font-bold ' + (styles[dir.label] || 'bg-gray-100 text-gray-700');
        badge.textContent = dir.label.toUpperCase();
        $('trendExplanation').textContent = dir.explanation;

        document.querySelectorAll('.trend-tab').forEach(tab => {
            const active = tab.dataset.grain === trendGrain;
            tab.className = 'trend-tab px-3 py-1 text-xs font-semibold rounded-lg border ' +
                (active ? 'bg-alertara-700 text-white border-alertara-700' : 'bg-white text-gray-600 border-gray-300');
        });

        drawBars('trendChart', t[trendGrain] || [], '#274d4c');
    }

    /** Inline bar chart — no chart library needed */
    function drawBars(targetId, series, color) {
        const el = $(targetId);
        if (!series.length) {
            el.innerHTML = '<p class="text-sm text-gray-500 py-8 text-center">No data in this period.</p>';
            return;
        }

        const max = Math.max.apply(null, series.map(s => s.count).concat([1]));
        const barWidth = series.length > 60 ? 4 : series.length > 30 ? 8 : 18;

        el.innerHTML =
            '<div class="flex items-end gap-[2px]" style="height:160px; min-width:' + (series.length * (barWidth + 2)) + 'px">' +
            series.map(s =>
                '<div class="flex-1 flex flex-col justify-end items-center group relative" style="min-width:' + barWidth + 'px">' +
                    '<div class="w-full rounded-t transition-all hover:opacity-80" style="height:' +
                        ((s.count / max) * 100) + '%; background:' + color + '; min-height:' + (s.count > 0 ? '2px' : '0') + '"></div>' +
                    '<div class="absolute bottom-full mb-1 hidden group-hover:block bg-gray-900 text-white text-[10px] rounded px-2 py-1 whitespace-nowrap z-10">' +
                        esc(s.label) + ': ' + s.count +
                    '</div>' +
                '</div>').join('') +
            '</div>' +
            '<div class="flex justify-between text-[10px] text-gray-400 mt-1">' +
                '<span>' + esc(series[0].label) + '</span>' +
                '<span>' + esc(series[series.length - 1].label) + '</span>' +
            '</div>';
    }

    function renderTypes(types, total) {
        if (!types.length) {
            $('typeDistribution').innerHTML = '<p class="text-sm text-gray-500">No records.</p>';
            return;
        }

        $('typeDistribution').innerHTML = types.map(t =>
            '<div>' +
                '<div class="flex justify-between items-center text-xs mb-1">' +
                    '<span class="font-semibold text-gray-800">' + esc(t.category) +
                        (t.simulated_count > 0 ? ' <span class="text-amber-700 font-normal">(' + t.simulated_count + ' sim)</span>' : '') +
                    '</span>' +
                    '<span class="text-gray-500">' + t.count + ' · ' + t.percent + '%</span>' +
                '</div>' +
                '<div class="h-2 bg-gray-100 rounded-full overflow-hidden flex">' +
                    '<div style="width:' + ((t.real_count / Math.max(1, total)) * 100) + '%" class="bg-alertara-600 h-full"></div>' +
                    '<div style="width:' + ((t.simulated_count / Math.max(1, total)) * 100) + '%" class="bg-amber-400 h-full"></div>' +
                '</div>' +
            '</div>').join('');
    }

    function renderTime(tp) {
        const wk = tp.weekday_vs_weekend;

        $('timeSummary').innerHTML =
            '<div class="bg-gray-50 border border-gray-200 rounded-lg p-3">' +
                '<div class="text-[10px] font-bold text-gray-400 uppercase">Peak Hour</div>' +
                '<div class="text-lg font-bold text-gray-900">' + (tp.peak_hour_label || '—') + '</div>' +
                '<div class="text-[11px] text-gray-500">' + tp.peak_hour_count + ' incidents</div>' +
            '</div>' +
            '<div class="bg-gray-50 border border-gray-200 rounded-lg p-3">' +
                '<div class="text-[10px] font-bold text-gray-400 uppercase">Peak Day</div>' +
                '<div class="text-lg font-bold text-gray-900">' + esc(tp.peak_day || '—') + '</div>' +
                '<div class="text-[11px] text-gray-500">' + tp.peak_day_count + ' incidents</div>' +
            '</div>' +
            '<div class="bg-gray-50 border border-gray-200 rounded-lg p-3 col-span-2">' +
                '<div class="text-[10px] font-bold text-gray-400 uppercase">Weekday vs Weekend</div>' +
                '<div class="text-sm font-bold text-gray-900 capitalize">' + esc(wk.busier) + 's are busier</div>' +
                '<div class="text-[11px] text-gray-500">' + wk.weekday_daily_avg + '/weekday vs ' +
                    wk.weekend_daily_avg + '/weekend day (' + (wk.difference_percent > 0 ? '+' : '') + wk.difference_percent + '%)</div>' +
            '</div>' +
            (tp.missing_time_count > 0 ?
                '<div class="col-span-2 text-[11px] text-orange-700 bg-orange-50 border border-orange-200 rounded-lg p-2">' +
                '<i class="fas fa-circle-info mr-1"></i>' + tp.missing_time_count +
                ' record(s) have no recorded time and are excluded from hourly figures.</div>' : '');

        drawBars('hourChart', tp.by_hour.map(h => ({ label: h.label, count: h.count })), '#3b82f6');
        drawBars('dowChart', tp.by_day_of_week.map(d => ({ label: d.day.slice(0, 3), count: d.count })), '#8b5cf6');
    }

    function renderHotspots(hotspots) {
        if (!hotspots.length) {
            $('hotspotList').innerHTML = '<p class="text-sm text-gray-500">No location clusters found — incidents are too scattered, or there are too few records.</p>';
            return;
        }

        $('hotspotList').innerHTML = hotspots.map(h =>
            '<div class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50">' +
                '<div class="flex-shrink-0 w-7 h-7 rounded-full bg-alertara-700 text-white text-xs font-bold flex items-center justify-center">' + h.rank + '</div>' +
                '<div class="flex-1 min-w-0">' +
                    '<div class="text-sm font-semibold text-gray-900">' + esc(h.dominant_category) +
                        (h.simulated_count > 0 ? ' <span class="text-amber-700 text-xs font-normal">(' + h.simulated_count + ' sim)</span>' : '') +
                    '</div>' +
                    '<div class="text-xs text-gray-500 mt-0.5">' + h.count + ' incidents · ' + h.share_percent + '% of all · ~' + h.radius_meters + 'm radius</div>' +
                    '<div class="text-[11px] text-gray-400 font-mono mt-0.5">' + h.latitude + ', ' + h.longitude + '</div>' +
                '</div>' +
            '</div>').join('');
    }

    function renderClusters(clusters) {
        $('clusterRule').textContent = clusters.length
            ? 'Incidents within ' + clusters[0].radius_meters + 'm of each other and inside a ' + clusters[0].window_hours + '-hour window.'
            : 'Incidents within 250m of each other and inside a 72-hour window.';

        if (!clusters.length) {
            $('clusterList').innerHTML = '<p class="text-sm text-gray-500">No repeat clusters detected in this period.</p>';
            return;
        }

        $('clusterList').innerHTML = clusters.map(c =>
            '<div class="p-3 rounded-lg border border-gray-200">' +
                '<div class="flex justify-between items-start mb-2">' +
                    '<div class="text-sm font-bold text-gray-900">' + c.incident_count + ' incidents in ' +
                        (c.span_days === 0 ? 'the same day' : c.span_days + ' day(s)') + '</div>' +
                    (c.simulated_count > 0 ? '<span class="px-1.5 py-0.5 bg-amber-200 text-amber-900 rounded text-[10px] font-bold">' + c.simulated_count + ' SIM</span>' : '') +
                '</div>' +
                '<div class="text-xs text-gray-500 mb-2">' + esc(c.first_date) + ' → ' + esc(c.last_date) + ' · ' + esc(c.categories.join(', ')) + '</div>' +
                '<div class="space-y-1">' +
                    c.incidents.slice(0, 5).map(i =>
                        '<div class="text-[11px] text-gray-600 flex items-center gap-2">' +
                            '<span class="w-1.5 h-1.5 rounded-full ' + (i.is_simulated ? 'bg-amber-500' : 'bg-alertara-600') + '"></span>' +
                            '<span class="font-mono">' + esc(i.incident_code) + '</span>' +
                            '<span class="truncate">' + esc(i.title) + '</span>' +
                        '</div>').join('') +
                    (c.incidents.length > 5 ? '<div class="text-[11px] text-gray-400">+' + (c.incidents.length - 5) + ' more</div>' : '') +
                '</div>' +
            '</div>').join('');
    }

    function renderAnomalies(a) {
        $('anomalyRule').textContent = a.note
            ? a.note
            : 'Days beyond 2 standard deviations from the period mean of ' + a.mean + ' incidents/day (threshold ' + a.threshold + ').';

        if (!a.detected.length) {
            $('anomalyList').innerHTML = '<p class="text-sm text-gray-500">No statistical outliers detected in this period.</p>';
            return;
        }

        $('anomalyList').innerHTML = a.detected.map(x =>
            '<div class="p-3 rounded-lg border ' + (x.severity === 'high' ? 'border-red-300 bg-red-50' : 'border-orange-200 bg-orange-50') + '">' +
                '<div class="flex justify-between items-center mb-1">' +
                    '<span class="text-sm font-bold ' + (x.severity === 'high' ? 'text-red-900' : 'text-orange-900') + '">' +
                        '<i class="fas ' + (x.type === 'spike' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down') + ' mr-1"></i>' + esc(x.date) +
                    '</span>' +
                    '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full ' +
                        (x.severity === 'high' ? 'bg-red-200 text-red-900' : 'bg-orange-200 text-orange-900') + '">' +
                        x.severity.toUpperCase() + ' · z=' + x.z_score +
                    '</span>' +
                '</div>' +
                '<div class="text-xs text-gray-700">' + esc(x.explanation) + '</div>' +
            '</div>').join('');
    }

    // ---------- wiring ----------
    $('simToggle').addEventListener('click', function () { setSimulation(!simulationOn); run(); });
    $('runBtn').addEventListener('click', run);
    $('daysSelect').addEventListener('change', run);
    ['volumeMultiplier', 'surgeCategory', 'timeSpike', 'locationSurge'].forEach(function (id) {
        $(id).addEventListener('change', function () { if (simulationOn) run(); });
    });
    document.querySelectorAll('.trend-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            trendGrain = tab.dataset.grain;
            if (latest) renderTrend(latest.trends);
        });
    });

    // Simulation starts OFF, as required
    setSimulation(false);
    run();
})();
</script>
@endpush
