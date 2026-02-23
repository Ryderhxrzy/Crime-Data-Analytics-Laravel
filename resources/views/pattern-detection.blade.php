@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

@extends('layouts.app')
@section('title', 'Pattern Detection')
@section('content')
    <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
        <!-- Page Header -->
        <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                        <i class="fas fa-magnifying-glass mr-3" style="color: #274d4c;"></i>Pattern Detection
                    </h1>
                    <p class="text-gray-600 mt-1 text-sm lg:text-base">Advanced pattern recognition and anomaly detection in crime data</p>
                </div>
            </div>
        </div>

        <!-- Standardized Filter Section -->
        <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
            <div class="mb-4 pb-4 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-900">
                    <i class="fas fa-filter mr-2 text-alertara-700"></i>Pattern Detection Filters
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <!-- Analysis Type -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Analysis Type</label>
                    <select id="analysisType" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="temporal" selected>Temporal Patterns</option>
                        <option value="spatial">Spatial Patterns</option>
                        <option value="behavioral">Behavioral Patterns</option>
                        <option value="sequential">Sequential Patterns</option>
                    </select>
                </div>

                <!-- Time Range -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Time Range</label>
                    <select id="timeRange" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="7d">Last 7 Days</option>
                        <option value="30d" selected>Last 30 Days</option>
                        <option value="90d">Last 90 Days</option>
                        <option value="1y">Last Year</option>
                    </select>
                </div>

                <!-- Pattern Sensitivity -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Sensitivity</label>
                    <select id="sensitivity" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="low">Low (Broad)</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High (Specific)</option>
                    </select>
                </div>

                <!-- Crime Category -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Crime Category</label>
                    <select id="patternCrimeType" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Categories</option>
                        @foreach($crimeCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Action Buttons (Span 2 columns) -->
                <div class="col-span-2 flex items-end gap-2">
                    <button onclick="runPatternDetection()" class="flex-1 px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-search"></i>Run Detection
                    </button>
                    <button onclick="resetPatternFilter()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-redo"></i>Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Pattern Detection Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border-purple-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-purple-900 mb-1">
                            <i class="fas fa-project-diagram mr-1"></i>Patterns Found
                        </p>
                        <p class="text-2xl font-bold text-purple-700" id="patternsFound">24</p>
                        <p class="text-xs text-purple-600 mt-1">Significant patterns detected</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 border-indigo-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-indigo-900 mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Anomalies
                        </p>
                        <p class="text-2xl font-bold text-indigo-700" id="anomaliesDetected">7</p>
                        <p class="text-xs text-indigo-600 mt-1">Unusual activity patterns</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-pink-50 to-pink-100 border-pink-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-pink-900 mb-1">
                            <i class="fas fa-link mr-1"></i>Correlations
                        </p>
                        <p class="text-2xl font-bold text-pink-700" id="correlations">12</p>
                        <p class="text-xs text-pink-600 mt-1">Related pattern clusters</p>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-teal-50 to-teal-100 border-teal-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold text-teal-900 mb-1">
                            <i class="fas fa-bullseye mr-1"></i>Accuracy
                        </p>
                        <p class="text-2xl font-bold text-teal-700" id="detectionAccuracy">92%</p>
                        <p class="text-xs text-teal-600 mt-1">Pattern detection accuracy</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pattern Detection Timeline -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow mb-8">
            <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-timeline mr-2" style="color: #274d4c;"></i>
                    Pattern Detection Timeline
                </h3>
                <p class="text-sm text-gray-600 mt-1">Chronological view of detected patterns and their evolution</p>
            </div>
            <div class="p-6">
                <div class="space-y-6" id="patternTimeline">
                    <!-- Timeline Item 1 -->
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-exclamation text-red-600 text-lg"></i>
                            </div>
                            <div class="w-1 h-20 bg-red-200"></div>
                        </div>
                        <div class="flex-1 pb-4">
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-semibold text-gray-900">Critical: Vehicle Theft Hotspot Sequence</h4>
                                    <span class="text-xs font-semibold text-red-700 bg-red-100 px-2 py-1 rounded">2 hours ago</span>
                                </div>
                                <p class="text-sm text-gray-700 mb-2">Sequential vehicle theft pattern detected with 91% confidence</p>
                                <div class="flex flex-wrap gap-2">
                                    <span class="text-xs bg-red-200 text-red-800 px-2 py-1 rounded">Sequential Pattern</span>
                                    <span class="text-xs bg-orange-200 text-orange-800 px-2 py-1 rounded">High Risk</span>
                                    <span class="text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded">4 Incidents</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline Item 2 -->
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-warning text-orange-600 text-lg"></i>
                            </div>
                            <div class="w-1 h-20 bg-orange-200"></div>
                        </div>
                        <div class="flex-1 pb-4">
                            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-semibold text-gray-900">Emerging: Industrial Zone Burglary Cluster</h4>
                                    <span class="text-xs font-semibold text-orange-700 bg-orange-100 px-2 py-1 rounded">6 hours ago</span>
                                </div>
                                <p class="text-sm text-gray-700 mb-2">Spatial pattern of burglaries in warehouses with 72% match rate</p>
                                <div class="flex flex-wrap gap-2">
                                    <span class="text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded">Spatial Pattern</span>
                                    <span class="text-xs bg-orange-200 text-orange-800 px-2 py-1 rounded">Medium Risk</span>
                                    <span class="text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded">8 Incidents</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline Item 3 -->
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-check text-purple-600 text-lg"></i>
                            </div>
                            <div class="w-1 h-20 bg-purple-200"></div>
                        </div>
                        <div class="flex-1 pb-4">
                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-semibold text-gray-900">Active: Weekend Night Theft Pattern</h4>
                                    <span class="text-xs font-semibold text-purple-700 bg-purple-100 px-2 py-1 rounded">1 day ago</span>
                                </div>
                                <p class="text-sm text-gray-700 mb-2">Consistent weekend night theft pattern in commercial areas with 87% match rate</p>
                                <div class="flex flex-wrap gap-2">
                                    <span class="text-xs bg-red-200 text-red-800 px-2 py-1 rounded">Temporal Pattern</span>
                                    <span class="text-xs bg-red-200 text-red-800 px-2 py-1 rounded">High Risk</span>
                                    <span class="text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded">12 Incidents</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline Item 4 -->
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mb-2">
                                <i class="fas fa-check text-green-600 text-lg"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-semibold text-gray-900">Resolved: Residential Robbery Streak</h4>
                                    <span class="text-xs font-semibold text-green-700 bg-green-100 px-2 py-1 rounded">3 days ago</span>
                                </div>
                                <p class="text-sm text-gray-700 mb-2">Pattern successfully identified and suspects arrested - pattern no longer active</p>
                                <div class="flex flex-wrap gap-2">
                                    <span class="text-xs bg-green-200 text-green-800 px-2 py-1 rounded">Resolved</span>
                                    <span class="text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded">6 Incidents</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Pattern Visualization -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Pattern Timeline (Temporal) -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-clock mr-2" style="color: #274d4c;"></i>
                        Pattern Occurrence Timeline
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 400px;">
                        <canvas id="temporalPatternChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Pattern Network -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-network-wired mr-2" style="color: #274d4c;"></i>
                        Pattern Correlation Network
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 400px;">
                        <canvas id="patternNetworkChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Pattern Analysis -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Frequency Patterns -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-chart-bar mr-2" style="color: #274d4c;"></i>
                        Frequency Patterns
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 300px;">
                        <canvas id="frequencyPatternChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sequence Patterns -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-sort-numeric-down mr-2" style="color: #274d4c;"></i>
                        Sequence Analysis
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 300px;">
                        <canvas id="sequencePatternChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Anomaly Detection -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-radar mr-2" style="color: #274d4c;"></i>
                        Anomaly Detection
                    </h3>
                </div>
                <div class="p-4">
                    <div style="position: relative; height: 300px;">
                        <canvas id="anomalyDetectionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detected Patterns List with Incident Sequences -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow mb-8">
            <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2" style="color: #274d4c;"></i>
                    Detected Patterns & Incident Sequences
                </h3>
                <p class="text-sm text-gray-600 mt-1">Detailed analysis with chronological incident sequences</p>
            </div>
            <div class="p-4">
                <div class="space-y-4" id="patternsList">
                    <!-- Pattern 1: Weekend Night Pattern with Sequence -->
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-purple-300 transition-colors">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-calendar-week text-purple-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Weekend Night Theft Pattern</h4>
                                    <p class="text-sm text-gray-600">Temporal • High Confidence • 87% Match</p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">Active</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                            <div class="text-sm">
                                <span class="font-medium text-gray-700">Frequency:</span>
                                <span class="text-gray-600 ml-2">Every weekend, 8PM-2AM</span>
                            </div>
                            <div class="text-sm">
                                <span class="font-medium text-gray-700">Locations:</span>
                                <span class="text-gray-600 ml-2">Downtown, Commercial District</span>
                            </div>
                            <div class="text-sm">
                                <span class="font-medium text-gray-700">Risk Level:</span>
                                <span class="text-red-600 ml-2 font-medium">High</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded p-3 mb-3">
                            <p class="text-sm text-gray-700">
                                <strong>Pattern Description:</strong> Significant increase in theft incidents during weekend nights, particularly in commercial areas. Pattern suggests organized activity with 3-4 incidents per weekend following similar MO.
                            </p>
                        </div>
                        <!-- Incident Sequence Timeline -->
                        <div class="bg-white border border-gray-200 rounded p-3 text-xs">
                            <p class="font-semibold text-gray-900 mb-2">Recent Incident Sequence:</p>
                            <div class="space-y-1 text-gray-700">
                                <div class="flex items-center gap-2"><span class="text-purple-600">●</span><strong>Feb 22, 11:45 PM</strong> - Theft at Downtown Mall</div>
                                <div class="flex items-center gap-2"><span class="text-purple-600">→</span><strong>Feb 22, 12:30 AM</strong> - Theft at Commercial Center (3.2 km away)</div>
                                <div class="flex items-center gap-2"><span class="text-purple-600">→</span><strong>Feb 22, 1:15 AM</strong> - Theft at Shopping District (1.8 km away)</div>
                                <div class="flex items-center gap-2"><span class="text-purple-600">→</span><strong>Feb 23, 2:00 AM</strong> - Theft at Business Plaza (2.1 km away)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Pattern 2: Industrial Zone Burglary -->
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 transition-colors">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-industry text-indigo-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Industrial Zone Burglary Cluster</h4>
                                    <p class="text-sm text-gray-600">Spatial • Medium Confidence • 72% Match</p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-semibold">Emerging</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                            <div class="text-sm">
                                <span class="font-medium text-gray-700">Frequency:</span>
                                <span class="text-gray-600 ml-2">2-3 times per week</span>
                            </div>
                            <div class="text-sm">
                                <span class="font-medium text-gray-700">Locations:</span>
                                <span class="text-gray-600 ml-2">Industrial Zone, Warehouses</span>
                            </div>
                            <div class="text-sm">
                                <span class="font-medium text-gray-700">Risk Level:</span>
                                <span class="text-orange-600 ml-2 font-medium">Medium</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded p-3 mb-3">
                            <p class="text-sm text-gray-700">
                                <strong>Pattern Description:</strong> Cluster of burglaries in industrial zone targeting warehouses during late night hours. Suggests possible insider knowledge or systematic surveillance of targets.
                            </p>
                        </div>
                        <!-- Incident Spatial Sequence -->
                        <div class="bg-white border border-gray-200 rounded p-3 text-xs">
                            <p class="font-semibold text-gray-900 mb-2">Spatial Sequence (Last 2 Weeks):</p>
                            <div class="space-y-1 text-gray-700">
                                <div class="flex items-center gap-2"><span class="text-indigo-600">●</span><strong>Feb 20, 2:30 AM</strong> - Warehouse A (Industrial Zone North)</div>
                                <div class="flex items-center gap-2"><span class="text-indigo-600">→</span><strong>Feb 22, 1:45 AM</strong> - Warehouse C (Industrial Zone South, 0.8 km away)</div>
                                <div class="flex items-center gap-2"><span class="text-indigo-600">→</span><strong>Feb 24, 3:00 AM</strong> - Warehouse B (Industrial Zone Central, 0.5 km away)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Pattern 3: Vehicle Theft Sequence -->
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-pink-300 transition-colors">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-car text-pink-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Vehicle Theft Hotspot Sequence</h4>
                                    <p class="text-sm text-gray-600">Sequential • High Confidence • 91% Match</p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-pink-100 text-pink-700 rounded-full text-xs font-semibold">Critical</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                            <div class="text-sm">
                                <span class="font-medium text-gray-700">Frequency:</span>
                                <span class="text-gray-600 ml-2">Every 3-4 days</span>
                            </div>
                            <div class="text-sm">
                                <span class="font-medium text-gray-700">Locations:</span>
                                <span class="text-gray-600 ml-2">Parking Areas, Residential Streets</span>
                            </div>
                            <div class="text-sm">
                                <span class="font-medium text-gray-700">Risk Level:</span>
                                <span class="text-red-600 ml-2 font-medium">Critical</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded p-3 mb-3">
                            <p class="text-sm text-gray-700">
                                <strong>Pattern Description:</strong> Sequential vehicle theft pattern following specific route and targeting similar vehicle types. High correlation with specific time windows suggests coordinated operation.
                            </p>
                        </div>
                        <!-- Sequential Timeline -->
                        <div class="bg-white border border-gray-200 rounded p-3 text-xs">
                            <p class="font-semibold text-gray-900 mb-2">Sequential Attack Pattern:</p>
                            <div class="space-y-1 text-gray-700">
                                <div class="flex items-center gap-2"><span class="text-pink-600">●</span><strong>Feb 15, 10:15 PM</strong> - Toyota Camry stolen (Residential District A)</div>
                                <div class="flex items-center gap-2"><span class="text-pink-600">→</span><strong>Feb 18, 9:45 PM</strong> - Honda Civic stolen (Parking lot B, 2.1 km away)</div>
                                <div class="flex items-center gap-2"><span class="text-pink-600">→</span><strong>Feb 21, 11:30 PM</strong> - Toyota Corolla stolen (Residential District C, 1.9 km away)</div>
                                <div class="flex items-center gap-2"><span class="text-pink-600">→</span><strong>Feb 23, 10:00 PM</strong> - Honda Accord stolen (Parking lot D, 2.3 km away) [ESTIMATED NEXT]</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pattern Intelligence & Recommendations -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
            <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-brain mr-2" style="color: #274d4c;"></i>
                    Pattern Intelligence & Strategic Recommendations
                </h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Predictive Insights -->
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <h4 class="font-semibold text-purple-900 mb-3">
                            <i class="fas fa-crystal-ball mr-1"></i>Predictive Insights
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-start">
                                <i class="fas fa-arrow-right text-purple-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Weekend pattern likely to continue for next 3 weeks</p>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-arrow-right text-purple-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Industrial activity may expand to adjacent areas</p>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-arrow-right text-purple-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Vehicle theft pattern shows 78% recurrence probability</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tactical Recommendations -->
                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                        <h4 class="font-semibold text-indigo-900 mb-3">
                            <i class="fas fa-chess mr-1"></i>Tactical Actions
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-start">
                                <i class="fas fa-shield-alt text-indigo-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Increase weekend patrols in downtown area</p>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-shield-alt text-indigo-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Set up surveillance in industrial zone</p>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-shield-alt text-indigo-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Deploy bait vehicles in high-risk areas</p>
                            </div>
                        </div>
                    </div>

                    <!-- Resource Optimization -->
                    <div class="bg-pink-50 border border-pink-200 rounded-lg p-4">
                        <h4 class="font-semibold text-pink-900 mb-3">
                            <i class="fas fa-cogs mr-1"></i>Resource Optimization
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-start">
                                <i class="fas fa-users text-pink-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Reallocate 30% units to pattern hotspots</p>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-clock text-pink-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Adjust shift schedules to match patterns</p>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-route text-pink-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Optimize patrol routes based on sequences</p>
                            </div>
                        </div>
                    </div>

                    <!-- Community Engagement -->
                    <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
                        <h4 class="font-semibold text-teal-900 mb-3">
                            <i class="fas fa-users-cog mr-1"></i>Community Strategy
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-start">
                                <i class="fas fa-bullhorn text-teal-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Alert residents about weekend patterns</p>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-handshake text-teal-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Partner with industrial security teams</p>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-eye text-teal-600 mt-1 mr-2 text-xs"></i>
                                <p class="text-sm text-gray-700">Establish neighborhood watch programs</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let temporalPatternChart, patternNetworkChart, frequencyPatternChart, sequencePatternChart, anomalyDetectionChart;

        document.addEventListener('DOMContentLoaded', function() {
            initializeTemporalPatternChart();
            initializePatternNetworkChart();
            initializeFrequencyPatternChart();
            initializeSequencePatternChart();
            initializeAnomalyDetectionChart();
            setupEventListeners();
        });

        function initializeTemporalPatternChart() {
            const ctx = document.getElementById('temporalPatternChart')?.getContext('2d');
            if (!ctx) return;

            const hours = Array.from({length: 24}, (_, i) => `${i}:00`);
            const weekdayData = hours.map(() => Math.floor(Math.random() * 20 + 10));
            const weekendData = hours.map(() => Math.floor(Math.random() * 40 + 20));

            // Enhance weekend evening hours
            for (let i = 20; i <= 23; i++) {
                weekendData[i] = Math.floor(Math.random() * 30 + 50);
            }

            temporalPatternChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: hours,
                    datasets: [{
                        label: 'Weekday Pattern',
                        data: weekdayData,
                        borderColor: '#274d4c',
                        backgroundColor: 'rgba(39, 77, 76, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Weekend Pattern',
                        data: weekendData,
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
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
                            title: {
                                display: true,
                                text: 'Incident Frequency'
                            }
                        }
                    }
                }
            });
        }

        function initializePatternNetworkChart() {
            const ctx = document.getElementById('patternNetworkChart')?.getContext('2d');
            if (!ctx) return;

            // Generate network data
            const patterns = [
                {x: 50, y: 50, r: 20, label: 'Theft Cluster'},
                {x: 30, y: 30, r: 15, label: 'Burglary Pattern'},
                {x: 70, y: 30, r: 12, label: 'Vehicle Theft'},
                {x: 30, y: 70, r: 10, label: 'Vandalism'},
                {x: 70, y: 70, r: 8, label: 'Assault Pattern'},
                {x: 50, y: 20, r: 6, label: 'Drug Related'},
                {x: 20, y: 50, r: 6, label: 'Fraud Pattern'}
            ];

            patternNetworkChart = new Chart(ctx, {
                type: 'bubble',
                data: {
                    datasets: [{
                        label: 'Pattern Connections',
                        data: patterns,
                        backgroundColor: function(context) {
                            const colors = ['#8b5cf6', '#6366f1', '#ec4899', '#f43f5e', '#f59e0b'];
                            return colors[context.dataIndex % colors.length];
                        },
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.raw.label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    }
                }
            });
        }

        function initializeFrequencyPatternChart() {
            const ctx = document.getElementById('frequencyPatternChart')?.getContext('2d');
            if (!ctx) return;

            frequencyPatternChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Pattern Frequency',
                        data: [12, 15, 18, 14, 25, 42, 38],
                        backgroundColor: function(context) {
                            const value = context.raw;
                            if (value > 35) return '#8b5cf6';
                            if (value > 25) return '#6366f1';
                            return '#a78bfa';
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
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        function initializeSequencePatternChart() {
            const ctx = document.getElementById('sequencePatternChart')?.getContext('2d');
            if (!ctx) return;

            sequencePatternChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Step 1', 'Step 2', 'Step 3', 'Step 4', 'Step 5', 'Step 6'],
                    datasets: [{
                        label: 'Common Sequence',
                        data: [85, 72, 68, 45, 32, 15],
                        borderColor: '#ec4899',
                        backgroundColor: 'rgba(236, 72, 153, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Alternative Sequence',
                        data: [45, 68, 85, 72, 50, 25],
                        borderColor: '#f43f5e',
                        backgroundColor: 'rgba(244, 63, 94, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
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
                                text: 'Probability (%)'
                            }
                        }
                    }
                }
            });
        }

        function initializeAnomalyDetectionChart() {
            const ctx = document.getElementById('anomalyDetectionChart')?.getContext('2d');
            if (!ctx) return;

            const labels = Array.from({length: 30}, (_, i) => `Day ${i + 1}`);
            const normalData = labels.map(() => Math.floor(Math.random() * 20 + 30));
            const anomalyData = labels.map(() => null);

            // Insert some anomalies
            anomalyData[5] = 85;
            anomalyData[12] = 78;
            anomalyData[18] = 92;
            anomalyData[25] = 71;

            anomalyDetectionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Normal Activity',
                        data: normalData,
                        borderColor: '#274d4c',
                        backgroundColor: 'rgba(39, 77, 76, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Anomalies',
                        data: anomalyData,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        pointRadius: 8,
                        pointHoverRadius: 10,
                        showLine: false
                    }]
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
                                text: 'Activity Level'
                            }
                        }
                    }
                }
            });
        }

        function setupEventListeners() {
            document.getElementById('analysisType').addEventListener('change', runPatternDetection);
            document.getElementById('timeRange').addEventListener('change', runPatternDetection);
            document.getElementById('sensitivity').addEventListener('change', runPatternDetection);
            document.getElementById('patternCrimeType').addEventListener('change', runPatternDetection);
        }

        function runPatternDetection() {
            // Simulate pattern detection
            const analysisType = document.getElementById('analysisType').value;
            const sensitivity = document.getElementById('sensitivity').value;
            
            // Update statistics based on analysis
            let patternsFound = Math.floor(Math.random() * 20 + 15);
            let anomaliesDetected = Math.floor(Math.random() * 8 + 3);
            let correlations = Math.floor(Math.random() * 10 + 8);
            let accuracy = Math.floor(Math.random() * 10 + 85);
            
            if (sensitivity === 'high') {
                patternsFound += 10;
                anomaliesDetected += 3;
                accuracy -= 5;
            }
            
            document.getElementById('patternsFound').textContent = patternsFound;
            document.getElementById('anomaliesDetected').textContent = anomaliesDetected;
            document.getElementById('correlations').textContent = correlations;
            document.getElementById('detectionAccuracy').textContent = accuracy + '%';
            
            // Refresh charts
            refreshCharts();
        }

        function refreshCharts() {
            // Simulate chart refresh
            if (temporalPatternChart) temporalPatternChart.update();
            if (patternNetworkChart) patternNetworkChart.update();
            if (frequencyPatternChart) frequencyPatternChart.update();
            if (sequencePatternChart) sequencePatternChart.update();
            if (anomalyDetectionChart) anomalyDetectionChart.update();
        }

        function exportPatterns() {
            alert('Pattern detection report exported successfully!');
        }

        function comparePatterns() {
            alert('Pattern comparison feature coming soon!');
        }
    </script>
@endsection
