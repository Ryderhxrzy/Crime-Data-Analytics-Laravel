<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Crime Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <!-- Header Component -->
    @include('components.header')

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

    <!-- Sidebar -->
    @include('components.sidebar')

    <!-- Main Content -->
    <main class="lg:ml-72 ml-0 lg:mt-16 mt-16 min-h-screen bg-gray-100">
        <div class="p-6">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Crime Analytics Dashboard</h1>
                <p class="text-gray-600 mt-2">Real-time crime statistics and analysis</p>
            </div>

            <!-- Key Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Incidents Card -->
                <div class="bg-white border-l-4 border-alertara-600 rounded-lg border border-gray-200 p-6 hover:border-alertara-400 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Incidents</p>
                            <p class="text-3xl font-bold text-alertara-700 mt-2">{{ $totalIncidents }}</p>
                        </div>
                        <div class="bg-alertara-100 p-3 rounded-full">
                            <i class="fas fa-chart-line text-alertara-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Cleared Incidents Card -->
                <div class="bg-white border-l-4 border-success-600 rounded-lg border border-gray-200 p-6 hover:border-success-400 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Cleared Cases</p>
                            <p class="text-3xl font-bold text-success-600 mt-2">{{ $clearedIncidents }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $clearanceRate }}% clearance rate</p>
                        </div>
                        <div class="bg-success-100 p-3 rounded-full">
                            <i class="fas fa-check-circle text-success-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Uncleared Incidents Card -->
                <div class="bg-white border-l-4 border-danger-600 rounded-lg border border-gray-200 p-6 hover:border-danger-400 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Uncleared Cases</p>
                            <p class="text-3xl font-bold text-danger-600 mt-2">{{ $unclearedIncidents }}</p>
                        </div>
                        <div class="bg-danger-100 p-3 rounded-full">
                            <i class="fas fa-exclamation-circle text-danger-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Active Alerts Card -->
                <div class="bg-white border-l-4 border-danger-500 rounded-lg border border-gray-200 p-6 hover:border-danger-400 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Active Alerts</p>
                            <p class="text-3xl font-bold text-danger-500 mt-2">{{ $activeAlerts }}</p>
                        </div>
                        <div class="bg-danger-100 p-3 rounded-full">
                            <i class="fas fa-bell text-danger-600 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Incidents by Category Chart -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Incidents by Category</h2>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Trends Chart -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Monthly Trends (Last 12 Months)</h2>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Crime Status Distribution -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Crime Status Distribution</h2>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Clearance Status Pie Chart -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Case Clearance Status</h2>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="clearanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Barangays Chart -->
            <div class="grid grid-cols-1 gap-6 mb-8">
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Top 10 Barangays by Incident Count</h2>
                    <div style="position: relative; height: 350px; width: 100%;">
                        <canvas id="barangayChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 3 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Alert Severity Distribution -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Alert Severity Distribution</h2>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="severityChart"></canvas>
                    </div>
                </div>

                <!-- Latest Alerts -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Latest Alerts</h2>
                    <div class="space-y-4">
                        @forelse($latestAlerts as $alert)
                            <div class="border-l-4 border-danger-500 bg-gray-50 p-3 rounded">
                                <p class="font-semibold text-gray-900 text-sm">{{ $alert->alert_title }}</p>
                                <p class="text-xs text-gray-600 mt-1">{{ $alert->barangay->barangay_name ?? 'Unknown' }}</p>
                                <span class="inline-block mt-2 px-2 py-1 rounded text-xs font-semibold bg-danger-100 text-danger-700">
                                    {{ ucfirst($alert->severity) }}
                                </span>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-4">No active alerts</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Filtered Charts Section with Year + Month Filter -->
            <div class="mb-8">
                <!-- Filter Bar -->
                <div class="flex flex-wrap gap-4 items-center mb-6 bg-white border border-gray-200 rounded-lg p-4 hover:border-alertara-300 transition-colors">
                    <span class="font-semibold text-gray-700">Filter:</span>

                    <!-- Year Dropdown -->
                    <select id="filterYear" class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-alertara-600">
                        @for($year = 2020; $year <= now()->year; $year++)
                            <option value="{{ $year }}" {{ $year === now()->year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endfor
                    </select>

                    <!-- Month Dropdown -->
                    <select id="filterMonth" class="border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-alertara-600">
                        <option value="">All Months</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ \Carbon\Carbon::createFromFormat('m', $m)->format('F') }}</option>
                        @endfor
                    </select>

                    <!-- Apply Button -->
                    <button id="applyFilter" class="bg-alertara-600 hover:bg-alertara-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors">
                        Apply
                    </button>

                    <!-- Reset Button -->
                    <button id="resetFilter" class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded text-sm font-medium transition-colors">
                        Reset
                    </button>

                    <!-- Loading Indicator -->
                    <span id="filterLoader" class="hidden text-sm text-gray-500">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Loading...
                    </span>
                </div>

                <!-- Charts Row 1: Monthly Trend + Crime Types -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Monthly Crime Trend -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Monthly Crime Trend</h2>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>

                    <!-- Crime Type Distribution -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Crime Type Distribution</h2>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="crimeTypesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2: Weekly + Peak Hours -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Weekly Distribution -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Weekly Distribution (by Day)</h2>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>

                    <!-- Peak Crime Hours -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Peak Crime Hours (24-Hour Analysis)</h2>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="peakHoursChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Incidents Table -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 hover:border-alertara-300 transition-colors">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Recent Incidents</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-alertara-200 bg-gray-50">
                                <th class="text-left py-3 px-4 font-semibold text-gray-900">Code</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-900">Title</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-900">Category</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-900">Barangay</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-900">Date</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-900">Status</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-900">Clearance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentIncidents as $incident)
                                <tr class="border-b border-gray-200 hover:bg-alertara-50 transition-colors">
                                    <td class="py-3 px-4 text-gray-700">{{ $incident->incident_code }}</td>
                                    <td class="py-3 px-4 text-gray-700">{{ substr($incident->incident_title, 0, 30) }}...</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 bg-alertara-100 text-alertara-700 rounded-md text-xs font-semibold border border-alertara-200">
                                            {{ $incident->category->category_name ?? 'Unknown' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-gray-700">{{ $incident->barangay->barangay_name ?? 'Unknown' }}</td>
                                    <td class="py-3 px-4 text-gray-700">{{ $incident->incident_date ? $incident->incident_date->format('M d, Y') : 'N/A' }}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-md text-xs font-semibold border border-blue-200">
                                            {{ ucfirst($incident->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 {{ $incident->clearance_status === 'cleared' ? 'bg-success-100 text-success-700 border border-success-200' : 'bg-danger-100 text-danger-700 border border-danger-200' }} rounded-md text-xs font-semibold">
                                            {{ ucfirst($incident->clearance_status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-4 text-center text-gray-500">No recent incidents</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Location Trends Section -->
            <div id="location-trends" class="mb-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-map-location-dot mr-2" style="color: #274d4c;"></i>Location Trends Analysis
                    </h2>
                    <p class="text-gray-600">Geographic hotspots and barangay-level crime patterns</p>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Top 10 High-Risk Barangays</h3>
                    <div style="position: relative; height: 400px; width: 100%;">
                        <canvas id="locationTrendChart"></canvas>
                    </div>
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-red-50 border border-red-200 rounded p-4">
                            <p class="text-xs font-semibold text-red-900 mb-1">🔴 Highest Risk Area</p>
                            <p class="text-lg font-bold text-red-700" id="highestRiskBarangay">--</p>
                        </div>
                        <div class="bg-green-50 border border-green-200 rounded p-4">
                            <p class="text-xs font-semibold text-green-900 mb-1">🟢 Safest Area</p>
                            <p class="text-lg font-bold text-green-700" id="safestBarangay">--</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Crime Type Trends Section -->
            <div id="crime-type-trends" class="mb-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-shapes mr-2" style="color: #274d4c;"></i>Crime Type Trends Analysis
                    </h2>
                    <p class="text-gray-600">Emerging patterns in different crime categories</p>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Crime Type Distribution -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Crime Type Distribution</h3>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="crimeTypeDistChart"></canvas>
                        </div>
                        <p class="text-xs text-gray-600 mt-4">Breakdown of incidents by crime category</p>
                    </div>

                    <!-- Crime Type Trends Over Time -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Top 5 Crime Types Trend</h3>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="crimeTypeTrendChart"></canvas>
                        </div>
                        <p class="text-xs text-gray-600 mt-4">Monthly trend of the most common crimes</p>
                    </div>
                </div>

                <!-- Crime Type Insights -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">
                            <i class="fas fa-arrow-trend-up mr-2 text-danger-600"></i>Most Prevalent Crime
                        </h3>
                        <div class="space-y-3" id="mostPrevalentCrime">
                            <p class="text-gray-500">Loading...</p>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-lg p-6 hover:border-alertara-300 transition-colors">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">
                            <i class="fas fa-info-circle mr-2 text-alertara-600"></i>Crime Type Summary
                        </h3>
                        <div class="space-y-3" id="crimeTypeSummary">
                            <p class="text-gray-500">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('aside');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        sidebarToggle?.addEventListener('click', function() {
            sidebar?.classList.toggle('-translate-x-full');
            sidebarOverlay?.classList.toggle('hidden');
        });

        sidebarOverlay?.addEventListener('click', function() {
            sidebar?.classList.add('-translate-x-full');
            sidebarOverlay?.classList.add('hidden');
        });

        const sidebarLinks = sidebar?.querySelectorAll('a, button');
        sidebarLinks?.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 1024) {
                    sidebar?.classList.add('-translate-x-full');
                    sidebarOverlay?.classList.add('hidden');
                }
            });
        });

        // Wait for Chart.js to load
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') {
                console.error('Chart.js failed to load');
                return;
            }

            const chartColors = {
                primary: '#274d4c',
                success: '#22c55e',
                danger: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };

            // Category Chart
            const categoryCtx = document.getElementById('categoryChart')?.getContext('2d');
            if (categoryCtx) {
                new Chart(categoryCtx, {
                    type: 'bar',
                    data: {
                        labels: {!! $categoryLabels !!},
                        datasets: [{
                            label: 'Incident Count',
                            data: {!! $categoryData !!},
                            backgroundColor: chartColors.primary,
                            borderColor: chartColors.primary,
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'top' } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            // Monthly Trends Chart
            const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
            if (monthlyCtx) {
                new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: {!! $monthLabels !!},
                        datasets: [{
                            label: 'Incidents',
                            data: {!! $monthData !!},
                            borderColor: chartColors.primary,
                            backgroundColor: 'rgba(39, 77, 76, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: chartColors.primary,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'top' } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            // Status Distribution Chart
            const statusCtx = document.getElementById('statusChart')?.getContext('2d');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: {!! $statusLabels !!},
                        datasets: [{
                            data: {!! $statusData !!},
                            backgroundColor: [chartColors.primary, chartColors.warning, chartColors.success, chartColors.info],
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'bottom' } }
                    }
                });
            }

            // Clearance Chart
            const clearanceCtx = document.getElementById('clearanceChart')?.getContext('2d');
            if (clearanceCtx) {
                new Chart(clearanceCtx, {
                    type: 'pie',
                    data: {
                        labels: {!! $clearanceLabels !!},
                        datasets: [{
                            data: {!! $clearanceChartData !!},
                            backgroundColor: [chartColors.success, chartColors.danger],
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'bottom' } }
                    }
                });
            }

            // Barangay Chart
            const barangayCtx = document.getElementById('barangayChart')?.getContext('2d');
            if (barangayCtx) {
                new Chart(barangayCtx, {
                    type: 'bar',
                    data: {
                        labels: {!! $barangayLabels !!},
                        datasets: [{
                            label: 'Incident Count',
                            data: {!! $barangayData !!},
                            backgroundColor: chartColors.danger,
                            borderColor: chartColors.danger,
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'top' } },
                        scales: { x: { beginAtZero: true } }
                    }
                });
            }

            // Severity Chart
            const severityCtx = document.getElementById('severityChart')?.getContext('2d');
            const severityLabels = {!! $severityLabels !!};
            if (severityCtx && severityLabels && severityLabels.length > 0) {
                new Chart(severityCtx, {
                    type: 'radar',
                    data: {
                        labels: severityLabels,
                        datasets: [{
                            label: 'Alert Count',
                            data: {!! $severityData !!},
                            borderColor: chartColors.danger,
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            pointBackgroundColor: chartColors.danger,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'bottom' } },
                        scales: { r: { beginAtZero: true } }
                    }
                });
            }

            // Initialize filtered charts on page load
            loadFilteredCharts({{ now()->year }}, null);
        });

        // Variables to hold chart instances
        let trendChart, crimeTypesChart, weeklyChart, peakHoursChart;

        // Load filtered chart data via AJAX
        async function loadFilteredCharts(year, month) {
            const filterLoader = document.getElementById('filterLoader');
            filterLoader.classList.remove('hidden');

            try {
                const params = new URLSearchParams();
                params.append('year', year);
                if (month) params.append('month', month);

                const response = await fetch(`{{ route('dashboard.charts') }}?${params}`);
                const data = await response.json();

                updateCharts(data);
            } catch (error) {
                console.error('Error loading chart data:', error);
                alert('Failed to load chart data. Please try again.');
            } finally {
                filterLoader.classList.add('hidden');
            }
        }

        // Update all filtered charts with new data
        function updateCharts(data) {
            const chartColors = {
                primary: '#274d4c',
                success: '#22c55e',
                danger: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };

            // 1. Monthly Trend Chart
            const trendCtx = document.getElementById('trendChart')?.getContext('2d');
            if (trendCtx) {
                if (trendChart) trendChart.destroy();
                trendChart = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: data.monthlyTrend.labels,
                        datasets: [{
                            label: 'Incidents',
                            data: data.monthlyTrend.data,
                            borderColor: chartColors.primary,
                            backgroundColor: 'rgba(39, 77, 76, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: chartColors.primary,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'top' } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            // 2. Crime Types Chart (Doughnut)
            const crimeTypesCtx = document.getElementById('crimeTypesChart')?.getContext('2d');
            if (crimeTypesCtx) {
                if (crimeTypesChart) crimeTypesChart.destroy();
                crimeTypesChart = new Chart(crimeTypesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.crimeTypes.labels,
                        datasets: [{
                            data: data.crimeTypes.data,
                            backgroundColor: [
                                chartColors.primary, chartColors.danger, chartColors.warning,
                                chartColors.info, chartColors.success, '#9333ea', '#ec4899', '#f97316', '#84cc16', '#06b6d4'
                            ],
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'right' } }
                    }
                });
            }

            // 3. Weekly Distribution Chart (Bar)
            const weeklyCtx = document.getElementById('weeklyChart')?.getContext('2d');
            if (weeklyCtx) {
                if (weeklyChart) weeklyChart.destroy();
                weeklyChart = new Chart(weeklyCtx, {
                    type: 'bar',
                    data: {
                        labels: data.weeklyDist.labels,
                        datasets: [{
                            label: 'Incident Count',
                            data: data.weeklyDist.data,
                            backgroundColor: chartColors.warning,
                            borderColor: chartColors.warning,
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'top' } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            // 4. Peak Crime Hours Chart (Bar with gradient effect)
            const peakHoursCtx = document.getElementById('peakHoursChart')?.getContext('2d');
            if (peakHoursCtx) {
                if (peakHoursChart) peakHoursChart.destroy();

                // Create gradient colors for hours (low=blue, high=red)
                const maxCount = Math.max(...data.peakHours.data, 1);
                const barColors = data.peakHours.data.map(count => {
                    const ratio = count / maxCount;
                    if (ratio < 0.33) return '#3b82f6'; // Blue
                    if (ratio < 0.66) return '#f59e0b'; // Amber
                    return '#ef4444'; // Red
                });

                peakHoursChart = new Chart(peakHoursCtx, {
                    type: 'bar',
                    data: {
                        labels: data.peakHours.labels,
                        datasets: [{
                            label: 'Incident Count',
                            data: data.peakHours.data,
                            backgroundColor: barColors,
                            borderColor: barColors,
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'top' } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }
        }

        // Event Listeners for Filter Controls
        document.getElementById('applyFilter').addEventListener('click', () => {
            const year = document.getElementById('filterYear').value;
            const month = document.getElementById('filterMonth').value;
            loadFilteredCharts(year, month || null);
        });

        document.getElementById('resetFilter').addEventListener('click', () => {
            document.getElementById('filterYear').value = {{ now()->year }};
            document.getElementById('filterMonth').value = '';
            loadFilteredCharts({{ now()->year }}, null);
        });

        // Allow Enter key to apply filter
        document.getElementById('filterYear').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') document.getElementById('applyFilter').click();
        });

        document.getElementById('filterMonth').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') document.getElementById('applyFilter').click();
        });

        // Initialize Location Trends Charts
        function initializeLocationTrends() {
            const locationCtx = document.getElementById('locationTrendChart')?.getContext('2d');
            if (locationCtx) {
                const barangayLabels = {!! json_encode($barangayLabels ?? []) !!};
                const barangayData = {!! json_encode($barangayData ?? []) !!};

                new Chart(locationCtx, {
                    type: 'bar',
                    data: {
                        labels: barangayLabels.length > 0 ? barangayLabels : ['Barangay 1', 'Barangay 2', 'Barangay 3'],
                        datasets: [{
                            label: 'Incident Count',
                            data: barangayData.length > 0 ? barangayData : [45, 38, 32, 28, 25, 22, 20, 18, 16, 14],
                            backgroundColor: '#274d4c',
                            borderColor: '#274d4c',
                            borderWidth: 1,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true } }
                    }
                });

                const barangayLabelsArray = barangayLabels.length > 0 ? barangayLabels : ['Barangay 1'];
                document.getElementById('highestRiskBarangay').textContent = barangayLabelsArray[0];
                document.getElementById('safestBarangay').textContent = barangayLabelsArray[barangayLabelsArray.length - 1];
            }
        }

        // Initialize Crime Type Trends Charts
        function initializeCrimeTypeTrends() {
            // Crime Type Distribution Pie Chart
            const crimeTypeCtx = document.getElementById('crimeTypeDistChart')?.getContext('2d');
            if (crimeTypeCtx) {
                const categoryLabels = {!! json_encode($categoryLabels ?? []) !!};
                const categoryData = {!! json_encode($categoryData ?? []) !!};

                const colors = ['#ef4444', '#f97316', '#eab308', '#84cc16', '#22c55e', '#06b6d4', '#0ea5e9', '#6366f1', '#8b5cf6', '#d946ef'];

                new Chart(crimeTypeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: categoryLabels.length > 0 ? categoryLabels : ['Crime Type 1', 'Crime Type 2', 'Crime Type 3'],
                        datasets: [{
                            data: categoryData.length > 0 ? categoryData : [30, 25, 20, 15, 10],
                            backgroundColor: colors.slice(0, Math.max(categoryLabels.length, 5)),
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }

            // Crime Type Trends Over Time (Line Chart)
            const crimeTypeTrendCtx = document.getElementById('crimeTypeTrendChart')?.getContext('2d');
            if (crimeTypeTrendCtx) {
                new Chart(crimeTypeTrendCtx, {
                    type: 'line',
                    data: {
                        labels: {!! json_encode($monthLabels ?? []) !!},
                        datasets: [
                            {
                                label: 'Theft',
                                data: [12, 14, 16, 15, 18, 17, 19, 21, 20, 22, 24, 25],
                                borderColor: '#ef4444',
                                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                tension: 0.4
                            },
                            {
                                label: 'Assault',
                                data: [8, 9, 8, 10, 11, 12, 13, 14, 15, 14, 16, 17],
                                borderColor: '#f97316',
                                backgroundColor: 'rgba(249, 115, 22, 0.1)',
                                tension: 0.4
                            },
                            {
                                label: 'Robbery',
                                data: [5, 6, 7, 6, 8, 9, 8, 10, 11, 10, 12, 13],
                                borderColor: '#eab308',
                                backgroundColor: 'rgba(234, 179, 8, 0.1)',
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }

            // Populate most prevalent crime
            const categoryLabels = {!! json_encode($categoryLabels ?? []) !!};
            const categoryData = {!! json_encode($categoryData ?? []) !!};
            if (categoryLabels.length > 0) {
                const mostPrevalentHtml = `
                    <div class="border-b border-gray-200 pb-3">
                        <p class="font-semibold text-gray-900">${categoryLabels[0]}</p>
                        <p class="text-2xl font-bold text-danger-600">${categoryData[0]} incidents</p>
                        <p class="text-xs text-gray-500 mt-1">Most common crime type</p>
                    </div>
                `;
                document.getElementById('mostPrevalentCrime').innerHTML = mostPrevalentHtml;
            }

            // Crime Type Summary
            const summaryHtml = `
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">Total Crime Types Tracked:</span>
                    <span class="font-bold text-alertara-600">${categoryLabels.length}</span>
                </div>
            `;
            document.getElementById('crimeTypeSummary').innerHTML = summaryHtml;
        }

        // Initialize all trend charts on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeLocationTrends();
            initializeCrimeTypeTrends();
        });
    </script>
</body>
</html>
