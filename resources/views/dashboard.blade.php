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
                <div class="bg-white rounded-lg shadow p-6">
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
                <div class="bg-white rounded-lg shadow p-6">
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
                <div class="bg-white rounded-lg shadow p-6">
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
                <div class="bg-white rounded-lg shadow p-6">
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
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Incidents by Category</h2>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Trends Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Monthly Trends (Last 12 Months)</h2>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Crime Status Distribution -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Crime Status Distribution</h2>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Clearance Status Pie Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Case Clearance Status</h2>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="clearanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Barangays Chart -->
            <div class="grid grid-cols-1 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Top 10 Barangays by Incident Count</h2>
                    <div style="position: relative; height: 350px; width: 100%;">
                        <canvas id="barangayChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 3 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Alert Severity Distribution -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Alert Severity Distribution</h2>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="severityChart"></canvas>
                    </div>
                </div>

                <!-- Latest Alerts -->
                <div class="bg-white rounded-lg shadow p-6">
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

            <!-- Recent Incidents Table -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Recent Incidents</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
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
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-gray-700">{{ $incident->incident_code }}</td>
                                    <td class="py-3 px-4 text-gray-700">{{ substr($incident->incident_title, 0, 30) }}...</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 bg-alertara-100 text-alertara-700 rounded text-xs font-semibold">
                                            {{ $incident->category->category_name ?? 'Unknown' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-gray-700">{{ $incident->barangay->barangay_name ?? 'Unknown' }}</td>
                                    <td class="py-3 px-4 text-gray-700">{{ $incident->incident_date ? $incident->incident_date->format('M d, Y') : 'N/A' }}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-semibold">
                                            {{ ucfirst($incident->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-1 {{ $incident->clearance_status === 'cleared' ? 'bg-success-100 text-success-700' : 'bg-danger-100 text-danger-700' }} rounded text-xs font-semibold">
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
        });
    </script>
</body>
</html>
