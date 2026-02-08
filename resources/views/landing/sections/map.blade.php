<section id="map" class="py-16 bg-white">
    <div class="container mx-auto px-4 max-w-7xl">
        <h2 class="text-4xl font-bold text-gray-900 mb-8">Crime Heatmap</h2>

        <!-- Map Controls -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button id="heatmapViewBtn" class="px-4 py-2 rounded bg-alertara-700 text-white font-medium transition-colors flex items-center gap-2">
                    <i class="fas fa-fire text-sm"></i>Heatmap
                </button>
                <button id="markersViewBtn" class="px-4 py-2 rounded text-gray-700 hover:bg-gray-200 font-medium transition-colors flex items-center gap-2">
                    <i class="fas fa-map-pin text-sm"></i>Markers
                </button>
            </div>
            <select id="dateRangeFilter" class="bg-white text-gray-900 px-4 py-2 rounded border border-gray-300 cursor-pointer">
                <option value="30">Last 30 Days</option>
                <option value="90">Last 3 Months</option>
                <option value="180" selected>Last 6 Months</option>
                <option value="all">All Time</option>
            </select>
        </div>

        <!-- Map Container -->
        <div id="crimeMap" class="w-full h-96 md:h-[500px] rounded-lg border border-gray-200 relative overflow-hidden shadow-sm">
            <div id="mapLoader" class="absolute inset-0 bg-white flex flex-col items-center justify-center z-10">
                <i class="fas fa-spinner fa-spin text-alertara-700 text-4xl mb-3"></i>
                <p class="text-gray-700">Loading...</p>
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-6 flex items-center gap-4 text-sm">
            <span class="text-gray-700 font-medium">Density:</span>
            <div class="flex items-center gap-2">
                <span class="text-gray-600">Low</span>
                <div class="w-24 h-4 rounded" style="background: linear-gradient(to right, rgba(0,0,255,0.3), rgba(255,0,0,0.8));"></div>
                <span class="text-gray-600">High</span>
            </div>
        </div>
    </div>
</section>
