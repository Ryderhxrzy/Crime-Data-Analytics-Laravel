@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

@extends('layouts.app')
@section('title', 'Pattern Detection')
@section('content')
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.7; }
            50% { transform: scale(1.2); opacity: 0.4; }
            100% { transform: scale(1); opacity: 0.7; }
        }
        
        .custom-marker, .chain-marker, .simulation-marker {
            background: transparent !important;
            border: none !important;
        }
        
        #crimeMap {
            z-index: 1;
        }
    </style>
    
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

        <!-- Analysis Type Selection -->
        <div class="bg-white rounded-xl p-6 mb-6 border border-gray-200">
            <div class="mb-4 pb-4 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-900">
                    <i class="fas fa-chart-line mr-2 text-alertara-700"></i>Analysis Type
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="analysis-type-card border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-alertara-500 transition" data-type="temporal">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-clock text-2xl text-alertara-600 mr-3"></i>
                        <h4 class="font-semibold text-gray-900">Temporal Analysis</h4>
                    </div>
                    <p class="text-sm text-gray-600">Detect time-based patterns and trends in crime data</p>
                </div>
                <div class="analysis-type-card border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-alertara-500 transition" data-type="spatial">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-map-marker-alt text-2xl text-alertara-600 mr-3"></i>
                        <h4 class="font-semibold text-gray-900">Spatial Analysis</h4>
                    </div>
                    <p class="text-sm text-gray-600">Identify geographic hotspots and location patterns</p>
                </div>
                <div class="analysis-type-card border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-alertara-500 transition" data-type="predictive">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-crystal-ball text-2xl text-alertara-600 mr-3"></i>
                        <h4 class="font-semibold text-gray-900">Predictive Analysis</h4>
                    </div>
                    <p class="text-sm text-gray-600">Forecast future crime trends and patterns</p>
                </div>
            </div>
        </div>

        <!-- Standardized Filter Section -->
        <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
            <div class="mb-4 pb-4 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-900">
                    <i class="fas fa-filter mr-2 text-alertara-700"></i>Customize Your Search
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <!-- Analysis Type Hidden (selected via cards) -->
                <input type="hidden" id="analysisType" value="temporal">

                <!-- Crime Category -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Crime Type</label>
                    <select id="patternCrimeType" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Types</option>
                        @foreach($crimeCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Specific Date -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Start Date</label>
                    <input type="date" id="startDate" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                </div>

                <!-- End Date -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">End Date</label>
                    <input type="date" id="endDate" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                </div>

                <!-- Day of Week s-->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Day of Week</label>
                    <select id="dayOfWeek" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Days</option>
                        <option value="monday">Monday</option>
                        <option value="tuesday">Tuesday</option>
                        <option value="wednesday">Wednesday</option>
                        <option value="thursday">Thursday</option>
                        <option value="friday">Friday</option>
                        <option value="saturday">Saturday</option>
                        <option value="sunday">Sunday</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Time of Day</label>
                    <select id="timeOfDay" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="">All Hours</option>
                        <option value="morning">Morning (5AM - 12PM)</option>
                        <option value="afternoon">Afternoon (12PM - 5PM)</option>
                        <option value="evening">Evening (5PM - 9PM)</option>
                        <option value="night">Night (9PM - 5AM)</option>
                    </select>
                </div>

                <!-- Location/Barangay -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Location</label>
                    <input type="text" id="location" placeholder="Enter barangay or area" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                </div>

                <!-- Sensitivity Level -->
                <div>
                    <label class="block text-sm font-medium text-alertara-800 mb-2">Sensitivity</label>
                    <select id="sensitivity" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                        <option value="low">Low - Broad Patterns</option>
                        <option value="medium" selected>Medium - Balanced</option>
                        <option value="high">High - Specific Patterns</option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-4 flex gap-2 justify-end">
                <button id="resetFilters" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                    <i class="fas fa-redo mr-2"></i>Reset
                </button>
                <button id="applyFilters" class="px-4 py-2 bg-alertara-700 hover:bg-alertara-800 text-white rounded-lg font-medium transition">
                    <i class="fas fa-search mr-2"></i>Analyze
                </button>
                <button id="runSimulation" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">
                    <i class="fas fa-play mr-2"></i>Run Simulation
                </button>
            </div>
        </div>

        <!-- Simulation Results Section -->
        <div id="simulationResults" class="bg-white rounded-xl p-6 mb-6 border border-gray-200 hidden">
            <div class="mb-4 pb-4 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-900">
                    <i class="fas fa-chart-bar mr-2 text-alertara-700"></i>Simulation Results
                </h3>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Predictions -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-900 mb-3">
                        <i class="fas fa-crystal-ball mr-2 text-blue-600"></i>Predictions
                    </h4>
                    <div id="predictionsList" class="space-y-2">
                        <!-- Predictions will be populated here -->
                    </div>
                </div>
                <!-- Recommendations -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-900 mb-3">
                        <i class="fas fa-lightbulb mr-2 text-yellow-600"></i>Recommendations
                    </h4>
                    <div id="recommendationsList" class="space-y-2">
                        <!-- Recommendations will be populated here -->
                    </div>
                </div>
            </div>
            <!-- Simulation Chart -->
            <div class="mt-6">
                <h4 class="font-semibold text-gray-900 mb-3">
                    <i class="fas fa-chart-line mr-2 text-green-600"></i>Trend Analysis
                </h4>
                <div class="bg-gray-50 rounded-lg p-4 h-64 flex items-center justify-center">
                    <canvas id="simulationChart" class="w-full h-full"></canvas>
                </div>
            </div>
        </div>

        <!-- Analysis Results Section -->
        <div id="analysisResults" class="bg-white rounded-xl p-6 mb-6 border border-gray-200 hidden">
            <div class="mb-4 pb-4 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-900">
                    <i class="fas fa-magnifying-glass mr-2 text-alertara-700"></i>Analysis Results
                </h3>
            </div>
            <div id="patternsList" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Pattern results will be populated here -->
            </div>
        </div>

        <!-- Map Visualization Section -->
        <div class="bg-white rounded-xl p-6 mb-6 border border-gray-200">
            <div class="mb-4 pb-4 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gray-900">
                    <i class="fas fa-map mr-2 text-alertara-700"></i>Crime Map Visualization - Quezon City
                </h3>
            </div>
            
            <!-- Map Controls -->
            <div class="mb-4 flex flex-wrap gap-2">
                <button id="showCurrentHotspots" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition">
                    <i class="fas fa-fire mr-1"></i>Current Hotspots
                </button>
                <button id="showPredictedHotspots" class="px-3 py-1 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm font-medium transition">
                    <i class="fas fa-chart-line mr-1"></i>Predicted Hotspots
                </button>
                <button id="showCrimeChains" class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition">
                    <i class="fas fa-link mr-1"></i>Crime Chains
                </button>
                <button id="toggleSimulation" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition">
                    <i class="fas fa-play mr-1"></i>Simulation
                </button>
            </div>

            <!-- Map Container -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Map -->
                <div class="lg:col-span-2">
                    <div id="crimeMap" class="h-96 rounded-lg border border-gray-300"></div>
                </div>
                
                <!-- Crime Chain Panel -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-900 mb-3">
                        <i class="fas fa-link mr-2 text-purple-600"></i>Crime Chains
                    </h4>
                    <div id="crimeChainsList" class="space-y-3">
                        <!-- Crime chains will be populated here -->
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-4 flex flex-wrap gap-4 text-sm">
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-red-500 rounded-full mr-2"></div>
                    <span>Theft</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-orange-500 rounded-full mr-2"></div>
                    <span>Robbery</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-yellow-500 rounded-full mr-2"></div>
                    <span>Assault</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-purple-500 rounded-full mr-2"></div>
                    <span>Crime Chain</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-blue-300 rounded-full mr-2 opacity-50"></div>
                    <span>Predicted Area</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            let map;
            let markers = [];
            let crimeChains = [];
            let simulationActive = false;
            
            function initializeMap() {
                // Initialize map centered on Quezon City
                map = L.map('crimeMap').setView([14.6760, 121.0437], 13);
                
                // Add OpenStreetMap tiles
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                
                // Add sample crime data
                addSampleCrimeData();
                addCrimeChains();
            }
            
            // Sample crime data for Quezon City
            function addSampleCrimeData() {
                const crimeData = [
                    // Commonwealth Area
                    { lat: 14.7489, lng: 121.0495, type: 'theft', description: 'Theft - Commonwealth Ave', severity: 'high' },
                    { lat: 14.7501, lng: 121.0512, type: 'theft', description: 'Theft - near SM Fairview', severity: 'high' },
                    { lat: 14.7523, lng: 121.0487, type: 'theft', description: 'Theft - Commonwealth Market', severity: 'high' },
                    { lat: 14.7515, lng: 121.0528, type: 'robbery', description: 'Robbery - Commonwealth', severity: 'critical' },
                    
                    // Cubao Area
                    { lat: 14.6121, lng: 121.0487, type: 'assault', description: 'Assault - Cubao', severity: 'medium' },
                    { lat: 14.6134, lng: 121.0501, type: 'robbery', description: 'Robbery - Ali Mall', severity: 'high' },
                    { lat: 14.6109, lng: 121.0472, type: 'theft', description: 'Theft - Cubao Station', severity: 'medium' },
                    
                    // UP Diliman Area
                    { lat: 14.6539, lng: 121.0684, type: 'theft', description: 'Theft - UP Diliman', severity: 'low' },
                    { lat: 14.6551, lng: 121.0692, type: 'assault', description: 'Assault - UP Village', severity: 'medium' },
                    
                    // Eastwood Area
                    { lat: 14.6084, lng: 121.0966, type: 'robbery', description: 'Robbery - Eastwood', severity: 'high' },
                    { lat: 14.6072, lng: 121.0951, type: 'theft', description: 'Theft - Eastwood Mall', severity: 'medium' }
                ];
                
                crimeData.forEach(crime => {
                    const color = getCrimeColor(crime.type);
                    const icon = L.divIcon({
                        className: 'custom-marker',
                        html: `<div style="background-color: ${color}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
                        iconSize: [20, 20]
                    });
                    
                    const marker = L.marker([crime.lat, crime.lng], { icon: icon })
                        .addTo(map)
                        .bindPopup(`
                            <div class="p-2">
                                <strong>${crime.description}</strong><br>
                                <small>Type: ${crime.type}</small><br>
                                <small>Severity: ${crime.severity}</small>
                            </div>
                        `);
                    
                    markers.push({ marker, type: crime.type, data: crime });
                });
            }
            
            // Add crime chains visualization
            function addCrimeChains() {
                // Commonwealth Area Crime Chain
                const commonwealthChain = [
                    { lat: 14.7489, lng: 121.0495, description: 'Point A: Theft Incident 1' },
                    { lat: 14.7501, lng: 121.0512, description: 'Point B: Theft Incident 2' },
                    { lat: 14.7515, lng: 121.0528, description: 'Point C: Robbery Incident' }
                ];
                
                // Draw chain line
                const chainLine = L.polyline(
                    commonwealthChain.map(point => [point.lat, point.lng]),
                    { color: 'purple', weight: 3, opacity: 0.7, dashArray: '10, 5' }
                ).addTo(map);
                
                // Add chain markers
                commonwealthChain.forEach((point, index) => {
                    const chainIcon = L.divIcon({
                        className: 'chain-marker',
                        html: `<div style="background-color: purple; width: 25px; height: 25px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">${String.fromCharCode(65 + index)}</div>`,
                        iconSize: [25, 25]
                    });
                    
                    L.marker([point.lat, point.lng], { icon: chainIcon })
                        .addTo(map)
                        .bindPopup(`
                            <div class="p-2">
                                <strong>${point.description}</strong><br>
                                <small>Crime Chain Connection</small>
                            </div>
                        `);
                });
                
                // Update crime chains panel
                updateCrimeChainsPanel();
            }
            
            // Update crime chains panel
            function updateCrimeChainsPanel() {
                const chainsList = document.getElementById('crimeChainsList');
                chainsList.innerHTML = `
                    <div class="bg-white rounded-lg p-3 border-l-4 border-purple-500">
                        <h5 class="font-semibold text-sm text-gray-900 mb-2">Commonwealth Area Chain</h5>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div class="flex items-center">
                                <span class="w-4 h-4 bg-purple-500 rounded-full text-white text-xs flex items-center justify-center mr-2">A</span>
                                <span>Theft Incident 1</span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-4 h-4 bg-purple-500 rounded-full text-white text-xs flex items-center justify-center mr-2">B</span>
                                <span>Theft Incident 2</span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-4 h-4 bg-purple-500 rounded-full text-white text-xs flex items-center justify-center mr-2">C</span>
                                <span>Robbery Incident</span>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-orange-600">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Pattern: Multiple theft incidents leading to robbery
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg p-3 border-l-4 border-orange-500">
                        <h5 class="font-semibold text-sm text-gray-900 mb-2">Pattern Analysis</h5>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div>• 3 theft incidents within 24 hours</div>
                            <div>• Same geographic area</div>
                            <div>• Escalation to robbery</div>
                            <div>• Possible organized activity</div>
                        </div>
                    </div>
                `;
            }
            
            // Get crime color by type
            function getCrimeColor(type) {
                const colors = {
                    'theft': '#ef4444',      // red
                    'robbery': '#f97316',    // orange
                    'assault': '#eab308'     // yellow
                };
                return colors[type] || '#6b7280';
            }
            
            // Map control functions
            document.getElementById('showCurrentHotspots').addEventListener('click', function() {
                // Reset map view to show all current crime data
                map.setView([14.6760, 121.0437], 13);
                this.classList.add('ring-2', 'ring-red-300');
                setTimeout(() => this.classList.remove('ring-2', 'ring-red-300'), 1000);
            });
            
            document.getElementById('showPredictedHotspots').addEventListener('click', function() {
                // Show predicted expansion areas
                showPredictedAreas();
                this.classList.add('ring-2', 'ring-orange-300');
                setTimeout(() => this.classList.remove('ring-2', 'ring-orange-300'), 1000);
            });
            
            document.getElementById('showCrimeChains').addEventListener('click', function() {
                // Focus on crime chains
                map.setView([14.7507, 121.0510], 15);
                this.classList.add('ring-2', 'ring-purple-300');
                setTimeout(() => this.classList.remove('ring-2', 'ring-purple-300'), 1000);
            });
            
            document.getElementById('toggleSimulation').addEventListener('click', function() {
                simulationActive = !simulationActive;
                if (simulationActive) {
                    this.innerHTML = '<i class="fas fa-pause mr-1"></i>Stop Simulation';
                    this.classList.remove('bg-green-600', 'hover:bg-green-700');
                    this.classList.add('bg-red-600', 'hover:bg-red-700');
                    startSimulation();
                } else {
                    this.innerHTML = '<i class="fas fa-play mr-1"></i>Simulation';
                    this.classList.remove('bg-red-600', 'hover:bg-red-700');
                    this.classList.add('bg-green-600', 'hover:bg-green-700');
                    stopSimulation();
                }
            });
            
            // Show predicted expansion areas
            function showPredictedAreas() {
                // Clear existing prediction layers
                map.eachLayer(layer => {
                    if (layer instanceof L.Circle && layer.options.isPrediction) {
                        map.removeLayer(layer);
                    }
                });
                
                // Add prediction circles around hotspots
                const hotspots = [
                    { lat: 14.7507, lng: 121.0510, radius: 800 }, // Commonwealth
                    { lat: 14.6121, lng: 121.0487, radius: 600 }, // Cubao
                    { lat: 14.6084, lng: 121.0966, radius: 500 }  // Eastwood
                ];
                
                hotspots.forEach(hotspot => {
                    L.circle([hotspot.lat, hotspot.lng], {
                        color: '#3b82f6',
                        fillColor: '#3b82f6',
                        fillOpacity: 0.2,
                        radius: hotspot.radius,
                        isPrediction: true
                    }).addTo(map).bindPopup('Predicted expansion area if theft increases by 10%');
                });
            }
            
            // Simulation functions
            let simulationInterval;
            function startSimulation() {
                let step = 0;
                simulationInterval = setInterval(() => {
                    if (step >= 5) {
                        stopSimulation();
                        return;
                    }
                    
                    // Simulate crime spread
                    simulateCrimeSpread(step);
                    step++;
                }, 2000);
            }
            
            function stopSimulation() {
                if (simulationInterval) {
                    clearInterval(simulationInterval);
                }
                simulationActive = false;
                const btn = document.getElementById('toggleSimulation');
                btn.innerHTML = '<i class="fas fa-play mr-1"></i>Simulation';
                btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
            }
            
            function simulateCrimeSpread(step) {
                // Add temporary markers to show spread
                const spreadPoints = [
                    { lat: 14.7489 + (step * 0.002), lng: 121.0495 + (step * 0.002) },
                    { lat: 14.7501 + (step * 0.001), lng: 121.0512 + (step * 0.001) }
                ];
                
                spreadPoints.forEach(point => {
                    const tempIcon = L.divIcon({
                        className: 'simulation-marker',
                        html: `<div style="background-color: #3b82f6; width: 15px; height: 15px; border-radius: 50%; border: 2px solid white; opacity: 0.7; animation: pulse 1s infinite;"></div>`,
                        iconSize: [15, 15]
                    });
                    
                    const tempMarker = L.marker([point.lat, point.lng], { icon: tempIcon })
                        .addTo(map)
                        .bindPopup('Predicted crime spread');
                    
                    // Remove after animation
                    setTimeout(() => map.removeLayer(tempMarker), 1500);
                });
            }
            
            // Initialize map when page loads
            initializeMap();
            // Analysis type card selection
            const analysisCards = document.querySelectorAll('.analysis-type-card');
            analysisCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove active state from all cards
                    analysisCards.forEach(c => {
                        c.classList.remove('border-alertara-500', 'bg-alertara-50');
                        c.classList.add('border-gray-200');
                    });
                    
                    // Add active state to selected card
                    this.classList.remove('border-gray-200');
                    this.classList.add('border-alertara-500', 'bg-alertara-50');
                    
                    // Update hidden input
                    document.getElementById('analysisType').value = this.dataset.type;
                });
            });

            // Set default active card
            document.querySelector('[data-type="temporal"]').click();

            // Reset filters button
            document.getElementById('resetFilters').addEventListener('click', function() {
                document.getElementById('analysisType').value = 'temporal';
                document.getElementById('patternCrimeType').value = '';
                document.getElementById('startDate').value = '';
                document.getElementById('endDate').value = '';
                document.getElementById('dayOfWeek').value = '';
                document.getElementById('timeOfDay').value = '';
                document.getElementById('location').value = '';
                document.getElementById('sensitivity').value = 'medium';
                
                // Reset analysis type selection
                document.querySelector('[data-type="temporal"]').click();
                
                // Hide results
                document.getElementById('analysisResults').classList.add('hidden');
                document.getElementById('simulationResults').classList.add('hidden');
            });

            // Apply filters button
            document.getElementById('applyFilters').addEventListener('click', function() {
                const filters = {
                    analysisType: document.getElementById('analysisType').value,
                    crimeType: document.getElementById('patternCrimeType').value,
                    startDate: document.getElementById('startDate').value,
                    endDate: document.getElementById('endDate').value,
                    dayOfWeek: document.getElementById('dayOfWeek').value,
                    timeOfDay: document.getElementById('timeOfDay').value,
                    location: document.getElementById('location').value,
                    sensitivity: document.getElementById('sensitivity').value
                };

                console.log('Pattern Detection Filters Applied:', filters);
                performAnalysis(filters);
            });

            // Run simulation button
            document.getElementById('runSimulation').addEventListener('click', function() {
                const filters = {
                    analysisType: document.getElementById('analysisType').value,
                    crimeType: document.getElementById('patternCrimeType').value,
                    startDate: document.getElementById('startDate').value,
                    endDate: document.getElementById('endDate').value,
                    location: document.getElementById('location').value
                };

                console.log('Running Simulation with filters:', filters);
                runSimulation(filters);
            });

            // Perform analysis function
            function performAnalysis(filters) {
                const resultsDiv = document.getElementById('analysisResults');
                const patternsList = document.getElementById('patternsList');
                
                // Sample patterns based on analysis type
                let patterns = [];
                
                if (filters.analysisType === 'temporal') {
                    patterns = [
                        {
                            title: 'Weekend Peak Pattern',
                            description: 'Crime rates increase by 35% during Friday-Saturday nights',
                            confidence: 87,
                            icon: 'fa-calendar-week'
                        },
                        {
                            title: 'Monthly Cycle',
                            description: 'Notable spike in incidents during mid-month periods',
                            confidence: 72,
                            icon: 'fa-calendar-alt'
                        }
                    ];
                } else if (filters.analysisType === 'spatial') {
                    patterns = [
                        {
                            title: 'Downtown Hotspot',
                            description: 'High concentration of incidents in commercial district',
                            confidence: 91,
                            icon: 'fa-map-marker-alt'
                        },
                        {
                            title: 'Transportation Corridor',
                            description: 'Pattern along main highway and bus routes',
                            confidence: 78,
                            icon: 'fa-road'
                        }
                    ];
                } else if (filters.analysisType === 'predictive') {
                    patterns = [
                        {
                            title: ' Rising Trend Alert',
                            description: '15% increase expected in next 30 days based on current patterns',
                            confidence: 82,
                            icon: 'fa-chart-line'
                        },
                        {
                            title: 'Seasonal Pattern',
                            description: 'Historical data suggests upcoming seasonal variation',
                            confidence: 76,
                            icon: 'fa-snowflake'
                        }
                    ];
                }

                // Display patterns
                patternsList.innerHTML = patterns.map(pattern => `
                    <div class="bg-gray-50 rounded-lg p-4 border-l-4 border-alertara-500">
                        <div class="flex items-start">
                            <i class="fas ${pattern.icon} text-alertara-600 mt-1 mr-3"></i>
                            <div class="flex-1">
                                <h5 class="font-semibold text-gray-900">${pattern.title}</h5>
                                <p class="text-sm text-gray-600 mt-1">${pattern.description}</p>
                                <div class="mt-2">
                                    <span class="text-xs bg-alertara-100 text-alertara-800 px-2 py-1 rounded-full">
                                        ${pattern.confidence}% Confidence
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');

                resultsDiv.classList.remove('hidden');
            }

            // Run simulation function
            function runSimulation(filters) {
                const resultsDiv = document.getElementById('simulationResults');
                const predictionsList = document.getElementById('predictionsList');
                const recommendationsList = document.getElementById('recommendationsList');
                
                // Sample predictions
                const predictions = [
                    {
                        type: 'warning',
                        text: 'If current trend continues, crime rate may increase by 12% next month',
                        impact: 'high'
                    },
                    {
                        type: 'info',
                        text: 'Historical patterns suggest 8% decrease during upcoming holiday period',
                        impact: 'medium'
                    },
                    {
                        type: 'success',
                        text: 'Increased police patrols could reduce incidents by up to 25%',
                        impact: 'high'
                    }
                ];

                // Sample recommendations
                const recommendations = [
                    {
                        priority: 'urgent',
                        text: 'Deploy additional mobile patrols to identified hotspot areas',
                        icon: 'fa-car'
                    },
                    {
                        priority: 'medium',
                        text: 'Increase surveillance during peak hours (7PM - 11PM)',
                        icon: 'fa-video'
                    },
                    {
                        priority: 'low',
                        text: 'Community outreach programs in vulnerable neighborhoods',
                        icon: 'fa-users'
                    }
                ];

                // Display predictions
                predictionsList.innerHTML = predictions.map(pred => {
                    const bgColor = pred.type === 'warning' ? 'bg-red-100 text-red-800' : 
                                   pred.type === 'success' ? 'bg-green-100 text-green-800' : 
                                   'bg-blue-100 text-blue-800';
                    return `
                        <div class="flex items-center p-3 ${bgColor} rounded-lg">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="text-sm">${pred.text}</span>
                        </div>
                    `;
                }).join('');

                // Display recommendations
                recommendationsList.innerHTML = recommendations.map(rec => {
                    const priorityColor = rec.priority === 'urgent' ? 'border-red-500' : 
                                        rec.priority === 'medium' ? 'border-yellow-500' : 
                                        'border-green-500';
                    return `
                        <div class="flex items-center p-3 bg-white border-l-4 ${priorityColor} rounded">
                            <i class="fas ${rec.icon} mr-3 text-gray-600"></i>
                            <span class="text-sm">${rec.text}</span>
                        </div>
                    `;
                }).join('');

                // Draw sample chart
                drawSimulationChart();
                
                resultsDiv.classList.remove('hidden');
            }

            // Draw simulation chart
            function drawSimulationChart() {
                const canvas = document.getElementById('simulationChart');
                const ctx = canvas.getContext('2d');
                
                // Simple line chart simulation
                canvas.width = canvas.offsetWidth;
                canvas.height = canvas.offsetHeight;
                
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // Sample data points
                const data = [30, 35, 32, 38, 42, 45, 48, 52, 49, 46, 43, 40];
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                
                const padding = 40;
                const chartWidth = canvas.width - padding * 2;
                const chartHeight = canvas.height - padding * 2;
                const maxValue = Math.max(...data);
                
                // Draw axes
                ctx.strokeStyle = '#e5e7eb';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(padding, padding);
                ctx.lineTo(padding, canvas.height - padding);
                ctx.lineTo(canvas.width - padding, canvas.height - padding);
                ctx.stroke();
                
                // Draw data line
                ctx.strokeStyle = '#274d4c';
                ctx.lineWidth = 2;
                ctx.beginPath();
                
                data.forEach((value, index) => {
                    const x = padding + (index / (data.length - 1)) * chartWidth;
                    const y = canvas.height - padding - (value / maxValue) * chartHeight;
                    
                    if (index === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });
                
                ctx.stroke();
                
                // Draw data points
                data.forEach((value, index) => {
                    const x = padding + (index / (data.length - 1)) * chartWidth;
                    const y = canvas.height - padding - (value / maxValue) * chartHeight;
                    
                    ctx.fillStyle = '#274d4c';
                    ctx.beginPath();
                    ctx.arc(x, y, 4, 0, 2 * Math.PI);
                    ctx.fill();
                });
            }

            // Set default date range (last 30 days)
            const today = new Date().toISOString().split('T')[0];
            const thirtyDaysAgo = new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0];
            document.getElementById('startDate').value = thirtyDaysAgo;
            document.getElementById('endDate').value = today;
        });
    </script>
@endsection
