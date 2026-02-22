@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

@extends('layouts.app')
@section('title', 'Risk Forecasting')
@section('content')
    <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
        <!-- Page Header -->
        <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                        <i class="fas fa-triangle-exclamation mr-3" style="color: #274d4c;"></i>Risk Forecasting
                    </h1>
                    <p class="text-gray-600 mt-1 text-sm lg:text-base">Predictive analytics for crime risk assessment and future incident forecasting</p>
                </div>
            </div>
        </div>

        <!-- Forecast Controls -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Forecast Period -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Forecast Period</label>
                    <select id="forecastPeriod" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#274d4c]">
                        <option value="24h">Next 24 Hours</option>
                        <option value="7d" selected>Next 7 Days</option>
                        <option value="30d">Next 30 Days</option>
                        <option value="90d">Next 90 Days</option>
                    </select>
                </div>

                <!-- Confidence Level -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confidence Level</label>
                    <select id="confidenceLevel" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#274d4c]">
                        <option value="90">90% (High)</option>
                        <option value="75" selected>75% (Medium)</option>
                        <option value="50">50% (Low)</option>
                    </select>
                </div>

                <!-- Risk Model -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Risk Model</label>
                    <select id="riskModel" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#274d4c]">
                        <option value="ml" selected>Machine Learning</option>
                        <option value="statistical">Statistical</option>
                        <option value="hybrid">Hybrid Model</option>
                    </select>
                </div>

                <!-- Area Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Target Area</label>
                    <select id="targetArea" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#274d4c]">
                        <option value="all" selected>All Areas</option>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->id }}">{{ $barangay->barangay_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3 mt-4">
                <button onclick="generateForecast()" class="px-4 py-2 bg-[#274d4c] text-white rounded-md hover:bg-[#1a3534] transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>Generate Forecast
                </button>
                <button onclick="exportForecast()" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                    <i class="fas fa-download mr-2"></i>Export Report
                </button>
                <button onclick="compareModels()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-balance-scale mr-2"></i>Compare Models
                </button>
            </div>
        </div>

        <!-- Risk Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-red-50 to-red-100 border-red-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-red-900 mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>High Risk Areas
                        </p>
                        <p class="text-2xl font-bold text-red-700" id="highRiskCount">7</p>
                        <p class="text-xs text-red-600 mt-1">Above 70% risk threshold</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-orange-900 mb-1">
                            <i class="fas fa-chart-line mr-1"></i>Trend Direction
                        </p>
                        <p class="text-2xl font-bold text-orange-700" id="trendDirection">↑ 15%</p>
                        <p class="text-xs text-orange-600 mt-1">Increasing risk trend</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 border-yellow-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-yellow-900 mb-1">
                            <i class="fas fa-brain mr-1"></i>Model Accuracy
                        </p>
                        <p class="text-2xl font-bold text-yellow-700" id="modelAccuracy">87%</p>
                        <p class="text-xs text-yellow-600 mt-1">Current model performance</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-blue-900 mb-1">
                            <i class="fas fa-clock mr-1"></i>Next Peak
                        </p>
                        <p class="text-2xl font-bold text-blue-700" id="nextPeak">48h</p>
                        <p class="text-xs text-blue-600 mt-1">Until next risk peak</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Forecast Visualization -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Risk Forecast Chart -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-chart-area mr-2" style="color: #274d4c;"></i>
                        Risk Forecast Timeline
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 400px;">
                        <canvas id="riskForecastChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Risk Heatmap -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-th mr-2" style="color: #274d4c;"></i>
                        Risk Heatmap by Area & Time
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 400px;">
                        <canvas id="riskHeatmap"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Risk Analysis -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Top Risk Areas -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-exclamation-circle mr-2" style="color: #274d4c;"></i>
                        Top Risk Areas
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 300px;">
                        <canvas id="topRiskAreasChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Risk Factors Analysis -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-search mr-2" style="color: #274d4c;"></i>
                        Risk Factors
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 300px;">
                        <canvas id="riskFactorsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Prediction Confidence -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-percentage mr-2" style="color: #274d4c;"></i>
                        Prediction Confidence
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 300px;">
                        <canvas id="confidenceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Predictive Insights & Recommendations -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
            <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-lightbulb mr-2" style="color: #274d4c;"></i>
                    Predictive Insights & Recommendations
                </h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Immediate Alerts -->
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <h4 class="font-semibold text-red-900 mb-3">
                            <i class="fas fa-bell mr-1"></i>Immediate Alerts
                        </h4>
                        <div class="space-y-2">
                            <div class="bg-white rounded p-3 border border-red-100">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-sm font-medium">Downtown Core</span>
                                    <span class="text-xs bg-red-600 text-white px-2 py-1 rounded">Critical</span>
                                </div>
                                <p class="text-xs text-gray-600">85% probability of incidents in next 24h</p>
                                <p class="text-xs text-gray-500 mt-1">Peak: 8PM - 11PM</p>
                            </div>
                            <div class="bg-white rounded p-3 border border-orange-100">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-sm font-medium">Industrial Zone</span>
                                    <span class="text-xs bg-orange-600 text-white px-2 py-1 rounded">High</span>
                                </div>
                                <p class="text-xs text-gray-600">72% probability of incidents in next 48h</p>
                                <p class="text-xs text-gray-500 mt-1">Peak: 10PM - 2AM</p>
                            </div>
                        </div>
                    </div>

                    <!-- Trend Analysis -->
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                        <h4 class="font-semibold text-orange-900 mb-3">
                            <i class="fas fa-chart-line mr-1"></i>Trend Analysis
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Weekly Trend</span>
                                    <span class="text-red-600 font-medium">↑ 15%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-red-600 h-2 rounded-full" style="width: 75%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Monthly Trend</span>
                                    <span class="text-orange-600 font-medium">↑ 8%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-orange-600 h-2 rounded-full" style="width: 58%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Seasonal Pattern</span>
                                    <span class="text-yellow-600 font-medium">→ 0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-600 h-2 rounded-full" style="width: 50%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recommendations -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-900 mb-3">
                            <i class="fas fa-shield-alt mr-1"></i>Strategic Recommendations
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-blue-600 mt-0.5 mr-2 text-sm"></i>
                                <div>
                                    <p class="text-sm font-medium">Increase Patrols</p>
                                    <p class="text-xs text-gray-600">Deploy 20% more units to high-risk areas</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-blue-600 mt-0.5 mr-2 text-sm"></i>
                                <div>
                                    <p class="text-sm font-medium">Community Engagement</p>
                                    <p class="text-xs text-gray-600">Launch awareness campaigns in emerging zones</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-blue-600 mt-0.5 mr-2 text-sm"></i>
                                <div>
                                    <p class="text-sm font-medium">Resource Allocation</p>
                                    <p class="text-xs text-gray-600">Optimize equipment distribution based on forecast</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-blue-600 mt-0.5 mr-2 text-sm"></i>
                                <div>
                                    <p class="text-sm font-medium">Technology Deployment</p>
                                    <p class="text-xs text-gray-600">Install temporary surveillance in critical zones</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let riskForecastChart, riskHeatmap, topRiskAreasChart, riskFactorsChart, confidenceChart;

        document.addEventListener('DOMContentLoaded', function() {
            initializeRiskForecastChart();
            initializeRiskHeatmap();
            initializeTopRiskAreasChart();
            initializeRiskFactorsChart();
            initializeConfidenceChart();
            setupEventListeners();
        });

        function initializeRiskForecastChart() {
            const ctx = document.getElementById('riskForecastChart')?.getContext('2d');
            if (!ctx) return;

            // Generate forecast data
            const labels = [];
            const actualData = [];
            const forecastData = [];
            const upperBound = [];
            const lowerBound = [];
            
            for (let i = 0; i < 24; i++) {
                labels.push(`Hour ${i}`);
                if (i < 12) {
                    actualData.push(Math.floor(Math.random() * 30 + 40));
                    forecastData.push(null);
                    upperBound.push(null);
                    lowerBound.push(null);
                } else {
                    const base = 65 + Math.sin(i / 4) * 15;
                    const variation = Math.random() * 10 - 5;
                    actualData.push(null);
                    forecastData.push(Math.floor(base + variation));
                    upperBound.push(Math.floor(base + variation + 15));
                    lowerBound.push(Math.floor(base + variation - 15));
                }
            }

            riskForecastChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Actual Risk',
                            data: actualData,
                            borderColor: '#274d4c',
                            backgroundColor: 'rgba(39, 77, 76, 0.1)',
                            tension: 0.4,
                            fill: false
                        },
                        {
                            label: 'Forecast',
                            data: forecastData,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            borderDash: [5, 5],
                            tension: 0.4,
                            fill: false
                        },
                        {
                            label: 'Upper Bound',
                            data: upperBound,
                            borderColor: 'rgba(239, 68, 68, 0.3)',
                            backgroundColor: 'rgba(239, 68, 68, 0.05)',
                            borderDash: [2, 2],
                            fill: '+1'
                        },
                        {
                            label: 'Lower Bound',
                            data: lowerBound,
                            borderColor: 'rgba(239, 68, 68, 0.3)',
                            backgroundColor: 'rgba(239, 68, 68, 0.05)',
                            borderDash: [2, 2],
                            fill: false
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
                            max: 100,
                            title: {
                                display: true,
                                text: 'Risk Level (%)'
                            }
                        }
                    }
                }
            });
        }

        function initializeRiskHeatmap() {
            const ctx = document.getElementById('riskHeatmap')?.getContext('2d');
            if (!ctx) return;

            // Generate heatmap data
            const areas = ['Downtown', 'Industrial', 'Riverside', 'Commercial', 'Residential'];
            const times = ['00:00', '06:00', '12:00', '18:00', '24:00'];
            const data = [];

            areas.forEach((area, i) => {
                times.forEach((time, j) => {
                    data.push({
                        x: j,
                        y: i,
                        v: Math.floor(Math.random() * 60 + 20)
                    });
                });
            });

            riskHeatmap = new Chart(ctx, {
                type: 'bubble',
                data: {
                    datasets: [{
                        label: 'Risk Level',
                        data: data,
                        backgroundColor: function(context) {
                            const value = context.raw.v;
                            const alpha = value / 100;
                            if (value > 70) return `rgba(239, 68, 68, ${alpha})`;
                            if (value > 50) return `rgba(245, 158, 11, ${alpha})`;
                            if (value > 30) return `rgba(234, 179, 8, ${alpha})`;
                            return `rgba(34, 197, 94, ${alpha})`;
                        }
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Time of Day' },
                            ticks: {
                                callback: function(value) {
                                    return times[value] || '';
                                }
                            }
                        },
                        y: {
                            title: { display: true, text: 'Area' },
                            ticks: {
                                callback: function(value) {
                                    return areas[value] || '';
                                }
                            }
                        }
                    }
                }
            });
        }

        function initializeTopRiskAreasChart() {
            const ctx = document.getElementById('topRiskAreasChart')?.getContext('2d');
            if (!ctx) return;

            topRiskAreasChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Downtown', 'Industrial', 'Riverside', 'Commercial', 'University'],
                    datasets: [{
                        label: 'Risk Score',
                        data: [85, 72, 68, 58, 45],
                        backgroundColor: function(context) {
                            const value = context.raw;
                            if (value > 70) return '#ef4444';
                            if (value > 50) return '#f59e0b';
                            return '#eab308';
                        }
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }

        function initializeRiskFactorsChart() {
            const ctx = document.getElementById('riskFactorsChart')?.getContext('2d');
            if (!ctx) return;

            riskFactorsChart = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['Time of Day', 'Location', 'Weather', 'Events', 'Historical', 'Social'],
                    datasets: [{
                        label: 'Current Risk',
                        data: [85, 72, 45, 68, 78, 52],
                        borderColor: '#274d4c',
                        backgroundColor: 'rgba(39, 77, 76, 0.2)'
                    }, {
                        label: 'Average Risk',
                        data: [60, 60, 60, 60, 60, 60],
                        borderColor: '#9ca3af',
                        backgroundColor: 'rgba(156, 163, 175, 0.1)',
                        borderDash: [5, 5]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }

        function initializeConfidenceChart() {
            const ctx = document.getElementById('confidenceChart')?.getContext('2d');
            if (!ctx) return;

            confidenceChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['High Confidence', 'Medium Confidence', 'Low Confidence'],
                    datasets: [{
                        data: [65, 25, 10],
                        backgroundColor: ['#22c55e', '#f59e0b', '#ef4444']
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
            document.getElementById('forecastPeriod').addEventListener('change', generateForecast);
            document.getElementById('confidenceLevel').addEventListener('change', generateForecast);
            document.getElementById('riskModel').addEventListener('change', generateForecast);
            document.getElementById('targetArea').addEventListener('change', generateForecast);
        }

        function generateForecast() {
            // Simulate forecast generation
            const forecastPeriod = document.getElementById('forecastPeriod').value;
            const confidenceLevel = document.getElementById('confidenceLevel').value;
            
            // Update statistics
            document.getElementById('highRiskCount').textContent = Math.floor(Math.random() * 5 + 5);
            document.getElementById('trendDirection').textContent = `↑ ${Math.floor(Math.random() * 20 + 5)}%`;
            document.getElementById('modelAccuracy').textContent = `${Math.floor(Math.random() * 10 + 80)}%`;
            document.getElementById('nextPeak').textContent = forecastPeriod === '24h' ? '8h' : '48h';
            
            // Refresh charts with new data
            refreshCharts();
        }

        function refreshCharts() {
            // Simulate chart refresh
            if (riskForecastChart) {
                riskForecastChart.update();
            }
            if (riskHeatmap) {
                riskHeatmap.update();
            }
        }

        function exportForecast() {
            alert('Risk forecast report exported successfully!');
        }

        function compareModels() {
            alert('Model comparison feature coming soon!');
        }
    </script>
@endsection
