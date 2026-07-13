@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

@extends('layouts.app')

@section('title', 'Time-Based Trends Analysis')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
@endpush

@section('content')
<div class="bg-gray-100 min-h-screen">
    <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
            <!-- Page Header -->
            <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                            <i class="fas fa-clock mr-3" style="color: #274d4c;"></i>Time-Based Trends Analysis
                        </h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Comprehensive crime patterns analysis across different time periods and hours</p>
                    </div>
                </div>
            </div>

            <!-- Standardized Filter Section -->
            <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
                <div class="mb-4 pb-4 border-b border-gray-200">
                    <h3 class="text-sm font-bold text-gray-900">
                        <i class="fas fa-filter mr-2 text-alertara-700"></i>Trend Filters
                    </h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <!-- Time Period -->
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Time Period</label>
                        <select id="trendFilterYear" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            @for($y = now()->year - 5; $y <= now()->year; $y++)
                                <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <!-- Month -->
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Month</label>
                        <select id="trendFilterMonth" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Months</option>
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}">{{ \Carbon\Carbon::createFromFormat('m', $m)->format('M') }}</option>
                            @endfor
                        </select>
                    </div>

                    <!-- Day of Week -->
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Day of Week</label>
                        <select id="trendDayOfWeek" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Days</option>
                            <option value="1">Sun</option>
                            <option value="2">Mon</option>
                            <option value="3">Tue</option>
                            <option value="4">Wed</option>
                            <option value="5">Thu</option>
                            <option value="6">Fri</option>
                            <option value="7">Sat</option>
                        </select>
                    </div>

                    <!-- Time of Day -->
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Time of Day</label>
                        <select id="trendTimeOfDay" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Times</option>
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                            <option value="evening">Evening</option>
                            <option value="night">Night</option>
                        </select>
                    </div>

                    <!-- Crime Type -->
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Crime Type</label>
                        <select id="trendFilterCrimeType" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Types</option>
                        </select>
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-end gap-2">
                        <button id="resetTrendFilter" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                            <i class="fas fa-redo"></i>Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Daily Crime Distribution - Full Width -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 hover:border-alertara-300 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900">Daily Crime Distribution</h2>
                    <button onclick="openDailyComparisonModal()" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="Open Comparison Analysis">
                        <i class="fas fa-expand text-lg"></i>
                    </button>
                </div>
                <div style="position: relative; height: 400px; width: 100%;">
                    <canvas id="dailyTrendChart"></canvas>
                </div>
                <p class="text-xs text-gray-600 mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    Daily crime incidents across all days of selected month - compare with previous periods and identify patterns
                </p>
            </div>

            <!-- Hourly Crime Peaks - Full Width -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 hover:border-alertara-300 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900">Hourly Crime Peaks</h2>
                    <button onclick="openHourlyComparisonModal()" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="Open Comparison Analysis">
                        <i class="fas fa-expand text-lg"></i>
                    </button>
                </div>
                <div style="position: relative; height: 350px; width: 100%;">
                    <canvas id="hourlyTrendChart"></canvas>
                </div>
                <p class="text-xs text-gray-600 mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    Peak crime hours during the day - identify high-risk time windows and compare patterns
                </p>
            </div>

            <!-- Time-Based Insights Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-blue-900 mb-1">
                                <i class="fas fa-calendar-alt mr-1"></i>Peak Day
                            </p>
                            <p class="text-2xl font-bold text-blue-700" id="peakDayDisplay">--</p>
                            <p class="text-xs text-blue-600 mt-1">Highest incidents on this day</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-orange-900 mb-1">
                                <i class="fas fa-hourglass-end mr-1"></i>Peak Hour
                            </p>
                            <p class="text-2xl font-bold text-orange-700" id="peakHourDisplay">--</p>
                            <p class="text-xs text-orange-600 mt-1">Most dangerous time of day</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-red-900 mb-1">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Avg Incidents/Day
                            </p>
                            <p class="text-2xl font-bold text-red-700" id="avgIncidentsDisplay">--</p>
                            <p class="text-xs text-red-600 mt-1">Average daily crime count</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-green-900 mb-1">
                                <i class="fas fa-trending-up mr-1"></i>Trend Direction
                            </p>
                            <p class="text-2xl font-bold text-green-700" id="trendDirectionDisplay">📊</p>
                            <p class="text-xs text-green-600 mt-1">Current weekly trend</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time Period Breakdown -->
            <!-- Day Period Analysis -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 hover:border-alertara-300 transition-colors">
                <h2 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-sun mr-2" style="color: #f59e0b;"></i>Day Period Analysis
                </h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-yellow-400 mr-3"></div>
                                <span class="text-sm font-medium text-gray-700">Morning (6AM-12PM)</span>
                            </div>
                            <p class="font-bold text-gray-900" id="morningCount">--</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-orange-400 mr-3"></div>
                                <span class="text-sm font-medium text-gray-700">Afternoon (12PM-6PM)</span>
                            </div>
                            <p class="font-bold text-gray-900" id="afternoonCount">--</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-blue-600 mr-3"></div>
                                <span class="text-sm font-medium text-gray-700">Evening (6PM-12AM)</span>
                            </div>
                            <p class="font-bold text-gray-900" id="eveningCount">--</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-gray-800 mr-3"></div>
                                <span class="text-sm font-medium text-gray-700">Night (12AM-6AM)</span>
                            </div>
                            <p class="font-bold text-gray-900" id="nightCount">--</p>
                        </div>
                    </div>
            </div>

            <!-- Weekday vs Weekend -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 hover:border-alertara-300 transition-colors">
                <h2 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-calendar-week mr-2" style="color: #274d4c;"></i>Weekday vs Weekend
                </h2>
                    <div class="space-y-6">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Weekdays (Mon-Fri)</span>
                                <p class="font-bold text-alertara-600" id="weekdayCount">--</p>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-alertara-600 h-3 rounded-full transition-all" id="weekdayBar" style="width: 65%;"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Weekends (Sat-Sun)</span>
                                <p class="font-bold text-danger-600" id="weekendCount">--</p>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-danger-600 h-3 rounded-full transition-all" id="weekendBar" style="width: 35%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Crime Heatmap by Hour & Day -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 hover:border-alertara-300 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-th mr-2" style="color: #ef4444;"></i>Crime Heatmap - Hour vs Day
                    </h2>
                    <span class="text-xs bg-danger-100 text-danger-700 px-2 py-1 rounded">Intensity Analysis</span>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-600">Color intensity represents crime frequency. Darker red = Higher crime rate</p>
                </div>
                <div class="overflow-x-auto">
                    <div id="heatmapContainer" style="min-width: 800px;">
                        <!-- Heatmap will be generated here -->
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-center space-x-4 text-xs">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-blue-100 border border-gray-300 mr-2"></div>
                        <span>Low (0-2)</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-yellow-200 border border-gray-300 mr-2"></div>
                        <span>Medium (3-5)</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-orange-400 border border-gray-300 mr-2"></div>
                        <span>High (6-8)</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-red-600 border border-gray-300 mr-2"></div>
                        <span>Critical (9+)</span>
                    </div>
                </div>
            </div>

            
            
            <!-- Key Insights -->
            <div class="bg-gradient-to-r from-alertara-50 to-blue-50 border border-alertara-200 rounded-lg p-6 mb-8">
                <h2 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-lightbulb mr-2" style="color: #f59e0b;"></i>Key Insights & Recommendations
                </h2>
                <div id="keyInsights" class="space-y-3">
                    <p class="text-gray-600">
                        <i class="fas fa-check-circle text-alertara-600 mr-2"></i>
                        <span id="insight1">Loading insights...</span>
                    </p>
                    <p class="text-gray-600">
                        <i class="fas fa-check-circle text-alertara-600 mr-2"></i>
                        <span id="insight2">Analyzing patterns...</span>
                    </p>
                    <p class="text-gray-600">
                        <i class="fas fa-check-circle text-alertara-600 mr-2"></i>
                        <span id="insight3">Generating recommendations...</span>
                    </p>
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

        // Initialize page on load
        document.addEventListener('DOMContentLoaded', function() {
            // Load crime categories filter options
            loadCrimeCategories();

            // Initialize charts from server-rendered data
            initializeTimeBasedTrendsCharts({{ now()->year }}, null);
            loadTimeBasedTrendsInsights();

            // Load real heatmap data from the server
            loadRealHeatmap();
        });

        // Load crime categories into the trend filter
        async function loadCrimeCategories() {
            try {
                const response = await fetch('/api/crime-categories');
                const categories = await response.json();
                const select = document.getElementById('trendFilterCrimeType');
                if (select) {
                    categories.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat.id;
                        option.textContent = cat.category_name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading categories:', error);
            }
        }

        // Compute trend direction from real monthly counts (last vs previous month)
        function computeTrendDirection(monthValues) {
            if (!monthValues || monthValues.length < 2) return '📊 No trend data';
            const last = monthValues[monthValues.length - 1];
            const prev = monthValues[monthValues.length - 2];
            if (prev === 0) return last > 0 ? '📈 Rising' : '➖ Stable';
            const change = Math.round(((last - prev) / prev) * 100);
            if (change > 10) return `📈 Rising ${change}%`;
            if (change < -10) return `📉 Falling ${Math.abs(change)}%`;
            return '➖ Stable';
        }

        // Initialize Time-Based Trends Charts
        function initializeTimeBasedTrendsCharts(year, month) {
            const chartColors = {
                primary: '#274d4c',
                danger: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };

            // Dynamic data from database
            const dayLabels = {!! $dailyLabels !!};
            const dayData = {!! $dailyData !!};
            const hourLabels = {!! $hourlyLabels !!};
            const hourData = {!! $hourlyData !!};
            const monthLabels = {!! $monthLabels !!};
            const monthData = {!! $monthData !!};

            // Store data globally for insights function
            window.currentDayLabels = dayLabels;
            window.currentDayData = dayData;
            window.currentHourLabels = hourLabels;
            window.currentHourData = hourData;

            // Daily Trend Chart - real incident counts per day of week from the database
            const dailyCtx = document.getElementById('dailyTrendChart')?.getContext('2d');
            if (dailyCtx) {
                if (window.dailyChart && typeof window.dailyChart.destroy === 'function') {
                    window.dailyChart.destroy();
                }

                window.dailyChart = new Chart(dailyCtx, {
                    type: 'bar',
                    data: {
                        labels: dayLabels,
                        datasets: [{
                            label: 'Incidents by Day of Week',
                            data: dayData,
                            backgroundColor: [
                                '#ef4444', '#f97316', '#eab308', '#84cc16', '#22c55e', '#06b6d4', '#0ea5e9'
                            ],
                            borderRadius: 6,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                                title: {
                                    display: true,
                                    text: 'Number of Incidents',
                                    font: { size: 12 }
                                }
                            },
                            x: { grid: { display: false } }
                        }
                    }
                });

                // Set peak day
                const maxDay = dayData.indexOf(Math.max(...dayData));
                document.getElementById('peakDayDisplay').textContent = dayLabels[maxDay];
            }

            // Hourly Trend Chart
            const hourlyCtx = document.getElementById('hourlyTrendChart')?.getContext('2d');
            if (hourlyCtx) {
                if (window.hourlyChart && typeof window.hourlyChart.destroy === 'function') {
                    window.hourlyChart.destroy();
                }
                window.hourlyChart = new Chart(hourlyCtx, {
                    type: 'line',
                    data: {
                        labels: hourLabels,
                        datasets: [{
                            label: 'Incidents by Hour',
                            data: hourData,
                            borderColor: chartColors.danger,
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: chartColors.danger,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'top' } },
                        scales: { y: { beginAtZero: true } }
                    }
                });

                // Set peak hour
                const maxHour = hourData.indexOf(Math.max(...hourData));
                document.getElementById('peakHourDisplay').textContent = hourLabels[maxHour];

                // Calculate period counts
                const morningSum = hourData.slice(6, 12).reduce((a, b) => a + b, 0);
                const afternoonSum = hourData.slice(12, 18).reduce((a, b) => a + b, 0);
                const eveningSum = hourData.slice(18, 24).reduce((a, b) => a + b, 0);
                const nightSum = hourData.slice(0, 6).reduce((a, b) => a + b, 0);

                document.getElementById('morningCount').textContent = morningSum;
                document.getElementById('afternoonCount').textContent = afternoonSum;
                document.getElementById('eveningCount').textContent = eveningSum;
                document.getElementById('nightCount').textContent = nightSum;

                // Calculate avg incidents
                const avgIncidents = Math.round((morningSum + afternoonSum + eveningSum + nightSum) / 4);
                document.getElementById('avgIncidentsDisplay').textContent = avgIncidents;
            }

            
            // Weekday vs Weekend calculation
            const weekdaySum = dayData.slice(1, 6).reduce((a, b) => a + b, 0);
            const weekendSum = dayData[0] + dayData[6];
            const total = weekdaySum + weekendSum;

            document.getElementById('weekdayCount').textContent = weekdaySum;
            document.getElementById('weekendCount').textContent = weekendSum;
            document.getElementById('weekdayBar').style.width = ((weekdaySum / total) * 100) + '%';
            document.getElementById('weekendBar').style.width = ((weekendSum / total) * 100) + '%';

            // Trend direction computed from real monthly counts
            document.getElementById('trendDirectionDisplay').textContent = computeTrendDirection(monthData);
        }

        // Load insights based on data
        function loadTimeBasedTrendsInsights() {
            // Use stored data if available (from filter updates), otherwise use initial data
            const dayLabels = window.currentDayLabels || {!! $dailyLabels !!};
            const dayData = window.currentDayData || {!! $dailyData !!};
            const hourLabels = window.currentHourLabels || {!! $hourlyLabels !!};
            const hourData = window.currentHourData || {!! $hourlyData !!};

            // Find peak day
            let peakDayIdx = 0;
            let maxDayCount = dayData[0];
            dayData.forEach((count, idx) => {
                if (count > maxDayCount) {
                    maxDayCount = count;
                    peakDayIdx = idx;
                }
            });

            // Find peak hour
            let peakHourIdx = 0;
            let maxHourCount = hourData[0];
            hourData.forEach((count, idx) => {
                if (count > maxHourCount) {
                    maxHourCount = count;
                    peakHourIdx = idx;
                }
            });

            // Calculate weekday percentage
            const weekdaySum = dayData.slice(1, 6).reduce((a, b) => a + b, 0);
            const weekendSum = dayData[0] + dayData[6];
            const total = weekdaySum + weekendSum;
            const weekdayPercent = total > 0 ? Math.round((weekdaySum / total) * 100) : 0;

            // Generate insights
            const peakDayName = dayLabels[peakDayIdx] || 'Unknown day';
            const peakHourStr = hourLabels[peakHourIdx] || 'Unknown time';

            document.getElementById('insight1').textContent = peakDayName + ' has the highest crime incidents (' + maxDayCount + ') - increase patrol presence during this day.';
            document.getElementById('insight2').textContent = 'Peak crime hour is around ' + peakHourStr + ' with ' + maxHourCount + ' incidents - strengthen operations during this time window.';
            document.getElementById('insight3').textContent = 'Weekday crimes account for ' + weekdayPercent + '% of total incidents - allocate more resources for weekday operations.';
        }

        // Load the real Day-vs-Hour heatmap from the database
        async function loadRealHeatmap() {
            const container = document.getElementById('heatmapContainer');
            container.innerHTML = '<div class="p-6 text-center text-sm text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading heatmap...</div>';

            try {
                const year = document.getElementById('trendFilterYear')?.value || new Date().getFullYear();
                const response = await fetch(`/dashboard/charts?year=${year}`);
                const data = await response.json();

                if (data.heatmapData) {
                    updateHeatmapWithData(data.heatmapData);
                } else {
                    container.innerHTML = '<div class="p-6 text-center text-sm text-gray-500">No heatmap data available.</div>';
                }
            } catch (error) {
                console.error('Error loading heatmap:', error);
                container.innerHTML = '<div class="p-6 text-center text-sm text-red-500">Failed to load heatmap data.</div>';
            }
        }

        // Get color based on crime intensity
        function getHeatmapColor(value) {
            if (value <= 2) return '#dbeafe'; // blue-100
            if (value <= 5) return '#fef3c7'; // yellow-200
            if (value <= 8) return '#fb923c'; // orange-400
            return '#dc2626'; // red-600
        }

        
        
        // Auto-apply filter functionality
        async function applyTrendFilter() {
            const year = document.getElementById('trendFilterYear').value;
            const month = document.getElementById('trendFilterMonth').value;
            const dayOfWeek = document.getElementById('trendDayOfWeek').value;
            const timeOfDay = document.getElementById('trendTimeOfDay').value;
            const crimeType = document.getElementById('trendFilterCrimeType').value;

            // Show loading state (loader element is optional)
            const loader = document.getElementById('trendFilterLoader');
            loader?.classList.add('active');

            try {
                // Fetch filtered data from server
                const params = new URLSearchParams();
                params.append('year', year);
                if (month) params.append('month', month);
                if (dayOfWeek) params.append('day_of_week', dayOfWeek);
                if (timeOfDay) params.append('time_of_day', timeOfDay);
                if (crimeType) params.append('crime_type', crimeType);

                const response = await fetch(`/dashboard/charts?${params.toString()}`);
                if (!response.ok) {
                    console.error('API response error:', response.status, response.statusText);
                    return;
                }

                const responseData = await response.json();

                // Update charts with new data
                updateTimeBasedChartsWithFilteredData(
                    responseData.monthlyTrend,
                    responseData.weeklyDist,
                    responseData.peakHours
                );

                // Reload insights with new data
                loadTimeBasedTrendsInsights();

                // Refresh heatmap with filtered data
                refreshNewFeaturesWithFilteredData(responseData);
            } catch (error) {
                console.error('Error fetching filtered time data:', error);
            } finally {
                // Hide loading state
                loader?.classList.remove('active');
            }
        }

        // Update charts with dynamically fetched data
        function updateTimeBasedChartsWithFilteredData(monthlyTrendData, weeklyDistData, peakHoursData) {
            const chartColors = {
                primary: '#274d4c',
                danger: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };

            // Extract labels and data
            const monthLabels = monthlyTrendData.labels || [];
            const monthData = monthlyTrendData.data || [];
            const dayLabels = weeklyDistData.labels || [];
            const dayData = weeklyDistData.data || [];
            const hourLabels = peakHoursData.labels || [];
            const hourData = peakHoursData.data || [];

            // Store data globally for insights function
            window.currentDayLabels = dayLabels;
            window.currentDayData = dayData;
            window.currentHourLabels = hourLabels;
            window.currentHourData = hourData;

            // Update Daily Trend Chart
            const dailyCtx = document.getElementById('dailyTrendChart')?.getContext('2d');
            if (dailyCtx && dayData.length > 0) {
                if (window.dailyChart && typeof window.dailyChart.destroy === 'function') {
                    window.dailyChart.destroy();
                }
                window.dailyChart = new Chart(dailyCtx, {
                    type: 'bar',
                    data: {
                        labels: dayLabels,
                        datasets: [{
                            label: 'Incidents by Day',
                            data: dayData,
                            backgroundColor: [
                                '#ef4444', '#f97316', '#eab308', '#84cc16', '#22c55e', '#06b6d4', '#0ea5e9'
                            ],
                            borderRadius: 6,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });

                // Set peak day
                const maxDay = dayData.indexOf(Math.max(...dayData));
                document.getElementById('peakDayDisplay').textContent = dayLabels[maxDay] || '--';
            }

            // Update Hourly Trend Chart
            const hourlyCtx = document.getElementById('hourlyTrendChart')?.getContext('2d');
            if (hourlyCtx && hourData.length > 0) {
                if (window.hourlyChart && typeof window.hourlyChart.destroy === 'function') {
                    window.hourlyChart.destroy();
                }
                window.hourlyChart = new Chart(hourlyCtx, {
                    type: 'line',
                    data: {
                        labels: hourLabels,
                        datasets: [{
                            label: 'Incidents by Hour',
                            data: hourData,
                            borderColor: chartColors.danger,
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: chartColors.danger,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'top' } },
                        scales: { y: { beginAtZero: true } }
                    }
                });

                // Set peak hour
                const maxHour = hourData.indexOf(Math.max(...hourData));
                document.getElementById('peakHourDisplay').textContent = hourLabels[maxHour] || '--';

                // Calculate period counts
                const morningSum = hourData.slice(6, 12).reduce((a, b) => a + b, 0);
                const afternoonSum = hourData.slice(12, 18).reduce((a, b) => a + b, 0);
                const eveningSum = hourData.slice(18, 24).reduce((a, b) => a + b, 0);
                const nightSum = hourData.slice(0, 6).reduce((a, b) => a + b, 0);

                document.getElementById('morningCount').textContent = morningSum;
                document.getElementById('afternoonCount').textContent = afternoonSum;
                document.getElementById('eveningCount').textContent = eveningSum;
                document.getElementById('nightCount').textContent = nightSum;

                // Calculate avg incidents
                const avgIncidents = Math.round((morningSum + afternoonSum + eveningSum + nightSum) / 4);
                document.getElementById('avgIncidentsDisplay').textContent = avgIncidents;
            }

            // Update Monthly Trend Chart
            const monthlyCtx = document.getElementById('monthlyTrendChart')?.getContext('2d');
            if (monthlyCtx) {
                if (window.monthlyTrendChart && typeof window.monthlyTrendChart.destroy === 'function') {
                    window.monthlyTrendChart.destroy();
                }

                if (monthData.length > 0) {
                    window.monthlyTrendChart = new Chart(monthlyCtx, {
                        type: 'line',
                        data: {
                            labels: monthLabels,
                            datasets: [{
                                label: 'Monthly Incidents',
                                data: monthData,
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
                } else {
                    // Show empty state if no data
                    const ctx = monthlyCtx;
                    ctx.fillStyle = '#d1d5db';
                    ctx.font = '14px Arial';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText('No data available for the selected period', ctx.canvas.width / 2, ctx.canvas.height / 2);
                }
            }

            // Update Weekday vs Weekend calculation
            if (dayData.length > 0) {
                const weekdaySum = dayData.slice(1, 6).reduce((a, b) => a + b, 0);
                const weekendSum = dayData[0] + dayData[6];
                const total = weekdaySum + weekendSum;

                document.getElementById('weekdayCount').textContent = weekdaySum;
                document.getElementById('weekendCount').textContent = weekendSum;

                if (total > 0) {
                    document.getElementById('weekdayBar').style.width = ((weekdaySum / total) * 100) + '%';
                    document.getElementById('weekendBar').style.width = ((weekendSum / total) * 100) + '%';
                }
            }

            // Trend direction computed from real monthly counts
            document.getElementById('trendDirectionDisplay').textContent = computeTrendDirection(monthData);
        }

        // Refresh new features with filtered data
        function refreshNewFeaturesWithFilteredData(responseData) {
            // Update heatmap with new data if available
            if (responseData.heatmapData) {
                updateHeatmapWithData(responseData.heatmapData);
            }
        }

        // Update heatmap with new data
        function updateHeatmapWithData(heatmapData) {
            const container = document.getElementById('heatmapContainer');
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const hours = Array.from({length: 24}, (_, i) => i.toString().padStart(2, '0') + ':00');
            
            let html = '<table class="w-full border-collapse">';
            html += '<thead><tr><th class="p-2 text-xs font-semibold text-gray-600">Hour</th>';
            days.forEach(day => {
                html += `<th class="p-2 text-xs font-semibold text-gray-600">${day}</th>`;
            });
            html += '</tr></thead><tbody>';
            
            hours.forEach((hour, hourIndex) => {
                html += `<tr><td class="p-2 text-xs font-medium text-gray-700">${hour}</td>`;
                days.forEach((day, dayIndex) => {
                    const value = heatmapData[dayIndex] ? heatmapData[dayIndex][hourIndex] || 0 : 0;
                    const color = getHeatmapColor(value);
                    html += `<td class="p-1 text-center cursor-pointer transition-all hover:scale-110" style="background-color: ${color}; color: ${value > 5 ? 'white' : '#374151'};" title="${day} ${hour}: ${value} incidents"><span class="text-xs font-semibold">${value}</span></td>`;
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            
            container.innerHTML = html;
        }

        
        
        // Handle date range preset buttons
        document.querySelectorAll('.dateRangeBtn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const range = this.getAttribute('data-range');
                const today = new Date();

                // Remove active class from all buttons
                document.querySelectorAll('.dateRangeBtn').forEach(b => {
                    b.classList.remove('active');
                });
                // Add active class to clicked button
                this.classList.add('active');

                switch(range) {
                    case 'today':
                        document.getElementById('trendFilterYear').value = today.getFullYear();
                        document.getElementById('trendFilterMonth').value = today.getMonth() + 1;
                        break;
                    case 'yesterday':
                        const yesterday = new Date(today);
                        yesterday.setDate(yesterday.getDate() - 1);
                        document.getElementById('trendFilterYear').value = yesterday.getFullYear();
                        document.getElementById('trendFilterMonth').value = yesterday.getMonth() + 1;
                        break;
                    case '7days':
                        document.getElementById('trendFilterMonth').value = '';
                        document.getElementById('trendFilterYear').value = today.getFullYear();
                        break;
                    case '30days':
                        document.getElementById('trendFilterMonth').value = '';
                        document.getElementById('trendFilterYear').value = today.getFullYear();
                        break;
                    case 'thismonth':
                        document.getElementById('trendFilterYear').value = today.getFullYear();
                        document.getElementById('trendFilterMonth').value = today.getMonth() + 1;
                        break;
                    case 'lastmonth':
                        const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        document.getElementById('trendFilterYear').value = lastMonth.getFullYear();
                        document.getElementById('trendFilterMonth').value = lastMonth.getMonth() + 1;
                        break;
                }
                applyTrendFilter();
            });
        });

        // Auto-apply on time filter change
        document.getElementById('trendFilterYear').addEventListener('change', applyTrendFilter);
        document.getElementById('trendFilterMonth').addEventListener('change', applyTrendFilter);
        document.getElementById('trendDayOfWeek').addEventListener('change', applyTrendFilter);
        document.getElementById('trendTimeOfDay').addEventListener('change', applyTrendFilter);
        document.getElementById('trendFilterCrimeType').addEventListener('change', applyTrendFilter);

        // Reset button
        document.getElementById('resetTrendFilter').addEventListener('click', function() {
            document.getElementById('trendFilterYear').value = '{{ now()->year }}';
            document.getElementById('trendFilterMonth').value = '';
            document.getElementById('trendDayOfWeek').value = '';
            document.getElementById('trendTimeOfDay').value = '';
            document.getElementById('trendFilterCrimeType').value = '';

            // Remove active class from all date range buttons
            document.querySelectorAll('.dateRangeBtn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Apply reset filters
            applyTrendFilter();
        });

        // Time Analysis Modal
        function openTimeAnalysisModal() {
            const modal = document.getElementById('timeAnalysisModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            
            // Initialize modal charts
            setTimeout(() => {
                initializeModalCharts();
            }, 100);
        }

        function closeTimeAnalysisModal() {
            const modal = document.getElementById('timeAnalysisModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        function initializeModalCharts() {
            // Initialize expanded daily chart
            const dailyCtx = document.getElementById('modalDailyChart')?.getContext('2d');
            if (dailyCtx && window.dailyChart) {
                // Clone the existing daily chart data with theme colors
                new Chart(dailyCtx, {
                    type: 'bar',
                    data: {
                        ...window.dailyChart.data,
                        datasets: [{
                            ...window.dailyChart.data.datasets[0],
                            backgroundColor: [
                                '#274d4c', '#ef4444', '#f59e0b', '#10b981', '#06b6d4', '#0ea5e9', '#8b5cf6'
                            ],
                            borderRadius: 8,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { display: false }
                        },
                        scales: { 
                            y: { 
                                beginAtZero: true,
                                grid: { color: 'rgba(0, 0, 0, 0.05)' }
                            },
                            x: { 
                                ticks: { font: { size: 12 } },
                                grid: { display: false }
                            }
                        }
                    }
                });
            }

            // Initialize expanded hourly chart
            const hourlyCtx = document.getElementById('modalHourlyChart')?.getContext('2d');
            if (hourlyCtx && window.hourlyChart) {
                // Clone the existing hourly chart data with theme colors
                new Chart(hourlyCtx, {
                    type: 'line',
                    data: {
                        ...window.hourlyChart.data,
                        datasets: [{
                            ...window.hourlyChart.data.datasets[0],
                            borderColor: '#274d4c',
                            backgroundColor: 'rgba(39, 77, 76, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#274d4c',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { display: true, position: 'top' }
                        },
                        scales: { 
                            y: { 
                                beginAtZero: true,
                                grid: { color: 'rgba(0, 0, 0, 0.05)' }
                            },
                            x: { 
                                ticks: { font: { size: 10 } },
                                grid: { display: false }
                            }
                        }
                    }
                });
            }

            // Initialize time period comparison chart
            initializeTimePeriodComparison();
            
            // Update modal statistics
            updateModalStatistics();
        }

        function initializeTimePeriodComparison() {
            const ctx = document.getElementById('timePeriodComparisonChart')?.getContext('2d');
            if (!ctx) return;

            // Compute real time-period totals from the current hourly data
            const hourData = window.currentHourData || {!! $hourlyData !!};
            const periods = ['Morning (6AM-12PM)', 'Afternoon (12PM-6PM)', 'Evening (6PM-12AM)', 'Night (12AM-6AM)'];
            const data = [
                hourData.slice(6, 12).reduce((a, b) => a + b, 0),
                hourData.slice(12, 18).reduce((a, b) => a + b, 0),
                hourData.slice(18, 24).reduce((a, b) => a + b, 0),
                hourData.slice(0, 6).reduce((a, b) => a + b, 0)
            ];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: periods,
                    datasets: [{
                        data: data,
                        backgroundColor: ['#274d4c', '#f59e0b', '#ef4444', '#3b82f6'],
                        borderWidth: 3,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'right',
                            labels: {
                                padding: 15,
                                font: { size: 12 },
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }

        function updateModalStatistics() {
            // Update modal statistics with current data
            const peakDay = document.getElementById('peakDayDisplay').textContent;
            const peakHour = document.getElementById('peakHourDisplay').textContent;
            const avgIncidents = document.getElementById('avgIncidentsDisplay').textContent;
            const trend = document.getElementById('trendDirectionDisplay').textContent;

            document.getElementById('modalPeakDay').textContent = peakDay;
            document.getElementById('modalPeakHour').textContent = peakHour;
            document.getElementById('modalAvgIncidents').textContent = avgIncidents;
            document.getElementById('modalTrend').textContent = trend;

            // Generate detailed insights
            generateModalInsights();
        }

        function generateModalInsights() {
            // Compute insights from the real current datasets
            const dayLabels = window.currentDayLabels || {!! $dailyLabels !!};
            const dayData = window.currentDayData || {!! $dailyData !!};
            const hourData = window.currentHourData || {!! $hourlyData !!};
            const hourLabels = window.currentHourLabels || {!! $hourlyLabels !!};

            const total = hourData.reduce((a, b) => a + b, 0) || 1;
            const periodSums = {
                'morning (6AM-12PM)': hourData.slice(6, 12).reduce((a, b) => a + b, 0),
                'afternoon (12PM-6PM)': hourData.slice(12, 18).reduce((a, b) => a + b, 0),
                'evening (6PM-12AM)': hourData.slice(18, 24).reduce((a, b) => a + b, 0),
                'night (12AM-6AM)': hourData.slice(0, 6).reduce((a, b) => a + b, 0)
            };
            const topPeriod = Object.entries(periodSums).sort((a, b) => b[1] - a[1])[0];
            const topPeriodShare = Math.round(topPeriod[1] / total * 100);

            const weekdaySum = dayData.slice(1, 6).reduce((a, b) => a + b, 0);
            const weekendSum = (dayData[0] || 0) + (dayData[6] || 0);
            const weekendAvg = weekendSum / 2;
            const weekdayAvg = weekdaySum / 5;
            const weekendVsWeekday = weekdayAvg > 0 ? Math.round((weekendAvg - weekdayAvg) / weekdayAvg * 100) : 0;

            const peakDay = dayLabels[dayData.indexOf(Math.max(...dayData))];
            const peakHour = hourLabels[hourData.indexOf(Math.max(...hourData))];

            const insights = [
                {
                    title: 'Peak Activity Analysis',
                    description: `Incidents are highest during the ${topPeriod[0]} window, accounting for ${topPeriodShare}% of recorded incidents in the current selection.`,
                    icon: 'fa-chart-line',
                    color: 'alertara'
                },
                {
                    title: 'Weekly Pattern',
                    description: weekendVsWeekday > 0
                        ? `Weekend days average ${weekendVsWeekday}% more incidents than weekdays, with ${peakDay} as the overall peak day.`
                        : `Weekdays average ${Math.abs(weekendVsWeekday)}% more incidents than weekends, with ${peakDay} as the overall peak day.`,
                    icon: 'fa-calendar-week',
                    color: 'orange'
                },
                {
                    title: 'Risk Assessment',
                    description: `The highest-risk window is around ${peakHour} on ${peakDay}s based on recorded incident frequency.`,
                    icon: 'fa-exclamation-triangle',
                    color: 'red'
                },
                {
                    title: 'Recommendation',
                    description: `Prioritize patrol deployment during the ${topPeriod[0]} period${weekendVsWeekday > 0 ? ', especially on weekends' : ' on weekdays'}, when incident concentration is highest.`,
                    icon: 'fa-lightbulb',
                    color: 'green'
                }
            ];

            const container = document.getElementById('modalInsights');
            let html = '';

            insights.forEach(insight => {
                const colorClasses = {
                    alertara: 'bg-alertara-50 border-alertara-200 text-alertara-700',
                    orange: 'bg-orange-50 border-orange-200 text-orange-700',
                    red: 'bg-red-50 border-red-200 text-red-700',
                    green: 'bg-green-50 border-green-200 text-green-700'
                };

                const iconColors = {
                    alertara: 'text-alertara-600',
                    orange: 'text-orange-600',
                    red: 'text-red-600',
                    green: 'text-green-600'
                };

                html += `
                    <div class="p-4 rounded-lg border ${colorClasses[insight.color]} bg-white hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas ${insight.icon} ${iconColors[insight.color]}"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 mb-2">${insight.title}</h4>
                                <p class="text-sm text-gray-600 leading-relaxed">${insight.description}</p>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTimeAnalysisModal();
            }
        });

        // Close modal on background click
        document.getElementById('timeAnalysisModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTimeAnalysisModal();
            }
        });

        // Daily Comparison Modal Functions
        function openDailyComparisonModal() {
            const modal = document.getElementById('dailyComparisonModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            
            setTimeout(() => {
                initializeDailyComparisonCharts();
            }, 100);
        }

        function closeDailyComparisonModal() {
            const modal = document.getElementById('dailyComparisonModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        // Hourly Comparison Modal Functions
        function openHourlyComparisonModal() {
            const modal = document.getElementById('hourlyComparisonModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            
            setTimeout(() => {
                initializeHourlyComparisonCharts();
            }, 100);
        }

        function closeHourlyComparisonModal() {
            const modal = document.getElementById('hourlyComparisonModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        function initializeDailyComparisonCharts() {
            // Initialize with default comparison
            updateDailyComparison();
            
            // Initialize current week trend chart
            const trendCtx = document.getElementById('dailyTrendAnalysisChart')?.getContext('2d');
            if (trendCtx && window.dailyChart) {
                // Clone the daily chart data for trend analysis as bar chart
                new Chart(trendCtx, {
                    type: 'bar',
                    data: {
                        ...window.dailyChart.data,
                        datasets: [{
                            ...window.dailyChart.data.datasets[0],
                            backgroundColor: [
                                '#ef4444', '#f97316', '#eab308', '#84cc16', '#22c55e', '#06b6d4', '#0ea5e9'
                            ],
                            borderColor: [
                                '#ef4444', '#f97316', '#eab308', '#84cc16', '#22c55e', '#06b6d4', '#0ea5e9'
                            ],
                            borderWidth: 2,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { 
                            y: { beginAtZero: true },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        }

        // Real-time daily comparison update - fetches real day-of-week counts
        function updateDailyComparison() {
            const comparisonType = document.getElementById('dailyComparisonType')?.value || 'month-over-month';
            const year = parseInt(document.getElementById('dailyYearFilter')?.value || new Date().getFullYear());
            const month = document.getElementById('dailyMonthFilter')?.value || '';

            // Show loading
            const loader = document.getElementById('dailyComparisonLoader');
            if (loader) loader.classList.add('active');

            // Clear previous timeout
            if (window.dailyComparisonTimeout) {
                clearTimeout(window.dailyComparisonTimeout);
            }

            // Debounce the update
            window.dailyComparisonTimeout = setTimeout(async () => {
                try {
                    const comparisonData = await fetchDailyComparisonData(comparisonType, year, month);
                    updateDailyComparisonChart(comparisonData);
                    updateDailyComparisonStatistics(comparisonData);
                } catch (error) {
                    console.error('Error loading daily comparison:', error);
                } finally {
                    if (loader) loader.classList.remove('active');
                }
            }, 500);
        }

        async function fetchWeeklyDist(params) {
            const response = await fetch(`/dashboard/charts?${new URLSearchParams(params)}`);
            const data = await response.json();
            return data.weeklyDist || { labels: [], data: [] };
        }

        async function fetchDailyComparisonData(type, year, month) {
            const labels = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const currentParams = { year: year };
            if (month) currentParams.month = month;

            const barStyle = (label, data, color) => ({
                label, data,
                backgroundColor: color, borderColor: color,
                borderWidth: 2, borderRadius: 6
            });

            if (type === 'year-over-year') {
                const previousParams = { year: year - 1 };
                if (month) previousParams.month = month;
                const [current, previous] = await Promise.all([
                    fetchWeeklyDist(currentParams),
                    fetchWeeklyDist(previousParams)
                ]);
                return {
                    labels,
                    datasets: [
                        barStyle(`${year}${month ? ' (month ' + month + ')' : ''}`, current.data, '#274d4c'),
                        barStyle(`${year - 1}${month ? ' (same month)' : ''}`, previous.data, '#94a3b8')
                    ]
                };
            }

            if (type === 'weekday-vs-weekend') {
                const current = await fetchWeeklyDist(currentParams);
                const d = current.data;
                return {
                    labels,
                    datasets: [
                        barStyle('Weekdays', [null, d[1], d[2], d[3], d[4], d[5], null], '#274d4c'),
                        barStyle('Weekends', [d[0], null, null, null, null, null, d[6]], '#f59e0b')
                    ]
                };
            }

            // Default: month-over-month (previous month, wrapping across years)
            const prevMonth = month ? (parseInt(month) === 1 ? 12 : parseInt(month) - 1) : '';
            const prevYear = month && parseInt(month) === 1 ? year - 1 : year;
            const previousParams = { year: prevYear };
            if (prevMonth) previousParams.month = prevMonth;

            const [current, previous] = await Promise.all([
                fetchWeeklyDist(currentParams),
                fetchWeeklyDist(previousParams)
            ]);

            return {
                labels,
                datasets: [
                    barStyle(month ? `Selected Month (${month}/${year})` : `${year}`, current.data, '#274d4c'),
                    barStyle(month ? `Previous Month (${prevMonth}/${prevYear})` : `${year - 1}`, previous.data, '#94a3b8')
                ]
            };
        }

        function updateDailyComparisonChart(data) {
            const ctx = document.getElementById('dailyComparisonChart')?.getContext('2d');
            if (!ctx) return;

            // Destroy existing chart
            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                existingChart.destroy();
            }

            // Create new chart
            new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function updateDailyComparisonStatistics(data) {
            // Calculate statistics from real comparison data
            if (data.datasets && data.datasets.length > 0) {
                const rawData = data.datasets[0].data;
                const nonNull = rawData.filter(d => d !== null && d !== undefined);
                if (!nonNull.length) return;

                const maxValue = Math.max(...nonNull);
                const minValue = Math.min(...nonNull);

                const peakDayElement = document.querySelector('[data-stat="peak-day"]');
                const lowestDayElement = document.querySelector('[data-stat="lowest-day"]');
                const weekChangeElement = document.querySelector('[data-stat="week-change"]');

                // Index against the ORIGINAL array so labels stay aligned when nulls exist
                if (peakDayElement) {
                    peakDayElement.textContent = data.labels[rawData.indexOf(maxValue)] || '--';
                }
                if (lowestDayElement) {
                    lowestDayElement.textContent = data.labels[rawData.indexOf(minValue)] || '--';
                }
                if (weekChangeElement) {
                    const previousTotal = data.datasets.length > 1
                        ? data.datasets[1].data.reduce((a, b) => a + (b || 0), 0)
                        : 0;
                    const trendElement = document.querySelector('[data-stat="daily-trend"]');
                    if (previousTotal > 0) {
                        const currentTotal = rawData.reduce((a, b) => a + (b || 0), 0);
                        const change = ((currentTotal - previousTotal) / previousTotal * 100).toFixed(1);
                        weekChangeElement.textContent = change >= 0 ? `+${change}%` : `${change}%`;
                        if (trendElement) {
                            trendElement.textContent = change > 10 ? '📈 Rising' : change < -10 ? '📉 Falling' : '➖ Stable';
                        }
                    } else {
                        weekChangeElement.textContent = '--';
                        if (trendElement) trendElement.textContent = '--';
                    }
                }

                // Update the pattern insight paragraphs from real data
                const weekdayInsight = document.getElementById('dailyWeekdayInsight');
                const weekendInsight = document.getElementById('dailyWeekendInsight');
                if (weekdayInsight && rawData.length === 7) {
                    const weekdayAvg = rawData.slice(1, 6).reduce((a, b) => a + (b || 0), 0) / 5;
                    const weekendAvg = ((rawData[0] || 0) + (rawData[6] || 0)) / 2;
                    if (weekdayAvg >= weekendAvg) {
                        const pct = weekendAvg > 0 ? Math.round((weekdayAvg - weekendAvg) / weekendAvg * 100) : 100;
                        weekdayInsight.textContent = `Weekdays average ${pct}% more incidents than weekend days in the selected period. Consider increased patrol during weekdays.`;
                    } else {
                        const pct = weekdayAvg > 0 ? Math.round((weekendAvg - weekdayAvg) / weekdayAvg * 100) : 100;
                        weekdayInsight.textContent = `Weekend days average ${pct}% more incidents than weekdays in the selected period. Consider increased weekend patrol coverage.`;
                    }
                }
                if (weekendInsight && rawData.length === 7) {
                    const peakIdx = rawData.indexOf(Math.max(...nonNull));
                    weekendInsight.textContent = `${data.labels[peakIdx]} recorded the most incidents (${Math.max(...nonNull)}) in this comparison. Focus resources on that day.`;
                }
            }
        }

        function resetDailyComparison() {
            document.getElementById('dailyComparisonType').value = 'month-over-month';
            document.getElementById('dailyYearFilter').value = new Date().getFullYear();
            document.getElementById('dailyMonthFilter').value = '';

            // Remove active class from all quick filter buttons
            document.querySelectorAll('.quick-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            updateDailyComparison();
        }

        // Quick filter functions for daily comparison (month-level granularity)
        function applyDailyQuickFilter(filterType) {
            const today = new Date();
            const currentYear = today.getFullYear();
            const currentMonth = today.getMonth() + 1;

            // Remove active class from all quick filter buttons
            document.querySelectorAll('.quick-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Add active class to clicked button
            event.target.classList.add('active');

            switch(filterType) {
                case 'this-month':
                    document.getElementById('dailyMonthFilter').value = currentMonth;
                    document.getElementById('dailyYearFilter').value = currentYear;
                    break;
                case 'last-month':
                    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    document.getElementById('dailyMonthFilter').value = lastMonth.getMonth() + 1;
                    document.getElementById('dailyYearFilter').value = lastMonth.getFullYear();
                    break;
                case 'this-year':
                    document.getElementById('dailyMonthFilter').value = '';
                    document.getElementById('dailyYearFilter').value = currentYear;
                    break;
            }

            // Trigger comparison update
            updateDailyComparison();
        }

        function initializeHourlyComparisonCharts() {
            // Initialize with default comparison
            updateHourlyComparison();
        }

        // Real-time hourly comparison update - fetches real hourly counts per day type
        function updateHourlyComparison() {
            const comparisonType = document.getElementById('hourlyComparisonType')?.value || 'day-types';
            const timeView = document.getElementById('hourlyTimeView')?.value || '24';
            const year = parseInt(document.getElementById('hourlyYearFilter')?.value || new Date().getFullYear());
            const month = document.getElementById('hourlyMonthFilter')?.value || '';

            // Show loading
            const loader = document.getElementById('hourlyComparisonLoader');
            if (loader) loader.classList.add('active');

            // Clear previous timeout
            if (window.hourlyComparisonTimeout) {
                clearTimeout(window.hourlyComparisonTimeout);
            }

            // Debounce the update
            window.hourlyComparisonTimeout = setTimeout(async () => {
                try {
                    const comparisonData = await fetchHourlyComparisonData(comparisonType, timeView, year, month);
                    updateHourlyComparisonChart(comparisonData);
                    updateHourlyPeakAnalysis(comparisonData);
                    updateHourlyComparisonStatistics(comparisonData);
                } catch (error) {
                    console.error('Error loading hourly comparison:', error);
                } finally {
                    if (loader) loader.classList.remove('active');
                }
            }, 500);
        }

        async function fetchPeakHours(params) {
            const response = await fetch(`/dashboard/charts?${new URLSearchParams(params)}`);
            const data = await response.json();
            return data.peakHours || { labels: [], data: [] };
        }

        function sliceHourView(labels, series, view) {
            const sliceAll = (arr) => {
                switch(view) {
                    case 'business': return arr.slice(8, 18);   // 8AM-6PM
                    case 'evening': return arr.slice(18, 24);   // 6PM-12AM
                    case 'night': return arr.slice(0, 6);       // 12AM-6AM
                    default: return arr;
                }
            };
            return {
                labels: sliceAll(labels),
                series: series.map(s => ({ ...s, data: sliceAll(s.data) }))
            };
        }

        async function fetchHourlyComparisonData(type, view, year, month) {
            const lineStyle = (label, data, color, bg) => ({
                label, data,
                borderColor: color, backgroundColor: bg,
                borderWidth: 3, tension: 0.4, fill: true
            });

            const baseParams = { year: year };
            if (month) baseParams.month = month;

            let labels = [];
            let series = [];

            if (type === 'year-over-year') {
                const prevParams = { ...baseParams, year: year - 1 };
                const [current, previous] = await Promise.all([
                    fetchPeakHours(baseParams),
                    fetchPeakHours(prevParams)
                ]);
                labels = current.labels;
                series = [
                    lineStyle(`${year}`, current.data, '#274d4c', 'rgba(39, 77, 76, 0.1)'),
                    lineStyle(`${year - 1}`, previous.data, '#94a3b8', 'rgba(148, 163, 184, 0.1)')
                ];
            } else {
                // Default: weekday vs weekend (real DAYOFWEEK-filtered queries)
                const [weekday, weekend] = await Promise.all([
                    fetchPeakHours({ ...baseParams, day_type: 'weekday' }),
                    fetchPeakHours({ ...baseParams, day_type: 'weekend' })
                ]);
                labels = weekday.labels;
                series = [
                    lineStyle('Weekdays (Mon-Fri)', weekday.data, '#274d4c', 'rgba(39, 77, 76, 0.1)'),
                    lineStyle('Weekends (Sat-Sun)', weekend.data, '#f59e0b', 'rgba(245, 158, 11, 0.1)')
                ];
            }

            const sliced = sliceHourView(labels, series, view);
            return { labels: sliced.labels, datasets: sliced.series };
        }

        function updateHourlyComparisonChart(data) {
            const ctx = document.getElementById('hourlyComparisonChart')?.getContext('2d');
            if (!ctx) return;

            // Destroy existing chart
            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                existingChart.destroy();
            }

            // Create new chart
            new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function updateHourlyPeakAnalysis(data) {
            const ctx = document.getElementById('hourlyPeakAnalysisChart')?.getContext('2d');
            if (!ctx || !data.datasets.length) return;

            // Period breakdown only makes sense on the full 24-hour view
            if (data.datasets[0].data.length !== 24) {
                const existing = Chart.getChart(ctx);
                if (existing) existing.destroy();
                return;
            }

            // Calculate time period totals from current data
            const currentData = data.datasets[0].data;
            const morningSum = currentData.slice(6, 12).reduce((a, b) => a + b, 0);
            const afternoonSum = currentData.slice(12, 18).reduce((a, b) => a + b, 0);
            const eveningSum = currentData.slice(18, 24).reduce((a, b) => a + b, 0);
            const nightSum = currentData.slice(0, 6).reduce((a, b) => a + b, 0);

            // Destroy existing chart
            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                existingChart.destroy();
            }

            // Create new peak analysis chart
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Morning (6-12)', 'Afternoon (12-18)', 'Evening (18-24)', 'Night (0-6)'],
                    datasets: [{
                        label: 'Incidents by Time Period',
                        data: [morningSum, afternoonSum, eveningSum, nightSum],
                        backgroundColor: [
                            '#fbbf24',
                            '#f59e0b', 
                            '#ef4444',
                            '#6b7280'
                        ],
                        borderWidth: 0,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        function updateHourlyComparisonStatistics(data) {
            if (data.datasets && data.datasets.length > 0) {
                const currentData = data.datasets[0].data;
                if (!currentData.length) return;

                const maxValue = Math.max(...currentData);
                const minValue = Math.min(...currentData);
                const totalIncidents = currentData.reduce((a, b) => a + b, 0);

                // Update statistics cards from real data
                const peakHourElement = document.querySelector('[data-stat="peak-hour"]');
                const lowestHourElement = document.querySelector('[data-stat="lowest-hour"]');
                const eveningShareElement = document.querySelector('[data-stat="evening-share"]');
                const riskLevelElement = document.querySelector('[data-stat="hourly-risk"]');

                if (peakHourElement) peakHourElement.textContent = data.labels[currentData.indexOf(maxValue)] || '--';
                if (lowestHourElement) lowestHourElement.textContent = data.labels[currentData.indexOf(minValue)] || '--';

                let eveningShare = null;
                if (eveningShareElement) {
                    if (currentData.length === 24 && totalIncidents > 0) {
                        eveningShare = ((currentData.slice(18, 24).reduce((a, b) => a + b, 0) +
                                       currentData.slice(0, 6).reduce((a, b) => a + b, 0)) / totalIncidents * 100).toFixed(1);
                        eveningShareElement.textContent = `${eveningShare}%`;
                    } else {
                        eveningShareElement.textContent = '--';
                    }
                }

                if (riskLevelElement) {
                    riskLevelElement.textContent = eveningShare === null ? '--'
                        : eveningShare >= 50 ? 'High' : eveningShare >= 30 ? 'Medium' : 'Low';
                }

                // Update the pattern insight paragraphs from real data
                const eveningInsight = document.getElementById('hourlyEveningInsight');
                const dayTypeInsight = document.getElementById('hourlyDayTypeInsight');
                if (eveningInsight) {
                    eveningInsight.textContent = eveningShare !== null
                        ? `${eveningShare}% of incidents occur between 6PM and 6AM in the selected period. ${eveningShare >= 50 ? 'Prioritize night patrol coverage.' : 'Night activity is moderate; maintain balanced coverage.'}`
                        : 'Switch to the 24-Hour View to compute the evening/night share.';
                }
                if (dayTypeInsight && data.datasets.length > 1) {
                    const secondTotal = data.datasets[1].data.reduce((a, b) => a + (b || 0), 0);
                    dayTypeInsight.textContent = `${data.datasets[0].label}: ${totalIncidents} incident(s) vs ${data.datasets[1].label}: ${secondTotal} incident(s) across the selected hours.`;
                }
            }
        }

        function resetHourlyComparison() {
            document.getElementById('hourlyComparisonType').value = 'day-types';
            document.getElementById('hourlyTimeView').value = '24';
            document.getElementById('hourlyYearFilter').value = new Date().getFullYear();
            document.getElementById('hourlyMonthFilter').value = '';
            
            // Remove active class from all quick filter buttons
            document.querySelectorAll('.quick-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            updateHourlyComparison();
        }

        // Quick filter functions for hourly comparison (month-level granularity)
        function applyHourlyQuickFilter(filterType) {
            const today = new Date();
            const currentYear = today.getFullYear();
            const currentMonth = today.getMonth() + 1;

            // Remove active class from all quick filter buttons
            document.querySelectorAll('.quick-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Add active class to clicked button
            event.target.classList.add('active');

            switch(filterType) {
                case 'this-month':
                    document.getElementById('hourlyTimeView').value = '24';
                    document.getElementById('hourlyMonthFilter').value = currentMonth;
                    document.getElementById('hourlyYearFilter').value = currentYear;
                    break;
                case 'last-month':
                    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    document.getElementById('hourlyTimeView').value = '24';
                    document.getElementById('hourlyMonthFilter').value = lastMonth.getMonth() + 1;
                    document.getElementById('hourlyYearFilter').value = lastMonth.getFullYear();
                    break;
                case 'this-year':
                    document.getElementById('hourlyTimeView').value = '24';
                    document.getElementById('hourlyMonthFilter').value = '';
                    document.getElementById('hourlyYearFilter').value = currentYear;
                    break;
                case 'evening-hours':
                    document.getElementById('hourlyTimeView').value = 'evening';
                    break;
                case 'night-hours':
                    document.getElementById('hourlyTimeView').value = 'night';
                    break;
            }

            // Trigger comparison update
            updateHourlyComparison();
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDailyComparisonModal();
                closeHourlyComparisonModal();
            }
        });

        // Close modals on background click
        const dailyModal = document.getElementById('dailyComparisonModal');
        if (dailyModal) {
            dailyModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDailyComparisonModal();
                }
            });
        }

        const hourlyModal = document.getElementById('hourlyComparisonModal');
        if (hourlyModal) {
            hourlyModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeHourlyComparisonModal();
                }
            });
        }

        // Debounce function for automatic filtering
        let modalFilterTimeout;
        function autoApplyModalFilters() {
            // Clear previous timeout
            clearTimeout(modalFilterTimeout);
            
            // Show loading state
            const loader = document.getElementById('modalFilterLoader');
            loader.classList.add('active');
            
            // Set new timeout for debouncing (500ms delay)
            modalFilterTimeout = setTimeout(() => {
                const year = document.getElementById('modalYearFilter').value;
                const month = document.getElementById('modalMonthFilter').value;
                const dayOfWeek = document.getElementById('modalDayFilter').value;
                const timeOfDay = document.getElementById('modalTimeFilter').value;

                // Fetch filtered data from server
                const params = new URLSearchParams();
                params.append('year', year);
                if (month) params.append('month', month);
                if (dayOfWeek) params.append('day_of_week', dayOfWeek);
                if (timeOfDay) params.append('time_of_day', timeOfDay);

                fetch(`/dashboard/charts?${params.toString()}`)
                    .then(response => response.json())
                    .then(responseData => {
                        // Update modal charts with new data
                        updateModalCharts(responseData);
                        // Update statistics
                        updateModalStatisticsFromData(responseData);
                    })
                    .catch(error => {
                        console.error('Error fetching modal data:', error);
                    })
                    .finally(() => {
                        loader.classList.remove('active');
                    });
            }, 500); // 500ms debounce delay
        }

        function resetModalFilters() {
            // Clear any pending timeout
            clearTimeout(modalFilterTimeout);
            
            document.getElementById('modalYearFilter').value = '{{ now()->year }}';
            document.getElementById('modalMonthFilter').value = '';
            document.getElementById('modalDayFilter').value = '';
            document.getElementById('modalTimeFilter').value = '';
            
            // Reinitialize modal with default data
            initializeModalCharts();
            updateModalStatistics();
        }

        function updateModalCharts(responseData) {
            // Update daily chart
            const dailyCtx = document.getElementById('modalDailyChart')?.getContext('2d');
            if (dailyCtx && responseData.weeklyDist) {
                const dailyChart = Chart.getChart(dailyCtx);
                if (dailyChart) {
                    dailyChart.data.labels = responseData.weeklyDist.labels;
                    dailyChart.data.datasets[0].data = responseData.weeklyDist.data;
                    dailyChart.update();
                }
            }

            // Update hourly chart
            const hourlyCtx = document.getElementById('modalHourlyChart')?.getContext('2d');
            if (hourlyCtx && responseData.peakHours) {
                const hourlyChart = Chart.getChart(hourlyCtx);
                if (hourlyChart) {
                    hourlyChart.data.labels = responseData.peakHours.labels;
                    hourlyChart.data.datasets[0].data = responseData.peakHours.data;
                    hourlyChart.update();
                }
            }
        }

        function updateModalStatisticsFromData(responseData) {
            // Calculate statistics from response data
            if (responseData.weeklyDist && responseData.weeklyDist.data) {
                const dayData = responseData.weeklyDist.data;
                const dayLabels = responseData.weeklyDist.labels;
                const maxDayIndex = dayData.indexOf(Math.max(...dayData));
                document.getElementById('modalPeakDay').textContent = dayLabels[maxDayIndex] || '--';
            }

            if (responseData.peakHours && responseData.peakHours.data) {
                const hourData = responseData.peakHours.data;
                const hourLabels = responseData.peakHours.labels;
                const maxHourIndex = hourData.indexOf(Math.max(...hourData));
                document.getElementById('modalPeakHour').textContent = hourLabels[maxHourIndex] || '--';
            }

            if (responseData.weeklyDist && responseData.weeklyDist.data) {
                const totalIncidents = responseData.weeklyDist.data.reduce((a, b) => a + b, 0);
                const avgIncidents = Math.round(totalIncidents / 7);
                document.getElementById('modalAvgIncidents').textContent = avgIncidents || '--';
            }

            // Keep trend as default or calculate from data
            document.getElementById('modalTrend').textContent = '📊 Analyzing...';
        }

        // Monthly Analysis Modal Functions
        function openMonthlyAnalysisModal() {
            const modal = document.getElementById('monthlyAnalysisModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            
            // Initialize modal charts
            setTimeout(() => {
                initializeMonthlyModalCharts();
            }, 100);
        }

        function closeMonthlyAnalysisModal() {
            const modal = document.getElementById('monthlyAnalysisModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        function initializeMonthlyModalCharts() {
            // Initialize expanded monthly chart
            const monthlyCtx = document.getElementById('modalMonthlyChart')?.getContext('2d');
            if (monthlyCtx && window.monthlyTrendChart) {
                // Clone the existing monthly chart data with enhanced styling
                new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        ...window.monthlyTrendChart.data,
                        datasets: [{
                            ...window.monthlyTrendChart.data.datasets[0],
                            borderColor: '#274d4c',
                            backgroundColor: 'rgba(39, 77, 76, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#274d4c',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { display: false }
                        },
                        scales: { 
                            y: { 
                                beginAtZero: true,
                                grid: { color: 'rgba(0, 0, 0, 0.05)' }
                            },
                            x: { 
                                ticks: { font: { size: 12 } },
                                grid: { display: false }
                            }
                        }
                    }
                });
            }

            // Initialize month comparison chart
            initializeMonthComparisonChart();
            
            // Update modal statistics
            updateMonthlyModalStatistics();
        }

        async function initializeMonthComparisonChart() {
            const ctx = document.getElementById('monthComparisonChart')?.getContext('2d');
            if (!ctx) return;

            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const currentYear = new Date().getFullYear();
            const previousYear = currentYear - 1;

            // Fetch real per-month counts for both years and align them to Jan-Dec
            const toMonthlyArray = (trend) => {
                const values = Array(12).fill(0);
                (trend.labels || []).forEach((label, i) => {
                    const monthIndex = parseInt(label.split('-')[1], 10) - 1;
                    if (monthIndex >= 0 && monthIndex < 12) values[monthIndex] = trend.data[i];
                });
                return values;
            };

            let currentYearData = Array(12).fill(0);
            let previousYearData = Array(12).fill(0);

            try {
                const [currentRes, previousRes] = await Promise.all([
                    fetch(`/dashboard/charts?year=${currentYear}`).then(r => r.json()),
                    fetch(`/dashboard/charts?year=${previousYear}`).then(r => r.json())
                ]);
                currentYearData = toMonthlyArray(currentRes.monthlyTrend || {});
                previousYearData = toMonthlyArray(previousRes.monthlyTrend || {});
            } catch (error) {
                console.error('Error loading year comparison data:', error);
            }

            const existingChart = Chart.getChart(ctx);
            if (existingChart) existingChart.destroy();

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Current Year',
                            data: currentYearData,
                            backgroundColor: '#274d4c',
                            borderColor: '#274d4c',
                            borderWidth: 2,
                            borderRadius: 6
                        },
                        {
                            label: 'Previous Year',
                            data: previousYearData,
                            backgroundColor: '#94a3b8',
                            borderColor: '#94a3b8',
                            borderWidth: 2,
                            borderRadius: 6
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
                        y: { 
                            beginAtZero: true,
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function updateMonthlyModalStatistics() {
            // Calculate statistics from current monthly data
            if (window.monthlyTrendChart && window.monthlyTrendChart.data) {
                const data = window.monthlyTrendChart.data.datasets[0].data;
                const labels = window.monthlyTrendChart.data.labels;
                
                // Find peak month
                const maxValue = Math.max(...data);
                const peakIndex = data.indexOf(maxValue);
                document.getElementById('modalPeakMonth').textContent = labels[peakIndex] || '--';
                
                // Calculate average
                const avgValue = Math.round(data.reduce((a, b) => a + b, 0) / data.length);
                document.getElementById('modalMonthlyAvg').textContent = avgValue || '--';
                
                // Calculate trend
                const trend = data[data.length - 1] > data[0] ? '📈 Rising' : '📉 Declining';
                document.getElementById('modalMonthlyTrend').textContent = trend;
                
                // Find lowest month
                const minValue = Math.min(...data);
                const lowestIndex = data.indexOf(minValue);
                document.getElementById('modalLowestMonth').textContent = labels[lowestIndex] || '--';
            }

            // Generate monthly insights
            generateMonthlyInsights();
        }

        function generateMonthlyInsights() {
            // Compute insights from the real 12-month series
            const monthLabels = {!! $monthLabels !!};
            const monthValues = {!! $monthData !!};

            const maxValue = Math.max(...monthValues);
            const minValue = Math.min(...monthValues);
            const peakMonth = monthLabels[monthValues.indexOf(maxValue)] || '--';
            const lowMonth = monthLabels[monthValues.indexOf(minValue)] || '--';
            const avgValue = monthValues.length ? Math.round(monthValues.reduce((a, b) => a + b, 0) / monthValues.length) : 0;
            const trendText = computeTrendDirection(monthValues);

            const insights = [
                {
                    title: 'Peak Month',
                    description: `${peakMonth} recorded the highest incident count (${maxValue}) in the last 12 months, while ${lowMonth} had the lowest (${minValue}).`,
                    icon: 'fa-calendar-alt',
                    color: 'alertara'
                },
                {
                    title: 'Monthly Average',
                    description: `Incidents average ${avgValue} per month across the last 12 months of recorded data.`,
                    icon: 'fa-chart-line',
                    color: 'orange'
                },
                {
                    title: 'Recent Trend',
                    description: `Month-over-month direction: ${trendText} based on the two most recent months of data.`,
                    icon: 'fa-exclamation-triangle',
                    color: 'red'
                },
                {
                    title: 'Strategic Planning',
                    description: `Allocate additional resources around ${peakMonth}, historically the highest-activity month in the current data.`,
                    icon: 'fa-lightbulb',
                    color: 'green'
                }
            ];

            const container = document.getElementById('monthlyInsights');
            let html = '';

            insights.forEach(insight => {
                const colorClasses = {
                    alertara: 'bg-alertara-50 border-alertara-200 text-alertara-700',
                    orange: 'bg-orange-50 border-orange-200 text-orange-700',
                    red: 'bg-red-50 border-red-200 text-red-700',
                    green: 'bg-green-50 border-green-200 text-green-700'
                };

                const iconColors = {
                    alertara: 'text-alertara-600',
                    orange: 'text-orange-600',
                    red: 'text-red-600',
                    green: 'text-green-600'
                };

                html += `
                    <div class="p-4 rounded-lg border ${colorClasses[insight.color]} bg-white hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas ${insight.icon} ${iconColors[insight.color]}"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 mb-2">${insight.title}</h4>
                                <p class="text-sm text-gray-600 leading-relaxed">${insight.description}</p>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMonthlyAnalysisModal();
            }
        });

        // Close modal on background click
        document.getElementById('monthlyAnalysisModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMonthlyAnalysisModal();
            }
        });
    </script>

    <!-- Time Analysis Modal -->
    <div id="timeAnalysisModal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-7xl w-full max-h-[85vh] overflow-hidden flex flex-col mt-16">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-alertara-700 to-alertara-600 text-white p-6 border-b border-alertara-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-2 flex items-center gap-3">
                            <i class="fas fa-clock"></i>
                            Time-Based Crime Analysis Dashboard
                        </h2>
                        <p class="text-alertara-100 text-sm">Comprehensive analysis of crime patterns across time periods</p>
                    </div>
                    <button onclick="closeTimeAnalysisModal()" class="text-white hover:bg-alertara-800 hover:bg-opacity-50 rounded-lg p-2 transition-all duration-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Filters -->
            <div class="bg-white border-b border-gray-200 p-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-600">Filters:</span>
                        <select id="modalYearFilter" class="compact-select" onchange="autoApplyModalFilters()">
                            @for($y = now()->year - 5; $y <= now()->year; $y++)
                                <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                        <select id="modalMonthFilter" class="compact-select" onchange="autoApplyModalFilters()">
                            <option value="">All Months</option>
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}">{{ \Carbon\Carbon::createFromFormat('m', $m)->format('M') }}</option>
                            @endfor
                        </select>
                        <select id="modalDayFilter" class="compact-select" onchange="autoApplyModalFilters()">
                            <option value="">All Days</option>
                            <option value="1">Sun</option>
                            <option value="2">Mon</option>
                            <option value="3">Tue</option>
                            <option value="4">Wed</option>
                            <option value="5">Thu</option>
                            <option value="6">Fri</option>
                            <option value="7">Sat</option>
                        </select>
                        <select id="modalTimeFilter" class="compact-select" onchange="autoApplyModalFilters()">
                            <option value="">All Times</option>
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                            <option value="evening">Evening</option>
                            <option value="night">Night</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="resetModalFilters()" class="bg-gray-500 text-white px-3 py-1 rounded text-xs font-medium hover:bg-gray-600 transition-colors">
                            Reset
                        </button>
                        <div class="filter-loader-compact" id="modalFilterLoader">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Loading...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto bg-gray-50">
                <div class="p-6">
                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-calendar-alt text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-blue-700">Peak Day</p>
                                    <p class="text-xl font-bold text-blue-900" id="modalPeakDay">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-clock text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-orange-700">Peak Hour</p>
                                    <p class="text-xl font-bold text-orange-900" id="modalPeakHour">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-chart-bar text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-red-700">Avg Incidents/Day</p>
                                    <p class="text-xl font-bold text-red-900" id="modalAvgIncidents">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-trending-up text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-green-700">Trend Direction</p>
                                    <p class="text-xl font-bold text-green-900" id="modalTrend">--</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Expanded Daily Chart -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-calendar-week text-alertara-600"></i>
                                Daily Crime Distribution - Detailed Analysis
                            </h3>
                            <div style="position: relative; height: 350px;">
                                <canvas id="modalDailyChart"></canvas>
                            </div>
                        </div>

                        <!-- Expanded Hourly Chart -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-hourglass-half text-alertara-600"></i>
                                Hourly Crime Peaks - Detailed Analysis
                            </h3>
                            <div style="position: relative; height: 350px;">
                                <canvas id="modalHourlyChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Time Period Analysis -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Time Period Comparison -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chart-pie text-alertara-600"></i>
                                Crime Distribution by Time Period
                            </h3>
                            <div style="position: relative; height: 300px;">
                                <canvas id="timePeriodComparisonChart"></canvas>
                            </div>
                        </div>

                        <!-- Detailed Insights -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-lightbulb text-alertara-600"></i>
                                Detailed Analysis Insights
                            </h3>
                            <div id="modalInsights" class="space-y-3">
                                <!-- Insights will be generated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Analysis Modal -->
    <div id="monthlyAnalysisModal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-7xl w-full max-h-[85vh] overflow-hidden flex flex-col mt-16">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-alertara-700 to-alertara-600 text-white p-6 border-b border-alertara-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-2 flex items-center gap-3">
                            <i class="fas fa-calendar-alt"></i>
                            Monthly Crime Analysis Dashboard
                        </h2>
                        <p class="text-alertara-100 text-sm">Comprehensive analysis of crime patterns across months and years</p>
                    </div>
                    <button onclick="closeMonthlyAnalysisModal()" class="text-white hover:bg-alertara-800 hover:bg-opacity-50 rounded-lg p-2 transition-all duration-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto bg-gray-50">
                <div class="p-6">
                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-calendar-alt text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-blue-700">Peak Month</p>
                                    <p class="text-xl font-bold text-blue-900" id="modalPeakMonth">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-chart-line text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-orange-700">Monthly Average</p>
                                    <p class="text-xl font-bold text-orange-900" id="modalMonthlyAvg">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-trending-up text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-red-700">Year Trend</p>
                                    <p class="text-xl font-bold text-red-900" id="modalMonthlyTrend">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-arrow-down text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-green-700">Lowest Month</p>
                                    <p class="text-xl font-bold text-green-900" id="modalLowestMonth">--</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Expanded Monthly Chart -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-calendar-week text-alertara-600"></i>
                                Monthly Crime Trend - Detailed Analysis
                            </h3>
                            <div style="position: relative; height: 400px;">
                                <canvas id="modalMonthlyChart"></canvas>
                            </div>
                        </div>

                        <!-- Year Comparison Chart -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chart-bar text-alertara-600"></i>
                                Year-over-Year Comparison
                            </h3>
                            <div style="position: relative; height: 400px;">
                                <canvas id="monthComparisonChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Insights -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-lightbulb text-alertara-600"></i>
                            Monthly Analysis Insights
                        </h3>
                        <div id="monthlyInsights" class="space-y-3">
                            <!-- Insights will be generated here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Comparison Modal -->
    <div id="dailyComparisonModal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-7xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-alertara-700 to-alertara-600 text-white p-6 border-b border-alertara-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-2 flex items-center gap-3">
                            <i class="fas fa-calendar-week"></i>
                            Daily Crime Comparison Analysis
                        </h2>
                        <p class="text-alertara-100 text-sm">Compare daily crime patterns across different time periods</p>
                    </div>
                    <button onclick="closeDailyComparisonModal()" class="text-white hover:bg-alertara-800 hover:bg-opacity-50 rounded-lg p-2 transition-all duration-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Comparison Filters -->
            <div class="bg-white border-b border-gray-200 p-4">
                <!-- Quick Filter Presets -->
                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs font-semibold text-gray-600">Quick Filters:</span>
                        <button onclick="applyDailyQuickFilter('this-month')" class="quick-filter-btn active">
                            <i class="fas fa-calendar-alt mr-1"></i>This Month
                        </button>
                        <button onclick="applyDailyQuickFilter('last-month')" class="quick-filter-btn">
                            <i class="fas fa-calendar-alt mr-1"></i>Last Month
                        </button>
                        <button onclick="applyDailyQuickFilter('this-year')" class="quick-filter-btn">
                            <i class="fas fa-calendar mr-1"></i>This Year
                        </button>
                    </div>
                </div>

                <!-- Advanced Filters -->
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-600">Compare:</span>
                        <select id="dailyComparisonType" class="compact-select" onchange="updateDailyComparison()">
                            <option value="month-over-month">Selected Period vs Previous Period</option>
                            <option value="year-over-year">Same Period Last Year</option>
                            <option value="weekday-vs-weekend">Weekday vs Weekend</option>
                        </select>
                        <select id="dailyYearFilter" class="compact-select" onchange="updateDailyComparison()">
                            @for($y = now()->year - 2; $y <= now()->year; $y++)
                                <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                        <select id="dailyMonthFilter" class="compact-select" onchange="updateDailyComparison()">
                            <option value="">All Months</option>
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}">{{ \Carbon\Carbon::createFromFormat('m', $m)->format('F') }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="resetDailyComparison()" class="bg-gray-500 text-white px-3 py-1 rounded text-xs font-medium hover:bg-gray-600 transition-colors">
                            Reset
                        </button>
                        <div class="filter-loader-compact" id="dailyComparisonLoader">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Loading...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto bg-gray-50">
                <div class="p-6">
                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-calendar-day text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-blue-700">Peak Day</p>
                                    <p class="text-xl font-bold text-blue-900" data-stat="peak-day">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-arrow-down text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-green-700">Lowest Day</p>
                                    <p class="text-xl font-bold text-green-900" data-stat="lowest-day">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-percentage text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-orange-700">Period Change</p>
                                    <p class="text-xl font-bold text-orange-900" data-stat="week-change">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-chart-line text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-red-700">Trend</p>
                                    <p class="text-xl font-bold text-red-900" data-stat="daily-trend">--</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Comparison Chart -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chart-bar text-alertara-600"></i>
                                Week-over-Week Comparison
                            </h3>
                            <div style="position: relative; height: 400px;">
                                <canvas id="dailyComparisonChart"></canvas>
                            </div>
                        </div>

                        <!-- Trend Analysis -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chart-line text-alertara-600"></i>
                                Current Week Trend
                            </h3>
                            <div style="position: relative; height: 400px;">
                                <canvas id="dailyTrendAnalysisChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Insights -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-lightbulb text-alertara-600"></i>
                            Daily Pattern Insights
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <h4 class="font-semibold text-blue-900 mb-2">Weekday vs Weekend Pattern</h4>
                                <p class="text-sm text-gray-700" id="dailyWeekdayInsight">Loading pattern analysis...</p>
                            </div>
                            <div class="p-4 bg-orange-50 border border-orange-200 rounded-lg">
                                <h4 class="font-semibold text-orange-900 mb-2">Peak Day</h4>
                                <p class="text-sm text-gray-700" id="dailyWeekendInsight">Loading pattern analysis...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hourly Comparison Modal -->
    <div id="hourlyComparisonModal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-7xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-alertara-700 to-alertara-600 text-white p-6 border-b border-alertara-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-2 flex items-center gap-3">
                            <i class="fas fa-clock"></i>
                            Hourly Crime Comparison Analysis
                        </h2>
                        <p class="text-alertara-100 text-sm">Compare hourly crime patterns across different day types</p>
                    </div>
                    <button onclick="closeHourlyComparisonModal()" class="text-white hover:bg-alertara-800 hover:bg-opacity-50 rounded-lg p-2 transition-all duration-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Comparison Filters -->
            <div class="bg-white border-b border-gray-200 p-4">
                <!-- Quick Filter Presets -->
                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs font-semibold text-gray-600">Quick Filters:</span>
                        <button onclick="applyHourlyQuickFilter('this-month')" class="quick-filter-btn active">
                            <i class="fas fa-calendar-alt mr-1"></i>This Month
                        </button>
                        <button onclick="applyHourlyQuickFilter('last-month')" class="quick-filter-btn">
                            <i class="fas fa-calendar-alt mr-1"></i>Last Month
                        </button>
                        <button onclick="applyHourlyQuickFilter('this-year')" class="quick-filter-btn">
                            <i class="fas fa-calendar mr-1"></i>This Year
                        </button>
                        <button onclick="applyHourlyQuickFilter('evening-hours')" class="quick-filter-btn">
                            <i class="fas fa-moon mr-1"></i>Evening Hours
                        </button>
                        <button onclick="applyHourlyQuickFilter('night-hours')" class="quick-filter-btn">
                            <i class="fas fa-bed mr-1"></i>Night Hours
                        </button>
                    </div>
                </div>

                <!-- Advanced Filters -->
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-600">Compare:</span>
                        <select id="hourlyComparisonType" class="compact-select" onchange="updateHourlyComparison()">
                            <option value="day-types">Weekday vs Weekend</option>
                            <option value="year-over-year">This Year vs Last Year</option>
                        </select>
                        <select id="hourlyTimeView" class="compact-select" onchange="updateHourlyComparison()">
                            <option value="24">24-Hour View</option>
                            <option value="business">Business Hours (8AM-6PM)</option>
                            <option value="evening">Evening Hours (6PM-12AM)</option>
                            <option value="night">Night Hours (12AM-6AM)</option>
                        </select>
                        <select id="hourlyYearFilter" class="compact-select" onchange="updateHourlyComparison()">
                            @for($y = now()->year - 2; $y <= now()->year; $y++)
                                <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                        <select id="hourlyMonthFilter" class="compact-select" onchange="updateHourlyComparison()">
                            <option value="">All Months</option>
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}">{{ \Carbon\Carbon::createFromFormat('m', $m)->format('F') }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="resetHourlyComparison()" class="bg-gray-500 text-white px-3 py-1 rounded text-xs font-medium hover:bg-gray-600 transition-colors">
                            Reset
                        </button>
                        <div class="filter-loader-compact" id="hourlyComparisonLoader">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Loading...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto bg-gray-50">
                <div class="p-6">
                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-fire text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-red-700">Peak Hour</p>
                                    <p class="text-xl font-bold text-red-900" data-stat="peak-hour">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-moon text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-blue-700">Lowest Hour</p>
                                    <p class="text-xl font-bold text-blue-900" data-stat="lowest-hour">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-percentage text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-orange-700">Evening/Night Share</p>
                                    <p class="text-xl font-bold text-orange-900" data-stat="evening-share">--</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-chart-line text-white text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-green-700">Night Risk Level</p>
                                    <p class="text-xl font-bold text-green-900" data-stat="hourly-risk">--</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Comparison Chart -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chart-line text-alertara-600"></i>
                                Day Type Comparison
                            </h3>
                            <div style="position: relative; height: 400px;">
                                <canvas id="hourlyComparisonChart"></canvas>
                            </div>
                        </div>

                        <!-- Peak Analysis -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chart-bar text-alertara-600"></i>
                                Time Period Analysis
                            </h3>
                            <div style="position: relative; height: 400px;">
                                <canvas id="hourlyPeakAnalysisChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Insights -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-lightbulb text-alertara-600"></i>
                            Hourly Pattern Insights
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                                <h4 class="font-semibold text-red-900 mb-2">Evening/Night Activity (6PM-6AM)</h4>
                                <p class="text-sm text-gray-700" id="hourlyEveningInsight">Loading pattern analysis...</p>
                            </div>
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <h4 class="font-semibold text-blue-900 mb-2">Comparison Summary</h4>
                                <p class="text-sm text-gray-700" id="hourlyDayTypeInsight">Loading pattern analysis...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection