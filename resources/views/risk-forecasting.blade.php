@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

@extends('layouts.app')
@section('title', 'Crime Forecast')
@section('content')
    <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
        <!-- Page Header -->
        <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                        <i class="fas fa-chart-line mr-3" style="color: #274d4c;"></i>Crime Forecast
                    </h1>
                    <p class="text-gray-600 mt-1 text-sm lg:text-base">
                        Trend projection over weekly incident counts &mdash; how many incidents the recent
                        trend implies for the coming period, and how far that projection can be trusted.
                    </p>
                </div>
                <div class="text-xs text-gray-500 lg:text-right shrink-0">
                    <div><span class="font-semibold text-gray-700">Forecast origin:</span> <span id="anchorDate">&mdash;</span></div>
                    <div><span class="font-semibold text-gray-700">Historical window:</span> <span id="windowLabel">&mdash;</span></div>
                </div>
            </div>
        </div>

        <!-- Forecast Controls -->
        <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
            <div class="mb-4 pb-4 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-900">
                    <i class="fas fa-filter mr-2 text-alertara-700"></i>Forecast Filters
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <!-- Forecast Period -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Forecast Period</label>
                    <select id="forecastPeriod" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="7" selected>Next 7 Days</option>
                        <option value="14">Next 14 Days</option>
                        <option value="30">Next 30 Days</option>
                        <option value="90">Next 90 Days</option>
                    </select>
                </div>

                <!-- Crime Type -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Crime Type</label>
                    <select id="crimeTypeFilter" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="" selected>All Types</option>
                        @if(isset($crimeCategories))
                            @foreach($crimeCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Case Status -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Case Status</label>
                    <select id="caseStatus" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Status</option>
                        <option value="reported">Reported</option>
                        <option value="under_investigation">Under Investigation</option>
                        <option value="solved">Solved</option>
                        <option value="closed">Closed</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <!-- Barangay -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Barangay</label>
                    <select id="targetArea" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="" selected>All Barangays</option>
                        @if(isset($barangays))
                            @foreach($barangays as $barangay)
                                <option value="{{ $barangay->id }}">{{ $barangay->barangay_name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Reset Button -->
                <div class="flex items-end">
                    <button type="button" id="resetBtn" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-redo"></i>
                        <span>Reset</span>
                    </button>
                </div>

                <!-- Generate Button -->
                <div class="flex items-end">
                    <button type="button" id="generateBtn" class="w-full px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors font-medium flex items-center justify-center gap-2 disabled:opacity-60">
                        <i class="fas fa-chart-line"></i>
                        <span>Generate</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Status / error strip -->
        <div id="statusStrip" class="hidden mb-6 rounded-lg border p-4 text-sm"></div>

        <!-- Method disclosure -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
                <i class="fas fa-circle-info text-gray-500 mt-1"></i>
                <div class="text-xs text-gray-600 leading-relaxed">
                    <p><span class="font-bold text-gray-800">Method:</span> <span id="methodText">&mdash;</span></p>
                    <p class="mt-1"><span class="font-bold text-gray-800">On the numbers:</span> <span id="confidenceNote">&mdash;</span></p>
                    <ul id="forecastNotes" class="mt-2 list-disc list-inside space-y-1 text-amber-700"></ul>
                </div>
            </div>
        </div>

        <!-- Main Forecast Chart (Full Width) -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
            <div class="mb-4">
                <h2 class="text-xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-chart-line mr-3" style="color: #274d4c;"></i>
                    Weekly Incidents &mdash; Recorded and Projected
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Solid line is recorded weekly counts &bull; dashed line is the fitted trend projected forward &bull;
                    the shaded band is the 95% prediction interval, which widens the further ahead it reaches.
                </p>
            </div>
            <div style="position: relative; height: 450px;">
                <canvas id="mainForecastChart"></canvas>
            </div>
        </div>

        <!-- Forecast Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Projected Trend -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">Projected Change</h3>
                <p class="text-3xl font-bold text-blue-700 mb-2" id="predictedTrend">&mdash;</p>
                <p class="text-xs text-gray-600" id="predictedTrendDetail">vs the same period's historical baseline</p>
            </div>

            <!-- Most frequent crime type -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">Most Frequent Type</h3>
                <p class="text-2xl font-bold text-purple-700 mb-2" id="likelyCrimeType">&mdash;</p>
                <p class="text-xs text-gray-600" id="likelyCrimeTypeDetail">base rate in the historical window</p>
            </div>

            <!-- Top projected street -->
            <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">Highest Projected Street</h3>
                <p class="text-2xl font-bold text-red-700 mb-2" id="highRiskArea">&mdash;</p>
                <p class="text-xs text-gray-600" id="highRiskAreaDetail">largest projected volume</p>
            </div>

            <!-- Confidence Score -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">Confidence Index</h3>
                <p class="text-3xl font-bold text-green-700 mb-2" id="confidenceScore">&mdash;</p>
                <p class="text-xs text-gray-600" id="confidenceDetail">from fit quality and sample size</p>
            </div>
        </div>

        <!-- Risk Level Classification -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-8 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-2 flex items-center">
                <i class="fas fa-traffic-light mr-3" style="color: #274d4c;"></i>
                Risk Level Classification
            </h2>
            <p class="text-sm text-gray-600 mb-6">
                A street's risk level is its projected volume measured against its <em>own</em> historical
                baseline for a period of the same length &mdash; not against other streets. The counts below
                are the streets that fall in each band for the current forecast.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- HIGH RISK -->
                <div class="text-center p-8 bg-red-50 border-2 border-red-300 rounded-lg">
                    <div class="text-5xl font-bold text-red-600 mb-3">&#128308;</div>
                    <h3 class="text-2xl font-bold text-red-700 mb-2">HIGH</h3>
                    <p class="text-4xl font-bold text-red-800 mb-2" id="riskCountHigh">&mdash;</p>
                    <p class="text-sm text-gray-700">streets projected at <strong>50% or more</strong> above their baseline</p>
                </div>

                <!-- MODERATE RISK -->
                <div class="text-center p-8 bg-yellow-50 border-2 border-yellow-300 rounded-lg">
                    <div class="text-5xl font-bold text-yellow-600 mb-3">&#128993;</div>
                    <h3 class="text-2xl font-bold text-yellow-700 mb-2">MODERATE</h3>
                    <p class="text-4xl font-bold text-yellow-800 mb-2" id="riskCountModerate">&mdash;</p>
                    <p class="text-sm text-gray-700">streets projected <strong>20% to 50%</strong> above their baseline</p>
                </div>

                <!-- LOW RISK -->
                <div class="text-center p-8 bg-green-50 border-2 border-green-300 rounded-lg">
                    <div class="text-5xl font-bold text-green-600 mb-3">&#128994;</div>
                    <h3 class="text-2xl font-bold text-green-700 mb-2">LOW</h3>
                    <p class="text-4xl font-bold text-green-800 mb-2" id="riskCountLow">&mdash;</p>
                    <p class="text-sm text-gray-700">streets projected <strong>under 20%</strong> above baseline, or falling</p>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-4" id="riskCoverageNote"></p>
        </div>

        <!-- Forecast Breakdown Table -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-2 flex items-center">
                <i class="fas fa-table mr-3" style="color: #274d4c;"></i>
                Forecast Breakdown
            </h2>
            <p class="text-sm text-gray-600 mb-6">
                One row per forecast week. The model is fitted on weekly counts, so a week is the smallest
                period it can honestly speak about &mdash; a per-day figure would imply precision it does not have.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-300 bg-gray-50">
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Period</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Projected Incidents</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">95% Range</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">vs Baseline</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Risk Level</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Confidence</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Most Frequent Type</th>
                        </tr>
                    </thead>
                    <tbody id="forecastTableBody">
                        <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">Loading forecast&hellip;</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Street Projections -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-2 flex items-center">
                <i class="fas fa-location-crosshairs mr-3" style="color: #274d4c;"></i>
                Street Projections
            </h2>
            <p class="text-sm text-gray-600 mb-6">
                Each street is fitted separately over the same window. This is a <strong>forward</strong>
                projection &mdash; for current concentration and risk scoring, see the Crime Hotspots page.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-300 bg-gray-50">
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Street</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Recorded</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Baseline</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Projected</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Change</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Risk</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Confidence</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Leading Type</th>
                        </tr>
                    </thead>
                    <tbody id="streetTableBody">
                        <tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">Loading&hellip;</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Trend Comparison Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-1 flex items-center">
                    <i class="fas fa-balance-scale mr-2" style="color: #274d4c;"></i>
                    <span id="monthComparisonTitle">Month Comparison</span>
                </h3>
                <p class="text-xs text-gray-500 mb-4" id="monthComparisonNote">&nbsp;</p>
                <div style="position: relative; height: 300px;">
                    <canvas id="monthComparisonChart"></canvas>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-1 flex items-center">
                    <i class="fas fa-history mr-2" style="color: #274d4c;"></i>
                    Recent Weeks vs 6-Month Average
                </h3>
                <p class="text-xs text-gray-500 mb-4" id="trendAverageNote">&nbsp;</p>
                <div style="position: relative; height: 300px;">
                    <canvas id="trendAverageChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Forecast Summary -->
        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 border-l-4 border-indigo-500 rounded-lg p-6 mb-8">
            <div class="flex items-start gap-4">
                <div class="text-2xl text-indigo-600"><i class="fas fa-clipboard-list"></i></div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Forecast Summary</h3>
                    <p class="text-gray-700 text-sm leading-relaxed" id="forecastSummaryText">&mdash;</p>
                    <p class="text-xs text-gray-500 mt-3">
                        Generated from the figures on this page. For narrative analysis and prevention
                        recommendations, use the Pattern Detection page.
                    </p>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 no-print">
            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-download mr-2" style="color: #274d4c;"></i>
                Export
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button type="button" id="exportPdfBtn" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium flex items-center justify-center gap-2">
                    <i class="fas fa-file-pdf"></i>
                    Print / Save as PDF
                </button>
                <button type="button" id="exportCsvBtn" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium flex items-center justify-center gap-2">
                    <i class="fas fa-download"></i>
                    Download Forecast Data (CSV)
                </button>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
        }
    </style>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

    <script>
        (function () {
            'use strict';

            // History depth per horizon: a longer projection needs a longer run-up
            // before the slope means anything. Clamped server-side to 28-365.
            const HISTORY_DAYS = { 7: 180, 14: 180, 30: 270, 90: 365 };

            const RISK_COLORS = { HIGH: '#ef4444', MODERATE: '#f59e0b', LOW: '#22c55e' };

            let mainForecastChart = null;
            let monthComparisonChart = null;
            let trendAverageChart = null;
            let forecast = null;

            const $ = (id) => document.getElementById(id);
            const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => (
                { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
            ));
            const signed = (n) => (n > 0 ? '+' : '') + n + '%';

            document.addEventListener('DOMContentLoaded', function () {
                ['forecastPeriod', 'crimeTypeFilter', 'caseStatus', 'targetArea'].forEach((id) => {
                    $(id).addEventListener('change', loadForecast);
                });
                $('generateBtn').addEventListener('click', loadForecast);
                $('resetBtn').addEventListener('click', resetFilters);
                $('exportCsvBtn').addEventListener('click', exportCsv);
                $('exportPdfBtn').addEventListener('click', () => window.print());

                loadForecast();
            });

            function resetFilters() {
                $('forecastPeriod').value = '7';
                $('crimeTypeFilter').value = '';
                $('caseStatus').value = '';
                $('targetArea').value = '';
                loadForecast();
            }

            function setStatus(message, tone) {
                const strip = $('statusStrip');
                if (!message) {
                    strip.className = 'hidden mb-6 rounded-lg border p-4 text-sm';
                    strip.textContent = '';
                    return;
                }
                const tones = {
                    error: 'bg-red-50 border-red-300 text-red-800',
                    warn: 'bg-amber-50 border-amber-300 text-amber-800',
                    info: 'bg-blue-50 border-blue-300 text-blue-800',
                };
                strip.className = 'mb-6 rounded-lg border p-4 text-sm ' + (tones[tone] || tones.info);
                strip.textContent = message;
            }

            function loadForecast() {
                const forecastDays = parseInt($('forecastPeriod').value, 10) || 7;
                const params = new URLSearchParams({
                    forecast_days: forecastDays,
                    historical_days: HISTORY_DAYS[forecastDays] || 180,
                    crime_type: $('crimeTypeFilter').value,
                    barangay: $('targetArea').value,
                    case_status: $('caseStatus').value,
                });

                $('generateBtn').disabled = true;
                setStatus('Generating forecast…', 'info');

                fetch('/api/crime-hotspot-forecast?' + params)
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Request failed (' + response.status + ')');
                        }
                        return response.json();
                    })
                    .then((data) => {
                        if (data.error) {
                            throw new Error(data.message || data.error);
                        }
                        forecast = data;
                        // Cleared before rendering: render() may raise its own
                        // warning (an empty window), and clearing afterwards
                        // would wipe it.
                        setStatus(null);
                        render(data);
                    })
                    .catch((error) => {
                        console.error('Forecast request failed:', error);
                        forecast = null;
                        setStatus('Could not generate the forecast: ' + error.message, 'error');
                        clearTables('Forecast unavailable.');
                    })
                    .finally(() => {
                        $('generateBtn').disabled = false;
                    });
            }

            function clearTables(message) {
                $('forecastTableBody').innerHTML =
                    '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">' + esc(message) + '</td></tr>';
                $('streetTableBody').innerHTML =
                    '<tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">' + esc(message) + '</td></tr>';
            }

            function render(data) {
                renderMeta(data);
                renderCards(data);
                renderRiskCounts(data);
                renderMainChart(data);
                renderForecastTable(data);
                renderStreetTable(data);
                renderComparisonCharts(data);
                renderSummaryText(data);

                if (data.citywide.historical_count === 0) {
                    setStatus('No incidents match these filters in the historical window, so there is nothing to project.', 'warn');
                }
            }

            function renderMeta(data) {
                $('anchorDate').textContent = data.anchor_date + (data.anchor_is_stale ? ' (dataset lags today)' : '');
                $('windowLabel').textContent = data.historical_days + ' days · ' + data.weeks_observed + ' weeks';
                $('methodText').textContent = data.method;
                $('confidenceNote').textContent = data.confidence_note;

                const notes = $('forecastNotes');
                notes.innerHTML = '';
                (data.notes || []).forEach((note) => {
                    const li = document.createElement('li');
                    li.textContent = note;
                    notes.appendChild(li);
                });
            }

            function renderCards(data) {
                const c = data.citywide;

                $('predictedTrend').textContent = signed(c.change_percent);
                $('predictedTrendDetail').textContent =
                    c.predicted_count + ' projected vs ' + c.baseline_count +
                    ' baseline over ' + data.forecast_days + ' days';

                if (data.top_category) {
                    $('likelyCrimeType').textContent = data.top_category.name;
                    $('likelyCrimeTypeDetail').textContent =
                        data.top_category.count + ' of ' + c.historical_count +
                        ' recorded incidents (' + data.top_category.share + '%) — base rate, not a separate model';
                } else {
                    $('likelyCrimeType').textContent = 'No data';
                    $('likelyCrimeTypeDetail').textContent = 'no incidents in the window';
                }

                const top = (data.streets || [])[0];
                if (top) {
                    $('highRiskArea').textContent = top.street;
                    $('highRiskAreaDetail').textContent =
                        top.predicted_count + ' projected (' + top.lower + '–' + top.upper + '), ' +
                        signed(top.change_percent) + ' vs its baseline';
                } else {
                    $('highRiskArea').textContent = 'None';
                    $('highRiskAreaDetail').textContent = 'no street met the minimum sample';
                }

                $('confidenceScore').textContent = c.confidence + '%';
                $('confidenceDetail').textContent =
                    'R² ' + c.r_squared + ' over ' + data.weeks_observed + ' weeks, ' +
                    c.historical_count + ' incidents';
            }

            function renderRiskCounts(data) {
                const counts = data.risk_counts || {};
                $('riskCountHigh').textContent = counts.high ?? 0;
                $('riskCountModerate').textContent = counts.moderate ?? 0;
                $('riskCountLow').textContent = counts.low ?? 0;

                const shown = (data.streets || []).length;
                const parts = ['Bands cover all ' + data.streets_considered + ' projected street(s).'];
                if (data.streets_considered > shown) {
                    parts.push('The table below lists the top ' + shown + ' by projected volume.');
                }
                if (data.streets_excluded > 0) {
                    parts.push(data.streets_excluded + ' street(s) had too few incidents to project and are not counted here.');
                }
                $('riskCoverageNote').textContent = parts.join(' ');
            }

            function renderMainChart(data) {
                const ctx = $('mainForecastChart');
                if (!ctx) return;

                const history = data.timeline.history || [];
                const projection = data.timeline.projection || [];

                const labels = history.map((h) => h.label).concat(projection.map((p) => p.label));
                const historyValues = history.map((h) => h.count).concat(projection.map(() => null));

                // The projection line starts on the last recorded point so the two
                // series join up instead of floating apart with a visual gap.
                const bridge = history.length ? history[history.length - 1].count : null;
                const projectedValues = history.map((h, i) => (i === history.length - 1 ? bridge : null))
                    .concat(projection.map((p) => p.predicted));
                const upperValues = history.map((h, i) => (i === history.length - 1 ? bridge : null))
                    .concat(projection.map((p) => p.upper));
                const lowerValues = history.map((h, i) => (i === history.length - 1 ? bridge : null))
                    .concat(projection.map((p) => p.lower));

                if (mainForecastChart) mainForecastChart.destroy();

                mainForecastChart = new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: '95% prediction interval',
                                data: upperValues,
                                borderColor: 'rgba(239, 68, 68, 0.25)',
                                backgroundColor: 'rgba(239, 68, 68, 0.12)',
                                borderWidth: 0,
                                pointRadius: 0,
                                fill: '+1',
                                tension: 0.3,
                                spanGaps: false,
                            },
                            {
                                label: '_intervalLower',
                                data: lowerValues,
                                borderColor: 'rgba(239, 68, 68, 0.25)',
                                borderWidth: 0,
                                pointRadius: 0,
                                fill: false,
                                tension: 0.3,
                                spanGaps: false,
                            },
                            {
                                label: 'Recorded (weekly)',
                                data: historyValues,
                                borderColor: '#274d4c',
                                backgroundColor: 'rgba(39, 77, 76, 0.05)',
                                fill: true,
                                borderWidth: 3,
                                tension: 0.3,
                                pointRadius: 3,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#274d4c',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                            },
                            {
                                label: 'Projected (weekly)',
                                data: projectedValues,
                                borderColor: '#ef4444',
                                borderDash: [6, 3],
                                borderWidth: 3,
                                fill: false,
                                tension: 0.3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#ef4444',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: { size: 12, weight: 'bold' },
                                    filter: (item) => !item.text.startsWith('_'),
                                },
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: { size: 13, weight: 'bold' },
                                bodyFont: { size: 12 },
                                filter: (item) => !item.dataset.label.startsWith('_'),
                                callbacks: {
                                    title: (items) => {
                                        const i = items[0].dataIndex;
                                        const bucket = i < history.length
                                            ? history[i]
                                            : projection[i - history.length];
                                        return bucket ? bucket.range : items[0].label;
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Incidents per week', font: { size: 12, weight: 'bold' } },
                                grid: { color: 'rgba(0, 0, 0, 0.05)' },
                            },
                            x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 16 } },
                        },
                    },
                });
            }

            function riskBadge(level) {
                const color = RISK_COLORS[level] || '#6b7280';
                return '<span class="px-3 py-1 rounded-full text-white font-medium text-xs" style="background-color: ' +
                    color + '">' + esc(level) + '</span>';
            }

            function renderForecastTable(data) {
                const body = $('forecastTableBody');
                const rows = data.timeline.projection || [];

                if (!rows.length) {
                    clearTables('No projection available for these filters.');
                    return;
                }

                body.innerHTML = rows.map((row) => `
                    <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                            ${esc(row.range)}
                            ${row.days < 7 ? '<span class="text-xs text-gray-500 block">' + row.days + '-day period</span>' : ''}
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900">${Math.round(row.predicted)}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">${row.lower} &ndash; ${row.upper}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">${esc(signed(row.change_percent))}
                            <span class="text-xs text-gray-500 block">baseline ${row.baseline}</span>
                        </td>
                        <td class="px-6 py-4 text-sm">${riskBadge(row.risk_level)}</td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">${row.confidence}%</td>
                        <td class="px-6 py-4 text-sm text-gray-700">${esc(row.top_category || '—')}</td>
                    </tr>
                `).join('');
            }

            function renderStreetTable(data) {
                const body = $('streetTableBody');
                const rows = data.streets || [];

                if (!rows.length) {
                    body.innerHTML =
                        '<tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">' +
                        'No street had enough incidents in this window to project.' +
                        '</td></tr>';
                    return;
                }

                body.innerHTML = rows.map((row) => `
                    <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">${esc(row.street)}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">${row.historical_count}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">${row.baseline_count}</td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900">${row.predicted_count}
                            <span class="text-xs font-normal text-gray-500 block">${row.lower} &ndash; ${row.upper}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">${esc(signed(row.change_percent))}</td>
                        <td class="px-6 py-4 text-sm">${riskBadge(row.risk_level)}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">${row.confidence}%</td>
                        <td class="px-6 py-4 text-sm text-gray-700">${esc(row.top_category || '—')}</td>
                    </tr>
                `).join('');
            }

            function renderComparisonCharts(data) {
                const months = data.comparison.months;
                const trend = data.comparison.trend_vs_average;

                $('monthComparisonTitle').textContent =
                    months.current_label + ' vs ' + months.previous_label;
                $('monthComparisonNote').textContent = months.current_is_partial
                    ? months.current_label + ' is incomplete — records run through ' + months.current_through +
                      ', so later buckets will read low.'
                    : 'Both months are complete.';

                if (monthComparisonChart) monthComparisonChart.destroy();
                monthComparisonChart = new Chart($('monthComparisonChart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: months.labels,
                        datasets: [
                            {
                                label: months.previous_label,
                                data: months.previous,
                                backgroundColor: 'rgba(156, 163, 175, 0.6)',
                                borderColor: '#9ca3af',
                                borderWidth: 1,
                            },
                            {
                                label: months.current_label,
                                data: months.current,
                                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                                borderColor: '#ef4444',
                                borderWidth: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { beginAtZero: true, title: { display: true, text: 'Incidents' } } },
                    },
                });

                $('trendAverageNote').textContent =
                    'Six-month mean is ' + trend.average_value + ' incidents per week (' +
                    trend.window_days + '-day window ending ' + data.anchor_date + ').';

                if (trendAverageChart) trendAverageChart.destroy();
                trendAverageChart = new Chart($('trendAverageChart').getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: trend.labels,
                        datasets: [
                            {
                                label: '6-month weekly average',
                                data: trend.average,
                                borderColor: '#6b7280',
                                borderDash: [5, 5],
                                borderWidth: 2,
                                fill: false,
                                tension: 0,
                                pointRadius: 0,
                            },
                            {
                                label: 'Recorded weekly count',
                                data: trend.current,
                                borderColor: '#ef4444',
                                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                fill: true,
                                borderWidth: 2,
                                tension: 0.3,
                                pointRadius: 4,
                                pointBackgroundColor: '#ef4444',
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { beginAtZero: true, title: { display: true, text: 'Incidents per week' } } },
                    },
                });
            }

            function renderSummaryText(data) {
                const c = data.citywide;

                if (c.historical_count === 0) {
                    $('forecastSummaryText').textContent =
                        'No incidents match these filters between ' +
                        (data.timeline.history[0] || {}).start + ' and ' + data.anchor_date +
                        '. Widen the filters or pick a different period.';
                    return;
                }

                const direction = c.trend === 'rising' ? 'rising'
                    : c.trend === 'falling' ? 'falling' : 'broadly flat';

                const sentences = [
                    'Over the ' + data.weeks_observed + ' weeks ending ' + data.anchor_date + ', ' +
                    c.historical_count + ' incidents were recorded — an average of ' +
                    c.weekly_average + ' per week, with a ' + direction + ' trend (' +
                    (c.slope_per_week >= 0 ? '+' : '') + c.slope_per_week + ' incidents per week, R² ' + c.r_squared + ').',

                    'Projected forward ' + data.forecast_days + ' days, that trend implies about ' +
                    c.predicted_count + ' incidents against a baseline of ' + c.baseline_count +
                    ' — a change of ' + signed(c.change_percent) + '.',
                ];

                const top = (data.streets || [])[0];
                if (top) {
                    sentences.push(
                        top.street + ' carries the largest projected volume (' + top.predicted_count +
                        ', ' + signed(top.change_percent) + ' against its own baseline), with ' +
                        (top.top_category || 'mixed types') + ' the most frequent type recorded there.'
                    );
                }

                if (c.r_squared < 0.3) {
                    sentences.push(
                        'The linear fit is weak, so this is closer to a restatement of the recent average than a trend call. ' +
                        'Treat the direction as a prompt to look, not as a conclusion.'
                    );
                }

                $('forecastSummaryText').textContent = sentences.join(' ');
            }

            function exportCsv() {
                if (!forecast) {
                    setStatus('Nothing to export yet — generate a forecast first.', 'warn');
                    return;
                }

                const q = (value) => '"' + String(value ?? '').replace(/"/g, '""') + '"';
                const lines = [];

                // Provenance first: an exported file outlives the screen it came from.
                lines.push(q('Crime Forecast export'));
                lines.push(q('Method') + ',' + q(forecast.method));
                lines.push(q('Note') + ',' + q(forecast.confidence_note));
                lines.push(q('Forecast origin') + ',' + q(forecast.anchor_date));
                lines.push(q('Historical window (days)') + ',' + q(forecast.historical_days));
                lines.push(q('Forecast horizon (days)') + ',' + q(forecast.forecast_days));
                lines.push(q('Recorded incidents in window') + ',' + q(forecast.citywide.historical_count));
                (forecast.notes || []).forEach((note) => lines.push(q('Caveat') + ',' + q(note)));
                lines.push('');

                lines.push(q('Weekly projection'));
                lines.push(['Period', 'Days', 'Projected', 'Lower 95%', 'Upper 95%', 'Baseline', 'Change %', 'Risk level', 'Confidence %', 'Most frequent type'].map(q).join(','));
                (forecast.timeline.projection || []).forEach((row) => {
                    lines.push([row.range, row.days, row.predicted, row.lower, row.upper, row.baseline,
                        row.change_percent, row.risk_level, row.confidence, row.top_category || ''].map(q).join(','));
                });
                lines.push('');

                lines.push(q('Street projections'));
                lines.push(['Street', 'Recorded', 'Baseline', 'Projected', 'Lower 95%', 'Upper 95%', 'Change %', 'Risk level', 'Confidence %', 'R squared', 'Leading type'].map(q).join(','));
                (forecast.streets || []).forEach((row) => {
                    lines.push([row.street, row.historical_count, row.baseline_count, row.predicted_count,
                        row.lower, row.upper, row.change_percent, row.risk_level, row.confidence,
                        row.r_squared, row.top_category || ''].map(q).join(','));
                });
                if (forecast.streets_excluded > 0) {
                    lines.push('');
                    lines.push(q('Excluded streets (below minimum sample)') + ',' + q(forecast.streets_excluded));
                }

                const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'crime_forecast_' + forecast.anchor_date + '_' + forecast.forecast_days + 'd.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }
        })();
    </script>
@endsection
