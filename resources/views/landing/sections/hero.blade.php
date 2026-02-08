<section id="home" class="min-h-screen flex items-center bg-white pt-20">
    <div class="container mx-auto px-4 py-20 max-w-7xl">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <!-- Left Content -->
            <div>
                <h1 class="text-5xl md:text-6xl font-bold text-gray-900 mb-6 leading-tight">
                    Crime Monitoring for QC
                </h1>
                <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                    Real-time crime data visualization and community safety alerts. Stay informed about incidents in your area.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="#map" class="inline-flex items-center justify-center px-6 py-3 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors font-semibold">
                        <i class="fas fa-map-marked-alt mr-2"></i>View Map
                    </a>
                    <a href="#submit-tip" class="inline-flex items-center justify-center px-6 py-3 border-2 border-alertara-700 text-alertara-700 rounded-lg hover:bg-alertara-50 transition-colors font-semibold">
                        <i class="fas fa-paper-plane mr-2"></i>Report Tip
                    </a>
                </div>
            </div>

            <!-- Right Stats -->
            <div class="space-y-4">
                <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                    <div class="text-4xl font-bold text-alertara-700 mb-2">{{ number_format($totalIncidents) }}</div>
                    <div class="text-gray-700 font-medium">Total Incidents</div>
                </div>
                <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                    <div class="text-4xl font-bold text-alertara-700 mb-2">{{ number_format($recentIncidents) }}</div>
                    <div class="text-gray-700 font-medium">Recent Incidents (30 days)</div>
                </div>
            </div>
        </div>
    </div>
</section>
