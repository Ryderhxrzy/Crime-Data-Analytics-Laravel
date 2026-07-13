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
                    <p class="text-gray-600 mt-1 text-sm lg:text-base">Track crime trends and patterns across different locations and barangays over time</p>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <!-- Time Period -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Time Period</label>
                    <select id="timePeriod" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="30">Last 30 Days</option>
                        <option value="90" selected>Last 90 Days</option>
                        <option value="180">Last 6 Months</option>
                        <option value="all">All Time</option>
                    </select>
                </div>

                <!-- Barangay -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Barangay</label>
                    <select id="barangay" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Barangays</option>
                        @if(isset($barangays))
                            @foreach($barangays as $b)
                                <option value="{{ $b->id }}">{{ $b->barangay_name }}</option>
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
                    <button onclick="resetFilters()" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-redo"></i>
                        <span>Reset</span>
                    </button>
                </div>

                <!-- Generate Button -->
                <div class="flex items-end">
                    <button onclick="generateTrends()" class="w-full px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors font-medium flex items-center justify-center gap-2">
                        <i class="fas fa-chart-line"></i>
                        <span>Analyze</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Trend Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Increasing Trends -->
            <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-300 rounded-lg p-6">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900">📈 Areas with Increasing Trends</h3>
                </div>
                <p class="text-3xl font-bold text-red-700 mb-2" id="increasingCount">0</p>
                <p class="text-xs text-gray-600">Locations showing upward trend</p>
            </div>

            <!-- Decreasing Trends -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-300 rounded-lg p-6">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900">📉 Areas with Decreasing Trends</h3>
                </div>
                <p class="text-3xl font-bold text-green-700 mb-2" id="decreasingCount">0</p>
                <p class="text-xs text-gray-600">Locations showing downward trend</p>
            </div>

            <!-- Fastest Growing -->
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-300 rounded-lg p-6">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900">🚀 Fastest Growing Area</h3>
                </div>
                <p class="text-2xl font-bold text-orange-700 mb-2" id="fastestGrowing">--</p>
                <p class="text-xs text-gray-600">Highest increase rate</p>
            </div>

            <!-- Most Stable -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-300 rounded-lg p-6">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900">→ Most Stable Location</h3>
                </div>
                <p class="text-2xl font-bold text-blue-700 mb-2" id="stableLocation">--</p>
                <p class="text-xs text-gray-600">Minimal change in incidents</p>
            </div>
        </div>

        <!-- Main Trend Chart -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-chart-line mr-3" style="color: #274d4c;"></i>
                Location Trend Analysis
            </h2>
            <p class="text-sm text-gray-600 mb-4">Incident trends for selected locations over the time period</p>
            <div style="position: relative; height: 400px;">
                <canvas id="locationTrendChart"></canvas>
            </div>
        </div>

        <!-- Trend Details Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Location Trend Table -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-list mr-2" style="color: #274d4c;"></i>
                    Location Trend Summary
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-300 bg-gray-50">
                                <th class="px-4 py-2 text-left font-bold text-gray-900">Location</th>
                                <th class="px-4 py-2 text-left font-bold text-gray-900">Incidents</th>
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

            <!-- Comparative Analysis -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-exchange-alt mr-2" style="color: #274d4c;"></i>
                    Comparison Chart
                </h3>
                <div style="position: relative; height: 350px;">
                    <canvas id="comparisonChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Hotspot Migration -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-arrows-alt mr-2" style="color: #274d4c;"></i>
                Hotspot Migration
            </h3>
            <p class="text-sm text-gray-600 mb-4">Which areas are gaining or losing incidents</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Gaining Areas -->
                <div class="border-l-4 border-red-500 pl-4">
                    <h4 class="font-bold text-red-700 mb-3">📍 Areas Gaining Incidents</h4>
                    <div id="gainingAreas" class="space-y-2 text-sm">
                        <!-- Populated dynamically -->
                    </div>
                </div>

                <!-- Losing Areas -->
                <div class="border-l-4 border-green-500 pl-4">
                    <h4 class="font-bold text-green-700 mb-3">📍 Areas Losing Incidents</h4>
                    <div id="losingAreas" class="space-y-2 text-sm">
                        <!-- Populated dynamically -->
                    </div>
                </div>

                <!-- Stable Areas -->
                <div class="border-l-4 border-blue-500 pl-4">
                    <h4 class="font-bold text-blue-700 mb-3">📍 Stable Areas</h4>
                    <div id="stableAreas" class="space-y-2 text-sm">
                        <!-- Populated dynamically -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Seasonal Patterns -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-calendar-alt mr-2" style="color: #274d4c;"></i>
                Seasonal Patterns by Location
            </h3>
            <div style="position: relative; height: 350px;">
                <canvas id="seasonalChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        let locationTrendChart, comparisonChart, seasonalChart;

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
            document.getElementById('timePeriod').addEventListener('change', generateTrends);
            document.getElementById('barangay').addEventListener('change', generateTrends);
            document.getElementById('crimeType').addEventListener('change', generateTrends);
            document.getElementById('caseStatus').addEventListener('change', generateTrends);
        }

        function resetFilters() {
            document.getElementById('timePeriod').value = '90';
            document.getElementById('barangay').value = '';
            document.getElementById('crimeType').value = '';
            document.getElementById('caseStatus').value = '';
            generateTrends();
        }

        // Fetch real location trend analytics from the database
        async function generateTrends() {
            const params = new URLSearchParams({
                time_period: document.getElementById('timePeriod').value,
                barangay: document.getElementById('barangay').value,
                crime_type: document.getElementById('crimeType').value,
                case_status: document.getElementById('caseStatus').value
            });

            try {
                const response = await fetch(`/dashboard/location-trends-data?${params}`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Request failed');
                }

                updateSummaryCards(data.summary);
                updateTrendTable(data.locations);
                updateHotspotMigration(data.migration);
                renderTrendChart(data.series);
                renderComparisonChart(data.locations);
                renderSeasonalChart(data.seasonal);
            } catch (error) {
                console.error('Error loading location trends:', error);
                document.getElementById('trendTableBody').innerHTML =
                    '<tr><td colspan="4" class="px-4 py-6 text-center text-red-500">Failed to load trend data. Please try again.</td></tr>';
            }
        }

        function updateSummaryCards(summary) {
            document.getElementById('increasingCount').textContent = summary.increasing_count;
            document.getElementById('decreasingCount').textContent = summary.decreasing_count;
            document.getElementById('fastestGrowing').textContent = summary.fastest_growing
                ? `${summary.fastest_growing.name} (+${summary.fastest_growing.change_percent}%)`
                : 'None';
            document.getElementById('stableLocation').textContent = summary.most_stable
                ? summary.most_stable.name
                : 'None';
        }

        function updateTrendTable(locations) {
            const tbody = document.getElementById('trendTableBody');

            if (!locations.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No incidents recorded for the selected filters.</td></tr>';
                return;
            }

            tbody.innerHTML = locations.slice(0, 10).map(loc => {
                const trendIcon = loc.trend === 'increasing' ? '📈' : loc.trend === 'decreasing' ? '📉' : '→';
                const color = loc.trend === 'increasing' ? 'text-red-600' : loc.trend === 'decreasing' ? 'text-green-600' : 'text-gray-600';
                return `
                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">${loc.name}</td>
                        <td class="px-4 py-3 text-gray-700">${loc.current}</td>
                        <td class="px-4 py-3 text-xl">${trendIcon}</td>
                        <td class="px-4 py-3 font-semibold ${color}">${loc.change_percent > 0 ? '+' : ''}${loc.change_percent}%</td>
                    </tr>
                `;
            }).join('');
        }

        function updateHotspotMigration(migration) {
            const renderList = (areas, arrow, colorClass, suffix) => areas.length
                ? areas.map(a => `<div class="flex items-center gap-2"><span class="${colorClass}">${arrow}</span><span>${a.name} (${suffix(a)})</span></div>`).join('')
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
                        { label: 'Current Period', data: top.map(l => l.current), backgroundColor: '#274d4c' },
                        { label: 'Previous Period', data: top.map(l => l.previous), backgroundColor: '#9ca3af' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
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
    </script>
@endsection
