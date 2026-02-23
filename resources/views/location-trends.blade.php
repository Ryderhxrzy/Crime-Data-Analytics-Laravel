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

        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            setupEventListeners();
            generateTrends();
        });

        function initializeCharts() {
            // Main Trend Chart
            const trendCtx = document.getElementById('locationTrendChart')?.getContext('2d');
            if (trendCtx) {
                locationTrendChart = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6'],
                        datasets: [
                            {
                                label: 'Barangay A',
                                data: [25, 30, 28, 35, 40, 45],
                                borderColor: '#ef4444',
                                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Barangay B',
                                data: [20, 22, 20, 18, 15, 12],
                                borderColor: '#22c55e',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Barangay C',
                                data: [30, 28, 30, 32, 31, 30],
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }

            // Comparison Chart
            const compCtx = document.getElementById('comparisonChart')?.getContext('2d');
            if (compCtx) {
                comparisonChart = new Chart(compCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Barangay A', 'Barangay B', 'Barangay C', 'Barangay D', 'Barangay E'],
                        datasets: [{
                            label: 'Current Period',
                            data: [45, 12, 30, 28, 35],
                            backgroundColor: '#274d4c'
                        },
                        {
                            label: 'Previous Period',
                            data: [30, 20, 28, 25, 32],
                            backgroundColor: '#9ca3af'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' }
                        }
                    }
                });
            }

            // Seasonal Chart
            const seasonalCtx = document.getElementById('seasonalChart')?.getContext('2d');
            if (seasonalCtx) {
                seasonalChart = new Chart(seasonalCtx, {
                    type: 'radar',
                    data: {
                        labels: ['Barangay A', 'Barangay B', 'Barangay C', 'Barangay D', 'Barangay E'],
                        datasets: [{
                            label: 'Q1 2024',
                            data: [65, 45, 70, 50, 60],
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.2)'
                        },
                        {
                            label: 'Q2 2024',
                            data: [55, 35, 75, 45, 55],
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.2)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
        }

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

        function generateTrends() {
            console.log('Generating location trends...');

            // Update summary cards
            document.getElementById('increasingCount').textContent = Math.floor(Math.random() * 5 + 3);
            document.getElementById('decreasingCount').textContent = Math.floor(Math.random() * 4 + 2);
            document.getElementById('fastestGrowing').textContent = 'Barangay ' + String.fromCharCode(65 + Math.floor(Math.random() * 5));
            document.getElementById('stableLocation').textContent = 'Barangay ' + String.fromCharCode(65 + Math.floor(Math.random() * 5));

            // Update trend table
            updateTrendTable();

            // Update migration data
            updateHotspotMigration();

            // Update charts
            if (locationTrendChart) locationTrendChart.update();
            if (comparisonChart) comparisonChart.update();
            if (seasonalChart) seasonalChart.update();
        }

        function updateTrendTable() {
            const barangays = ['Barangay A', 'Barangay B', 'Barangay C', 'Barangay D', 'Barangay E'];
            const tbody = document.getElementById('trendTableBody');
            tbody.innerHTML = '';

            barangays.forEach(barangay => {
                const incidents = Math.floor(Math.random() * 50 + 10);
                const change = Math.floor(Math.random() * 30 - 15);
                const trend = change > 5 ? '📈' : change < -5 ? '📉' : '→';
                const color = change > 5 ? 'text-red-600' : change < -5 ? 'text-green-600' : 'text-gray-600';

                tbody.innerHTML += `
                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">${barangay}</td>
                        <td class="px-4 py-3 text-gray-700">${incidents}</td>
                        <td class="px-4 py-3 text-xl">${trend}</td>
                        <td class="px-4 py-3 font-semibold ${color}">${change > 0 ? '+' : ''}${change}%</td>
                    </tr>
                `;
            });
        }

        function updateHotspotMigration() {
            const barangays = ['Barangay A', 'Barangay B', 'Barangay C', 'Barangay D', 'Barangay E'];

            const gaining = barangays.slice(0, 2);
            const losing = barangays.slice(2, 4);
            const stable = barangays.slice(4);

            document.getElementById('gainingAreas').innerHTML = gaining.map(b =>
                `<div class="flex items-center gap-2"><span class="text-red-600">↑</span><span>${b}</span></div>`
            ).join('');

            document.getElementById('losingAreas').innerHTML = losing.map(b =>
                `<div class="flex items-center gap-2"><span class="text-green-600">↓</span><span>${b}</span></div>`
            ).join('');

            document.getElementById('stableAreas').innerHTML = stable.map(b =>
                `<div class="flex items-center gap-2"><span class="text-blue-600">→</span><span>${b}</span></div>`
            ).join('');
        }
    </script>
@endsection
