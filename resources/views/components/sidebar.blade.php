<link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">

<!-- Sidebar -->
<aside class="w-72 border-r border-gray-200 fixed left-0 top-0 bottom-0 transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0 z-50 flex flex-col overflow-hidden">

    <div class="bg-gradient-to-r from-alertara-50 to-alertara-100 border-b-2 border-alertara-200 px-4 sm:px-6 lg:px-8 py-5 flex-shrink-0 relative overflow-hidden">
        <!-- Decorative Pattern -->
        <div class="absolute top-0 right-0 w-20 h-20 bg-alertara-200 rounded-full opacity-20 -mr-10 -mt-10"></div>
        <div class="absolute bottom-0 left-0 w-16 h-16 bg-alertara-200 rounded-full opacity-20 -ml-8 -mb-8"></div>

        <!-- Close Button (Mobile Only) - Top Right -->
        <div class="absolute top-3 right-3 z-20 lg:hidden">
            <button id="sidebarClose" class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-alertara-700 text-white hover:bg-alertara-800 shadow-md hover:shadow-lg transition-all hover:scale-110"
                    title="Close Sidebar">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <!-- Logout Icon Top Left (Mobile Only) -->
        <div class="absolute top-3 left-3 z-20 lg:hidden">
            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-alertara-700 text-white hover:bg-alertara-800 shadow-md hover:shadow-lg transition-all hover:scale-110"
                        title="Logout">
                    <i class="fas fa-arrow-right-from-bracket text-sm"></i>
                </button>
            </form>
        </div>

        <!-- Logout Icon Top Right (Desktop Only) -->
        <div class="absolute top-3 right-3 z-20 hidden lg:block">
            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-alertara-700 text-white hover:bg-alertara-800 shadow-md hover:shadow-lg transition-all hover:scale-110"
                        title="Logout">
                    <i class="fas fa-arrow-right-from-bracket text-sm"></i>
                </button>
            </form>
        </div>

        @php
            $authUser = session('auth_user');
            $localUser = Auth::user();
            $isAuthenticated = !empty($authUser) || !empty($localUser);

            // Determine user data source (JWT or Local Auth)
            $userName = $authUser['full_name'] ?? $authUser['email'] ?? $localUser?->full_name ?? $localUser?->name ?? 'User';
            $userRole = $authUser['role'] ?? $localUser?->role ?? 'User';
        @endphp
        @if($isAuthenticated)
            <div class="flex flex-col items-center text-center relative z-10">
                <div class="w-16 h-16 bg-gradient-to-br from-alertara-500 to-alertara-700 rounded-full flex items-center justify-center flex-shrink-0 mb-3 shadow-lg ring-4 ring-alertara-100">
                    <i class="fas fa-user text-white text-2xl"></i>
                </div>
                <p class="text-sm font-bold text-alertara-900 truncate w-full">{{ $userName }}</p>
                <p class="text-xs text-alertara-600 font-medium truncate w-full mt-0.5">{{ ucfirst($userRole) }}</p>
            </div>
        @endif
    </div>

    <!-- Scrollable Content -->
    <div class="sidebar-scroll overflow-y-auto flex-1">
        <div class="p-3">
            <!-- Navigation Sections -->
            <nav class="space-y-1">
                <!-- System Overview -->
                <div class="nav-section">
                    <a href="{{ authUrl('dashboard') }}"
                       class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('dashboard') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                        <i class="fas fa-chart-pie w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <!-- Crime Analysis Section -->
                <div class="nav-section">
                    <span class="section-label">Crime Analysis</span>

                    <!-- Crime Mapping -->
                    <div class="tree-node mt-0.5">
                        <a href="{{ authUrl('mapping') }}"
                           class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('mapping') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                            <i class="fas fa-map w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Crime Mapping</span>
                        </a>
                    </div>

                    <!-- Add Crime Incident (Testing) -->
                    <div class="tree-node">
                        <a href="{{ route('crime-incident.create') }}"
                           class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('crime-incident.create') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                            <i class="fas fa-plus-circle w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Add Crime Incident</span>
                        </a>
                    </div>

                    <!-- Real-Time Test -->
                    <div class="tree-node">
                        <a href="{{ authUrl('realtime-test') }}"
                           class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('realtime-test') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                            <i class="fas fa-wifi w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Real-Time Test</span>
                        </a>
                    </div>
                </div>

                <!-- Analytics Section -->
                <div class="nav-section">
                    <span class="section-label">Analytics</span>

                    <!-- Trend Analytics (Collapsible) -->
                    <div class="mt-0.5">
                        <button class="crime-trend-toggle tree-node w-full flex items-center justify-between px-3 py-2 rounded text-sm transition-colors {{ (request()->routeIs('time-based-trends') || request()->routeIs('location-trends') || request()->routeIs('crime-type-trends')) ? 'active-nav-item is-open-trigger' : 'text-alertara-800 hover:bg-alertara-200' }}"
                                type="button">
                            <span class="flex items-center">
                                <i class="fas fa-chart-line w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Trend Analytics</span>
                            </span>
                            <i class="fas fa-chevron-right text-xs chevron-icon {{ (request()->routeIs('time-based-trends') || request()->routeIs('location-trends') || request()->routeIs('crime-type-trends')) ? 'text-alertara-100' : 'text-alertara-600' }}"></i>
                        </button>
                        <div class="crime-trend-content dropdown-menu submenu-tree {{ (request()->routeIs('time-based-trends') || request()->routeIs('location-trends') || request()->routeIs('crime-type-trends')) ? 'is-open' : '' }}">
                            <a href="{{ authUrl('time-based-trends') }}"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('time-based-trends') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                                <i class="fas fa-calendar-days w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Time-Based Trends</span>
                            </a>
                            <a href="{{ authUrl('location-trends') }}"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('location-trends') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                                <i class="fas fa-map-pin w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Location Trends</span>
                            </a>
                            <a href="{{ authUrl('crime-type-trends') }}"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('crime-type-trends') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                                <i class="fas fa-chart-bar w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Crime Type Trends</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Predictive Section -->
                <div class="nav-section">
                    <span class="section-label">Predictive</span>

                    <!-- Predictive Analytics (Collapsible) -->
                    <div class="mt-0.5">
                        <button class="crime-predictive-toggle tree-node w-full flex items-center justify-between px-3 py-2 rounded text-sm transition-colors {{ (request()->routeIs('crime-hotspot') || request()->routeIs('risk-forecasting') || request()->routeIs('pattern-detection')) ? 'active-nav-item is-open-trigger' : 'text-alertara-800 hover:bg-alertara-200' }}"
                                type="button">
                            <span class="flex items-center">
                                <i class="fas fa-brain w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Predictive Analytics</span>
                            </span>
                            <i class="fas fa-chevron-right text-xs chevron-icon {{ (request()->routeIs('crime-hotspot') || request()->routeIs('risk-forecasting') || request()->routeIs('pattern-detection')) ? 'text-alertara-100' : 'text-alertara-600' }}"></i>
                        </button>
                        <div class="crime-predictive-content dropdown-menu submenu-tree {{ (request()->routeIs('crime-hotspot') || request()->routeIs('risk-forecasting') || request()->routeIs('pattern-detection')) ? 'is-open' : '' }}">
                            <a href="{{ authUrl('crime-hotspot') }}"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('crime-hotspot') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                                <i class="fas fa-location-dot w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Crime Hotspot</span>
                            </a>
                            <a href="{{ authUrl('risk-forecasting') }}"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('risk-forecasting') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                                <i class="fas fa-triangle-exclamation w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Risk Forecasting</span>
                            </a>
                            <a href="{{ authUrl('pattern-detection') }}"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('pattern-detection') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                                <i class="fas fa-magnifying-glass w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Pattern Detection</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Reports & Alerts Section -->
                <div class="nav-section">
                    <span class="section-label">Reports</span>

                    <!-- Reports (Collapsible) -->
                    <div class="mt-0.5">
                        <button class="crime-reports-toggle tree-node w-full flex items-center justify-between px-3 py-2 rounded text-sm text-alertara-800 hover:bg-alertara-200 transition-colors"
                                type="button">
                            <span class="flex items-center">
                                <i class="fas fa-file-pdf w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Reports</span>
                            </span>
                            <i class="fas fa-chevron-right text-xs chevron-icon text-alertara-600"></i>
                        </button>
                        <div class="crime-reports-content dropdown-menu submenu-tree">
                            <a href="#reports"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm text-alertara-800 hover:bg-alertara-200 transition-colors">
                                <i class="fas fa-eye w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>View Reports</span>
                            </a>
                            <a href="#reports-download"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm text-alertara-800 hover:bg-alertara-200 transition-colors">
                                <i class="fas fa-download w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Download Report</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="nav-section">
                    <span class="section-label">Alerts</span>
                    <!-- Alerts (Collapsible) -->
                    <div class="mt-0.5">
                        <button class="crime-alerts-toggle tree-node w-full flex items-center justify-between px-3 py-2 rounded text-sm text-alertara-800 hover:bg-alertara-200 transition-colors"
                                type="button">
                            <span class="flex items-center">
                                <i class="fas fa-bell w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Alerts</span>
                            </span>
                            <i class="fas fa-chevron-right text-xs chevron-icon text-alertara-600"></i>
                        </button>
                        <div class="crime-alerts-content dropdown-menu submenu-tree">
                            <a href="{{ authUrl('alerts.active') }}"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm text-alertara-800 hover:bg-alertara-200 transition-colors">
                                <i class="fas fa-circle-dot w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Active Alerts</span>
                            </a>
                            <a href="{{ authUrl('alerts.history') }}"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm text-alertara-800 hover:bg-alertara-200 transition-colors">
                                <i class="fas fa-clock-rotate-left w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Alert History</span>
                            </a>
                            <a href="{{ authUrl('alerts.settings') }}"
                               class="tree-node flex items-center px-3 py-2 rounded text-sm text-alertara-800 hover:bg-alertara-200 transition-colors">
                                <i class="fas fa-sliders-h w-4 h-4 mr-3 flex-shrink-0"></i>
                                <span>Threshold Settings</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Crime Management Section -->
                <div class="nav-section">
                    <span class="section-label">Crime Management</span>

                    <!-- Crime Incidents -->
                    <div class="tree-node mt-0.5">
                        <a href="{{ authUrl('crimes.index') }}"
                           class="tree-node flex items-center px-3 py-2 rounded text-sm {{ request()->routeIs('crimes.*') ? 'active-nav-item' : 'text-alertara-800 hover:bg-alertara-200' }} transition-colors">
                            <i class="fas fa-file-lines w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Crime</span>
                        </a>
                    </div>
                </div>

                <!-- Account Section -->
                <div class="nav-section">
                    <a href="#profile"
                       class="tree-node flex items-center px-3 py-2 rounded text-sm text-alertara-800 hover:bg-alertara-200 transition-colors">
                        <i class="fas fa-user w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Profile</span>
                    </a>
                    <a href="#settings"
                       class="tree-node flex items-center px-3 py-2 rounded text-sm text-alertara-800 hover:bg-alertara-200 transition-colors">
                        <i class="fas fa-sliders-h w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span>Settings</span>
                    </a>
                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit"
                                class="w-full tree-node flex items-center px-3 py-2 rounded text-sm text-alertara-800 hover:bg-alertara-200 transition-colors">
                            <i class="fas fa-arrow-right-from-bracket w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </nav>
        </div>
    </div>
</aside>

<!-- Search Modal -->
<div class="search-overlay" id="searchOverlay">
    <div class="search-modal">
        <div class="search-modal-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" class="search-modal-input" id="searchModalInput" placeholder="Search..." autocomplete="off">
            <span class="search-modal-esc" id="searchModalEsc">Esc</span>
        </div>
        <div class="search-modal-results" id="searchModalResults"></div>
        <div class="search-modal-footer">
            <span><kbd>&uarr;</kbd><kbd>&darr;</kbd> to navigate</span>
            <span><kbd>&crarr;</kbd> to select</span>
        </div>
    </div>
</div>

<!-- Sidebar JavaScript -->
<script src="{{ asset('js/sidebar.js') }}"></script>