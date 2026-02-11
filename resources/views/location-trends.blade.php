@extends('layouts.app')
@section('title', 'Location Trends Analysis')
@section('content')
    <div class="p-6">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-map-marked-alt mr-3" style="color: #274d4c;"></i>
                Location Trends Analysis
            </h1>
            <p class="text-gray-600">Comprehensive crime patterns analysis across different locations and areas</p>
        </div>

        <!-- Compact Location Filter Section -->
        <div class="compact-filter bg-white border border-gray-200 rounded-lg shadow-sm p-4 mb-6" style="position: sticky; top: 4rem; z-index: 40;">
            <style>
                .compact-filter {
                    background: rgba(255, 255, 255, 0.98);
                    border: 1px solid #e5e7eb;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                }
                .dateRangeBtn {
                    padding: 4px 8px;
                    border-radius: 6px;
                    border: 1px solid #e5e7eb;
                    font-size: 11px;
                    font-weight: 500;
                    cursor: pointer;
                    background: white;
                    color: #6b7280;
                    transition: all 0.2s ease;
                    white-space: nowrap;
                }
                .dateRangeBtn:hover:not(.active) {
                    background-color: #f9fafb;
                    border-color: #274d4c;
                    color: #274d4c;
                }
                .dateRangeBtn.active {
                    background: #274d4c;
                    border-color: #274d4c;
                    color: white;
                }
                .compact-select {
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                    padding: 6px 8px;
                    font-size: 12px;
                    background: white;
                    color: #374151;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    font-weight: 500;
                }
                .compact-select:hover {
                    border-color: #274d4c;
                }
                .compact-select:focus {
                    outline: none;
                    border-color: #274d4c;
                    box-shadow: 0 0 0 2px rgba(39, 77, 76, 0.1);
                }
                .reset-btn-compact {
                    background: #ef4444;
                    border: none;
                    color: white;
                    padding: 6px 12px;
                    border-radius: 6px;
                    font-size: 11px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                .reset-btn-compact:hover {
                    background: #dc2626;
                }
                .filter-loader-compact {
                    display: none;
                    align-items: center;
                    font-size: 11px;
                    color: #274d4c;
                    font-weight: 500;
                }
                .filter-loader-compact.active {
                    display: flex;
                }
                .quick-filter-btn {
                    padding: 6px 12px;
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                    font-size: 11px;
                    font-weight: 500;
                    cursor: pointer;
                    background: white;
                    color: #6b7280;
                    transition: all 0.2s ease;
                    white-space: nowrap;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                }
                .quick-filter-btn:hover {
                    background-color: #f9fafb;
                    border-color: #274d4c;
                    color: #274d4c;
                }
                .quick-filter-btn.active {
                    background: #274d4c;
                    color: white;
                    border-color: #274d4c;
                }
            </style>
            
            <!-- Compact Filter Layout -->
            <div class="flex flex-wrap items-center gap-4">
                <!-- Date Range Buttons -->
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-gray-600 mr-2">Quick:</span>
                    <button class="dateRangeBtn" data-range="today">Today</button>
                    <button class="dateRangeBtn" data-range="7days">7 Days</button>
                    <button class="dateRangeBtn" data-range="30days">30 Days</button>
                    <button class="dateRangeBtn" data-range="thismonth">This Month</button>
                </div>

                <!-- Divider -->
                <div class="h-6 w-px bg-gray-300"></div>

                <!-- Location Filters -->
                <div class="flex items-center gap-3">
                    <select id="locationBarangay" class="compact-select">
                        <option value="">All Barangays</option>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->id }}">{{ $barangay->barangay_name }}</option>
                        @endforeach
                    </select>
                    <select id="locationCrimeType" class="compact-select">
                        <option value="">All Crime Types</option>
                        @foreach($crimeCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                        @endforeach
                    </select>
                    <select id="locationTimePeriod" class="compact-select">
                        <option value="">All Time Periods</option>
                        <option value="7">Last 7 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="365">Last Year</option>
                    </select>
                    <select id="locationRiskLevel" class="compact-select">
                        <option value="">All Risk Levels</option>
                        <option value="low">Low Risk</option>
                        <option value="medium">Medium Risk</option>
                        <option value="high">High Risk</option>
                    </select>
                </div>

                <!-- Divider -->
                <div class="h-6 w-px bg-gray-300"></div>

                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <button id="resetLocationFilter" class="reset-btn-compact">
                        <i class="fas fa-redo mr-1"></i>Reset
                    </button>
                    <div class="filter-loader-compact" id="locationFilterLoader">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Loading...
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-blue-900 mb-1">
                            <i class="fas fa-map-marker-alt mr-1"></i>Total Locations
                        </p>
                        <p class="text-2xl font-bold text-blue-700" id="totalLocationsCount">0</p>
                        <p class="text-xs text-blue-600 mt-1">Total barangays with incidents</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 border-green-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-green-900 mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Highest Risk Area
                        </p>
                        <p class="text-2xl font-bold text-green-700" id="highestRiskArea">--</p>
                        <p class="text-xs text-green-600 mt-1">Barangay with most incidents</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-orange-900 mb-1">
                            <i class="fas fa-chart-line mr-1"></i>Average Risk Level
                        </p>
                        <p class="text-2xl font-bold text-orange-700" id="avgRiskLevel">--</p>
                        <p class="text-xs text-orange-600 mt-1">Average risk level across all locations</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-red-50 to-red-100 border-red-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-red-900 mb-1">
                            <i class="fas fa-fire mr-1"></i>Most Active Time
                        </p>
                        <p class="text-2xl font-bold text-red-700" id="mostActiveTime">--</p>
                        <p class="text-xs text-red-600 mt-1">Time period with most incidents</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Crime Hotspot Bubble Map -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-map-marked-alt" style="color: #274d4c;"></i>
                        Crime Hotspot Bubble Map
                    </h3>
                    <button onclick="openDetailedAnalysis('hotspot')" class="px-3 py-1 bg-[#274d4c] text-white text-sm rounded-lg hover:bg-[#1a3534] transition-colors flex items-center gap-2">
                        <i class="fas fa-expand-alt"></i>
                        Open Detailed Analysis
                    </button>
                </div>
                <div style="position: relative; height: 400px;">
                    <canvas id="locationHotspotChart"></canvas>
                </div>
            </div>

            <!-- Top Risk Areas List -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle" style="color: #274d4c;"></i>
                        Top Risk Areas
                    </h3>
                    <button onclick="openDetailedAnalysis('risk')" class="px-3 py-1 bg-[#274d4c] text-white text-sm rounded-lg hover:bg-[#1a3534] transition-colors flex items-center gap-2">
                        <i class="fas fa-expand-alt"></i>
                        Open Detailed Analysis
                    </button>
                </div>
                <div style="position: relative; height: 400px;">
                    <canvas id="topRiskAreasChart"></canvas>
                </div>
            </div>

            <!-- Location Comparison Bar Chart -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-chart-bar" style="color: #274d4c;"></i>
                        Location Comparison
                    </h3>
                    <button onclick="openDetailedAnalysis('comparison')" class="px-3 py-1 bg-[#274d4c] text-white text-sm rounded-lg hover:bg-[#1a3534] transition-colors flex items-center gap-2">
                        <i class="fas fa-expand-alt"></i>
                        Open Detailed Analysis
                    </button>
                </div>
                <div style="position: relative; height: 400px;">
                    <canvas id="locationComparisonChart"></canvas>
                </div>
            </div>

            <!-- Crime Type by Location Doughnut Chart -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-chart-pie" style="color: #274d4c;"></i>
                        Crime Type by Location
                    </h3>
                    <button onclick="openDetailedAnalysis('crimeType')" class="px-3 py-1 bg-[#274d4c] text-white text-sm rounded-lg hover:bg-[#1a3534] transition-colors flex items-center gap-2">
                        <i class="fas fa-expand-alt"></i>
                        Open Detailed Analysis
                    </button>
                </div>
                <div style="position: relative; height: 400px;">
                    <canvas id="crimeTypeByLocationChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Insights -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-lightbulb" style="color: #274d4c;"></i>
                    Location-Based Insights
                </h3>
                <button onclick="openDetailedAnalysis('insights')" class="px-3 py-1 bg-[#274d4c] text-white text-sm rounded-lg hover:bg-[#1a3534] transition-colors flex items-center gap-2">
                    <i class="fas fa-expand-alt"></i>
                    Open Detailed Analysis
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-blue-50 border-blue-200 rounded-lg">
                    <h4 class="font-semibold text-blue-900 mb-2">Resource Allocation</h4>
                    <p class="text-sm text-gray-700">Based on the data analysis, consider reallocating more resources to high-risk areas during peak hours and weekends. Focus on community policing in identified hotspots.</p>
                </div>
                <div class="p-4 bg-orange-50 border-orange-200 rounded-lg">
                    <h4 class="font-semibold text-orange-900 mb-2">Patrol Optimization</h4>
                    <p class="text-sm text-gray-700">Optimize patrol routes based on historical crime patterns and time-based analysis. Consider different patrol strategies for different times of day and days of week.</p>
                </div>
                <div class="p-4 bg-green-50 border-green-200 rounded-lg">
                    <h4 class="font-semibold text-green-900 mb-2">Community Engagement</h4>
                    <p class="text-sm text-gray-700">Increase community awareness and reporting in high-crime areas. Establish neighborhood watch programs and community policing initiatives.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Location Analysis Modal -->
    <div id="locationAnalysisModal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-7xl w-full max-h-[85vh] overflow-hidden flex flex-col mt-16">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-[#274d4c] to-[#1a3534] text-white p-6 border-b border-[#1a3534]">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-2 flex items-center gap-3">
                            <i class="fas fa-map-marked-alt"></i>
                            Location-Based Crime Analysis Dashboard
                        </h2>
                        <p class="text-gray-200 text-sm">Comprehensive analysis of crime patterns across different locations and areas</p>
                    </div>
                    <button onclick="closeLocationAnalysisModal()" class="text-white hover:bg-[#1a3534] hover:bg-opacity-50 rounded-lg p-2 transition-all duration-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto bg-gray-50 p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-blue-700">Total Locations</p>
                                <p class="text-xl font-bold text-blue-900" id="modalTotalLocations">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-green-50 to-green-100 border-green-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-green-700">Highest Risk Area</p>
                                <p class="text-xl font-bold text-green-900" id="modalHighestRiskArea">--</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-orange-700">Most Active Time</p>
                                <p class="text-xl font-bold text-orange-900" id="modalMostActiveTime">--</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-red-50 to-red-100 border-red-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-fire text-white text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-red-700">Average Risk Level</p>
                                <p class="text-xl font-bold text-red-900" id="modalAvgRiskLevel">--</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Expanded Hotspot Chart -->
                    <div id="modal-hotspot-section" class="analysis-section bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-map-marked-alt" style="color: #274d4c;"></i>
                            Expanded Hotspot Analysis
                        </h3>
                        <div style="position: relative; height: 400px;">
                            <canvas id="modalHotspotChart"></canvas>
                        </div>
                    </div>

                    <!-- Expanded Top Risk Areas -->
                    <div id="modal-risk-section" class="analysis-section bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle" style="color: #274d4c;"></i>
                            Top Risk Areas Detailed
                        </h3>
                        <div style="position: relative; height: 400px;">
                            <canvas id="modalTopRiskAreasChart"></canvas>
                        </div>
                    </div>

                    <!-- Expanded Location Comparison -->
                    <div id="modal-comparison-section" class="analysis-section bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-chart-bar" style="color: #274d4c;"></i>
                            Location Comparison Analysis
                        </h3>
                        <div style="position: relative; height: 400px;">
                            <canvas id="modalLocationComparisonChart"></canvas>
                        </div>
                    </div>

                    <!-- Expanded Crime Type Distribution -->
                    <div id="modal-crimetype-section" class="analysis-section bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-chart-pie" style="color: #274d4c;"></i>
                            Crime Type Distribution by Location
                        </h3>
                        <div style="position: relative; height: 400px;">
                            <canvas id="modalCrimeTypeByLocationChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Detailed Insights -->
                <div id="modal-insights-section" class="analysis-section bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <i class="fas fa-lightbulb" style="color: #274d4c;"></i>
                        Location-Based Strategic Insights
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 bg-blue-50 border-blue-200 rounded-lg">
                            <h4 class="font-semibold text-blue-900 mb-2">Resource Allocation</h4>
                            <p class="text-sm text-gray-700">Based on the data analysis, consider reallocating more resources to high-risk areas during peak hours and weekends. Focus on community policing in identified hotspots.</p>
                        </div>
                        <div class="p-4 bg-orange-50 border-orange-200 rounded-lg">
                            <h4 class="font-semibold text-orange-900 mb-2">Patrol Optimization</h4>
                            <p class="text-sm text-gray-700">Optimize patrol routes based on historical crime patterns and time-based analysis. Consider different patrol strategies for different times of day and days of week.</p>
                        </div>
                        <div class="p-4 bg-green-50 border-green-200 rounded-lg">
                            <h4 class="font-semibold text-green-900 mb-2">Community Engagement</h4>
                            <p class="text-sm text-gray-700">Increase community awareness and reporting in high-crime areas. Establish neighborhood watch programs and community policing initiatives.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Store chart instances globally
        const locationCharts = {};

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize charts
            initializeAllCharts();
            
            // Setup filter functionality
            setupLocationFilters();
            
            // Load initial data
            loadLocationData();
        });

        function initializeAllCharts() {
            // Main page charts
            locationCharts.hotspot = initializeLocationHotspotChart();
            locationCharts.topRiskAreas = initializeTopRiskAreasChart();
            locationCharts.locationComparison = initializeLocationComparisonChart();
            locationCharts.crimeTypeByLocation = initializeCrimeTypeByLocationChart();
        }

        function initializeLocationHotspotChart() {
            const ctx = document.getElementById('locationHotspotChart')?.getContext('2d');
            if (!ctx) return null;

            const data = {
                datasets: [{
                    label: 'Crime Hotspots',
                    data: [
                        {x: 20, y: 30, r: 15, label: 'Downtown'},
                        {x: 40, y: 20, r: 12, label: 'Riverside'},
                        {x: 60, y: 40, r: 18, label: 'Industrial Zone'},
                        {x: 30, y: 60, r: 8, label: 'Suburbs'},
                        {x: 80, y: 25, r: 10, label: 'Commercial District'}
                    ],
                    backgroundColor: 'rgba(39, 77, 76, 0.6)',
                    borderColor: '#274d4c',
                    borderWidth: 2
                }]
            };

            return new Chart(ctx, {
                type: 'bubble',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const raw = context.raw;
                                    return `${raw.label}: ${raw.r} incidents`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Location Area'
                            },
                            grid: { display: false },
                            min: 0,
                            max: 100
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Risk Level'
                            },
                            beginAtZero: true,
                            min: 0,
                            max: 100
                        }
                    }
                }
            });
        }

        function initializeTopRiskAreasChart() {
            const ctx = document.getElementById('topRiskAreasChart')?.getContext('2d');
            if (!ctx) return null;

            const data = {
                labels: ['Downtown', 'Industrial Zone', 'Riverside', 'Commercial District', 'Suburbs'],
                datasets: [{
                    label: 'Risk Score',
                    data: [8.5, 7.2, 6.8, 9.1, 6.5],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(34, 197, 94, 0.8)'
                    ],
                    borderColor: '#274d4c',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            };

            return new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Risk Score: ${context.raw}/10`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 10,
                            grid: { color: '#e5e7eb' }
                        },
                        y: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function initializeLocationComparisonChart() {
            const ctx = document.getElementById('locationComparisonChart')?.getContext('2d');
            if (!ctx) return null;

            const data = {
                labels: ['Downtown', 'Industrial Zone', 'Riverside', 'Commercial District', 'Suburbs'],
                datasets: [
                    {
                        label: 'Current Period',
                        data: [120, 95, 78, 65, 45],
                        backgroundColor: '#274d4c',
                        borderColor: '#274d4c',
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: 'Previous Period',
                        data: [105, 88, 82, 70, 55],
                        backgroundColor: '#94a3b8',
                        borderColor: '#64748b',
                        borderWidth: 1,
                        borderRadius: 6
                    }
                ]
            };

            return new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        x: {
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#e5e7eb' }
                        }
                    }
                }
            });
        }

        function initializeCrimeTypeByLocationChart() {
            const ctx = document.getElementById('crimeTypeByLocationChart')?.getContext('2d');
            if (!ctx) return null;

            const data = {
                labels: ['Theft', 'Assault', 'Vandalism', 'Robbery', 'Others'],
                datasets: [{
                    data: [132, 98, 45, 67, 28],
                    backgroundColor: [
                        '#ef4444',
                        '#f59e0b',
                        '#10b981',
                        '#3b82f6',
                        '#6b7280'
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            };

            return new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        function setupLocationFilters() {
            const dateRangeBtns = document.querySelectorAll('.dateRangeBtn');
            
            dateRangeBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    dateRangeBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    applyLocationFilter();
                });
            });

            document.getElementById('locationBarangay').addEventListener('change', applyLocationFilter);
            document.getElementById('locationCrimeType').addEventListener('change', applyLocationFilter);
            document.getElementById('locationTimePeriod').addEventListener('change', applyLocationFilter);
            document.getElementById('locationRiskLevel').addEventListener('change', applyLocationFilter);

            document.getElementById('resetLocationFilter').addEventListener('click', function() {
                document.getElementById('locationBarangay').value = '';
                document.getElementById('locationCrimeType').value = '';
                document.getElementById('locationTimePeriod').value = '';
                document.getElementById('locationRiskLevel').value = '';
                
                dateRangeBtns.forEach(btn => btn.classList.remove('active'));
                
                applyLocationFilter();
            });
        }

        async function applyLocationFilter() {
            const loader = document.getElementById('locationFilterLoader');
            loader.classList.add('active');
            
            try {
                const barangay = document.getElementById('locationBarangay').value;
                const crimeType = document.getElementById('locationCrimeType').value;
                const timePeriod = document.getElementById('locationTimePeriod').value;
                const riskLevel = document.getElementById('locationRiskLevel').value;
                const activeDateRange = document.querySelector('.dateRangeBtn.active')?.dataset.range || '';
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                const response = await fetch('/dashboard/location-charts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        barangay: barangay,
                        crime_type: crimeType,
                        time_period: timePeriod,
                        risk_level: riskLevel,
                        date_range: activeDateRange
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                // Update all charts and statistics with the new data
                updateAllCharts(data);
                updateStatistics(data);
                
            } catch (error) {
                console.error('Error applying location filter:', error);
                // Show error message to user
                alert('Error loading data. Please try again.');
            } finally {
                loader.classList.remove('active');
            }
        }

        function updateAllCharts(data) {
            // Update Hotspot Chart
            if (data.heatmapData && locationCharts.hotspot) {
                const bubbleData = data.heatmapData.map((point, index) => ({
                    x: point.x,
                    y: point.y,
                    r: Math.max(5, point.v / 2),
                    label: point.label || `Location ${index + 1}`
                }));
                
                locationCharts.hotspot.data.datasets[0].data = bubbleData;
                locationCharts.hotspot.update();
            }
            
            // Update Top Risk Areas Chart
            if (data.riskAnalysis && locationCharts.topRiskAreas) {
                const topAreas = data.riskAnalysis.slice(0, 5);
                locationCharts.topRiskAreas.data.labels = topAreas.map(item => item.barangay);
                locationCharts.topRiskAreas.data.datasets[0].data = topAreas.map(item => 
                    Math.min(10, item.incidents / 5)
                );
                locationCharts.topRiskAreas.update();
            }
            
            // Update Location Comparison Chart
            if (data.locationComparison && locationCharts.locationComparison) {
                locationCharts.locationComparison.data.labels = data.locationComparison.labels;
                locationCharts.locationComparison.data.datasets[0].data = data.locationComparison.data;
                locationCharts.locationComparison.update();
            }
            
            // Update Crime Type Chart
            if (data.crimeTypeData && locationCharts.crimeTypeByLocation) {
                locationCharts.crimeTypeByLocation.data.labels = data.crimeTypeData.labels;
                locationCharts.crimeTypeByLocation.data.datasets[0].data = data.crimeTypeData.data;
                locationCharts.crimeTypeByLocation.update();
            }
        }

        function updateStatistics(data) {
            // Update main statistics cards
            if (data.riskAnalysis) {
                const riskAnalysis = data.riskAnalysis;
                
                // Total locations
                document.getElementById('totalLocationsCount').textContent = riskAnalysis.length || '0';
                
                // Highest risk area
                if (riskAnalysis.length > 0) {
                    const highestRisk = riskAnalysis.reduce((max, item) => 
                        item.incidents > max.incidents ? item : max, riskAnalysis[0]);
                    document.getElementById('highestRiskArea').textContent = highestRisk.barangay || '--';
                    
                    // Average risk level
                    const avgIncidents = riskAnalysis.reduce((sum, item) => sum + item.incidents, 0) / riskAnalysis.length;
                    document.getElementById('avgRiskLevel').textContent = avgIncidents.toFixed(1);
                }
            }
            
            if (data.locationComparison) {
                // Most active time - you can calculate this from your data
                document.getElementById('mostActiveTime').textContent = '6:00 PM - 9:00 PM';
            }
            
            // Update modal statistics
            document.getElementById('modalTotalLocations').textContent = document.getElementById('totalLocationsCount').textContent;
            document.getElementById('modalHighestRiskArea').textContent = document.getElementById('highestRiskArea').textContent;
            document.getElementById('modalAvgRiskLevel').textContent = document.getElementById('avgRiskLevel').textContent;
            document.getElementById('modalMostActiveTime').textContent = document.getElementById('mostActiveTime').textContent;
        }

        async function loadLocationData() {
            // Load initial data on page load
            await applyLocationFilter();
        }

        // Modal Functions
        function openLocationAnalysisModal() {
            const modal = document.getElementById('locationAnalysisModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            
            setTimeout(() => {
                initializeModalCharts();
            }, 100);
        }

        function closeLocationAnalysisModal() {
            const modal = document.getElementById('locationAnalysisModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        // Open Detailed Analysis for specific section
        function openDetailedAnalysis(section) {
            // Update modal title based on section
            const modalTitle = document.querySelector('#locationAnalysisModal h2');
            const modalSubtitle = document.querySelector('#locationAnalysisModal p');
            
            const sectionTitles = {
                'hotspot': 'Crime Hotspot Analysis',
                'risk': 'Risk Areas Analysis',
                'comparison': 'Location Comparison Analysis',
                'crimeType': 'Crime Type Distribution Analysis',
                'insights': 'Strategic Insights Analysis'
            };
            
            const sectionSubtitles = {
                'hotspot': 'Detailed analysis of crime hotspots and geographical patterns',
                'risk': 'Comprehensive risk assessment across different locations',
                'comparison': 'Comparative analysis of crime incidents across locations',
                'crimeType': 'Distribution and patterns of crime types by location',
                'insights': 'Strategic recommendations and actionable insights'
            };
            
            modalTitle.innerHTML = `<i class="fas fa-map-marked-alt mr-3"></i>${sectionTitles[section] || 'Location Analysis'}`;
            modalSubtitle.textContent = sectionSubtitles[section] || 'Detailed location-based crime analysis';
            
            // Show modal
            openLocationAnalysisModal();
            
            // Focus on specific section/tab based on analysis type
            setTimeout(() => {
                highlightAnalysisSection(section);
            }, 200);
        }
        
        // Highlight specific analysis section in modal
        function highlightAnalysisSection(section) {
            // Remove any existing highlights
            document.querySelectorAll('.analysis-section').forEach(el => {
                el.classList.remove('ring-2', 'ring-[#274d4c]', 'bg-blue-50');
            });
            
            // Add highlight to the relevant section
            const sectionMap = {
                'hotspot': 'modal-hotspot-section',
                'risk': 'modal-risk-section', 
                'comparison': 'modal-comparison-section',
                'crimeType': 'modal-crimetype-section',
                'insights': 'modal-insights-section'
            };
            
            const targetSection = document.getElementById(sectionMap[section]);
            if (targetSection) {
                targetSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                targetSection.classList.add('ring-2', 'ring-[#274d4c]', 'bg-blue-50');
                
                // Remove highlight after 3 seconds
                setTimeout(() => {
                    targetSection.classList.remove('ring-2', 'ring-[#274d4c]', 'bg-blue-50');
                }, 3000);
            }
        }

        function initializeModalCharts() {
            // Initialize Modal Hotspot Chart
            const modalHotspotCtx = document.getElementById('modalHotspotChart')?.getContext('2d');
            if (modalHotspotCtx) {
                new Chart(modalHotspotCtx, {
                    type: 'bubble',
                    data: {
                        datasets: [{
                            label: 'Crime Hotspots',
                            data: [
                                {x: 25, y: 35, r: 18, label: 'Downtown'},
                                {x: 45, y: 25, r: 15, label: 'Riverside'},
                                {x: 65, y: 45, r: 22, label: 'Industrial Zone'},
                                {x: 35, y: 65, r: 12, label: 'Suburbs'},
                                {x: 85, y: 30, r: 14, label: 'Commercial District'}
                            ],
                            backgroundColor: 'rgba(39, 77, 76, 0.6)',
                            borderColor: '#274d4c',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }

            // Initialize Modal Top Risk Areas Chart
            const modalTopRiskCtx = document.getElementById('modalTopRiskAreasChart')?.getContext('2d');
            if (modalTopRiskCtx) {
                new Chart(modalTopRiskCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Downtown', 'Industrial Zone', 'Riverside', 'Commercial District', 'Suburbs'],
                        datasets: [{
                            label: 'Risk Score',
                            data: [8.5, 7.2, 6.8, 9.1, 6.5],
                            backgroundColor: [
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(34, 197, 94, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y'
                    }
                });
            }

            // Initialize Modal Location Comparison Chart
            const modalComparisonCtx = document.getElementById('modalLocationComparisonChart')?.getContext('2d');
            if (modalComparisonCtx) {
                new Chart(modalComparisonCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Downtown', 'Industrial Zone', 'Riverside', 'Commercial District', 'Suburbs'],
                        datasets: [
                            {
                                label: 'Current Period',
                                data: [120, 95, 78, 65, 45],
                                backgroundColor: '#274d4c'
                            },
                            {
                                label: 'Previous Period',
                                data: [105, 88, 82, 70, 55],
                                backgroundColor: '#94a3b8'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }

            // Initialize Modal Crime Type Chart
            const modalCrimeTypeCtx = document.getElementById('modalCrimeTypeByLocationChart')?.getContext('2d');
            if (modalCrimeTypeCtx) {
                new Chart(modalCrimeTypeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Theft', 'Assault', 'Vandalism', 'Robbery', 'Others'],
                        datasets: [{
                            data: [132, 98, 45, 67, 28],
                            backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#6b7280']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right' }
                        },
                        cutout: '60%'
                    }
                });
            }
        }

        // Event listeners for modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLocationAnalysisModal();
            }
        });

        document.getElementById('locationAnalysisModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeLocationAnalysisModal();
            }
        });

        // Expose modal functions globally
        window.openLocationAnalysisModal = openLocationAnalysisModal;
        window.closeLocationAnalysisModal = closeLocationAnalysisModal;
    </script>
@endsection