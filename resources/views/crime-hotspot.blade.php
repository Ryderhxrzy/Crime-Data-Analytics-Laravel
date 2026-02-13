@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

@extends('layouts.app')
@section('title', 'Crime Hotspot Analysis')
@section('content')
    <div class="p-6">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-location-dot mr-3" style="color: #274d4c;"></i>
                Crime Hotspot Analysis
            </h1>
            <p class="text-gray-600">Advanced geographic analysis of crime concentration areas and hotspots</p>
        </div>

        <!-- Interactive Map Controls -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Heatmap Intensity -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Heatmap Intensity</label>
                    <input type="range" id="heatIntensity" min="1" max="10" value="5" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                        <span>Low</span>
                        <span id="intensityValue">5</span>
                        <span>High</span>
                    </div>
                </div>

                <!-- Time Period Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Time Period</label>
                    <select id="timePeriod" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#274d4c]">
                        <option value="24h">Last 24 Hours</option>
                        <option value="7d" selected>Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                        <option value="90d">Last 90 Days</option>
                        <option value="1y">Last Year</option>
                    </select>
                </div>

                <!-- Crime Type Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Crime Type</label>
                    <select id="crimeTypeFilter" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#274d4c]">
                        <option value="">All Types</option>
                        @foreach($crimeCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- View Mode -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">View Mode</label>
                    <div class="flex gap-2">
                        <button onclick="setViewMode('heatmap')" class="view-mode-btn flex-1 px-3 py-2 text-sm bg-[#274d4c] text-white rounded-md hover:bg-[#1a3534] transition-colors">
                            <i class="fas fa-fire-flame-curved mr-1"></i>Heatmap
                        </button>
                        <button onclick="setViewMode('clusters')" class="view-mode-btn flex-1 px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                            <i class="fas fa-circle-nodes mr-1"></i>Clusters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3 mt-4">
                <button onclick="refreshHotspotData()" class="px-4 py-2 bg-[#274d4c] text-white rounded-md hover:bg-[#1a3534] transition-colors">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh Data
                </button>
                <button onclick="exportHotspotMap()" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                    <i class="fas fa-download mr-2"></i>Export Map
                </button>
                <button onclick="toggleRealTime()" id="realTimeBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                    <i class="fas fa-play mr-2"></i>Real-time
                </button>
            </div>
        </div>

        <!-- Hotspot Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-red-50 to-red-100 border-red-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-red-900 mb-1">
                            <i class="fas fa-fire mr-1"></i>Active Hotspots
                        </p>
                        <p class="text-2xl font-bold text-red-700" id="activeHotspots">12</p>
                        <p class="text-xs text-red-600 mt-1">High concentration areas</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-orange-900 mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Critical Zones
                        </p>
                        <p class="text-2xl font-bold text-orange-700" id="criticalZones">5</p>
                        <p class="text-xs text-orange-600 mt-1">Require immediate attention</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 border-yellow-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-yellow-900 mb-1">
                            <i class="fas fa-chart-line mr-1"></i>Emerging Areas
                        </p>
                        <p class="text-2xl font-bold text-yellow-700" id="emergingAreas">8</p>
                        <p class="text-xs text-yellow-600 mt-1">New potential hotspots</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-blue-900 mb-1">
                            <i class="fas fa-shield-alt mr-1"></i>Safe Zones
                        </p>
                        <p class="text-2xl font-bold text-blue-700" id="safeZones">23</p>
                        <p class="text-xs text-blue-600 mt-1">Low crime activity areas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Map Container -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Interactive Hotspot Map -->
            <div class="lg:col-span-2 bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-map mr-2" style="color: #274d4c;"></i>
                        Interactive Crime Hotspot Map
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 600px;">
                        <canvas id="hotspotMap"></canvas>
                    </div>
                </div>
            </div>

            <!-- Hotspot Details Panel -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-info-circle mr-2" style="color: #274d4c;"></i>
                        Hotspot Details
                    </h3>
                </div>
                <div class="p-4">
                    <div id="hotspotDetails" class="space-y-4">
                        <div class="text-center text-gray-500 py-8">
                            <i class="fas fa-mouse-pointer text-4xl mb-3"></i>
                            <p>Click on a hotspot to view details</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hotspot Analysis Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Hotspot Intensity Over Time -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-chart-area mr-2" style="color: #274d4c;"></i>
                        Hotspot Intensity Trends
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 300px;">
                        <canvas id="intensityTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Hotspot Distribution by Type -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-chart-pie mr-2" style="color: #274d4c;"></i>
                        Crime Type Distribution in Hotspots
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 300px;">
                        <canvas id="hotspotCrimeTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Predictive Insights -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
            <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-brain mr-2" style="color: #274d4c;"></i>
                    Predictive Hotspot Analysis
                </h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <h4 class="font-semibold text-red-900 mb-2">
                            <i class="fas fa-exclamation-circle mr-1"></i>High Risk Predictions
                        </h4>
                        <p class="text-sm text-gray-700 mb-3">Areas likely to become hotspots in the next 48 hours</p>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium">Downtown Core</span>
                                <span class="text-xs bg-red-600 text-white px-2 py-1 rounded">85%</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium">Industrial Zone</span>
                                <span class="text-xs bg-red-600 text-white px-2 py-1 rounded">72%</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                        <h4 class="font-semibold text-orange-900 mb-2">
                            <i class="fas fa-clock mr-1"></i>Peak Time Analysis
                        </h4>
                        <p class="text-sm text-gray-700 mb-3">Most likely times for hotspot activity</p>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium">Evening (6PM-10PM)</span>
                                <span class="text-xs bg-orange-600 text-white px-2 py-1 rounded">68%</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium">Weekend Nights</span>
                                <span class="text-xs bg-orange-600 text-white px-2 py-1 rounded">54%</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-900 mb-2">
                            <i class="fas fa-route mr-1"></i>Patrol Recommendations
                        </h4>
                        <p class="text-sm text-gray-700 mb-3">Optimized patrol routes for hotspot coverage</p>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium">Route A</span>
                                <span class="text-xs bg-blue-600 text-white px-2 py-1 rounded">12 zones</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium">Route B</span>
                                <span class="text-xs bg-blue-600 text-white px-2 py-1 rounded">8 zones</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let hotspotMap, intensityTrendChart, hotspotCrimeTypeChart;
        let currentViewMode = 'heatmap';
        let realTimeInterval = null;
        let isRealTimeActive = false;

        document.addEventListener('DOMContentLoaded', function() {
            initializeHotspotMap();
            initializeIntensityTrendChart();
            initializeHotspotCrimeTypeChart();
            setupEventListeners();
            updateStatistics();
        });

        function initializeHotspotMap() {
            const ctx = document.getElementById('hotspotMap')?.getContext('2d');
            if (!ctx) return;

            // Generate realistic hotspot data
            const hotspotData = generateHotspotData();

            hotspotMap = new Chart(ctx, {
                type: 'bubble',
                data: {
                    datasets: [{
                        label: 'Crime Hotspots',
                        data: hotspotData,
                        backgroundColor: function(context) {
                            const value = context.raw.v;
                            const alpha = Math.min(value / 100, 0.8);
                            return `rgba(239, 68, 68, ${alpha})`;
                        },
                        borderColor: '#dc2626',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'point'
                    },
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            const element = elements[0];
                            const dataPoint = element.element.$context.raw;
                            showHotspotDetails(dataPoint);
                        }
                    },
                    onHover: (event, elements) => {
                        ctx.canvas.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const point = context.raw;
                                    return [
                                        `Location: ${point.label}`,
                                        `Incidents: ${point.v}`,
                                        `Risk Level: ${point.risk}`
                                    ];
                                }
                            }
                        },
                        zoom: {
                            zoom: {
                                wheel: { enabled: true },
                                pinch: { enabled: true },
                                mode: 'xy'
                            },
                            pan: {
                                enabled: true,
                                mode: 'xy'
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Longitude' },
                            grid: { display: false },
                            min: 0,
                            max: 100
                        },
                        y: {
                            title: { display: true, text: 'Latitude' },
                            grid: { 
                                color: '#e5e7eb',
                                drawBorder: false
                            },
                            min: 0,
                            max: 100
                        }
                    }
                }
            });
        }

        function generateHotspotData() {
            const locations = [
                { name: 'Downtown Core', x: 50, y: 50, base: 85 },
                { name: 'Industrial Zone', x: 75, y: 30, base: 72 },
                { name: 'Riverside District', x: 25, y: 70, base: 68 },
                { name: 'Commercial Center', x: 60, y: 60, base: 58 },
                { name: 'Residential North', x: 30, y: 20, base: 45 },
                { name: 'Suburban East', x: 80, y: 80, base: 38 },
                { name: 'University Area', x: 40, y: 40, base: 42 },
                { name: 'Transport Hub', x: 55, y: 25, base: 65 }
            ];

            return locations.map(loc => ({
                x: loc.x + (Math.random() - 0.5) * 10,
                y: loc.y + (Math.random() - 0.5) * 10,
                r: Math.max(10, loc.base / 3),
                v: loc.base + Math.floor(Math.random() * 20 - 10),
                label: loc.name,
                risk: loc.base > 70 ? 'Critical' : loc.base > 50 ? 'High' : loc.base > 30 ? 'Medium' : 'Low'
            }));
        }

        function showHotspotDetails(dataPoint) {
            const detailsDiv = document.getElementById('hotspotDetails');
            detailsDiv.innerHTML = `
                <div class="space-y-4">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <h4 class="font-bold text-red-900 mb-2">${dataPoint.label}</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Risk Level:</span>
                                <span class="text-sm font-semibold ${dataPoint.risk === 'Critical' ? 'text-red-600' : dataPoint.risk === 'High' ? 'text-orange-600' : 'text-yellow-600'}">${dataPoint.risk}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Total Incidents:</span>
                                <span class="text-sm font-semibold">${dataPoint.v}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Hotspot Score:</span>
                                <span class="text-sm font-semibold">${Math.round(dataPoint.r * 3)}%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h5 class="font-semibold text-gray-900 mb-2">Recent Activity</h5>
                        <div class="space-y-1">
                            <div class="text-xs text-gray-600">• Theft - 2 hours ago</div>
                            <div class="text-xs text-gray-600">• Assault - 5 hours ago</div>
                            <div class="text-xs text-gray-600">• Vandalism - 8 hours ago</div>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 rounded-lg p-4">
                        <h5 class="font-semibold text-blue-900 mb-2">Recommended Actions</h5>
                        <div class="space-y-1">
                            <div class="text-xs text-gray-700">• Increase patrol frequency</div>
                            <div class="text-xs text-gray-700">• Install surveillance cameras</div>
                            <div class="text-xs text-gray-700">• Community engagement program</div>
                        </div>
                    </div>
                </div>
            `;
        }

        function initializeIntensityTrendChart() {
            const ctx = document.getElementById('intensityTrendChart')?.getContext('2d');
            if (!ctx) return;

            intensityTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00', '24:00'],
                    datasets: [{
                        label: 'Hotspot Intensity',
                        data: [45, 32, 38, 52, 68, 85, 72],
                        borderColor: '#274d4c',
                        backgroundColor: 'rgba(39, 77, 76, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        function initializeHotspotCrimeTypeChart() {
            const ctx = document.getElementById('hotspotCrimeTypeChart')?.getContext('2d');
            if (!ctx) return;

            hotspotCrimeTypeChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Theft', 'Assault', 'Vandalism', 'Burglary', 'Drug Related', 'Other'],
                    datasets: [{
                        data: [35, 25, 15, 12, 8, 5],
                        backgroundColor: [
                            '#ef4444', '#f59e0b', '#eab308', 
                            '#22c55e', '#3b82f6', '#8b5cf6'
                        ]
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

        function setupEventListeners() {
            // Heat intensity slider
            const intensitySlider = document.getElementById('heatIntensity');
            const intensityValue = document.getElementById('intensityValue');
            
            intensitySlider.addEventListener('input', function() {
                intensityValue.textContent = this.value;
                updateHeatmapIntensity(this.value);
            });

            // Time period filter
            document.getElementById('timePeriod').addEventListener('change', function() {
                refreshHotspotData();
            });

            // Crime type filter
            document.getElementById('crimeTypeFilter').addEventListener('change', function() {
                refreshHotspotData();
            });
        }

        function setViewMode(mode) {
            currentViewMode = mode;
            
            // Update button styles
            document.querySelectorAll('.view-mode-btn').forEach(btn => {
                btn.classList.remove('bg-[#274d4c]', 'text-white');
                btn.classList.add('bg-gray-200', 'text-gray-700');
            });
            
            event.target.classList.remove('bg-gray-200', 'text-gray-700');
            event.target.classList.add('bg-[#274d4c]', 'text-white');
            
            refreshHotspotData();
        }

        function updateHeatmapIntensity(intensity) {
            if (hotspotMap) {
                const data = hotspotMap.data.datasets[0].data;
                hotspotMap.data.datasets[0].data = data.map(point => ({
                    ...point,
                    r: Math.max(5, (point.v * intensity) / 10)
                }));
                hotspotMap.update();
            }
        }

        function refreshHotspotData() {
            // Simulate data refresh
            const newData = generateHotspotData();
            
            if (hotspotMap) {
                hotspotMap.data.datasets[0].data = newData;
                hotspotMap.update();
            }
            
            updateStatistics();
        }

        function updateStatistics() {
            // Simulate updating statistics with random variations
            document.getElementById('activeHotspots').textContent = Math.floor(Math.random() * 5 + 10);
            document.getElementById('criticalZones').textContent = Math.floor(Math.random() * 3 + 3);
            document.getElementById('emergingAreas').textContent = Math.floor(Math.random() * 4 + 6);
            document.getElementById('safeZones').textContent = Math.floor(Math.random() * 8 + 18);
        }

        function toggleRealTime() {
            const btn = document.getElementById('realTimeBtn');
            isRealTimeActive = !isRealTimeActive;
            
            if (isRealTimeActive) {
                btn.innerHTML = '<i class="fas fa-pause mr-2"></i>Pause';
                btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                btn.classList.add('bg-red-600', 'hover:bg-red-700');
                
                realTimeInterval = setInterval(() => {
                    refreshHotspotData();
                }, 5000);
            } else {
                btn.innerHTML = '<i class="fas fa-play mr-2"></i>Real-time';
                btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                
                if (realTimeInterval) {
                    clearInterval(realTimeInterval);
                    realTimeInterval = null;
                }
            }
        }

        function exportHotspotMap() {
            // Simulate export functionality
            alert('Hotspot map exported successfully!');
        }
    </script>
@endsection
