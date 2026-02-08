<section id="home" class="min-h-screen flex items-center bg-white pt-0">
    <div class="container mx-auto px-4 py-8 lg:py-16 max-w-7xl">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-center">
            <!-- Left Content -->
            <div>
                <div class="mb-6 inline-flex items-center px-4 py-1.5 bg-alertara-50 rounded-full border border-alertara-200">
                    <span class="w-2.5 h-2.5 bg-alertara-700 rounded-full mr-2.5"></span>
                    <span class="text-sm font-semibold text-alertara-700">Real-Time Safety Data</span>
                </div>

                <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold text-gray-900 mb-6 leading-tight">
                    Stay Informed.<br>Stay Safe.
                </h1>

                <p class="text-lg md:text-xl text-gray-700 mb-3 leading-relaxed font-medium">
                    Monitor crime activity in real-time across Quezon City with our interactive heatmap.
                </p>

                <p class="text-base md:text-lg text-gray-600 mb-8 leading-relaxed">
                    Get instant alerts, report anonymous tips, and access transparent crime statistics to keep yourself and your community safe.
                </p>

                <!-- Key Features -->
                <div class="space-y-4 mb-10">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 mt-1">
                            <div class="w-10 h-10 bg-alertara-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-bolt text-alertara-700 text-lg"></i>
                            </div>
                        </div>
                        <div>
                            <p class="text-base md:text-lg font-semibold text-gray-900">Live Heatmap</p>
                            <p class="text-sm md:text-base text-gray-600">Real-time density visualization</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 mt-1">
                            <div class="w-10 h-10 bg-alertara-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-lock text-alertara-700 text-lg"></i>
                            </div>
                        </div>
                        <div>
                            <p class="text-base md:text-lg font-semibold text-gray-900">Anonymous Tips</p>
                            <p class="text-sm md:text-base text-gray-600">Report safely without identification</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 mt-1">
                            <div class="w-10 h-10 bg-alertara-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-bar text-alertara-700 text-lg"></i>
                            </div>
                        </div>
                        <div>
                            <p class="text-base md:text-lg font-semibold text-gray-900">Data Access</p>
                            <p class="text-sm md:text-base text-gray-600">Transparent crime statistics</p>
                        </div>
                    </div>
                </div>

                <!-- CTA Buttons - Smaller -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="#map" class="inline-flex items-center justify-center px-4 py-2 sm:py-2.5 bg-alertara-700 text-white rounded-md hover:bg-alertara-800 transition-colors text-xs sm:text-sm font-medium">
                        <i class="fas fa-map-marked-alt mr-2"></i>View Map
                    </a>
                    <a href="#submit-tip" class="inline-flex items-center justify-center px-4 py-2 sm:py-2.5 border border-alertara-700 text-alertara-700 rounded-md hover:bg-alertara-50 transition-colors text-xs sm:text-sm font-medium">
                        <i class="fas fa-paper-plane mr-2"></i>Report Tip
                    </a>
                </div>
            </div>

            <!-- Right Stats & Illustration -->
            <div class="space-y-5">
                <!-- Illustration/Visual Area -->
                <div class="bg-gradient-to-br from-alertara-700 to-alertara-900 rounded-2xl p-8 md:p-12 text-white text-center min-h-56 flex flex-col items-center justify-center">
                    <div class="text-6xl md:text-7xl mb-4">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <p class="text-sm md:text-base font-medium">Interactive Crime Heatmap</p>
                    <p class="text-xs md:text-sm text-alertara-100 mt-2">Visualize crime density patterns</p>
                </div>

                <!-- Stats Row 1 -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gradient-to-br from-alertara-50 to-gray-50 p-5 rounded-xl border border-alertara-200 hover:shadow-md transition-shadow">
                        <div class="text-4xl md:text-5xl font-bold text-alertara-700 mb-2">{{ number_format($totalIncidents) }}</div>
                        <div class="text-sm md:text-base text-gray-700 font-semibold">Total Incidents</div>
                        <p class="text-xs text-gray-500 mt-1">Recorded data</p>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-gray-300 hover:shadow-md transition-shadow">
                        <div class="text-4xl md:text-5xl font-bold text-alertara-700 mb-2">{{ number_format($recentIncidents) }}</div>
                        <div class="text-sm md:text-base text-gray-700 font-semibold">Recent Cases</div>
                        <p class="text-xs text-gray-500 mt-1">Last 30 days</p>
                    </div>
                </div>

                <!-- Additional Stats -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-blue-50 p-5 rounded-xl border border-blue-200 text-center">
                        <div class="text-3xl md:text-4xl font-bold text-blue-700 mb-1">24/7</div>
                        <div class="text-xs md:text-sm text-blue-900 font-medium">Real-Time Updates</div>
                    </div>

                    <div class="bg-green-50 p-5 rounded-xl border border-green-200 text-center">
                        <div class="text-3xl md:text-4xl font-bold text-green-700 mb-1">100%</div>
                        <div class="text-xs md:text-sm text-green-900 font-medium">Anonymous Safe</div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                    <div class="flex gap-3">
                        <i class="fas fa-lightbulb text-amber-600 text-lg mt-1 flex-shrink-0"></i>
                        <div>
                            <p class="text-sm font-semibold text-amber-900 mb-1">Did You Know?</p>
                            <p class="text-xs md:text-sm text-amber-800">Our platform uses data from official sources and community reports to give you accurate safety insights for your area.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
