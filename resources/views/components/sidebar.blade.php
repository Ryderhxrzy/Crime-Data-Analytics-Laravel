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
                <!-- System Overview -->
                <div>
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('dashboard') ? 'bg-alertara-700 text-alertara-100' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                        <i class="fas fa-chart-pie w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <!-- Crime Analysis Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide px-3 py-2">Crime Analysis</h3>
                </div>

                <!-- Crime Mapping -->
                <div>
                    <a href="{{ authUrl('mapping') }}"
                       class="flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('mapping') ? 'bg-alertara-700 text-alertara-100' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                        <i class="fas fa-map w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Crime Mapping</span>
                    </a>
                </div>

                <!-- Trend Analysis Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide px-3 py-2">Analytics</h3>
                </div>

                <!-- Trend Analysis (Collapsible) -->
                <div>
                    <button class="trend-toggle w-full flex items-center justify-between px-3 py-2 rounded text-sm transition-colors {{ (request()->routeIs('time-based-trends') || request()->routeIs('location-trends') || request()->routeIs('crime-type-trends')) ? 'bg-alertara-700 text-alertara-100 font-semibold mb-2' : 'text-gray-700 hover:bg-gray-100' }}"
                            type="button">
                        <span class="flex items-center">
                            <i class="fas fa-chart-line w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Trend Analytics</span>
                        </span>
                        <i class="fas fa-chevron-right w-2 h-2 transition-transform duration-200 chevron-icon {{ (request()->routeIs('time-based-trends') || request()->routeIs('location-trends') || request()->routeIs('crime-type-trends')) ? 'text-alertara-100' : 'text-gray-400' }}"></i>
                    </button>
                    <div class="trend-content {{ (request()->routeIs('time-based-trends') || request()->routeIs('location-trends') || request()->routeIs('crime-type-trends')) ? '' : 'hidden' }} space-y-0 ml-3 mt-0">
                        <a href="{{ authUrl('time-based-trends') }}"
                           class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('time-based-trends') ? 'bg-alertara-100 text-alertara-700' : '' }}">
                            <i class="fas fa-calendar-days w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Time-Based Trends</span>
                        </a>
                        <a href="{{ authUrl('location-trends') }}"
                           class="flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('location-trends') ? 'bg-alertara-100 text-alertara-700' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                            <i class="fas fa-map-pin w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Location Trends</span>
                        </a>
                        <a href="{{ authUrl('crime-type-trends') }}"
                           class="flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('crime-type-trends') ? 'bg-alertara-100 text-alertara-700' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                            <i class="fas fa-chart-bar w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Crime Type Trends</span>
                        </a>
                    </div>
                </div>

                <!-- Predictive Analytics Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide px-3 py-2">Predictive</h3>
                </div>

                <!-- Predictive Analytics (Collapsible) -->
                <div>
                    <button class="predictive-toggle w-full flex items-center justify-between px-3 py-2 rounded text-sm transition-colors {{ (request()->routeIs('crime-hotspot') || request()->routeIs('risk-forecasting') || request()->routeIs('pattern-detection')) ? 'bg-alertara-700 text-alertara-100 font-semibold mb-2' : 'text-gray-700 hover:bg-gray-100' }}"
                            type="button">
                        <span class="flex items-center">
                            <i class="fas fa-brain w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Predictive Analytics</span>
                        </span>
                        <i class="fas fa-chevron-right w-2 h-2 transition-transform duration-200 chevron-icon {{ (request()->routeIs('crime-hotspot') || request()->routeIs('risk-forecasting') || request()->routeIs('pattern-detection')) ? 'text-alertara-100' : 'text-gray-400' }}"></i>
                    </button>
                    <div class="predictive-content {{ (request()->routeIs('crime-hotspot') || request()->routeIs('risk-forecasting') || request()->routeIs('pattern-detection')) ? '' : 'hidden' }} space-y-0 ml-3 mt-0">
                        <a href="{{ authUrl('crime-hotspot') }}"
                           class="flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('crime-hotspot') ? 'bg-alertara-100 text-alertara-700' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                            <i class="fas fa-location-dot w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Crime Hotspot</span>
                        </a>
                        <a href="{{ authUrl('risk-forecasting') }}"
                           class="flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('risk-forecasting') ? 'bg-alertara-100 text-alertara-700' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                            <i class="fas fa-triangle-exclamation w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Risk Forecasting</span>
                        </a>
                        <a href="{{ authUrl('pattern-detection') }}"
                           class="flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('pattern-detection') ? 'bg-alertara-100 text-alertara-700' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                            <i class="fas fa-magnifying-glass w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Pattern Detection</span>
                        </a>
                    </div>
                </div>

                <!-- Reports & Alerts Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide px-3 py-2">Reports & Alerts</h3>
                </div>

                <!-- Reports (Collapsible) -->
                <div>
                    <button class="reports-toggle w-full flex items-center justify-between px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                            type="button">
                        <span class="flex items-center">
                            <i class="fas fa-file-lines w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Reports</span>
                        </span>
                        <i class="fas fa-chevron-right w-2 h-2 transition-transform duration-200 chevron-icon text-gray-400"></i>
                    </button>
                    <div class="reports-content hidden space-y-0 ml-3 mt-0">
                        <a href="#reports"
                           class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-eye w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>View Reports</span>
                        </a>
                        <a href="#reports-download"
                           class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-download w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Download Report</span>
                        </a>
                    </div>
                </div>

                <!-- Alerts -->
                <div>
                    <a href="#alerts"
                       class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-exclamation-circle w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Alerts</span>
                    </a>
                </div>

                <!-- Crime Management Section Header -->
                <div class="pt-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide px-3 py-2">Crime Management</h3>
                </div>

                <!-- Crime Incidents -->
                <div>
                    <a href="{{ route('crimes.index') }}"
                       class="flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('crimes.*') ? 'bg-alertara-700 text-alertara-100' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                        <i class="fas fa-file-lines w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Crime</span>
                    </a>
                </div>

                <!-- Account Section -->
                <div class="pt-4 mt-4 border-t border-gray-200">
                    <a href="#profile"
                       class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-user w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Profile</span>
                    </a>
                    <a href="#settings"
                       class="flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-sliders-h w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Settings</span>
                    </a>
                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center px-3 py-2 rounded text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-arrow-right-from-bracket w-4 h-4 mr-3 flex-shrink-0"></i>
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

    // Initialize chevron rotation for active menus
    document.addEventListener('DOMContentLoaded', function() {
        const trendContent = document.querySelector('.trend-content');
        const trendChevron = document.querySelector('.trend-toggle .chevron-icon');

        // If Trend Analytics submenu is visible (active) for time-based, location, or crime type trends, rotate chevron
        if (trendContent && !trendContent.classList.contains('hidden') && trendChevron) {
            trendChevron.style.transform = 'rotate(90deg)';
        }

        const predictiveContent = document.querySelector('.predictive-content');
        const predictiveChevron = document.querySelector('.predictive-toggle .chevron-icon');

        // If Predictive Analytics submenu is visible (active) for crime hotspot, risk forecasting, or pattern detection, rotate chevron
        if (predictiveContent && !predictiveContent.classList.contains('hidden') && predictiveChevron) {
            predictiveChevron.style.transform = 'rotate(90deg)';
        }
    });
</script>
