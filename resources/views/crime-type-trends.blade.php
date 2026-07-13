@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

@extends('layouts.app')
@section('title', 'Crime Type Trends Analysis')
@section('content')
    <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
        <!-- Page Header -->
        <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                        <i class="fas fa-chart-bar mr-3" style="color: #274d4c;"></i>Crime Type Trends Analysis
                    </h1>
                    <p class="text-gray-600 mt-1 text-sm lg:text-base">Comprehensive analysis of crime patterns by type across different time periods and locations</p>
                </div>
            </div>
        </div>

        <!-- Standardized Filter Section -->
        <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
            <div class="mb-4 pb-4 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-900">
                    <i class="fas fa-filter mr-2 text-alertara-700"></i>Crime Type Filters
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <!-- Time Period -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Time Period</label>
                    <select id="crimeTypeTimePeriod" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="180">Last 6 Months</option>
                        <option value="all" selected>All Time</option>
                    </select>
                </div>

                <!-- Barangay -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Barangay</label>
                    <select id="crimeTypeBarangay" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Barangays</option>
                        @if(isset($barangays))
                            @foreach($barangays as $b)
                                <option value="{{ $b->id }}">{{ $b->barangay_name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Crime Category -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Crime Category</label>
                    <select id="crimeTypeCategory" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Categories</option>
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
                    <select id="crimeTypeCaseStatus" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Status</option>
                        <option value="reported">Reported</option>
                        <option value="under_investigation">Under Investigation</option>
                        <option value="solved">Solved</option>
                        <option value="closed">Closed</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <!-- Clearance Status -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Clearance</label>
                    <select id="crimeTypeClearanceStatus" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Clearances</option>
                        <option value="cleared">Cleared</option>
                        <option value="uncleared">Uncleared</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex items-end gap-2">
                    <button id="resetCrimeTypeFilter" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-redo"></i>Reset
                    </button>
                </div>
            </div>
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
        // Latest analytics payload from the server (drives all charts and modals)
        let crimeTypeData = null;
        let distributionChart, trendsChart, byLocationChart, severityChart;

        // Initialize page on load
        document.addEventListener('DOMContentLoaded', function() {
            loadCrimeTypeData();

            // Auto-apply filters on change
            ['crimeTypeTimePeriod', 'crimeTypeBarangay', 'crimeTypeCategory', 'crimeTypeCaseStatus', 'crimeTypeClearanceStatus'].forEach(id => {
                document.getElementById(id)?.addEventListener('change', loadCrimeTypeData);
            });

            document.getElementById('resetCrimeTypeFilter')?.addEventListener('click', function() {
                document.getElementById('crimeTypeTimePeriod').value = 'all';
                document.getElementById('crimeTypeBarangay').value = '';
                document.getElementById('crimeTypeCategory').value = '';
                document.getElementById('crimeTypeCaseStatus').value = '';
                document.getElementById('crimeTypeClearanceStatus').value = '';
                loadCrimeTypeData();
            });
        });

        // Fetch real crime-type analytics from the database
        async function loadCrimeTypeData() {
            const params = new URLSearchParams({
                time_period: document.getElementById('crimeTypeTimePeriod').value,
                barangay: document.getElementById('crimeTypeBarangay').value,
                category: document.getElementById('crimeTypeCategory').value,
                status: document.getElementById('crimeTypeCaseStatus').value,
                clearance: document.getElementById('crimeTypeClearanceStatus').value
            });

            try {
                const response = await fetch(`/dashboard/crime-type-trends-data?${params}`);
                const data = await response.json();
                if (!data.success) throw new Error(data.error || 'Request failed');

                crimeTypeData = data;
                updateCrimeTypeStatistics(data.stats);
                renderDistributionChart(data.distribution);
                renderTrendsChart(data.monthly);
                renderByLocationChart(data.by_location);
                renderSeverityChart(data.severity);
            } catch (error) {
                console.error('Error loading crime type trends:', error);
            }
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
                                    <ul class="text-sm text-gray-700 space-y-2" id="modalDistributionInsights"></ul>
                                </div>
                                <div class="p-4 bg-green-50 border-green-200 rounded-lg">
                                    <h4 class="font-semibold text-green-900 mb-2">Recommendations</h4>
                                    <ul class="text-sm text-gray-700 space-y-2" id="modalDistributionRecommendations"></ul>
                                </div>
                            </div>
                        </div>
                    `;
                    if (crimeTypeData) {
                        renderDistributionChart(crimeTypeData.distribution, 'modalCrimeTypeDistributionChart');
                        renderDistributionInsights(crimeTypeData);
                    }
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
                                    <h4 class="font-semibold text-red-900 mb-2">Trending Up (last 30 days)</h4>
                                    <p class="text-sm text-gray-700" id="modalTrendRising">--</p>
                                </div>
                                <div class="p-4 bg-yellow-50 border-yellow-200 rounded-lg">
                                    <h4 class="font-semibold text-yellow-900 mb-2">Most Common</h4>
                                    <p class="text-sm text-gray-700" id="modalTrendCommon">--</p>
                                </div>
                                <div class="p-4 bg-green-50 border-green-200 rounded-lg">
                                    <h4 class="font-semibold text-green-900 mb-2">Trending Down (last 30 days)</h4>
                                    <p class="text-sm text-gray-700" id="modalTrendDeclining">--</p>
                                </div>
                            </div>
                        </div>
                    `;
                    if (crimeTypeData) {
                        renderTrendsChart(crimeTypeData.monthly, 'modalCrimeTypeTrendsChart');
                        document.getElementById('modalTrendRising').textContent = crimeTypeData.stats.trending_up;
                        document.getElementById('modalTrendCommon').textContent = crimeTypeData.stats.most_common;
                        document.getElementById('modalTrendDeclining').textContent = crimeTypeData.stats.trending_down;
                    }
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
                                        <p class="text-sm text-gray-700" id="modalLocationHighRisk">--</p>
                                    </div>
                                    <div class="p-4 bg-orange-50 border-orange-200 rounded-lg">
                                        <h5 class="font-semibold text-orange-900 mb-2">Crime Type Patterns</h5>
                                        <p class="text-sm text-gray-700" id="modalLocationPatterns">--</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    if (crimeTypeData) {
                        renderByLocationChart(crimeTypeData.by_location, 'modalCrimeTypeByLocationChart');
                        renderLocationInsights(crimeTypeData);
                    }
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
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="modalSeverityCards">
                                <!-- Populated from real severity data -->
                            </div>
                        </div>
                    `;
                    if (crimeTypeData) {
                        renderSeverityChart(crimeTypeData.severity, 'modalCrimeTypeSeverityChart');
                        renderSeverityCards(crimeTypeData.severity);
                    }
                    break;
            }
        }

        // Computed insight lists for the distribution modal
        function renderDistributionInsights(data) {
            const labels = data.distribution.labels;
            const values = data.distribution.values;
            const total = values.reduce((a, b) => a + b, 0) || 1;

            const insightsList = document.getElementById('modalDistributionInsights');
            const recsList = document.getElementById('modalDistributionRecommendations');
            if (!insightsList || !recsList || !labels.length) {
                if (insightsList) insightsList.innerHTML = '<li>• No data for the selected filters.</li>';
                if (recsList) recsList.innerHTML = '';
                return;
            }

            const topShare = (values[0] / total * 100).toFixed(1);
            const top3Share = (values.slice(0, 3).reduce((a, b) => a + b, 0) / total * 100).toFixed(1);

            insightsList.innerHTML = `
                <li>• ${labels[0]} accounts for ${topShare}% of all recorded incidents</li>
                <li>• Top 3 crime types represent ${top3Share}% of incidents</li>
                <li>• ${labels.length} distinct crime type(s) recorded in the selected period</li>
            `;
            recsList.innerHTML = `
                <li>• Prioritize prevention programs targeting ${labels[0]}</li>
                ${labels[1] ? `<li>• Secondary focus: ${labels[1]} (${(values[1] / total * 100).toFixed(1)}% of incidents)</li>` : ''}
                <li>• Use the Pattern Detection simulator to test interventions for these types</li>
            `;
        }

        // Computed insight text for the location modal
        function renderLocationInsights(data) {
            const highRiskEl = document.getElementById('modalLocationHighRisk');
            const patternsEl = document.getElementById('modalLocationPatterns');
            if (!highRiskEl || !patternsEl) return;

            const areas = data.by_location.labels;
            if (!areas.length) {
                highRiskEl.textContent = 'No location data for the selected filters.';
                patternsEl.textContent = '--';
                return;
            }

            highRiskEl.textContent = `${areas.slice(0, 3).join(', ')} show the highest incident concentrations in the current selection.`;

            const topCategory = data.by_location.datasets[0];
            if (topCategory) {
                const maxIdx = topCategory.values.indexOf(Math.max(...topCategory.values));
                patternsEl.textContent = `${topCategory.name} is the leading crime type, most concentrated in ${areas[maxIdx] ?? areas[0]}.`;
            }
        }

        // Real severity breakdown cards for the severity modal
        function renderSeverityCards(severity) {
            const container = document.getElementById('modalSeverityCards');
            if (!container) return;

            const total = severity.low + severity.medium + severity.high + severity.critical;
            const pct = (v) => total > 0 ? Math.round(v / total * 100) : 0;

            container.innerHTML = `
                <div class="p-4 bg-green-50 border-green-200 rounded-lg text-center">
                    <h5 class="font-semibold text-green-900 mb-2">Low Severity</h5>
                    <p class="text-2xl font-bold text-green-700">${severity.low}</p>
                    <p class="text-sm text-gray-700">${pct(severity.low)}% of incidents</p>
                </div>
                <div class="p-4 bg-yellow-50 border-yellow-200 rounded-lg text-center">
                    <h5 class="font-semibold text-yellow-900 mb-2">Medium Severity</h5>
                    <p class="text-2xl font-bold text-yellow-700">${severity.medium}</p>
                    <p class="text-sm text-gray-700">${pct(severity.medium)}% of incidents</p>
                </div>
                <div class="p-4 bg-red-50 border-red-200 rounded-lg text-center">
                    <h5 class="font-semibold text-red-900 mb-2">High Severity</h5>
                    <p class="text-2xl font-bold text-red-700">${severity.high}</p>
                    <p class="text-sm text-gray-700">${pct(severity.high)}% of incidents</p>
                </div>
                <div class="p-4 bg-red-900 border-red-200 rounded-lg text-center">
                    <h5 class="font-semibold text-white mb-2">Critical</h5>
                    <p class="text-2xl font-bold text-white">${severity.critical}</p>
                    <p class="text-sm text-red-100">${pct(severity.critical)}% of incidents</p>
                </div>
            `;
        }

        // Update Crime Type Statistics from real server data
        function updateCrimeTypeStatistics(stats) {
            document.getElementById('totalCrimeTypeCount').textContent = stats.total_types;
            document.getElementById('mostCommonCrimeType').textContent = stats.most_common;
            document.getElementById('trendingUpCrimeType').textContent = stats.trending_up;
            document.getElementById('trendingDownCrimeType').textContent = stats.trending_down;
        }

        // Distribution doughnut - real per-category counts with category colors
        function renderDistributionChart(distribution, canvasId = 'crimeTypeDistributionChart') {
            const ctx = document.getElementById(canvasId)?.getContext('2d');
            if (!ctx) return;

            const existing = Chart.getChart(ctx);
            if (existing) existing.destroy();

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: distribution.labels,
                    datasets: [{
                        data: distribution.values,
                        backgroundColor: distribution.colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.parsed * 100) / total).toFixed(1) : 0;
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '50%'
                }
            });
        }

        // Trends line chart - real monthly counts per top category
        function renderTrendsChart(monthly, canvasId = 'crimeTypeTrendsChart') {
            const ctx = document.getElementById(canvasId)?.getContext('2d');
            if (!ctx) return;

            const existing = Chart.getChart(ctx);
            if (existing) existing.destroy();

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: monthly.labels,
                    datasets: monthly.datasets.map(d => ({
                        label: d.name,
                        data: d.values,
                        borderColor: d.color,
                        backgroundColor: 'transparent',
                        tension: 0.4
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

        // By-location grouped bars - real per-barangay per-category counts
        function renderByLocationChart(byLocation, canvasId = 'crimeTypeByLocationChart') {
            const ctx = document.getElementById(canvasId)?.getContext('2d');
            if (!ctx) return;

            const existing = Chart.getChart(ctx);
            if (existing) existing.destroy();

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: byLocation.labels,
                    datasets: byLocation.datasets.map(d => ({
                        label: d.name,
                        data: d.values,
                        backgroundColor: d.color,
                        borderWidth: 2,
                        borderRadius: 4
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { maxRotation: 45, minRotation: 45, font: { size: 10 } }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            title: { display: true, text: 'Number of Incidents' }
                        }
                    }
                }
            });
        }

        // Severity bars - real counts via category severity levels
        function renderSeverityChart(severity, canvasId = 'crimeTypeSeverityChart') {
            const ctx = document.getElementById(canvasId)?.getContext('2d');
            if (!ctx) return;

            const existing = Chart.getChart(ctx);
            if (existing) existing.destroy();

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Low', 'Medium', 'High', 'Critical'],
                    datasets: [{
                        label: 'Number of Incidents',
                        data: [severity.low, severity.medium, severity.high, severity.critical],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#7c2d12'],
                        borderWidth: 2,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
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
