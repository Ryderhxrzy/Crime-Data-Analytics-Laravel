@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

@extends('layouts.app')
@section('title', 'Location Trends')
@section('content')
    <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
        <!-- Page Header -->
        <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                        <i class="fas fa-map-marked-alt mr-3" style="color: #274d4c;"></i>Location Trends
                    </h1>
                    <p class="text-gray-600 mt-1 text-sm lg:text-base">
                        Street-level crime trends across Barangay San Agustin, Quezon City — which streets are rising,
                        which are cooling down, and what is being committed on each one.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="exportStreetReport()"
                            class="px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors flex items-center gap-2 text-sm font-semibold">
                        <i class="fas fa-file-csv"></i>
                        <span>Export street report</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters - Standard Design -->
        <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
            <div class="mb-4 pb-4 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-900">
                    <i class="fas fa-filter mr-2 text-alertara-700"></i>Location Filters
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Time Period -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Time Period</label>
                    <select id="timePeriod" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="all" selected>All Time</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="180">Last 6 Months</option>
                    </select>
                </div>

                <!-- Street -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Street</label>
                    <select id="street" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Streets</option>
                        @if(isset($streets))
                            @foreach($streets as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Crime Type -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Crime Type</label>
                    <select id="crimeType" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Types</option>
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

                <!-- Reset Button -->
                <div class="flex items-end">
                    <button onclick="resetFilters()" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-redo"></i>
                        <span>Reset</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Load / error banner -->
        <div id="locationStatusBanner" class="hidden mb-6 rounded-lg border p-4 text-sm"></div>

        <!-- Trend Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-alertara-50 to-alertara-100 border border-alertara-200 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3"><i class="fas fa-road mr-1"></i> Streets with Incidents</h3>
                <p class="text-3xl font-bold text-alertara-700 mb-2" id="streetCount">0</p>
                <p class="text-xs text-gray-600">Streets recorded in this selection</p>
            </div>
            <div class="bg-gradient-to-br from-slate-50 to-slate-100 border border-slate-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3"><i class="fas fa-layer-group mr-1"></i> Incidents</h3>
                <p class="text-3xl font-bold text-slate-700 mb-2" id="totalIncidents">0</p>
                <p class="text-xs text-gray-600">Total for the selected period</p>
            </div>
            <div class="bg-gradient-to-br from-teal-50 to-teal-100 border border-teal-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3"><i class="fas fa-check-circle mr-1"></i> Clearance Rate</h3>
                <p class="text-3xl font-bold text-teal-700 mb-2" id="clearanceRate">0%</p>
                <p class="text-xs text-gray-600">Share of cleared cases</p>
            </div>
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">🔥 Busiest Street</h3>
                <p class="text-xl font-bold text-purple-700 mb-2" id="busiestStreet">--</p>
                <p class="text-xs text-gray-600">Most incidents in this period</p>
            </div>

            <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">📈 Streets Trending Up</h3>
                <p class="text-3xl font-bold text-red-700 mb-2" id="increasingCount">0</p>
                <p class="text-xs text-gray-600">More incidents than the previous window</p>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">📉 Streets Trending Down</h3>
                <p class="text-3xl font-bold text-green-700 mb-2" id="decreasingCount">0</p>
                <p class="text-xs text-gray-600">Fewer incidents than the previous window</p>
            </div>
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">🚀 Fastest Growing Street</h3>
                <p class="text-xl font-bold text-orange-700 mb-2" id="fastestGrowing">--</p>
                <p class="text-xs text-gray-600">Highest increase rate</p>
            </div>
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-300 rounded-lg p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">→ Most Stable Street</h3>
                <p class="text-xl font-bold text-blue-700 mb-2" id="stableLocation">--</p>
                <p class="text-xs text-gray-600">Minimal change in incidents</p>
            </div>
        </div>

        <!-- Main Trend Chart -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-chart-line mr-3" style="color: #274d4c;"></i>
                Street Trend Analysis
            </h2>
            <p class="text-sm text-gray-600 mb-4">Monthly incident counts for the busiest streets in the selection</p>
            <div style="position: relative; height: 400px;">
                <canvas id="locationTrendChart"></canvas>
            </div>
        </div>

        <!-- Trend Details -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2" style="color: #274d4c;"></i>
                    Street Trend Summary
                </h3>
                <span class="text-xs text-gray-500" id="trendTableNote"></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-300 bg-gray-50">
                            <th class="px-4 py-2 text-left font-bold text-gray-900">Street</th>
                            <th class="px-4 py-2 text-left font-bold text-gray-900">Incidents</th>
                            <th class="px-4 py-2 text-left font-bold text-gray-900">Leading Crime Type</th>
                            <th class="px-4 py-2 text-left font-bold text-gray-900">Cleared</th>
                            <th class="px-4 py-2 text-left font-bold text-gray-900">Last Incident</th>
                            <th class="px-4 py-2 text-left font-bold text-gray-900">Trend</th>
                            <th class="px-4 py-2 text-left font-bold text-gray-900">Change</th>
                        </tr>
                    </thead>
                    <tbody id="trendTableBody">
                        <!-- Dynamically populated -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Current vs previous -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-exchange-alt mr-2" style="color: #274d4c;"></i>
                    Current vs Previous Window
                </h3>
                <p class="text-sm text-gray-600 mb-4">Two equal windows compared street by street</p>
                <div style="position: relative; height: 350px;">
                    <canvas id="comparisonChart"></canvas>
                </div>
            </div>

            <!-- Crime mix per street -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-layer-group mr-2" style="color: #274d4c;"></i>
                    Crime Mix per Street
                </h3>
                <p class="text-sm text-gray-600 mb-4">What is actually being committed on the busiest streets</p>
                <div style="position: relative; height: 350px;">
                    <canvas id="crimeMixChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Hotspot Migration -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-arrows-alt mr-2" style="color: #274d4c;"></i>
                Hotspot Migration
            </h3>
            <p class="text-sm text-gray-600 mb-4">Which streets are gaining or losing incidents</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="border-l-4 border-red-500 pl-4">
                    <h4 class="font-bold text-red-700 mb-3">📍 Streets Gaining Incidents</h4>
                    <div id="gainingAreas" class="space-y-2 text-sm"></div>
                </div>
                <div class="border-l-4 border-green-500 pl-4">
                    <h4 class="font-bold text-green-700 mb-3">📍 Streets Losing Incidents</h4>
                    <div id="losingAreas" class="space-y-2 text-sm"></div>
                </div>
                <div class="border-l-4 border-blue-500 pl-4">
                    <h4 class="font-bold text-blue-700 mb-3">📍 Stable Streets</h4>
                    <div id="stableAreas" class="space-y-2 text-sm"></div>
                </div>
            </div>
        </div>

        <!-- Seasonal Patterns -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-calendar-alt mr-2" style="color: #274d4c;"></i>
                Seasonal Patterns by Street
            </h3>
            <p class="text-sm text-gray-600 mb-4">Quarterly spread for the busiest streets in the most recent recorded year</p>
            <div style="position: relative; height: 350px;">
                <canvas id="seasonalChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        let locationTrendChart, comparisonChart, seasonalChart, crimeMixChart;
        let locationData = null;   // latest payload, also used by the CSV export

        const SERIES_COLORS = [
            { border: '#ef4444', bg: 'rgba(239, 68, 68, 0.1)' },
            { border: '#3b82f6', bg: 'rgba(59, 130, 246, 0.1)' },
            { border: '#22c55e', bg: 'rgba(34, 197, 94, 0.1)' },
            { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.1)' },
            { border: '#8b5cf6', bg: 'rgba(139, 92, 246, 0.1)' }
        ];

        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            generateTrends();
        });

        function setupEventListeners() {
            ['timePeriod', 'street', 'crimeType', 'caseStatus'].forEach(id => {
                document.getElementById(id)?.addEventListener('change', generateTrends);
            });
        }

        function resetFilters() {
            document.getElementById('timePeriod').value = 'all';
            document.getElementById('street').value = '';
            document.getElementById('crimeType').value = '';
            document.getElementById('caseStatus').value = '';
            generateTrends();
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (c) => (
                { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
            ));
        }

        // A failed or empty load has to be visible on the page, not only in the console.
        function setStatusBanner(kind, message) {
            const banner = document.getElementById('locationStatusBanner');
            if (!banner) return;

            if (!kind) {
                banner.className = 'hidden mb-6 rounded-lg border p-4 text-sm';
                banner.innerHTML = '';
                return;
            }

            const styles = {
                loading: 'bg-gray-50 border-gray-200 text-gray-700',
                empty: 'bg-yellow-50 border-yellow-200 text-yellow-800',
                error: 'bg-red-50 border-red-200 text-red-800'
            };
            const icons = {
                loading: 'fa-spinner fa-spin',
                empty: 'fa-circle-info',
                error: 'fa-triangle-exclamation'
            };

            banner.className = `mb-6 rounded-lg border p-4 text-sm ${styles[kind]}`;
            banner.innerHTML = `<i class="fas ${icons[kind]} mr-2"></i>${message}`;
        }

        // Fetch real street-level trend analytics from the database
        async function generateTrends() {
            const params = new URLSearchParams({
                time_period: document.getElementById('timePeriod').value,
                street: document.getElementById('street').value,
                crime_type: document.getElementById('crimeType').value,
                case_status: document.getElementById('caseStatus').value
            });

            setStatusBanner('loading', 'Loading street trends...');

            try {
                const response = await fetch(`/dashboard/location-trends-data?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || `Request failed (HTTP ${response.status})`);
                }

                locationData = data;

                updateSummaryCards(data.summary);
                updateTrendTable(data.locations, data.window_days);
                updateHotspotMigration(data.migration);
                renderTrendChart(data.series);
                renderComparisonChart(data.locations);
                renderCrimeMixChart(data.crime_mix);
                renderSeasonalChart(data.seasonal);

                setStatusBanner(
                    data.summary.total_incidents ? null : 'empty',
                    'No incidents match the selected filters. Widen the time period or clear a filter.'
                );
            } catch (error) {
                console.error('Error loading location trends:', error);
                setStatusBanner('error', `Could not load street trends. ${error.message}`);
                document.getElementById('trendTableBody').innerHTML =
                    '<tr><td colspan="7" class="px-4 py-6 text-center text-red-500">Failed to load trend data. Please try again.</td></tr>';
            }
        }

        function updateSummaryCards(summary) {
            document.getElementById('streetCount').textContent = summary.street_count;
            document.getElementById('totalIncidents').textContent = summary.total_incidents;
            document.getElementById('clearanceRate').textContent = `${summary.clearance_rate}%`;
            document.getElementById('increasingCount').textContent = summary.increasing_count;
            document.getElementById('decreasingCount').textContent = summary.decreasing_count;

            document.getElementById('busiestStreet').textContent = summary.busiest
                ? `${summary.busiest.name} (${summary.busiest.current})`
                : 'None';
            document.getElementById('fastestGrowing').textContent = summary.fastest_growing
                ? `${summary.fastest_growing.name} (+${summary.fastest_growing.change_percent}%)`
                : 'None';
            document.getElementById('stableLocation').textContent = summary.most_stable
                ? `${summary.most_stable.name} (${summary.most_stable.change_percent > 0 ? '+' : ''}${summary.most_stable.change_percent}%)`
                : 'None';
        }

        function updateTrendTable(locations, windowDays) {
            const tbody = document.getElementById('trendTableBody');
            const note = document.getElementById('trendTableNote');

            note.textContent = windowDays
                ? `Trend compares the last ${windowDays} days against the ${windowDays} days before them`
                : '';

            if (!locations.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No incidents recorded for the selected filters.</td></tr>';
                return;
            }

            tbody.innerHTML = locations.map(loc => {
                const trendIcon = loc.trend === 'increasing' ? '📈' : loc.trend === 'decreasing' ? '📉' : '→';
                const color = loc.trend === 'increasing' ? 'text-red-600' : loc.trend === 'decreasing' ? 'text-green-600' : 'text-gray-600';
                const category = loc.top_category
                    ? `<span class="inline-flex items-center gap-2">
                           <span style="width:10px;height:10px;border-radius:9999px;background:${loc.top_category_color};display:inline-block;"></span>
                           ${escapeHtml(loc.top_category)} <span class="text-gray-400">(${loc.top_category_count})</span>
                       </span>`
                    : '<span class="text-gray-400">--</span>';

                return `
                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">${escapeHtml(loc.name)}</td>
                        <td class="px-4 py-3 text-gray-700">${loc.current}</td>
                        <td class="px-4 py-3 text-gray-700">${category}</td>
                        <td class="px-4 py-3 text-gray-700">${loc.cleared} <span class="text-gray-400">(${loc.clearance_rate}%)</span></td>
                        <td class="px-4 py-3 text-gray-700">${escapeHtml(loc.last_incident || '--')}</td>
                        <td class="px-4 py-3 text-xl">${trendIcon}</td>
                        <td class="px-4 py-3 font-semibold ${color}">${loc.change_percent > 0 ? '+' : ''}${loc.change_percent}%</td>
                    </tr>
                `;
            }).join('');
        }

        function updateHotspotMigration(migration) {
            const renderList = (areas, arrow, colorClass, suffix) => areas.length
                ? areas.map(a => `<div class="flex items-center gap-2"><span class="${colorClass}">${arrow}</span><span>${escapeHtml(a.name)} (${suffix(a)})</span></div>`).join('')
                : '<div class="text-gray-400 italic">None in this period</div>';

            document.getElementById('gainingAreas').innerHTML =
                renderList(migration.gaining, '↑', 'text-red-600', a => `+${a.change_percent}%`);
            document.getElementById('losingAreas').innerHTML =
                renderList(migration.losing, '↓', 'text-green-600', a => `${a.change_percent}%`);
            document.getElementById('stableAreas').innerHTML =
                renderList(migration.stable, '→', 'text-blue-600', a => `${a.current} incident${a.current === 1 ? '' : 's'}`);
        }

        function renderTrendChart(series) {
            const ctx = document.getElementById('locationTrendChart')?.getContext('2d');
            if (!ctx) return;
            if (locationTrendChart) locationTrendChart.destroy();

            locationTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: series.labels,
                    datasets: series.datasets.map((s, i) => ({
                        label: s.name,
                        data: s.values,
                        borderColor: SERIES_COLORS[i % SERIES_COLORS.length].border,
                        backgroundColor: SERIES_COLORS[i % SERIES_COLORS.length].bg,
                        tension: 0.4,
                        fill: true
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        function renderComparisonChart(locations) {
            const ctx = document.getElementById('comparisonChart')?.getContext('2d');
            if (!ctx) return;
            if (comparisonChart) comparisonChart.destroy();

            const top = locations.slice(0, 8);
            comparisonChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: top.map(l => l.name),
                    datasets: [
                        { label: 'Current Window', data: top.map(l => l.trend_current), backgroundColor: '#274d4c' },
                        { label: 'Previous Window', data: top.map(l => l.trend_previous), backgroundColor: '#9ca3af' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 10 } } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        function renderCrimeMixChart(mix) {
            const ctx = document.getElementById('crimeMixChart')?.getContext('2d');
            if (!ctx) return;
            if (crimeMixChart) crimeMixChart.destroy();

            crimeMixChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: mix.labels,
                    datasets: mix.datasets.map(d => ({
                        label: d.name,
                        data: d.values,
                        backgroundColor: d.color
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        x: { stacked: true, ticks: { maxRotation: 45, minRotation: 45, font: { size: 10 } } },
                        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        function renderSeasonalChart(seasonal) {
            const ctx = document.getElementById('seasonalChart')?.getContext('2d');
            if (!ctx) return;
            if (seasonalChart) seasonalChart.destroy();

            const quarterColors = [
                { border: '#ef4444', bg: 'rgba(239, 68, 68, 0.2)' },
                { border: '#3b82f6', bg: 'rgba(59, 130, 246, 0.2)' },
                { border: '#22c55e', bg: 'rgba(34, 197, 94, 0.2)' },
                { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.2)' }
            ];

            seasonalChart = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: seasonal.areas,
                    datasets: seasonal.quarters.map((q, i) => ({
                        label: q.quarter,
                        data: q.values,
                        borderColor: quarterColors[i % quarterColors.length].border,
                        backgroundColor: quarterColors[i % quarterColors.length].bg
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // Street report: the table exactly as filtered, as a CSV file
        function exportStreetReport() {
            if (!locationData || !locationData.locations.length) {
                alert('There is nothing to export for the current filters.');
                return;
            }

            const period = document.getElementById('timePeriod').selectedOptions[0].text;
            const streetPick = document.getElementById('street').value || 'All streets';
            const typePick = document.getElementById('crimeType').selectedOptions[0].text;
            const statusPick = document.getElementById('caseStatus').selectedOptions[0].text;

            const cell = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
            const rows = [
                ['Location Trends - street report'],
                ['Generated', new Date().toLocaleString()],
                ['Period', period],
                ['Street', streetPick],
                ['Crime type', typePick],
                ['Case status', statusPick],
                [],
                ['Street', 'Incidents', 'Leading crime type', 'Leading type count', 'Cleared', 'Clearance rate %',
                 'Last incident', 'Trend', 'Change %', 'Current window', 'Previous window']
            ].map(r => r.map(cell).join(','));

            locationData.locations.forEach(loc => {
                rows.push([
                    loc.name, loc.current, loc.top_category || '', loc.top_category_count,
                    loc.cleared, loc.clearance_rate, loc.last_incident || '', loc.trend,
                    loc.change_percent, loc.trend_current, loc.trend_previous
                ].map(cell).join(','));
            });

            const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `location-trends-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }
    </script>
@endsection
