<style>
    /* Custom thin scrollbar */
    .sidebar-scroll::-webkit-scrollbar {
        width: 6px;
    }
    .sidebar-scroll::-webkit-scrollbar-track {
        background: transparent;
    }
    .sidebar-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 3px;
    }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }
</style>

<!-- Sidebar -->
<aside class="w-72 bg-white border-r border-gray-200 fixed left-0 top-0 bottom-0 transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0 z-30 flex flex-col overflow-hidden">

    <!-- Search Box (Sticky) -->
    <div class="bg-white border-b border-gray-200 p-3 flex-shrink-0">
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text"
                   placeholder="Quick search..."
                   class="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded hover:border-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent">
            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs">Ctrl K</span>
        </div>
    </div>

    <!-- Scrollable Content -->
    <div class="sidebar-scroll overflow-y-auto flex-1">
        <div class="p-3">
            <!-- Navigation Sections -->
            <nav class="space-y-1">
                <!-- Crime Management Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide px-3 py-2">Crime Management</h3>
                </div>

                <!-- Crime Incidents -->
                <div>
                    <a href="{{ route('crimes.index') }}"
                       class="flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('crimes.*') ? 'bg-alertara-700 text-alertara-100' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                        <i class="fas fa-file-text w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Crime</span>
                    </a>
                </div>

                <!-- System Overview -->
                <div>
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('dashboard') ? 'bg-alertara-700 text-alertara-100' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                        <i class="fas fa-home w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>System Overview</span>
                    </a>
                </div>

                <!-- Analytics Summary -->
                <div>
                    <a href="#analytics"
                       class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-chart-bar w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Analytics Summary</span>
                    </a>
                </div>

                <!-- Crime Mapping Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide px-3 py-2">Crime Analysis</h3>
                </div>

                <!-- Crime Mapping -->
                <div>
                    <a href="#crime-mapping"
                       class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-map-marked-alt w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Crime Mapping</span>
                    </a>
                </div>

                <!-- Trend Analysis Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-alertara-600 uppercase tracking-wide px-3 py-2">Trend Analysis</h3>
                </div>

                <!-- Trend Analysis (Collapsible) -->
                <div>
                    <button class="trend-toggle w-full flex items-center justify-between px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                            type="button">
                        <span class="flex items-center">
                            <i class="fas fa-chart-line w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Trend Analytics</span>
                        </span>
                        <i class="fas fa-chevron-right w-3 h-3 transition-transform duration-200 chevron-icon text-gray-400"></i>
                    </button>
                    <div class="trend-content hidden space-y-0 ml-3 mt-0">
                        <a href="#time-trends"
                           class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-circle-dot w-2 h-2 mr-3 flex-shrink-0"></i>
                            <span>Time-Based Trends</span>
                        </a>
                        <a href="#location-trends"
                           class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-circle-dot w-2 h-2 mr-3 flex-shrink-0"></i>
                            <span>Location Trends</span>
                        </a>
                        <a href="#crime-type-trends"
                           class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-circle-dot w-2 h-2 mr-3 flex-shrink-0"></i>
                            <span>Crime Type Trends</span>
                        </a>
                    </div>
                </div>

                <!-- Predictive Policing Tools Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-alertara-600 uppercase tracking-wide px-3 py-2">Predictive Policing</h3>
                </div>

                <!-- Predictive Analytics (Collapsible) -->
                <div>
                    <button class="predictive-toggle w-full flex items-center justify-between px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                            type="button">
                        <span class="flex items-center">
                            <i class="fas fa-robot w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Predictive Analytics</span>
                        </span>
                        <i class="fas fa-chevron-right w-3 h-3 transition-transform duration-200 chevron-icon text-gray-400"></i>
                    </button>
                    <div class="predictive-content hidden space-y-0 ml-3 mt-0">
                        <a href="#predictive"
                           class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-circle-dot w-2 h-2 mr-3 flex-shrink-0"></i>
                            <span>Analytics Details</span>
                        </a>
                    </div>
                </div>

                <!-- Key Metrics Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-alertara-600 uppercase tracking-wide px-3 py-2">Key Metrics</h3>
                </div>

                <!-- Key Metrics (Collapsible) -->
                <div>
                    <button class="metrics-toggle w-full flex items-center justify-between px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                            type="button">
                        <span class="flex items-center">
                            <i class="fas fa-chart-pie w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Key Metrics</span>
                        </span>
                        <i class="fas fa-chevron-right w-3 h-3 transition-transform duration-200 chevron-icon text-gray-400"></i>
                    </button>
                    <div class="metrics-content hidden space-y-0 ml-3 mt-0">
                        <a href="#metrics"
                           class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-circle-dot w-2 h-2 mr-3 flex-shrink-0"></i>
                            <span>Metrics Overview</span>
                        </a>
                    </div>
                </div>

                <!-- Reports & Alerts Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-alertara-600 uppercase tracking-wide px-3 py-2">Reports & Alerts</h3>
                </div>

                <!-- Reports (Collapsible) -->
                <div>
                    <button class="reports-toggle w-full flex items-center justify-between px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                            type="button">
                        <span class="flex items-center">
                            <i class="fas fa-file-alt w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Reports</span>
                        </span>
                        <i class="fas fa-chevron-right w-3 h-3 transition-transform duration-200 chevron-icon text-gray-400"></i>
                    </button>
                    <div class="reports-content hidden space-y-0 ml-3 mt-0">
                        <a href="#reports"
                           class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-circle-dot w-2 h-2 mr-3 flex-shrink-0"></i>
                            <span>View Reports</span>
                        </a>
                    </div>
                </div>

                <!-- Alerts -->
                <div>
                    <a href="#alerts"
                       class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-bell w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Alerts</span>
                    </a>
                </div>

                <!-- Account Section -->
                <div class="pt-4 mt-4 border-t border-gray-200">
                    <a href="#profile"
                       class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-user-circle w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Profile</span>
                    </a>
                    <a href="#settings"
                       class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-cog w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Settings</span>
                    </a>
                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-sign-out-alt w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </nav>
        </div>
    </div>
</aside>

<script>
    // Search functionality
    const searchInput = document.querySelector('input[placeholder="Quick search..."]');
    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'k' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                this.focus();
            }
        });
    }

    // Dropdown toggle functionality
    function setupToggle(toggleSelector, contentSelector) {
        const toggles = document.querySelectorAll(toggleSelector);
        toggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const content = this.parentElement.querySelector(contentSelector);
                const icon = this.querySelector('.chevron-icon');

                if (content && icon) {
                    content.classList.toggle('hidden');

                    if (content.classList.contains('hidden')) {
                        icon.style.transform = 'rotate(0deg)';
                    } else {
                        icon.style.transform = 'rotate(90deg)';
                    }
                }
            });
        });
    }

    // Setup all dropdown toggles
    setupToggle('.trend-toggle', '.trend-content');
    setupToggle('.predictive-toggle', '.predictive-content');
    setupToggle('.metrics-toggle', '.metrics-content');
    setupToggle('.reports-toggle', '.reports-content');
</script>
