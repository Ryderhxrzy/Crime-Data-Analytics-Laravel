<?php
// Include centralized authentication to validate JWT tokens
require_once app_path('auth-include.php');

// Check if token is in URL and store it
if (request()->query('token')) {
    $token = request()->query('token');
    session(['jwt_token' => $token]);
}

// Check if user is authenticated and redirect if needed
if (!getCurrentUser()) {
    // User not authenticated, redirect to main domain
    return redirect(getMainDomain());
}

// Check if we should redirect based on role/department
$redirectUrl = getRedirectUrl();
if ($redirectUrl !== request()->url()) {
    // Different URL needed, redirect to it
    return redirect($redirectUrl);
}
?>

@extends('layouts.app')
@section('title', 'Crime Type Trends Analysis')
@section('content')
    <div class="p-6">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-chart-bar mr-3" style="color: #274d4c;"></i>
                Crime Type Trends Analysis
            </h1>
            <p class="text-gray-600">Comprehensive analysis of crime patterns by type across different time periods and locations</p>
        </div>

        <!-- Crime Type Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border-purple-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-purple-900 mb-1">
                            <i class="fas fa-list mr-1"></i>Total Crime Types
                        </p>
                        <p class="text-2xl font-bold text-purple-700" id="totalCrimeTypeCount">--</p>
                        <p class="text-xs text-purple-600 mt-1">Different crime categories</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-red-50 to-red-100 border-red-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-red-900 mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Most Common
                        </p>
                        <p class="text-2xl font-bold text-red-700" id="mostCommonCrimeType">--</p>
                        <p class="text-xs text-red-600 mt-1">Highest frequency crime type</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 border-yellow-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-yellow-900 mb-1">
                            <i class="fas fa-chart-line mr-1"></i>Trending Up
                        </p>
                        <p class="text-2xl font-bold text-yellow-700" id="trendingUpCrimeType">--</p>
                        <p class="text-xs text-yellow-600 mt-1">Fastest growing crime type</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 border-green-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-green-900 mb-1">
                            <i class="fas fa-arrow-down mr-1"></i>Trending Down
                        </p>
                        <p class="text-2xl font-bold text-green-700" id="trendingDownCrimeType">--</p>
                        <p class="text-xs text-green-600 mt-1">Decreasing crime type</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Crime Type Distribution -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-chart-pie mr-2" style="color: #274d4c;"></i>Crime Type Distribution
                    </h3>
                    <button onclick="openCrimeTypeAnalysisModal('distribution')" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="Open Detailed Analysis">
                        <i class="fas fa-expand text-lg"></i>
                    </button>
                </div>
                <div style="position: relative; height: 400px;">
                    <canvas id="crimeTypeDistributionChart"></canvas>
                </div>
            </div>

            <!-- Crime Type Trends Over Time -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-chart-line mr-2" style="color: #274d4c;"></i>Crime Type Trends
                    </h3>
                    <button onclick="openCrimeTypeAnalysisModal('trends')" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="Open Detailed Analysis">
                        <i class="fas fa-expand text-lg"></i>
                    </button>
                </div>
                <div style="position: relative; height: 400px;">
                    <canvas id="crimeTypeTrendsChart"></canvas>
                </div>
            </div>

            <!-- Crime Type by Location -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-map-marker-alt mr-2" style="color: #274d4c;"></i>Crime Type by Location
                    </h3>
                    <button onclick="openCrimeTypeAnalysisModal('location')" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="Open Detailed Analysis">
                        <i class="fas fa-expand text-lg"></i>
                    </button>
                </div>
                <div style="position: relative; height: 400px;">
                    <canvas id="crimeTypeByLocationChart"></canvas>
                </div>
            </div>

            <!-- Crime Type Severity Analysis -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-exclamation-circle mr-2" style="color: #274d4c;"></i>Severity Analysis
                    </h3>
                    <button onclick="openCrimeTypeAnalysisModal('severity')" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="Open Detailed Analysis">
                        <i class="fas fa-expand text-lg"></i>
                    </button>
                </div>
                <div style="position: relative; height: 400px;">
                    <canvas id="crimeTypeSeverityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Insights -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
            <h3 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-lightbulb mr-2" style="color: #f59e0b;"></i>Crime Type Insights & Recommendations
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="p-4 bg-blue-50 border-blue-200 rounded-lg">
                    <h4 class="font-semibold text-blue-900 mb-2">Prevention Focus</h4>
                    <p class="text-sm text-gray-700">Based on current trends, focus prevention efforts on the most common crime types during peak hours and high-risk locations.</p>
                </div>
                <div class="p-4 bg-red-50 border-red-200 rounded-lg">
                    <h4 class="font-semibold text-red-900 mb-2">Resource Allocation</h4>
                    <p class="text-sm text-gray-700">Allocate more resources to combat trending crime types and consider specialized units for high-severity incidents.</p>
                </div>
                <div class="p-4 bg-green-50 border-green-200 rounded-lg">
                    <h4 class="font-semibold text-green-900 mb-2">Community Programs</h4>
                    <p class="text-sm text-gray-700">Develop targeted community awareness programs for specific crime types showing upward trends in residential areas.</p>
                </div>
            </div>
        </div>

    <!-- Crime Type Analysis Modal -->
    <div id="crimeTypeAnalysisModal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-7xl w-full max-h-[85vh] overflow-hidden flex flex-col mt-16">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-alertara-700 to-alertara-600 text-white p-6 border-b border-alertara-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-2 flex items-center gap-3">
                            <i class="fas fa-chart-bar"></i>
                            Crime Type Analysis Dashboard
                        </h2>
                        <p class="text-alertara-100 text-sm">Comprehensive analysis of crime patterns by type across different dimensions</p>
                    </div>
                    <button onclick="closeCrimeTypeAnalysisModal()" class="text-white hover:bg-alertara-800 hover:bg-opacity-50 rounded-lg p-2 transition-all duration-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto bg-gray-50">
                <div class="p-6">
                    <!-- Dynamic Content Based on Chart Type -->
                    <div id="modalContent">
                        <!-- Content will be dynamically loaded based on chart type -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
        // Initialize page on load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize charts
            initializeCrimeTypeCharts();
        });

        // Initialize Crime Type Charts
        function initializeCrimeTypeCharts() {
            initializeCrimeTypeDistributionChart();
            initializeCrimeTypeTrendsChart();
            initializeCrimeTypeByLocationChart();
            initializeCrimeTypeSeverityChart();
            
            // Update statistics
            updateCrimeTypeStatistics();
        }

        // Open Crime Type Analysis Modal
        function openCrimeTypeAnalysisModal(chartType) {
            const modal = document.getElementById('crimeTypeAnalysisModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            
            // Initialize modal charts
            setTimeout(() => {
                initializeModalCrimeTypeCharts(chartType);
            }, 100);
        }

        // Close Crime Type Analysis Modal
        function closeCrimeTypeAnalysisModal() {
            const modal = document.getElementById('crimeTypeAnalysisModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        // Initialize Modal Crime Type Charts
        function initializeModalCrimeTypeCharts(chartType) {
            const modalContent = document.getElementById('modalContent');
            
            // Clear existing content
            modalContent.innerHTML = '';
            
            // Load specific content based on chart type
            switch(chartType) {
                case 'distribution':
                    modalContent.innerHTML = `
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-chart-pie mr-2" style="color: #274d4c;"></i>Expanded Distribution Analysis
                            </h3>
                            <div style="position: relative; height: 500px;">
                                <canvas id="modalCrimeTypeDistributionChart"></canvas>
                            </div>
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-blue-50 border-blue-200 rounded-lg">
                                    <h4 class="font-semibold text-blue-900 mb-2">Key Insights</h4>
                                    <ul class="text-sm text-gray-700 space-y-2">
                                        <li>• Theft accounts for 35.2% of all crimes</li>
                                        <li>• Top 3 crime types represent 75.4% of incidents</li>
                                        <li>• Seasonal patterns show summer peak for theft</li>
                                    </ul>
                                </div>
                                <div class="p-4 bg-green-50 border-green-200 rounded-lg">
                                    <h4 class="font-semibold text-green-900 mb-2">Recommendations</h4>
                                    <ul class="text-sm text-gray-700 space-y-2">
                                        <li>• Increase patrols during evening hours</li>
                                        <li>• Target theft prevention programs</li>
                                        <li>• Focus on high-risk commercial areas</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    `;
                    initializeModalCrimeTypeDistributionChart();
                    break;
                    
                case 'trends':
                    modalContent.innerHTML = `
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-chart-line mr-2" style="color: #274d4c;"></i>Detailed Trend Analysis
                            </h3>
                            <div style="position: relative; height: 500px;">
                                <canvas id="modalCrimeTypeTrendsChart"></canvas>
                            </div>
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-4 bg-red-50 border-red-200 rounded-lg">
                                    <h4 class="font-semibold text-red-900 mb-2">Rising Trends</h4>
                                    <p class="text-sm text-gray-700">Theft and Fraud showing significant increases over past 6 months</p>
                                </div>
                                <div class="p-4 bg-yellow-50 border-yellow-200 rounded-lg">
                                    <h4 class="font-semibold text-yellow-900 mb-2">Stable Patterns</h4>
                                    <p class="text-sm text-gray-700">Assault rates remain consistent with minor fluctuations</p>
                                </div>
                                <div class="p-4 bg-green-50 border-green-200 rounded-lg">
                                    <h4 class="font-semibold text-green-900 mb-2">Declining Types</h4>
                                    <p class="text-sm text-gray-700">Vandalism showing steady decrease over past year</p>
                                </div>
                            </div>
                        </div>
                    `;
                    initializeModalCrimeTypeTrendsChart();
                    break;
                    
                case 'location':
                    modalContent.innerHTML = `
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-map-marker-alt mr-2" style="color: #274d4c;"></i>Comprehensive Location Analysis
                            </h3>
                            
                            <!-- Location Filter -->
                            <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                <div class="flex items-center gap-4">
                                    <label class="text-sm font-medium text-gray-700">Filter by Location:</label>
                                    <select id="locationFilter" class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="all">All Locations</option>
                                        @foreach($locationData as $locationId => $location)
                                            <option value="{{ $locationId }}">{{ $location['name'] }}</option>
                                        @endforeach
                                    </select>
                                    <button onclick="filterLocationData()" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-filter mr-2"></i>Apply Filter
                                    </button>
                                    <button onclick="resetLocationFilter()" class="bg-gray-500 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-600 transition-colors">
                                        <i class="fas fa-redo mr-2"></i>Reset
                                    </button>
                                </div>
                            </div>
                            
                            <div style="position: relative; height: 400px;">
                                <canvas id="modalCrimeTypeByLocationChart"></canvas>
                            </div>
                            
                            <!-- Crime Breakdown by Location -->
                            <div class="mt-6">
                                <h4 class="font-semibold text-gray-900 mb-4">Crime Breakdown by Location</h4>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Theft</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assault</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vandalism</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Burglary</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fraud</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk Level</th>
                                            </tr>
                                        </thead>
                                        <tbody id="locationCrimeTable" class="bg-white divide-y divide-gray-200">
                                            <!-- Table content will be populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <h4 class="font-semibold text-gray-900 mb-4">Location-Specific Insights</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="p-4 bg-purple-50 border-purple-200 rounded-lg">
                                        <h5 class="font-semibold text-purple-900 mb-2">High-Risk Areas</h5>
                                        <p class="text-sm text-gray-700">Downtown, Industrial Zone, and City Center show highest crime concentrations</p>
                                    </div>
                                    <div class="p-4 bg-orange-50 border-orange-200 rounded-lg">
                                        <h5 class="font-semibold text-orange-900 mb-2">Crime Type Patterns</h5>
                                        <p class="text-sm text-gray-700">Theft dominates commercial areas, Assault prevalent in industrial zones</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    initializeModalCrimeTypeByLocationChart();
                    populateLocationCrimeTable();
                    setupLocationFilter();
                    break;
                    
                case 'severity':
                    modalContent.innerHTML = `
                        <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-exclamation-triangle mr-2" style="color: #274d4c;"></i>Advanced Severity Analysis
                            </h3>
                            <div style="position: relative; height: 500px;">
                                <canvas id="modalCrimeTypeSeverityChart"></canvas>
                            </div>
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="p-4 bg-green-50 border-green-200 rounded-lg text-center">
                                    <h5 class="font-semibold text-green-900 mb-2">Low Severity</h5>
                                    <p class="text-2xl font-bold text-green-700">120</p>
                                    <p class="text-sm text-gray-700">48% of incidents</p>
                                </div>
                                <div class="p-4 bg-yellow-50 border-yellow-200 rounded-lg text-center">
                                    <h5 class="font-semibold text-yellow-900 mb-2">Medium Severity</h5>
                                    <p class="text-2xl font-bold text-yellow-700">85</p>
                                    <p class="text-sm text-gray-700">34% of incidents</p>
                                </div>
                                <div class="p-4 bg-red-50 border-red-200 rounded-lg text-center">
                                    <h5 class="font-semibold text-red-900 mb-2">High Severity</h5>
                                    <p class="text-2xl font-bold text-red-700">45</p>
                                    <p class="text-sm text-gray-700">18% of incidents</p>
                                </div>
                                <div class="p-4 bg-red-900 border-red-200 rounded-lg text-center">
                                    <h5 class="font-semibold text-white mb-2">Critical</h5>
                                    <p class="text-2xl font-bold text-white">15</p>
                                    <p class="text-sm text-red-100">6% of incidents</p>
                                </div>
                            </div>
                        </div>
                    `;
                    initializeModalCrimeTypeSeverityChart();
                    break;
            }
        }

        // Initialize Modal Crime Type Distribution Chart
        function initializeModalCrimeTypeDistributionChart() {
            const ctx = document.getElementById('modalCrimeTypeDistributionChart')?.getContext('2d');
            if (!ctx) return;

            const data = {
                labels: ['Theft', 'Assault', 'Vandalism', 'Burglary', 'Fraud', 'Other'],
                datasets: [{
                    data: [35, 25, 15, 12, 8, 5],
                    backgroundColor: [
                        '#ef4444',
                        '#f59e0b',
                        '#10b981',
                        '#3b82f6',
                        '#8b5cf6',
                        '#6b7280'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            };

            new Chart(ctx, {
                type: 'pie',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed * 100) / total).toFixed(1);
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize Modal Crime Type Trends Chart
        function initializeModalCrimeTypeTrendsChart() {
            const ctx = document.getElementById('modalCrimeTypeTrendsChart')?.getContext('2d');
            if (!ctx) return;

            const data = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Theft',
                    data: [65, 68, 72, 70, 75, 78, 82, 85, 88, 86, 90, 92],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Assault',
                    data: [45, 42, 48, 50, 52, 55, 58, 60, 62, 64, 66, 68],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Vandalism',
                    data: [25, 22, 20, 18, 16, 15, 14, 13, 12, 11, 10, 9],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Burglary',
                    data: [18, 20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }]
            };

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
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Initialize Modal Crime Type by Location Chart
        function initializeModalCrimeTypeByLocationChart() {
            const ctx = document.getElementById('modalCrimeTypeByLocationChart')?.getContext('2d');
            if (!ctx) return;

            // Extract data from locationCrimeData for chart
            const locations = Object.keys(locationCrimeData);
            const labels = locations.map(id => locationCrimeData[id].name);
            
            // Create single row data - sum all crime types across locations
            const theftData = locations.map(id => locationCrimeData[id].theft);
            const assaultData = locations.map(id => locationCrimeData[id].assault);
            const vandalismData = locations.map(id => locationCrimeData[id].vandalism);
            const burglaryData = locations.map(id => locationCrimeData[id].burglary);
            const fraudData = locations.map(id => locationCrimeData[id].fraud);

            const data = {
                labels: labels,
                datasets: [
                    {
                        label: 'Theft',
                        data: theftData,
                        backgroundColor: '#ef4444',
                        borderWidth: 2,
                        borderRadius: 4
                    },
                    {
                        label: 'Assault',
                        data: assaultData,
                        backgroundColor: '#f59e0b',
                        borderWidth: 2,
                        borderRadius: 4
                    },
                    {
                        label: 'Vandalism',
                        data: vandalismData,
                        backgroundColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 4
                    },
                    {
                        label: 'Burglary',
                        data: burglaryData,
                        backgroundColor: '#3b82f6',
                        borderWidth: 2,
                        borderRadius: 4
                    },
                    {
                        label: 'Fraud',
                        data: fraudData,
                        backgroundColor: '#8b5cf6',
                        borderWidth: 2,
                        borderRadius: 4
                    }
                ]
            };

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
                        x: {
                            grid: { display: false },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: { size: 10 }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Incidents'
                            }
                        }
                    }
                }
            });
        }

        // Initialize Modal Crime Type Severity Chart
        function initializeModalCrimeTypeSeverityChart() {
            const ctx = document.getElementById('modalCrimeTypeSeverityChart')?.getContext('2d');
            if (!ctx) return;

            const data = {
                labels: ['Low', 'Medium', 'High', 'Critical'],
                datasets: [{
                    label: 'Number of Incidents',
                    data: [120, 85, 45, 15],
                    backgroundColor: [
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#7c2d12'
                    ],
                    borderWidth: 2,
                    borderRadius: 6
                }]
            };

            new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Update Crime Type Statistics
        function updateCrimeTypeStatistics() {
            // Update statistics cards with sample data
            document.getElementById('totalCrimeTypeCount').textContent = '12';
            document.getElementById('mostCommonCrimeType').textContent = 'Theft';
            document.getElementById('trendingUpCrimeType').textContent = 'Assault';
            document.getElementById('trendingDownCrimeType').textContent = 'Vandalism';
        }

        // Initialize Crime Type Distribution Chart
        function initializeCrimeTypeDistributionChart() {
            const ctx = document.getElementById('crimeTypeDistributionChart')?.getContext('2d');
            if (!ctx) return;

            const data = {
                labels: ['Theft', 'Assault', 'Vandalism', 'Burglary', 'Fraud', 'Other'],
                datasets: [{
                    data: [35, 25, 15, 12, 8, 5],
                    backgroundColor: [
                        '#ef4444',
                        '#f59e0b',
                        '#10b981',
                        '#3b82f6',
                        '#8b5cf6',
                        '#6b7280'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            };

            new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' }
                    },
                    cutout: '50%'
                }
            });
        }

        // Initialize Crime Type Trends Chart
        function initializeCrimeTypeTrendsChart() {
            const ctx = document.getElementById('crimeTypeTrendsChart')?.getContext('2d');
            if (!ctx) return;

            const data = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Theft',
                    data: [65, 68, 72, 70, 75, 78],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Assault',
                    data: [45, 42, 48, 50, 52, 55],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Vandalism',
                    data: [25, 22, 20, 18, 16, 15],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            };

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
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Initialize Crime Type by Location Chart
        function initializeCrimeTypeByLocationChart() {
            const ctx = document.getElementById('crimeTypeByLocationChart')?.getContext('2d');
            if (!ctx) return;

            // Extract data from locationCrimeData for chart
            const locations = Object.keys(locationCrimeData);
            const labels = locations.map(id => locationCrimeData[id].name);
            
            // Create single row data - sum all crime types across locations
            const theftData = locations.map(id => locationCrimeData[id].theft);
            const assaultData = locations.map(id => locationCrimeData[id].assault);
            const vandalismData = locations.map(id => locationCrimeData[id].vandalism);
            const burglaryData = locations.map(id => locationCrimeData[id].burglary);
            const fraudData = locations.map(id => locationCrimeData[id].fraud);

            const data = {
                labels: labels,
                datasets: [
                    {
                        label: 'Theft',
                        data: theftData,
                        backgroundColor: '#ef4444',
                        borderWidth: 2,
                        borderRadius: 4
                    },
                    {
                        label: 'Assault',
                        data: assaultData,
                        backgroundColor: '#f59e0b',
                        borderWidth: 2,
                        borderRadius: 4
                    },
                    {
                        label: 'Vandalism',
                        data: vandalismData,
                        backgroundColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 4
                    },
                    {
                        label: 'Burglary',
                        data: burglaryData,
                        backgroundColor: '#3b82f6',
                        borderWidth: 2,
                        borderRadius: 4
                    },
                    {
                        label: 'Fraud',
                        data: fraudData,
                        backgroundColor: '#8b5cf6',
                        borderWidth: 2,
                        borderRadius: 4
                    }
                ]
            };

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
                        x: {
                            grid: { display: false },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: { size: 10 }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Incidents'
                            }
                        }
                    }
                }
            });
        }

        // Initialize Crime Type Severity Chart
        function initializeCrimeTypeSeverityChart() {
            const ctx = document.getElementById('crimeTypeSeverityChart')?.getContext('2d');
            if (!ctx) return;

            const data = {
                labels: ['Low', 'Medium', 'High', 'Critical'],
                datasets: [{
                    label: 'Number of Incidents',
                    data: [120, 85, 45, 15],
                    backgroundColor: [
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#7c2d12'
                    ],
                    borderWidth: 2,
                    borderRadius: 6
                }]
            };

            new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Location crime data from database
        const locationCrimeData = @json($locationData);

        // Populate location crime table
        function populateLocationCrimeTable() {
            const tableBody = document.getElementById('locationCrimeTable');
            if (!tableBody) return;
            
            let tableHTML = '';
            for (const [locationId, data] of Object.entries(locationCrimeData)) {
                const riskLevel = data.total > 100 ? 'High' : data.total > 50 ? 'Medium' : 'Low';
                const riskColor = riskLevel === 'High' ? 'red' : riskLevel === 'Medium' ? 'yellow' : 'green';
                const riskBgColor = riskLevel === 'High' ? 'bg-red-100 text-red-800' : riskLevel === 'Medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';
                
                tableHTML += `
                    <tr data-location="${locationId}">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${data.name}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${data.theft}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${data.assault}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${data.vandalism}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${data.burglary}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${data.fraud}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${data.total}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${riskBgColor}">
                                ${riskLevel}
                            </span>
                        </td>
                    </tr>
                `;
            }
            
            tableBody.innerHTML = tableHTML;
        }

        // Setup location filter
        function setupLocationFilter() {
            const filter = document.getElementById('locationFilter');
            if (filter) {
                filter.addEventListener('change', filterLocationData);
            }
        }

        // Filter location data
        function filterLocationData() {
            const filterValue = document.getElementById('locationFilter').value;
            const rows = document.querySelectorAll('#locationCrimeTable tr');
            
            rows.forEach(row => {
                if (filterValue === 'all') {
                    row.style.display = '';
                } else {
                    const locationId = row.getAttribute('data-location');
                    const locationData = locationCrimeData[locationId];
                    if (locationData) {
                        // Check if location name matches filter
                        const locationName = locationData.name.toLowerCase().replace(/\s+/g, '');
                        const filterName = filterValue.toLowerCase();
                        row.style.display = locationName.includes(filterName) ? '' : 'none';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }

        // Reset location filter
        function resetLocationFilter() {
            document.getElementById('locationFilter').value = 'all';
            filterLocationData();
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCrimeTypeAnalysisModal();
            }
        });

        // Close modal on background click
        document.getElementById('crimeTypeAnalysisModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCrimeTypeAnalysisModal();
            }
        });
    </script>
@endsection
