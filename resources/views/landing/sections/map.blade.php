<section id="map" class="py-16 bg-white mt-12">
    <div class="container mx-auto px-4 max-w-7xl">
        <h2 class="text-4xl font-bold text-gray-900 mb-2">Crime Heatmap - Quezon City</h2>
        <p class="text-gray-600 mb-8 text-base">Real-time crime density visualization across Quezon City. The green boundary shows the exact Quezon City limits and coverage area.</p>

        <!-- Map Controls -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div class="flex flex-col gap-2">
                <label for="dateRangeFilter" class="text-sm font-medium text-gray-700">Filter by Date Range:</label>
                <select id="dateRangeFilter" class="bg-white text-gray-900 px-4 py-2 sm:py-2.5 rounded-lg border border-gray-300 cursor-pointer text-xs sm:text-sm font-medium hover:border-alertara-600 transition-colors">
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 3 Months</option>
                    <option value="180">Last 6 Months</option>
                    <option value="all" selected>All Time</option>
                </select>
            </div>
        </div>

        <!-- Map Container with proper z-index handling -->
        <div id="crimeMap" class="w-full h-96 md:h-[550px] rounded-xl border-2 border-alertara-300 relative overflow-hidden shadow-lg z-0">
            <div id="mapLoader" class="absolute inset-0 bg-white flex flex-col items-center justify-center z-10">
                <i class="fas fa-spinner fa-spin text-alertara-700 text-4xl mb-2"></i>
                <p class="text-gray-700 text-sm">Loading map...</p>
            </div>
        </div>

        <!-- Legend & Info -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Legend -->
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Crime Density Scale</h3>
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-full h-5 rounded-lg" style="background: linear-gradient(to right, #3498db 0%, #2ecc71 20%, #f39c12 40%, #e74c3c 60%, #c0392b 80%, #8b0000 100%);"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-600 px-1 mt-2">
                        <span>Low Density</span>
                        <span>Medium</span>
                        <span>High Density</span>
                    </div>
                    <p class="text-xs text-gray-600 mt-3">
                        <strong>Color Guide:</strong>
                        Blue = Low | Green = Low-Medium | Orange = Medium | Red = High | Dark Red = Very High incident density
                    </p>
                </div>
            </div>

            <!-- Map Info -->
            <div class="bg-blue-50 rounded-xl border border-blue-200 p-5">
                <h3 class="text-sm font-semibold text-blue-900 mb-3">About This Map</h3>
                <ul class="text-xs text-blue-800 space-y-2">
                    <li class="flex gap-2">
                        <span class="text-blue-600 font-bold">•</span>
                        <span><strong>QC Boundary (Green):</strong> Exact Quezon City geographical limits from qc_map.geojson</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-blue-600 font-bold">•</span>
                        <span><strong>Coverage Area:</strong> All crime data shown within QC limits only</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-blue-600 font-bold">•</span>
                        <span><strong>Heatmap Layer:</strong> Shows crime density - hover to see details</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-blue-600 font-bold">•</span>
                        <span><strong>Interactive:</strong> Pan, zoom, hover on boundary, and filter by date</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
