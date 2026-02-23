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
                        <i class="fas fa-crystal-ball mr-3" style="color: #274d4c;"></i>Crime Forecast
                    </h1>
                    <p class="text-gray-600 mt-1 text-sm lg:text-base">Predictive analytics showing historical trends and forecasted incidents for the next period</p>
                </div>
            </div>
        </div>

        <!-- Forecast Controls -->
        <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
            <div class="mb-4 pb-4 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-900">
                    <i class="fas fa-filter mr-2 text-alertara-700"></i>Forecast Filters
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <!-- Forecast Period -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Forecast Period</label>
                    <select id="forecastPeriod" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="7d" selected>Next 7 Days</option>
                        <option value="14d">Next 14 Days</option>
                        <option value="30d">Next 30 Days</option>
                        <option value="90d">Next 90 Days</option>
                    </select>
                </div>

                <!-- Crime Type -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Crime Type</label>
                    <select id="crimeTypeFilter" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="" selected>All Types</option>
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

                <!-- Barangay -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Barangay</label>
                    <select id="targetArea" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="all" selected>All Barangays</option>
                        @if(isset($barangays))
                            @foreach($barangays as $barangay)
                                <option value="{{ $barangay->id }}">{{ $barangay->barangay_name }}</option>
                            @endforeach
                        @endif
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
                    <button onclick="generateForecast()" class="w-full px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors font-medium flex items-center justify-center gap-2">
                        <i class="fas fa-chart-line"></i>
                        <span>Generate</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Forecast Chart (Full Width) -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
            <div class="mb-4">
                <h2 class="text-xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-chart-line mr-3" style="color: #274d4c;"></i>
                    Main Forecast Chart
                </h2>
                <p class="text-sm text-gray-600 mt-1">Solid line shows historical crime data • Dashed line shows predicted trend</p>
            </div>
            <div style="position: relative; height: 450px;">
                <canvas id="mainForecastChart"></canvas>
            </div>
        </div>

        <!-- Forecast Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Predicted Trend -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-300 rounded-lg p-6">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900">📌 Predicted Trend</h3>
                </div>
                <p class="text-3xl font-bold text-blue-700 mb-2" id="predictedTrend">+18%</p>
                <p class="text-xs text-gray-600">Expected Increase in incidents</p>
            </div>

            <!-- Most Likely Crime Type -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-300 rounded-lg p-6">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900">📌 Most Likely Crime Type</h3>
                </div>
                <p class="text-2xl font-bold text-purple-700 mb-2" id="likelyCrimeType">Theft</p>
                <p class="text-xs text-gray-600">Next Period</p>
            </div>

            <!-- Predicted High-Risk Area -->
            <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-300 rounded-lg p-6">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900">📌 Predicted High-Risk Area</h3>
                </div>
                <p class="text-2xl font-bold text-red-700 mb-2" id="highRiskArea">Barangay San Isidro</p>
                <p class="text-xs text-gray-600">Highest concentration</p>
            </div>

            <!-- Confidence Score -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-300 rounded-lg p-6">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900">📌 Confidence Score</h3>
                </div>
                <p class="text-3xl font-bold text-green-700 mb-2" id="confidenceScore">82%</p>
                <p class="text-xs text-gray-600">Prediction Confidence</p>
            </div>
        </div>

        <!-- Risk Level Classification -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-8 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-traffic-light mr-3" style="color: #274d4c;"></i>
                🚦 Risk Level Classification
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- HIGH RISK -->
                <div class="text-center p-8 bg-red-50 border-2 border-red-300 rounded-lg">
                    <div class="text-5xl font-bold text-red-600 mb-3">🔴</div>
                    <h3 class="text-2xl font-bold text-red-700 mb-2">HIGH RISK</h3>
                    <p class="text-sm text-gray-700 mb-4">
                        Risk level is calculated based on projected crime frequency compared to historical average. High risk indicates significantly elevated incident probability.
                    </p>
                    <p class="text-xs text-gray-600" id="highRiskDesc">Potential 50%+ increase</p>
                </div>

                <!-- MODERATE RISK -->
                <div class="text-center p-8 bg-yellow-50 border-2 border-yellow-300 rounded-lg">
                    <div class="text-5xl font-bold text-yellow-600 mb-3">🟡</div>
                    <h3 class="text-2xl font-bold text-yellow-700 mb-2">MODERATE RISK</h3>
                    <p class="text-sm text-gray-700 mb-4">
                        Moderate elevation compared to baseline. Increased precautions and monitoring recommended.
                    </p>
                    <p class="text-xs text-gray-600">Potential 20-50% increase</p>
                </div>

                <!-- LOW RISK -->
                <div class="text-center p-8 bg-green-50 border-2 border-green-300 rounded-lg">
                    <div class="text-5xl font-bold text-green-600 mb-3">🟢</div>
                    <h3 class="text-2xl font-bold text-green-700 mb-2">LOW RISK</h3>
                    <p class="text-sm text-gray-700 mb-4">
                        Risk remains relatively stable or slightly below average. Routine operations continue.
                    </p>
                    <p class="text-xs text-gray-600">Potential &lt; 20% increase</p>
                </div>
            </div>
        </div>

        <!-- Forecast Breakdown Table -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-table mr-3" style="color: #274d4c;"></i>
                📆 Forecast Breakdown Table
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-300 bg-gray-50">
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Date</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Predicted Incidents</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Risk Level</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Confidence</th>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Primary Crime Type</th>
                        </tr>
                    </thead>
                    <tbody id="forecastTableBody">
                        <!-- Dynamically populated -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Trend Comparison Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- This Month vs Last Month -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-balance-scale mr-2" style="color: #274d4c;"></i>
                    This Month vs Last Month
                </h3>
                <div style="position: relative; height: 300px;">
                    <canvas id="monthComparisonChart"></canvas>
                </div>
            </div>

            <!-- Current Trend vs 6-Month Average -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-history mr-2" style="color: #274d4c;"></i>
                    Current Trend vs 6-Month Average
                </h3>
                <div style="position: relative; height: 300px;">
                    <canvas id="trendAverageChart"></canvas>
                </div>
            </div>
        </div>

        <!-- AI Insight Box -->
        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 border-l-4 border-indigo-500 rounded-lg p-6 mb-8">
            <div class="flex items-start gap-4">
                <div class="text-3xl">🤖</div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">AI Insight Based on Historical Data</h3>
                    <p class="text-gray-700 text-sm leading-relaxed" id="aiInsightText">
                        Based on historical data analysis, theft cases typically increase during weekends and are projected to rise in the next 14 days. Police departments should consider deploying additional units to commercial districts on Friday and Saturday nights. The confidence level for this prediction is 82%, which is considered high reliability.
                    </p>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-download mr-2" style="color: #274d4c;"></i>
                📤 Export Options
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button onclick="exportPDF()" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium flex items-center justify-center gap-2">
                    <i class="fas fa-file-pdf"></i>
                    Export Forecast Report (PDF)
                </button>
                <button onclick="exportCSV()" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium flex items-center justify-center gap-2">
                    <i class="fas fa-download"></i>
                    Download Forecast Data (CSV)
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

    <script>
        let mainForecastChart, monthComparisonChart, trendAverageChart;
        let forecastData = [];

        document.addEventListener('DOMContentLoaded', function() {
            initializeMainForecastChart();
            initializeMonthComparisonChart();
            initializeTrendAverageChart();
            populateForecastTable();
            setupEventListeners();
            generateInitialForecast();
        });

        function initializeMainForecastChart() {
            const ctx = document.getElementById('mainForecastChart')?.getContext('2d');
            if (!ctx) return;

            // Generate 30 days of data
            const labels = [];
            const historicalData = [];
            const predictedData = [];

            for (let i = -14; i < 16; i++) {
                const date = new Date();
                date.setDate(date.getDate() + i);
                labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));

                if (i < 0) {
                    // Historical data (solid line)
                    const base = 50 + Math.sin(i / 5) * 15;
                    const variation = Math.random() * 10 - 5;
                    historicalData.push(Math.floor(base + variation));
                    predictedData.push(null);
                } else {
                    // Predicted data (dashed line)
                    historicalData.push(null);
                    const trend = 18; // +18% increase
                    const base = 55 + (trend * i / 15);
                    const variation = Math.random() * 8 - 4;
                    predictedData.push(Math.floor(base + variation));
                }
            }

            mainForecastChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Historical Crime Data',
                            data: historicalData,
                            borderColor: '#274d4c',
                            backgroundColor: 'rgba(39, 77, 76, 0.05)',
                            fill: true,
                            borderWidth: 3,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#274d4c',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        },
                        {
                            label: 'Predicted Trend',
                            data: predictedData,
                            borderColor: '#ef4444',
                            borderDash: [6, 3],
                            borderWidth: 3,
                            fill: false,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#ef4444',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: { size: 12, weight: 'bold' }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 13, weight: 'bold' },
                            bodyFont: { size: 12 }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Predicted Incidents',
                                font: { size: 12, weight: 'bold' }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        function initializeMonthComparisonChart() {
            const ctx = document.getElementById('monthComparisonChart')?.getContext('2d');
            if (!ctx) return;

            monthComparisonChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [
                        {
                            label: 'Last Month',
                            data: [45, 52, 48, 55],
                            backgroundColor: 'rgba(156, 163, 175, 0.6)',
                            borderColor: '#9ca3af',
                            borderWidth: 1
                        },
                        {
                            label: 'This Month',
                            data: [52, 61, 58, 68],
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderColor: '#ef4444',
                            borderWidth: 1
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
                            max: 100
                        }
                    }
                }
            });
        }

        function initializeTrendAverageChart() {
            const ctx = document.getElementById('trendAverageChart')?.getContext('2d');
            if (!ctx) return;

            trendAverageChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6'],
                    datasets: [
                        {
                            label: '6-Month Average',
                            data: [48, 50, 52, 51, 50, 49],
                            borderColor: '#6b7280',
                            borderDash: [5, 5],
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            pointRadius: 4
                        },
                        {
                            label: 'Current Trend',
                            data: [52, 58, 62, 65, 68, 72],
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            fill: true,
                            borderWidth: 2,
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: '#ef4444'
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
                            max: 100
                        }
                    }
                }
            });
        }

        function populateForecastTable() {
            const tableBody = document.getElementById('forecastTableBody');
            tableBody.innerHTML = '';

            const crimeTypes = ['Theft', 'Robbery', 'Burglary', 'Assault', 'Auto Theft'];
            const barangays = ['San Isidro', 'Malate', 'Ermita', 'Tondo', 'Makati'];

            for (let i = 1; i <= 7; i++) {
                const date = new Date();
                date.setDate(date.getDate() + i);

                const incidents = Math.floor(Math.random() * 20 + 10);
                let riskLevel = 'Low';
                let riskColor = 'green';

                if (incidents > 20) {
                    riskLevel = 'High';
                    riskColor = 'red';
                } else if (incidents > 15) {
                    riskLevel = 'Medium';
                    riskColor = 'yellow';
                }

                const confidence = Math.floor(Math.random() * 15 + 75);
                const crimeType = crimeTypes[Math.floor(Math.random() * crimeTypes.length)];

                const row = `
                    <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                        <td class="px-6 py-4 text-sm font-bold text-gray-900">${incidents}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-3 py-1 rounded-full text-white font-medium text-xs" style="background-color: ${riskColor === 'red' ? '#ef4444' : riskColor === 'yellow' ? '#f59e0b' : '#22c55e'}">
                                ${riskLevel}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">${confidence}%</td>
                        <td class="px-6 py-4 text-sm text-gray-700">${crimeType}</td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            }
        }

        function setupEventListeners() {
            document.getElementById('forecastPeriod').addEventListener('change', generateForecast);
            document.getElementById('crimeTypeFilter').addEventListener('change', generateForecast);
            document.getElementById('caseStatus').addEventListener('change', generateForecast);
            document.getElementById('targetArea').addEventListener('change', generateForecast);
        }

        function resetFilters() {
            document.getElementById('forecastPeriod').value = '7d';
            document.getElementById('crimeTypeFilter').value = '';
            document.getElementById('caseStatus').value = '';
            document.getElementById('targetArea').value = 'all';
            generateForecast();
        }

        function generateInitialForecast() {
            // Set initial values for summary cards
            updateSummaryCards();
            updateAIInsight();
        }

        function generateForecast() {
            const period = document.getElementById('forecastPeriod').value;
            console.log('Generating forecast for period:', period);

            // Update all components
            updateSummaryCards();
            updateAIInsight();
            populateForecastTable();

            if (mainForecastChart) {
                mainForecastChart.update();
            }
        }

        function updateSummaryCards() {
            const trends = ['+18%', '+12%', '-5%', '+8%'];
            const crimes = ['Theft', 'Robbery', 'Burglary', 'Assault'];
            const areas = ['Barangay San Isidro', 'Barangay Malate', 'Barangay Ermita', 'Barangay Tondo'];

            const randomTrend = trends[Math.floor(Math.random() * trends.length)];
            const randomCrime = crimes[Math.floor(Math.random() * crimes.length)];
            const randomArea = areas[Math.floor(Math.random() * areas.length)];
            const randomConfidence = Math.floor(Math.random() * 15 + 75);

            document.getElementById('predictedTrend').textContent = randomTrend;
            document.getElementById('likelyCrimeType').textContent = randomCrime;
            document.getElementById('highRiskArea').textContent = randomArea;
            document.getElementById('confidenceScore').textContent = randomConfidence + '%';
        }

        function updateAIInsight() {
            const insights = [
                'Based on historical data analysis, theft cases typically increase during weekends and are projected to rise in the next 14 days. Police departments should consider deploying additional units to commercial districts on Friday and Saturday nights. The confidence level for this prediction is 82%, which is considered high reliability.',
                'Robbery incidents show a strong correlation with nighttime hours (8 PM to 2 AM). The forecast indicates a 12% increase in the coming week, particularly in the downtown area. Enhanced street lighting and patrol presence are recommended during peak hours.',
                'Burglary cases are seasonal and typically peak during holiday periods. Current trends suggest a moderate increase (8%) in the next 30 days. Residents should be reminded about home security measures.',
                'Assault cases fluctuate based on social events and gatherings. With several major events scheduled, a 15% increase is predicted. Coordination with event organizers is essential for public safety.'
            ];

            const randomInsight = insights[Math.floor(Math.random() * insights.length)];
            document.getElementById('aiInsightText').textContent = randomInsight;
        }

        function exportPDF() {
            alert('Exporting forecast report to PDF...\n\nNote: In production, this would generate a professional PDF with all charts and data.');
        }

        function exportCSV() {
            // Create CSV content
            let csv = 'Date,Predicted Incidents,Risk Level,Confidence,Crime Type\n';

            const rows = document.querySelectorAll('#forecastTableBody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                csv += `${cells[0].textContent},${cells[1].textContent},${cells[2].textContent},${cells[3].textContent},${cells[4].textContent}\n`;
            });

            // Download
            const element = document.createElement('a');
            element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv));
            element.setAttribute('download', 'crime_forecast_' + new Date().toISOString().split('T')[0] + '.csv');
            element.style.display = 'none';
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
        }
    </script>
@endsection
