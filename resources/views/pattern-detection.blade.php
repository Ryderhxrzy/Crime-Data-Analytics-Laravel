@extends('layouts.app')

@section('title', 'Pattern Detection Simulation')

@section('content')
<div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12" id="simulationApp">
    <!-- Page Header & Mode Selection -->
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Pattern Detection Simulation</h1>
                <p class="text-gray-600 mt-1 text-sm lg:text-base">Run "What-If" scenarios to predict crime reduction through strategic interventions.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 bg-gray-50 p-3 rounded-xl border border-gray-100">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-gray-400 uppercase mb-1">Simulation Mode</span>
                    <select id="simMode" class="bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-sm font-semibold text-blue-700 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm transition-all cursor-pointer">
                        <option value="historical">Historical Signature</option>
                        <option value="predictive" selected>AI Predictive Model</option>
                        <option value="randomized">Randomized Stress Test</option>
                    </select>
                </div>
                <div class="h-10 w-[1px] bg-gray-200 hidden sm:block"></div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                        </span>
                        <span class="text-xs font-medium text-gray-600" id="simStatus">Engine Ready</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Sidebar: Intervention Controls -->
        <aside class="w-full lg:w-80 flex-shrink-0 space-y-6">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden lg:sticky lg:top-20 transition-all hover:shadow-md">
                <div class="p-4 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between">
                    <h2 class="font-bold text-gray-900 flex items-center gap-2 text-sm">
                        <i class="fas fa-tools text-blue-600"></i> What-If Interventions
                    </h2>
                    <button id="resetInterventions" class="text-[10px] bg-white border border-gray-200 px-2 py-1 rounded hover:bg-gray-50 transition-colors text-gray-500 font-bold uppercase tracking-tighter">Reset</button>
                </div>
                
                <div class="p-5 space-y-6">
                    <!-- Police Patrol Level -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Police Patrol Level</label>
                            <span id="patrolLabel" class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-100">Medium</span>
                        </div>
                        <div class="relative pt-1">
                            <input type="range" id="patrolSlider" min="0" max="2" value="1" step="1" class="w-full h-1.5 bg-gray-100 rounded-lg appearance-none cursor-pointer accent-blue-600">
                            <div class="flex justify-between text-[10px] text-gray-400 mt-2 px-1">
                                <span>Low</span>
                                <span>Medium</span>
                                <span>High</span>
                            </div>
                        </div>
                    </div>

                    <!-- CCTV Coverage -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">CCTV Infrastructure</label>
                        </div>
                        <select id="cctvSelect" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                            <option value="none">No Coverage</option>
                            <option value="partial">Partial (Strategic Points)</option>
                            <option value="full" selected>Full (High Density)</option>
                            <option value="custom">-- Add Custom Value --</option>
                        </select>
                        <!-- Custom CCTV Input (Hidden by default) -->
                        <div id="customCctvEntry" class="hidden animate-fade-in">
                            <div class="relative">
                                <input type="number" id="customCctvValue" placeholder="E.g. 5 New CCTV Units" class="w-full pl-3 pr-10 py-2 border border-blue-200 rounded-xl text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400">Units</span>
                            </div>
                        </div>
                    </div>

                    <!-- Environment Toggles -->
                    <div class="space-y-3">
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-3">Environmental Safety</label>
                        
                        <!-- Street Lighting -->
                        <label class="flex items-center justify-between p-3 bg-gray-50/50 border border-gray-100 rounded-xl cursor-pointer hover:bg-white hover:border-blue-200 transition-all group">
                            <span class="flex flex-col">
                                <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-700">Street Lighting</span>
                                <span class="text-[10px] text-gray-400">Reduce concealment spots</span>
                            </span>
                            <div class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="lightingToggle" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </div>
                        </label>

                        <!-- Community Watch -->
                        <label class="flex items-center justify-between p-3 bg-gray-50/50 border border-gray-100 rounded-xl cursor-pointer hover:bg-white hover:border-blue-200 transition-all group">
                            <span class="flex flex-col">
                                <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-700">Community Watch</span>
                                <span class="text-[10px] text-gray-400">Resident vigilance programs</span>
                            </span>
                            <div class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="communityToggle" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </div>
                        </label>

                        <!-- Restricted Access -->
                        <label class="flex items-center justify-between p-3 bg-gray-50/50 border border-gray-100 rounded-xl cursor-pointer hover:bg-white hover:border-blue-200 transition-all group">
                            <span class="flex flex-col">
                                <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-700">Checkpoints</span>
                                <span class="text-[10px] text-gray-400">Entry/Exit monitoring</span>
                            </span>
                            <div class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="accessToggle" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </div>
                        </label>
                    </div>

                    <button id="runSimulation" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-lg shadow-blue-200 transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2 mt-4 overflow-hidden relative group">
                        <span class="absolute inset-0 w-full h-full bg-white opacity-0 group-hover:opacity-10 transition-opacity"></span>
                        <i class="fas fa-play text-xs" id="runIcon"></i> 
                        <span id="runText">Run Simulation</span>
                    </button>
                </div>
            </div>
            
            <!-- Quick Insights Card -->
            <div class="bg-gradient-to-br from-gray-900 to-gray-800 p-6 rounded-2xl border border-gray-700 shadow-xl text-white">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Real-time Metrics</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-300">Total Simulated Crimes</span>
                        <span id="metricTotal" class="text-xl font-bold text-blue-400 animate-pulse">48</span>
                    </div>
                    <div class="w-full bg-gray-700 h-1.5 rounded-full overflow-hidden">
                        <div id="metricTotalBar" class="bg-blue-400 h-full w-[48%] transition-all duration-700 overflow-hidden"></div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-300">Hotspots Detected</span>
                        <span id="metricHotspots" class="text-xl font-bold text-amber-400">4</span>
                    </div>
                    <div class="w-full bg-gray-700 h-1.5 rounded-full overflow-hidden">
                        <div id="metricHotspotsBar" class="bg-amber-400 h-full w-[25%] transition-all duration-700 overflow-hidden"></div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-300">High-Risk Coverage</span>
                        <span id="metricRisk" class="text-xl font-bold text-red-500">22%</span>
                    </div>
                    <div class="w-full bg-gray-700 h-1.5 rounded-full overflow-hidden">
                        <div id="metricRiskBar" class="bg-red-500 h-full w-[22%] transition-all duration-700 overflow-hidden"></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Display: Map & Outputs -->
        <div class="flex-grow space-y-6">
            <!-- Applied Filters Badge Section -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm min-h-[60px] flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-bold text-gray-400 uppercase mr-2 tracking-widest">Active Filters:</span>
                <div id="activeFilters" class="flex flex-wrap gap-2">
                    <!-- Badges populated by JS -->
                    <span class="px-3 py-1 bg-blue-50 text-blue-700 border border-blue-100 rounded-full text-[10px] font-bold flex items-center gap-2">
                        <i class="fas fa-robot text-[8px]"></i> Predictive Mode
                    </span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-600 border border-gray-200 rounded-full text-[10px] font-bold flex items-center gap-2">
                        <i class="fas fa-lightbulb text-[8px]"></i> Street Lighting ON
                    </span>
                </div>
                <div id="noFilters" class="hidden text-xs text-gray-400 italic font-medium">No active intervention filters applied.</div>
            </div>

            <!-- Visualization Section -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col h-[650px] relative">
                <!-- Map Controls Overlay -->
                <div class="absolute top-4 left-4 z-10 flex flex-col gap-2">
                    <button class="w-10 h-10 bg-white border border-gray-200 rounded-xl shadow-lg flex items-center justify-center text-gray-600 hover:text-blue-600 transition-all hover:scale-105">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="w-10 h-10 bg-white border border-gray-200 rounded-xl shadow-lg flex items-center justify-center text-gray-600 hover:text-blue-600 transition-all hover:scale-105">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
                
                <div class="absolute top-4 right-4 z-10 flex flex-col gap-2 pointer-events-none">
                    <div class="bg-white/95 backdrop-blur-sm p-4 rounded-2xl border border-gray-200 shadow-xl space-y-3 min-w-[180px]">
                        <h4 class="text-[10px] font-bold text-gray-400 uppercase border-b border-gray-100 pb-2">Visualization Legend</h4>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-gray-400 border-2 border-white shadow-sm"></div>
                                <span class="text-[10px] font-bold text-gray-600">Historical Crimes</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-red-500 border-2 border-white shadow-sm ring-4 ring-red-100"></div>
                                <span class="text-[10px] font-bold text-gray-600">Simulated Crimes</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-4 bg-orange-100/50 border border-dashed border-orange-300 rounded-sm"></div>
                                <span class="text-[10px] font-bold text-gray-600">Identified Hotspot</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Simulation Progress Overlay -->
                <div id="simProcessing" class="absolute inset-0 bg-white/80 backdrop-blur-sm z-50 hidden items-center justify-center flex-col gap-6 animate-fade-in">
                    <div class="relative w-24 h-24">
                        <div class="absolute inset-0 border-4 border-gray-100 rounded-full"></div>
                        <div class="absolute inset-0 border-4 border-t-blue-600 rounded-full animate-spin"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-brain text-blue-600 text-2xl animate-pulse"></i>
                        </div>
                    </div>
                    <div class="text-center">
                        <h3 class="text-lg font-bold text-gray-900 mb-1">Recalculating Patterns</h3>
                        <p class="text-xs text-gray-500" id="processingText">Applying interventions through Predictive Engine...</p>
                    </div>
                </div>

                <!-- Map Container Placeholder -->
                <div id="mapCanvas" class="flex-grow bg-[#f0f2f5] overflow-hidden relative group transition-opacity duration-500">
                    <!-- SVG-Based Fake Map Grid -->
                    <svg class="absolute inset-0 w-full h-full" xmlns="http://www.w3.org/2000/svg">
                        <pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse">
                            <path d="M 60 0 L 0 0 0 60" fill="none" stroke="rgba(0,0,0,0.03)" stroke-width="1"/>
                        </pattern>
                        <rect width="100%" height="100%" fill="url(#grid)" />
                        
                        <!-- Simple Road Layout -->
                        <g opacity="0.4" stroke="#fff" stroke-width="8" fill="none" stroke-linecap="round">
                            <path d="M 0,200 Q 400,200 800,400" />
                            <path d="M 400,0 L 400,800" />
                            <path d="M 0,550 L 1000,550" />
                        </g>

                        <!-- Hotspot Circles (Dynamic) -->
                        <g id="hotspotGroup" class="transition-all duration-[1500ms]">
                            <!-- SVG render updated by JS -->
                        </g>

                        <!-- Marker Particles (Dynamic) -->
                        <g id="markerGroup" class="transition-all duration-[1000ms]">
                            <!-- Individual markers for Historical & simulated -->
                        </g>
                    </svg>
                    
                    <!-- Floating Coordinates -->
                    <div class="absolute bottom-6 left-6 text-[9px] font-mono text-gray-400 bg-white/60 px-3 py-1 rounded-full border border-gray-200 backdrop-blur-sm">
                        VIEWPORT CACHE: 14.6542° N, 121.0336° E
                    </div>
                </div>
            </div>

            <!-- Simulation Intelligence Report -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-200 bg-gray-50/50 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 leading-tight">Simulation Intelligence Report</h2>
                        <span class="text-[10px] text-gray-400 font-bold uppercase">Dynamic Analysis Output</span>
                    </div>
                </div>
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                        <div class="space-y-6">
                            <div>
                                <h4 class="text-[11px] font-extrabold text-blue-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                    Intervention Effect Analysis
                                </h4>
                                <div id="analysisText" class="p-6 bg-blue-50/50 border border-blue-100 rounded-2xl leading-relaxed text-sm text-gray-700 italic border-l-4">
                                    Current simulation predicts that by enabling <span class="font-bold text-blue-700">Full CCTV Coverage</span> and <span class="font-bold text-blue-700">Street Lighting</span>, property-related crimes are likely to decrease by <span class="font-bold text-green-600 text-lg">18-22%</span> in the central residential block.
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 group hover:border-blue-200 transition-all">
                                    <span class="block text-[10px] text-gray-400 font-bold uppercase mb-2">Sim Confidence</span>
                                    <div class="flex items-baseline gap-1">
                                        <span id="confidenceValue" class="text-2xl font-bold text-gray-900">88.5</span>
                                        <span class="text-xs text-gray-400">%</span>
                                    </div>
                                    <div class="mt-3 w-full bg-gray-200 h-1 rounded-full overflow-hidden">
                                        <div class="bg-blue-600 h-full w-[88%]"></div>
                                    </div>
                                </div>
                                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 group hover:border-blue-200 transition-all">
                                    <span class="block text-[10px] text-gray-400 font-bold uppercase mb-2">Crime Displacement</span>
                                    <div class="flex items-baseline gap-1 text-amber-600">
                                        <span id="displacementValue" class="text-2xl font-bold">Low</span>
                                    </div>
                                    <div class="mt-3 w-full bg-gray-200 h-1 rounded-full overflow-hidden">
                                        <div class="bg-amber-500 h-full w-[30%]"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <h4 class="text-[11px] font-extrabold text-amber-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                Top Predicted Impacts
                            </h4>
                            <div id="impactList" class="space-y-4">
                                <!-- Populated by JS -->
                                <div class="flex items-start gap-4 p-4 rounded-xl hover:bg-gray-50/80 transition-all border border-transparent hover:border-gray-100 group">
                                    <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center text-green-600 flex-shrink-0 group-hover:scale-110 transition-transform">
                                        <i class="fas fa-arrow-down text-xs"></i>
                                    </div>
                                    <div>
                                        <span class="block text-sm font-bold text-gray-800">Theft Suppression</span>
                                        <p class="text-xs text-gray-500">Predicted reduction in Bagong Pag-asa central market area due to checkpoints.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4 p-4 rounded-xl hover:bg-gray-50/80 transition-all border border-transparent hover:border-gray-100 group">
                                    <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center text-red-600 flex-shrink-0 group-hover:scale-110 transition-transform">
                                        <i class="fas fa-exclamation-circle text-xs"></i>
                                    </div>
                                    <div>
                                        <span class="block text-sm font-bold text-gray-800">Night-time Vulnerability</span>
                                        <p class="text-xs text-gray-500">Despite lighting, unauthorized access persists in northern perimeter parks.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
/**
 * Pattern Detection Simulation Engine (Frontend Mockup)
 * No backend API calls - logic resides here for demonstration.
 */
document.addEventListener('DOMContentLoaded', function() {
    // DOM ELements
    const runBtn = document.getElementById('runSimulation');
    const simProcessing = document.getElementById('simProcessing');
    const mapCanvas = document.getElementById('mapCanvas');
    const analysisText = document.getElementById('analysisText');
    const metricTotal = document.getElementById('metricTotal');
    const metricHotspots = document.getElementById('metricHotspots');
    const metricRisk = document.getElementById('metricRisk');
    const activeFiltersContainer = document.getElementById('activeFilters');
    const noFiltersMsg = document.getElementById('noFilters');
    
    // Intervention Field ELements
    const simMode = document.getElementById('simMode');
    const patrolSlider = document.getElementById('patrolSlider');
    const patrolLabel = document.getElementById('patrolLabel');
    const cctvSelect = document.getElementById('cctvSelect');
    const customCctvEntry = document.getElementById('customCctvEntry');
    const customCctvValue = document.getElementById('customCctvValue');
    const lightingToggle = document.getElementById('lightingToggle');
    const communityToggle = document.getElementById('communityToggle');
    const accessToggle = document.getElementById('accessToggle');
    const resetBtn = document.getElementById('resetInterventions');

    // State
    let simulationData = {
        historical: [],
        simulated: [],
        hotspots: []
    };

    // --- UTILS ---
    
    function getRandomInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function showProcessing(show) {
        if (show) {
            simProcessing.classList.remove('hidden');
            simProcessing.classList.add('flex');
            runBtn.disabled = true;
            runBtn.classList.add('opacity-70');
        } else {
            simProcessing.classList.remove('flex');
            simProcessing.classList.add('hidden');
            runBtn.disabled = false;
            runBtn.classList.remove('opacity-70');
        }
    }

    // --- LOGIC ---

    function generateSimulation() {
        // Collect current settings
        const mode = simMode.value;
        const patrol = parseInt(patrolSlider.value);
        const cctv = cctvSelect.value;
        const cctvCustom = customCctvValue.value;
        const lighting = lightingToggle.checked;
        const community = communityToggle.checked;
        const access = accessToggle.checked;

        // Base values per mode
        let baseCount = mode === 'historical' ? 40 : (mode === 'predictive' ? 55 : 70);
        let hotspotCount = mode === 'historical' ? 3 : (mode === 'predictive' ? 5 : 8);

        // Apply Intervention "Math" (Simulated purely for UI)
        let reduction = 0;
        if (patrol === 2) reduction += 15;
        if (patrol === 1) reduction += 5;
        if (cctv === 'full') reduction += 12;
        if (cctv === 'partial') reduction += 4;
        if (cctv === 'custom' && cctvCustom > 0) reduction += Math.min(20, cctvCustom * 2);
        if (lighting) reduction += 8;
        if (community) reduction += 10;
        if (access) reduction += 14;

        // Cap reduction to realistic amount
        reduction = Math.min(60, reduction);
        
        const finalCount = Math.max(5, Math.round(baseCount * (1 - (reduction / 100))));
        const finalHotspots = Math.max(1, Math.round(hotspotCount * (1 - (reduction / 150))));

        // Update Markers Store
        simulationData.historical = [];
        for(let i=0; i<30; i++) {
            simulationData.historical.push({ x: getRandomInt(50, 750), y: getRandomInt(50, 550) });
        }

        simulationData.simulated = [];
        for(let i=0; i<finalCount; i++) {
            simulationData.simulated.push({ x: getRandomInt(50, 750), y: getRandomInt(50, 550) });
        }

        simulationData.hotspots = [];
        for(let i=0; i<finalHotspots; i++) {
            simulationData.hotspots.push({ 
                x: getRandomInt(100, 700), 
                y: getRandomInt(100, 500), 
                r: getRandomInt(40, 100),
                severity: getRandomInt(1, 10)
            });
        }

        updateUI(finalCount, finalHotspots, reduction, mode);
        renderMap();
        updateFilterBadges();
    }

    function updateUI(count, hotspotNum, reductionPerc, mode) {
        metricTotal.innerText = count;
        document.getElementById('metricTotalBar').style.width = Math.min(100, (count/80)*100) + '%';
        
        metricHotspots.innerText = hotspotNum;
        document.getElementById('metricHotspotsBar').style.width = Math.min(100, (hotspotNum/10)*100) + '%';

        const riskVal = Math.max(5, 45 - reductionPerc);
        metricRisk.innerText = Math.round(riskVal) + '%';
        document.getElementById('metricRiskBar').style.width = riskVal + '%';

        // Update Report Text
        const interventions = [];
        if (parseInt(patrolSlider.value) > 0) interventions.push("Enhanced Patrols");
        if (cctvSelect.value !== 'none') interventions.push("CCTV Implementation");
        if (lightingToggle.checked) interventions.push("Improved Lighting");
        if (communityToggle.checked) interventions.push("Community Policing");
        if (accessToggle.checked) interventions.push("Access Controls");

        let text = "";
        if (interventions.length === 0) {
            text = `Under a <span class="font-bold text-blue-700">${mode}</span> scenario with no interventions, patterns suggest high volatility in residential sections. Stability index is currenty at <span class="text-red-500 font-bold">low levels</span>.`;
        } else {
            text = `Combined application of <span class="font-bold text-blue-700">${interventions.join(', ')}</span> has suppressed predictive signatures by <span class="font-bold text-green-600 text-lg">${reductionPerc}%</span>. Major hotspots have been ${reductionPerc > 30 ? 'dismantled' : 'contained'}.`;
        }
        analysisText.innerHTML = text;

        document.getElementById('confidenceValue').innerText = (80 + Math.random() * 15).toFixed(1);
    }

    function renderMap() {
        const markerGroup = document.getElementById('markerGroup');
        const hotspotGroup = document.getElementById('hotspotGroup');
        
        markerGroup.innerHTML = '';
        hotspotGroup.innerHTML = '';

        // Render Hotspots (Bottom layer)
        simulationData.hotspots.forEach(h => {
            const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
            circle.setAttribute("cx", h.x);
            circle.setAttribute("cy", h.y);
            circle.setAttribute("r", h.r);
            circle.setAttribute("fill", "rgba(245, 158, 11, 0.08)");
            circle.setAttribute("stroke", "rgba(245, 158, 11, 0.3)");
            circle.setAttribute("stroke-width", "1.5");
            circle.setAttribute("stroke-dasharray", "4,2");
            circle.classList.add("animate-pulse");
            hotspotGroup.appendChild(circle);
        });

        // Render Historical (Middle)
        simulationData.historical.forEach(m => {
            const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
            circle.setAttribute("cx", m.x);
            circle.setAttribute("cy", m.y);
            circle.setAttribute("r", "2.5");
            circle.setAttribute("fill", "#94a3b8");
            circle.setAttribute("opacity", "0.6");
            circle.setAttribute("stroke", "#fff");
            circle.setAttribute("stroke-width", "0.5");
            markerGroup.appendChild(circle);
        });

        // Render Simulated (Top)
        simulationData.simulated.forEach(m => {
            const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
            circle.setAttribute("cx", m.x);
            circle.setAttribute("cy", m.y);
            circle.setAttribute("r", "3.5");
            circle.setAttribute("fill", "#ef4444");
            circle.setAttribute("stroke", "#fff");
            circle.setAttribute("stroke-width", "1");
            circle.classList.add("transition-all", "duration-1000");
            
            // Interaction
            circle.style.cursor = "pointer";
            circle.addEventListener('mouseover', () => circle.setAttribute('r', '6'));
            circle.addEventListener('mouseout', () => circle.setAttribute('r', '3.5'));
            
            markerGroup.appendChild(circle);
        });
    }

    function updateFilterBadges() {
        const createBadge = (text, icon, colorClass = 'bg-blue-50 text-blue-700 border-blue-100') => {
            return `<span class="px-3 py-1 ${colorClass} border rounded-full text-[10px] font-bold animate-fade-in flex items-center gap-2">
                <i class="${icon} text-[8px]"></i> ${text}
            </span>`;
        };

        let html = '';
        html += createBadge(simMode.selectedOptions[0].text, 'fas fa-server');
        
        const patrolVal = parseInt(patrolSlider.value);
        if (patrolVal === 2) html += createBadge('High Patrols', 'fas fa-user-shield');
        if (patrolVal === 1) html += createBadge('Medium Patrols', 'fas fa-user-shield');
        
        if (cctvSelect.value === 'full') html += createBadge('Full CCTV', 'fas fa-video');
        if (cctvSelect.value === 'partial') html += createBadge('Partial CCTV', 'fas fa-video');
        if (cctvSelect.value === 'custom' && customCctvValue.value) html += createBadge(`+${customCctvValue.value} CCTV Units`, 'fas fa-video', 'bg-purple-50 text-purple-700 border-purple-100');
        
        if (lightingToggle.checked) html += createBadge('Street Lighting', 'fas fa-lightbulb', 'bg-amber-50 text-amber-700 border-amber-100');
        if (communityToggle.checked) html += createBadge('Watch Program', 'fas fa-users');
        if (accessToggle.checked) html += createBadge('Checkpoints', 'fas fa-door-closed');

        activeFiltersContainer.innerHTML = html;
        if (!html) {
            noFiltersMsg.classList.remove('hidden');
        } else {
            noFiltersMsg.classList.add('hidden');
        }
    }

    // --- EVENTS ---

    patrolSlider.oninput = function() {
        const labels = ['Low', 'Medium', 'High'];
        patrolLabel.innerText = labels[this.value];
    };

    cctvSelect.onchange = function() {
        if(this.value === 'custom') {
            customCctvEntry.classList.remove('hidden');
        } else {
            customCctvEntry.classList.add('hidden');
        }
    };

    runBtn.onclick = function() {
        showProcessing(true);
        // Simulate "Thinking" time
        setTimeout(() => {
            generateSimulation();
            showProcessing(false);
        }, 1200);
    };

    resetBtn.onclick = function() {
        simMode.value = 'predictive';
        patrolSlider.value = 1;
        patrolLabel.innerText = 'Medium';
        cctvSelect.value = 'full';
        customCctvEntry.classList.add('hidden');
        customCctvValue.value = '';
        lightingToggle.checked = true;
        communityToggle.checked = false;
        accessToggle.checked = false;
        generateSimulation();
    };

    // Initial Run
    generateSimulation();
});
</script>
@endpush

@push('styles')
<style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fade-in 0.3s ease-out forwards;
    }
    
    /* Specialized slider for simulation */
    input[type='range']::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 16px;
        height: 16px;
        background: #2563eb;
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        cursor: pointer;
    }
</style>
@endpush
