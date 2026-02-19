<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crime Incidents - Crime Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.css" />
    @vite(['resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-gray-50">
    <!-- Header Component -->
    @include('components.header')

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

    <!-- Sidebar -->
    @include('components.sidebar')

    <!-- Main Content -->
    <main class="lg:ml-72 ml-0 lg:mt-16 mt-16 min-h-screen bg-gray-50">
        <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
            <!-- Page Header -->
            <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Crime Incidents</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Manage and view all reported crime incidents</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button id="addIncidentBtn" class="px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2">
                            <i class="fas fa-plus"></i>
                            <span class="hidden sm:inline">Add Incident</span>
                        </button>
                        <button id="exportBtn" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2">
                            <i class="fas fa-download"></i>
                            <span class="hidden sm:inline">Export</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Search</label>
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search incidents..." 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-20 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 focus:text-alertara-700 bg-white">
                            <i class="fas fa-search absolute left-3 top-3 text-alertara-500"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Category</label>
                        <select id="categoryFilter" class="w-full px-3 py-2 border border-gray-20 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Case Status</label>
                        <select id="caseStatusFilter" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Status</option>
                            <option value="reported">Reported</option>
                            <option value="under_investigation">Under Investigation</option>
                            <option value="solved">Solved</option>
                            <option value="closed">Closed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Clearance Status</label>
                        <select id="clearanceStatusFilter" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Clearance</option>
                            <option value="cleared">Cleared</option>
                            <option value="uncleared">Uncleared</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Barangay</label>
                        <select id="barangayFilter" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Barangays</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Date Range</label>
                        <input type="date" id="dateFilter" 
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                    </div>
                </div>
            </div>

            <!-- Detailed Table Section -->
            <div class="mt-6 bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">All Incidents</h2>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">Show:</span>
                            <select id="tablePageSize" class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Incident Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Barangay</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Clearance</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="crimesTableBody" class="divide-y divide-gray-200">
                            <!-- Table rows will be populated here -->
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="p-4 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalRecords">0</span> results
                        </div>
                        <div class="flex gap-2" id="pagination">
                            <!-- Pagination buttons will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Incident Detail Modal -->
    <div id="incidentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-900">Incident Details</h3>
                    <button id="closeModal" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div id="modalContent" class="p-6">
                <!-- Modal content will be populated here -->
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.js"></script>
    @vite(['resources/js/crime-page.ts'])
</body>
</html>
