<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crime Incidents - Crime Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.css" />
    @vite(['resources/js/app.js'])

    <!-- Skeleton Loading Animation -->
    <style>
        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }

        .skeleton-shimmer {
            background: linear-gradient(
                90deg,
                #f0f0f0 0%,
                #e0e0e0 50%,
                #f0f0f0 100%
            );
            background-size: 1000px 100%;
            animation: shimmer 2s infinite;
        }

        /* Blurred text for encrypted data */
        .blur-text {
            background: linear-gradient(90deg, #e5e7eb, #d1d5db, #e5e7eb);
            color: transparent;
            user-select: none;
            letter-spacing: 2px;
            border-radius: 4px;
            padding: 4px 0;
            filter: blur(2px);
            opacity: 0.8;
        }

        .blur-text:hover {
            opacity: 0.9;
            cursor: not-allowed;
        }

        /* Blurred text badge for table display */
        .blur-text-badge {
            background: linear-gradient(90deg, #f3f4f6, #e5e7eb, #f3f4f6);
            color: transparent;
            user-select: none;
            padding: 2px 6px;
            border-radius: 3px;
            filter: blur(1px);
            opacity: 0.7;
            display: inline-block;
            min-width: 80px;
            text-align: center;
            font-weight: 500;
            cursor: not-allowed;
        }

        .blur-text-badge:hover {
            opacity: 0.85;
        }
    </style>

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
            <div class="mt-6 bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200">
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
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    <input type="checkbox" id="selectAllCheckbox" class="w-4 h-4 rounded border-gray-300 cursor-pointer">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Title & Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Location</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Incident Details</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Statistics & Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Complainant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Evidence</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="crimesTableBody" class="divide-y divide-gray-200">
                            <!-- Skeleton Loading Rows (shown while loading) -->
                            <tr id="skeletonRow1" class="animate-pulse">
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-24"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-32"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-24"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-28"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-16"></div></td>
                            </tr>
                            <tr id="skeletonRow2" class="animate-pulse">
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-24"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-32"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-24"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-28"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-16"></div></td>
                            </tr>
                            <tr id="skeletonRow3" class="animate-pulse">
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-24"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-32"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-24"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-28"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-16"></div></td>
                            </tr>
                            <tr id="skeletonRow4" class="animate-pulse">
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-24"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-32"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-24"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-28"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-16"></div></td>
                            </tr>
                            <tr id="skeletonRow5" class="animate-pulse">
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-24"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-32"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-24"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-28"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-20"></div></td>
                                <td class="px-4 py-3"><div class="h-4 skeleton-shimmer rounded w-16"></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="p-4 border border-gray-200">
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

    <!-- Add Incident Modal -->
    <div id="addIncidentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] flex flex-col my-8">
            <div class="p-6 border-b border-gray-100 bg-white flex-shrink-0">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <i class="fas fa-plus mr-2 text-alertara-600"></i>Add New Incident
                    </h3>
                    <button id="closeAddModal" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="overflow-y-auto flex-1">
            <form id="addIncidentForm" class="p-6">
                @csrf
                <!-- Row 1: Title, Category, and Barangay -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-heading mr-1 text-alertara-600"></i>Incident Title
                        </label>
                        <input type="text" name="incident_title" id="modalIncidentTitle" placeholder="e.g., Robbery at 7-11"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-tag mr-1 text-alertara-600"></i>Crime Category
                        </label>
                        <select name="crime_category_id" id="modalCrimeCategory" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                            <option value="">Select a category...</option>
                            <!-- Categories will be populated by JavaScript -->
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1 text-alertara-600"></i>Barangay
                        </label>
                        <select name="barangay_id" id="modalBarangay" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                            <option value="">Select a barangay...</option>
                            <!-- Barangays will be populated by JavaScript -->
                        </select>
                    </div>
                </div>

                <!-- Row 2: Description with Language Support -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <i class="fas fa-align-left mr-1 text-alertara-600"></i>Description
                        </label>
                        <div class="flex items-center gap-2">
                            <select id="descriptionLanguage" class="px-3 py-1 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent bg-white">
                                <option value="english">English</option>
                                <option value="tagalog">Tagalog</option>
                                <option value="taglish">Taglish (Mix)</option>
                            </select>

                            <!-- Microphone button for voice input -->
                            <button type="button" id="voiceInputBtn" title="Click to start recording, click again to stop"
                                    class="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all flex items-center justify-center gap-1 min-w-[90px]">
                                <i class="fas fa-microphone text-gray-600"></i>
                                <span id="voiceInputStatus" class="text-gray-700 font-medium"></span>
                            </button>

                            <!-- Generate Title Button -->
                            <button type="button" id="generateTitleBtn" title="Generate title from description using AI"
                                    class="px-3 py-1 text-xs border border-alertara-300 rounded-lg bg-white hover:bg-alertara-50 hover:border-alertara-500 transition-all flex items-center gap-2 text-alertara-700 font-medium">
                                <i class="fas fa-sparkles"></i>
                                <span>Generate Title</span>
                            </button>

                            <span id="geminiStatus" class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800 flex items-center gap-1 whitespace-nowrap border border-blue-200 transition-all hidden">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span id="geminiStatusText">Generating...</span>
                            </span>
                        </div>
                    </div>
                    <div class="relative">
                        <textarea name="incident_description" id="modalIncidentDescription" placeholder="Detailed description of incident..."
                                  rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required></textarea>
                    </div>
                    <small class="text-gray-500 mt-1 block">💡 Tip: Use the mic button for voice input • Click "Generate Title" button to create title from description</small>
                </div>

                <!-- Row 3: Date and Time -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-1 text-alertara-600"></i>Incident Date
                        </label>
                        <input type="date" name="incident_date" id="modalIncidentDate"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-clock mr-1 text-alertara-600"></i>Incident Time
                        </label>
                        <input type="time" name="incident_time" id="modalIncidentTime"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                    </div>
                </div>

                <!-- Row 4: Location -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1 text-alertara-600"></i>Latitude
                        </label>
                        <input type="number" step="0.000001" name="latitude" id="modalLatitude" placeholder="e.g., 14.6091"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                        <small class="text-gray-500">QC Range: 14.5 to 14.8</small>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-compass mr-1 text-alertara-600"></i>Longitude
                        </label>
                        <input type="number" step="0.000001" name="longitude" id="modalLongitude" placeholder="e.g., 121.0245"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                        <small class="text-gray-500">QC Range: 120.9 to 121.2</small>
                    </div>
                </div>

                <!-- Map Selection Link -->
                <div class="mb-6">
                    <button type="button" id="openLocationMapBtn"
                            class="text-alertara-600 hover:text-alertara-700 hover:underline font-medium cursor-pointer transition-colors flex items-center gap-2">
                        <i class="fas fa-map-location-dot text-alertara-600"></i>
                        View map to select location
                    </button>
                </div>

                <!-- Row 5: Address -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-home mr-1 text-alertara-600"></i>Address Details
                    </label>
                    <input type="text" name="address_details" id="modalAddress" placeholder="Complete address of incident..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent">
                </div>

                <!-- Row 6: Status -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-info-circle mr-1 text-alertara-600"></i>Case Status
                        </label>
                        <select name="status" id="modalCaseStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                            <option value="reported">Reported</option>
                            <option value="under_investigation">Under Investigation</option>
                            <option value="solved">Solved</option>
                            <option value="closed">Closed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-check-circle mr-1 text-alertara-600"></i>Clearance Status
                        </label>
                        <select name="clearance_status" id="modalClearanceStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                            <option value="uncleared">Uncleared</option>
                            <option value="cleared">Cleared</option>
                        </select>
                    </div>
                </div>

                <!-- Other Information (Optional) Section -->
                <div class="pt-6 border-t border-gray-200 mt-6">
                    <div class="mb-6">
                        <button type="button" id="toggleOtherInfo" class="flex items-center gap-2 text-lg font-semibold text-gray-900 cursor-pointer hover:text-alertara-600 transition-colors">
                            <i class="fas fa-chevron-right transition-transform" id="otherInfoChevron"></i>
                            Other Information <span class="text-sm font-normal text-gray-500">(Optional)</span>
                        </button>
                    </div>
                    <div id="otherInfoContent" class="hidden space-y-6">
                        <!-- Row 1: Victim and Suspect Count -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-users mr-1 text-alertara-600"></i>Victim Count
                                </label>
                                <input type="number" name="victim_count" id="modalVictimCount" min="0" placeholder="e.g., 1"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user-secret mr-1 text-alertara-600"></i>Suspect Count
                                </label>
                                <input type="number" name="suspect_count" id="modalSuspectCount" min="0" placeholder="e.g., 1"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent">
                            </div>
                        </div>

                        <!-- Row 2: Modus Operandi -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-lightbulb mr-1 text-alertara-600"></i>Modus Operandi
                            </label>
                            <textarea name="modus_operandi" id="modalModusOperandi" placeholder="Method and pattern of the crime..."
                                      rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"></textarea>
                            <small class="text-gray-500">How was the crime committed? What was the method?</small>
                        </div>

                        <!-- Row 3: Weather Condition -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-cloud-sun mr-1 text-alertara-600"></i>Weather Condition
                            </label>
                            <input type="text" name="weather_condition" id="modalWeatherCondition" placeholder="e.g., Rainy, Sunny, Cloudy, Night-time"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent">
                        </div>

                        <!-- Row 4: Assigned Officer -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-badge mr-1 text-alertara-600"></i>Assigned Officer
                            </label>
                            <input type="text" name="assigned_officer" id="modalAssignedOfficer" placeholder="Name of assigned investigating officer"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent">
                        </div>

                        <!-- Row 5: Clearance Date (Conditional) -->
                        <div id="clearanceDateField" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar-check mr-1 text-alertara-600"></i>Clearance Date
                            </label>
                            <input type="date" name="clearance_date" id="modalClearanceDate"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent">
                            <small class="text-gray-500">Only applicable when case is marked as Cleared</small>
                        </div>
                    </div>
                </div>

                <!-- Optional: Persons Involved and Evidence Section -->
                <div class="pt-6 border-t border-gray-200 mt-6">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Additional Information <span class="text-sm font-normal text-gray-500">(Optional)</span></h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Persons Involved Button -->
                        <button type="button" id="openPersonsModal" class="p-4 border-2 border-dashed border-alertara-300 rounded-lg hover:border-alertara-500 hover:bg-alertara-50 transition-colors text-left">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-users text-alertara-600 text-xl"></i>
                                <div>
                                    <h5 class="font-semibold text-gray-900">Add Persons Involved</h5>
                                    <p class="text-sm text-gray-600">Complainant, victim, or suspect info</p>
                                </div>
                            </div>
                        </button>

                        <!-- Evidence Button -->
                        <button type="button" id="openEvidenceModal" class="p-4 border-2 border-dashed border-alertara-300 rounded-lg hover:border-alertara-500 hover:bg-alertara-50 transition-colors text-left">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-fingerprint text-alertara-600 text-xl"></i>
                                <div>
                                    <h5 class="font-semibold text-gray-900">Add Evidence</h5>
                                    <p class="text-sm text-gray-600">Weapons, documents, photos, etc.</p>
                                </div>
                            </div>
                        </button>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <!-- Persons Summary -->
                        <div class="p-3 bg-alertara-50 rounded-lg border border-alertara-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-user-circle text-alertara-600"></i>
                                    <span class="text-sm text-gray-700"><span id="personsCount" class="font-semibold">0</span> person(s) added</span>
                                </div>
                                <button type="button" id="editPersonsBtn" class="text-xs text-alertara-600 hover:text-alertara-700 font-semibold">Edit</button>
                            </div>
                        </div>

                        <!-- Evidence Summary -->
                        <div class="p-3 bg-alertara-50 rounded-lg border border-alertara-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-box text-alertara-600"></i>
                                    <span class="text-sm text-gray-700"><span id="evidenceCount" class="font-semibold">0</span> evidence item(s) added</span>
                                </div>
                                <button type="button" id="editEvidenceBtn" class="text-xs text-alertara-600 hover:text-alertara-700 font-semibold">Edit</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 mt-6">
                    <button type="button" id="cancelAddIncident" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2">
                        <i class="fas fa-save"></i>
                        Save Incident
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <!-- Persons Involved Modal -->
    <div id="personsInvolvedModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] flex flex-col">
            <div class="p-6 border-b border-gray-100 bg-white flex-shrink-0">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <i class="fas fa-users mr-2 text-blue-600"></i>Add Person Involved
                    </h3>
                    <button id="closePersonsModal" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="overflow-y-auto flex-1">
            <div class="p-6">
                <form id="addPersonForm">
                    <!-- Person Type Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-person-circle-check mr-2 text-alertara-600"></i>Person Type
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <label class="flex items-center p-3 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-alertara-500 hover:bg-alertara-50 transition-all" data-person-type="complainant">
                                <input type="radio" name="person_type" value="complainant" class="w-4 h-4 text-alertara-600">
                                <span class="ml-3 font-medium text-gray-700">Complainant</span>
                            </label>
                            <label class="flex items-center p-3 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-alertara-500 hover:bg-alertara-50 transition-all" data-person-type="victim">
                                <input type="radio" name="person_type" value="victim" class="w-4 h-4 text-alertara-600">
                                <span class="ml-3 font-medium text-gray-700">Victim</span>
                            </label>
                            <label class="flex items-center p-3 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-alertara-500 hover:bg-alertara-50 transition-all" data-person-type="suspect">
                                <input type="radio" name="person_type" value="suspect" class="w-4 h-4 text-alertara-600">
                                <span class="ml-3 font-medium text-gray-700">Suspect</span>
                            </label>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-4">
                            <i class="fas fa-address-card mr-2 text-gray-700"></i>Personal Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                <input type="text" name="first_name" placeholder="John"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                                <input type="text" name="middle_name" placeholder="Michael"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                <input type="text" name="last_name" placeholder="Doe"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-4">
                            <i class="fas fa-phone mr-2 text-gray-700"></i>Contact Information
                        </h4>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                            <input type="tel" name="contact_number" placeholder="+63 9XX XXXX XXX"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent">
                            <small class="text-gray-500">Include country code (e.g., +63)</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Additional Information</label>
                            <textarea name="other_info" placeholder="E.g., Address, email, age, physical description..."
                                      rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"></textarea>
                            <small class="text-gray-500">Optional: Any additional details about this person</small>
                        </div>
                    </div>

                    <!-- Privacy Notice -->
                    <div class="p-4 bg-alertara-50 border border-alertara-200 rounded-lg mb-6">
                        <p class="text-sm text-alertara-800">
                            <i class="fas fa-shield-alt mr-2 text-alertara-600"></i>
                            <strong>Data Security:</strong> All personal information will be encrypted and securely stored according to data protection regulations.
                        </p>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <button type="button" id="cancelPersons" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="button" id="addPersonBtn" class="px-6 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2">
                            <i class="fas fa-plus"></i>
                            Add Person
                        </button>
                    </div>
                </form>

                <!-- Added Persons List -->
                <div id="personsListContainer" class="mt-6 pt-6 border-t border-gray-200">
                    <h4 class="font-semibold text-gray-900 mb-4">
                        <i class="fas fa-list mr-2 text-gray-700"></i>Persons Added
                    </h4>
                    <div id="personsList" class="space-y-2">
                        <!-- Persons will be listed here -->
                    </div>
                    <div id="noPersonsMsg" class="text-center py-6 text-gray-500">
                        <i class="fas fa-inbox text-2xl mb-2 block text-gray-300"></i>
                        <p>No persons added yet</p>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <!-- Evidence Modal -->
    <div id="evidenceModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] flex flex-col">
            <div class="p-6 border-b border-gray-100 bg-white flex-shrink-0">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <i class="fas fa-fingerprint mr-2 text-green-600"></i>Add Evidence
                    </h3>
                    <button id="closeEvidenceModal" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="overflow-y-auto flex-1">
            <div class="p-6">
                <form id="addEvidenceForm">
                    <!-- Evidence Type Selection with Icons -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-tag mr-2 text-alertara-600"></i>Evidence Type
                        </label>

                        <!-- Custom Dropdown Button -->
                        <div class="relative">
                            <button type="button" id="evidenceTypeBtn" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent text-left flex items-center justify-between bg-white hover:bg-gray-50">
                                <span id="selectedEvidenceLabel" class="flex items-center gap-2">
                                    <i class="fas fa-caret-down text-gray-400"></i>
                                    <span class="text-gray-500">Select evidence type...</span>
                                </span>
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </button>

                            <!-- Hidden select for form submission -->
                            <select name="evidence_type" id="evidenceTypeSelect" class="hidden" required></select>

                            <!-- Custom Dropdown Menu -->
                            <div id="evidenceTypeDropdown" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg z-10 max-h-96 overflow-y-auto">
                                <div class="p-2 space-y-1">
                                    <!-- Weapon -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Weapon">
                                        <i class="fas fa-gun text-red-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Weapon</span>
                                    </button>

                                    <!-- Clothing -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Clothing">
                                        <i class="fas fa-shirt text-blue-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Clothing</span>
                                    </button>

                                    <!-- Fingerprint -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Fingerprint">
                                        <i class="fas fa-fingerprint text-purple-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Fingerprint</span>
                                    </button>

                                    <!-- Biological Sample -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Biological Sample">
                                        <i class="fas fa-dna text-green-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Biological Sample</span>
                                    </button>

                                    <!-- Document -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Document">
                                        <i class="fas fa-file-alt text-yellow-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Document</span>
                                    </button>

                                    <!-- Photo -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Photo">
                                        <i class="fas fa-image text-cyan-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Photo</span>
                                    </button>

                                    <!-- Video -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Video">
                                        <i class="fas fa-video text-pink-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Video</span>
                                    </button>

                                    <!-- Audio -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Audio">
                                        <i class="fas fa-microphone text-orange-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Audio</span>
                                    </button>

                                    <!-- Digital File -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Digital File">
                                        <i class="fas fa-laptop text-indigo-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Digital File</span>
                                    </button>

                                    <!-- Testimonial -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Testimonial">
                                        <i class="fas fa-comment text-teal-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Testimonial</span>
                                    </button>

                                    <!-- Other -->
                                    <button type="button" class="evidence-option w-full text-left px-3 py-2 rounded-lg hover:bg-alertara-50 flex items-center gap-3 transition-colors" data-value="Other">
                                        <i class="fas fa-box text-gray-600 w-5 text-center"></i>
                                        <span class="font-medium text-gray-900">Other</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Evidence Type Note -->
                        <div id="evidenceTypeNote" class="mt-3 p-3 rounded-lg bg-alertara-50 border border-alertara-200 hidden">
                            <p id="noteText" class="text-sm text-alertara-800"></p>
                        </div>
                    </div>

                    <!-- Evidence Details -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-4">
                            <i class="fas fa-info-circle mr-2 text-gray-700"></i>Evidence Details
                        </h4>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" id="evidenceDescription" placeholder="Detailed description of the evidence (e.g., color, size, condition, where found, etc.)..."
                                      rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"></textarea>
                            <small class="text-gray-500">Be as detailed as possible for documentation purposes</small>
                        </div>

                        <!-- File Upload Section -->
                        <div id="fileUploadSection" class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-upload mr-2 text-alertara-600"></i>Upload Evidence Files
                            </label>

                            <!-- Supported Files Info -->
                            <div id="supportedFilesInfo" class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-xs font-semibold text-blue-900 mb-2">
                                    <i class="fas fa-info-circle mr-1"></i>Supported File Types:
                                </p>
                                <p id="supportedExtensions" class="text-xs text-blue-800">All common file types supported</p>
                            </div>

                            <!-- Drop Zone with File Input -->
                            <div class="border-2 border-dashed border-alertara-300 rounded-lg p-8 text-center hover:border-alertara-500 hover:bg-alertara-50 transition-colors cursor-pointer" id="dropZone">
                                <i class="fas fa-cloud-upload-alt text-4xl text-alertara-400 mb-3 block"></i>
                                <p class="text-sm font-medium text-gray-700 mb-1">Click to upload or drag and drop</p>
                                <p class="text-xs text-gray-500">Max file size: 50MB per file | Multiple files supported</p>
                                <input type="file" name="evidence_files" id="evidenceFileInput" class="hidden" multiple>
                            </div>

                            <!-- File Preview List -->
                            <div id="filePreviewContainer" class="mt-4 space-y-2 hidden">
                                <h5 class="text-sm font-semibold text-gray-900 mb-2">Selected Files:</h5>
                                <div id="filePreviewList"></div>
                            </div>
                        </div>

                    </div>

                    <!-- Evidence Categories Info -->
                    <div class="p-4 bg-alertara-50 border border-alertara-200 rounded-lg mb-6">
                        <p class="text-sm text-alertara-800">
                            <i class="fas fa-lightbulb mr-2 text-alertara-600"></i>
                            <strong>Tip:</strong> Evidence can be added during incident creation or updated later as the investigation progresses.
                        </p>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <button type="button" id="cancelEvidence" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="button" id="addEvidenceBtn" class="px-6 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2">
                            <i class="fas fa-plus"></i>
                            Add Evidence
                        </button>
                    </div>
                </form>

                <!-- Added Evidence List -->
                <div id="evidenceListContainer" class="mt-6 pt-6 border-t border-gray-200">
                    <h4 class="font-semibold text-gray-900 mb-4 flex items-center justify-between">
                        <span>
                            <i class="fas fa-file-alt mr-2 text-alertara-600"></i>Evidence Added
                            <span id="evidenceCount" class="ml-2 inline-block px-2 py-1 bg-alertara-100 text-alertara-700 text-xs rounded-full font-semibold">0</span>
                        </span>
                    </h4>
                    <div id="evidenceList" class="space-y-2">
                        <!-- Evidence items will be listed here -->
                    </div>
                    <div id="noEvidenceMsg" class="text-center py-6 text-gray-500">
                        <i class="fas fa-inbox text-2xl mb-2 block text-gray-300"></i>
                        <p>No evidence added yet</p>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <!-- Location Picker Modal with Map -->
    <div id="locationPickerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
            <!-- Modal Header -->
            <div class="p-6 border-b border-gray-200 bg-white flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">
                            <i class="fas fa-map-location-dot mr-2 text-blue-600"></i>Select Crime Location
                        </h2>
                        <p class="text-gray-600 text-sm mt-1">Click on the map to select latitude and longitude</p>
                    </div>
                    <button type="button" id="closeLocationModal" class="text-gray-500 hover:text-gray-700 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="overflow-y-auto flex-1">
            <div class="p-6">
                <!-- Map Container -->
                <div id="locationPickerMap" class="w-full h-96 rounded-lg border-2 border-alertara-300 mb-6"></div>

                <!-- Selected Coordinates Display -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <label class="text-sm font-semibold text-gray-700 block mb-2">
                            <i class="fas fa-map-marker-alt mr-1 text-blue-600"></i>Selected Latitude
                        </label>
                        <p id="selectedLatDisplay" class="text-lg font-mono text-blue-800">Click on map to select</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <label class="text-sm font-semibold text-gray-700 block mb-2">
                            <i class="fas fa-compass mr-1 text-green-600"></i>Selected Longitude
                        </label>
                        <p id="selectedLngDisplay" class="text-lg font-mono text-green-800">Click on map to select</p>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Instructions:</strong> Click anywhere on the map within the QC boundary to select a location. The coordinates will appear above.
                    </p>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button type="button" id="doneLocationBtn"
                            class="flex-1 px-4 py-3 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-check"></i>
                        Done
                    </button>
                    <button type="button" id="cancelLocationBtn"
                            class="flex-1 px-4 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.js"></script>
    <script src="https://unpkg.com/leaflet-heatmap@1.0.0/dist/leaflet-heatmap.js"></script>

    <!-- Modal Control Script (Design Only) -->
    <script>
        // Gemini API Configuration for Title Auto-Generation
        const GEMINI_API_KEY = '{{ env("GEMINI_API_KEY") }}';
        // Using gemini-2.5-flash (latest free tier model)
        const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
        let titleGenerationTimeout;
        let isListening = false;
        let recognition = null;

        // Language prompts for different languages
        const languagePrompts = {
            english: 'You are a crime incident reporting system. Based on the following crime incident description, generate a SHORT and CONCISE incident title in ENGLISH (maximum 8 words). Only return the title, nothing else.',
            tagalog: 'Ikaw ay isang crime incident reporting system. Batay sa sumusunod na crime incident description, lumikha ng isang MAIKLI at MALINAW na incident title sa TAGALOG (maximum 8 words). Ibalik lamang ang title, walang iba.',
            taglish: 'You are a crime incident reporting system. Based sa sumusunod na crime incident description, lumikha ng SHORT and CONCISE incident title sa TAGLISH/TAGALOG with English words (maximum 8 words). Return lang ang title, nothing else.'
        };

        // Manual title generation
        const modalIncidentDescription = document.getElementById('modalIncidentDescription');
        const modalIncidentTitle = document.getElementById('modalIncidentTitle');
        const descriptionLanguage = document.getElementById('descriptionLanguage');
        const geminiStatus = document.getElementById('geminiStatus');
        const geminiStatusText = document.getElementById('geminiStatusText');
        const generateTitleBtn = document.getElementById('generateTitleBtn');
        const voiceInputBtn = document.getElementById('voiceInputBtn');
        const voiceInputStatus = document.getElementById('voiceInputStatus');

        // Initialize Web Speech API for voice input
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        let isGenerating = false;

        if (SpeechRecognition) {
            recognition = new SpeechRecognition();
            recognition.continuous = true;  // Keep listening until user stops
            recognition.interimResults = true;
            recognition.lang = 'en-US';  // Default language

            // Voice input event listeners
            voiceInputBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                if (!isListening) {
                    // Start recording
                    recognition.start();
                    isListening = true;
                    if (voiceInputBtn) voiceInputBtn.classList.add('border-red-500', 'bg-red-50', 'animate-pulse');
                    if (voiceInputStatus) voiceInputStatus.textContent = 'Stop';
                    console.log('[Voice] Recording started');
                } else {
                    // Stop recording
                    recognition.stop();
                    isListening = false;
                }
            });

            recognition.onstart = () => {
                isListening = true;
                if (voiceInputBtn) voiceInputBtn.classList.add('border-red-500', 'bg-red-50', 'animate-pulse');
                if (voiceInputStatus) voiceInputStatus.textContent = 'Stop';
                console.log('[Voice] Microphone permission granted - listening...');
            };

            let finalTranscript = '';

            recognition.onresult = (event) => {
                let interimTranscript = '';

                // Only process final results to avoid duplicates
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;

                    if (event.results[i].isFinal) {
                        // Only add final results to avoid duplicates
                        finalTranscript += transcript + ' ';
                        console.log('[Voice] Final result:', transcript);
                    } else {
                        // Show interim results for real-time feedback (optional)
                        interimTranscript += transcript;
                    }
                }

                // Only update textarea when we have final results
                if (finalTranscript.trim()) {
                    const textarea = document.getElementById('modalIncidentDescription');
                    if (textarea) {
                        const currentValue = textarea.value.trim();

                        // Combine with existing content
                        if (currentValue) {
                            textarea.value = currentValue + ' ' + finalTranscript.trim();
                        } else {
                            textarea.value = finalTranscript.trim();
                        }

                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        console.log('[Voice] Text added:', finalTranscript.trim());
                        finalTranscript = ''; // Reset for next batch of final results
                    }
                }
            };

            recognition.onerror = (event) => {
                console.error('[Voice] Error:', event.error);
                let errorMsg = 'Error';

                switch(event.error) {
                    case 'no-speech':
                        errorMsg = 'No speech detected';
                        break;
                    case 'network':
                        errorMsg = 'Network error';
                        break;
                    case 'audio-capture':
                        errorMsg = 'No microphone';
                        break;
                    case 'not-allowed':
                        errorMsg = 'Permission denied';
                        break;
                    default:
                        errorMsg = event.error;
                }

                if (voiceInputStatus) voiceInputStatus.textContent = errorMsg;
                if (voiceInputBtn) {
                    voiceInputBtn.classList.add('border-red-500', 'bg-red-50');
                    voiceInputBtn.classList.remove('animate-pulse');
                }
            };

            recognition.onend = () => {
                isListening = false;
                if (voiceInputBtn) voiceInputBtn.classList.remove('border-red-500', 'bg-red-50', 'animate-pulse');
                if (voiceInputStatus) voiceInputStatus.textContent = '';
                console.log('[Voice] Recording stopped');

                // Only restart if user didn't explicitly stop it
                if (isListening) {
                    recognition.start();
                }
            };
        } else {
            // Browser doesn't support Web Speech API
            voiceInputBtn.disabled = true;
            voiceInputBtn.title = 'Voice input not supported in this browser';
            voiceInputBtn.classList.add('opacity-50', 'cursor-not-allowed');
            voiceInputStatus.textContent = 'N/A';
        }

        // Helper function to update Gemini status
        function updateGeminiStatus(status) {
            const statusConfig = {
                'generating': { bg: 'bg-blue-100', border: 'border-blue-200', text: 'text-blue-800', icon: 'fa-spinner fa-spin', message: 'Generating...' },
                'error': { bg: 'bg-red-100', border: 'border-red-200', text: 'text-red-800', icon: 'fa-exclamation-circle', message: 'Error' },
                'success': { bg: 'bg-green-100', border: 'border-green-200', text: 'text-green-800', icon: 'fa-check-circle', message: 'Success!' }
            };

            const config = statusConfig[status] || statusConfig['generating'];
            geminiStatus.className = `text-xs px-2 py-1 rounded-full ${config.bg} ${config.text} flex items-center gap-1 whitespace-nowrap border ${config.border} transition-all`;
            geminiStatus.innerHTML = `
                <i class="fas ${config.icon}"></i>
                <span>${config.message}</span>
            `;
            console.log(`[Gemini Status] ${config.message}`);
        }

        // Initialize Gemini on page load
        window.addEventListener('load', () => {
            console.log('🔍 [Gemini Debug] Initializing Gemini API...');
            console.log('📌 API Key:', GEMINI_API_KEY ? '✅ Present' : '❌ Missing');
            console.log('📌 API URL:', GEMINI_API_URL);
        });

        // Generate Title Button Click Handler
        generateTitleBtn?.addEventListener('click', async (e) => {
            e.preventDefault();

            const description = modalIncidentDescription.value.trim();

            if (description.length < 20) {
                alert('⚠️ Please enter at least 20 characters in the description first');
                return;
            }

            if (!GEMINI_API_KEY) {
                alert('❌ Gemini API key is not configured');
                return;
            }

            generateTitleBtn.disabled = true;
            geminiStatus.classList.remove('hidden');
            geminiStatusText.textContent = 'Generating...';

            await generateTitleFromDescription(description);

            generateTitleBtn.disabled = false;

            // Hide status after 2 seconds
            setTimeout(() => {
                geminiStatus.classList.add('hidden');
            }, 2000);
        });

        // Function to generate title using Gemini API
        async function generateTitleFromDescription(description) {
            try {
                updateGeminiStatus('generating');

                const selectedLanguage = descriptionLanguage?.value || 'english';
                const languagePrompt = languagePrompts[selectedLanguage];

                const requestBody = {
                    contents: [{
                        parts: [{
                            text: `${languagePrompt}

Description: "${description}"

Title:`
                        }]
                    }]
                };

                console.log('🚀 [Gemini Request] Sending to API...');
                console.log('📝 Language:', selectedLanguage);
                console.log('📝 Description length:', description.length);
                console.log('🔗 URL:', `${GEMINI_API_URL}?key=${GEMINI_API_KEY.substring(0, 10)}...`);

                const response = await fetch(`${GEMINI_API_URL}?key=${GEMINI_API_KEY}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestBody)
                });

                console.log('📊 [Gemini Response] Status:', response.status, response.statusText);

                if (!response.ok) {
                    const errorData = await response.text();
                    console.error('❌ [Gemini Error] Response body:', errorData);
                    throw new Error(`Gemini API error: ${response.status} ${response.statusText} - ${errorData}`);
                }

                const data = await response.json();
                console.log('✅ [Gemini Success] Response received');
                console.log('📦 Response data:', data);

                if (data.candidates && data.candidates[0] && data.candidates[0].content) {
                    const generatedTitle = data.candidates[0].content.parts[0].text
                        .trim()
                        .replace(/^Title:\s*/i, '')
                        .replace(/['"]/g, '');

                    console.log('✨ [Title Generated]', generatedTitle);

                    // Update title field with generated title
                    if (!modalIncidentTitle.value || modalIncidentTitle.value === '') {
                        modalIncidentTitle.value = generatedTitle;
                        // Trigger change event in case any listeners are attached
                        modalIncidentTitle.dispatchEvent(new Event('change', { bubbles: true }));
                        console.log(`✓ Title filled (${selectedLanguage}):`, generatedTitle);
                        updateGeminiStatus('success');
                    } else {
                        console.log('ℹ️ Title field already has value, replacing with generated title');
                        modalIncidentTitle.value = generatedTitle;
                        modalIncidentTitle.dispatchEvent(new Event('change', { bubbles: true }));
                        updateGeminiStatus('success');
                    }
                } else {
                    console.warn('⚠️ [Gemini Warning] No candidates in response');
                    updateGeminiStatus('error');
                }
            } catch (error) {
                console.error('❌ [Gemini Error] Failed to generate title:', error);
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack,
                    name: error.name
                });
                updateGeminiStatus('error');
                alert('❌ Failed to generate title: ' + error.message);
            }
        }

        // ====== Location Picker Modal with Map ======
        const locationPickerModal = document.getElementById('locationPickerModal');
        const openLocationMapBtn = document.getElementById('openLocationMapBtn');
        const closeLocationModal = document.getElementById('closeLocationModal');
        const doneLocationBtn = document.getElementById('doneLocationBtn');
        const cancelLocationBtn = document.getElementById('cancelLocationBtn');
        const selectedLatDisplay = document.getElementById('selectedLatDisplay');
        const selectedLngDisplay = document.getElementById('selectedLngDisplay');

        let locationPickerMap = null;
        let selectedLocationLat = null;
        let selectedLocationLng = null;
        let locationMarker = null;

        // Open location picker modal
        openLocationMapBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            locationPickerModal.classList.remove('hidden');

            // Initialize map if not already done
            if (!locationPickerMap) {
                initializeLocationPickerMap();
            }

            // Reset map view to QC
            if (locationPickerMap) {
                locationPickerMap.invalidateSize();
            }
        });

        // Close location picker modal
        closeLocationModal?.addEventListener('click', () => {
            locationPickerModal.classList.add('hidden');
        });

        cancelLocationBtn?.addEventListener('click', () => {
            locationPickerModal.classList.add('hidden');
        });

        // Done button - populate lat/lng fields
        doneLocationBtn?.addEventListener('click', () => {
            if (selectedLocationLat !== null && selectedLocationLng !== null) {
                modalLatitude.value = selectedLocationLat;
                modalLongitude.value = selectedLocationLng;
                modalLatitude.dispatchEvent(new Event('change', { bubbles: true }));
                modalLongitude.dispatchEvent(new Event('change', { bubbles: true }));
                locationPickerModal.classList.add('hidden');
                console.log('[Location] Coordinates set - Lat:', selectedLocationLat, 'Lng:', selectedLocationLng);
            } else {
                alert('❌ Please select a location on the map first');
            }
        });

        // Initialize location picker map
        function initializeLocationPickerMap() {
            // Map center (QC center)
            const qcCenter = [14.6759, 121.0437];

            locationPickerMap = L.map('locationPickerMap').setView(qcCenter, 12);

            // OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(locationPickerMap);

            // Load and add QC boundary from GeoJSON
            fetch('{{ asset("qc_map.geojson") }}')
                .then(response => response.json())
                .then(data => {
                    L.geoJSON(data, {
                        style: {
                            color: '#274d4c',
                            weight: 3,
                            opacity: 1,
                            fillOpacity: 0.08,
                            fillColor: '#e8f5f3'
                        },
                        onEachFeature: (feature, layer) => {
                            layer.on('mouseover', () => {
                                layer.setStyle({
                                    weight: 4,
                                    fillOpacity: 0.15,
                                    fillColor: '#d0ebe7'
                                });
                            });
                            layer.on('mouseout', () => {
                                layer.setStyle({
                                    weight: 3,
                                    fillOpacity: 0.08,
                                    fillColor: '#e8f5f3'
                                });
                            });
                        }
                    }).addTo(locationPickerMap);

                    // Fit map to QC boundary
                    const geoJSONBounds = L.geoJSON(data).getBounds();
                    locationPickerMap.fitBounds(geoJSONBounds, { padding: [50, 50] });
                })
                .catch(err => console.error('[Location] Error loading GeoJSON:', err));

            // Handle map clicks to select location
            locationPickerMap.on('click', (e) => {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;

                // Validate if point is within QC range (approximate)
                if (lat >= 14.5 && lat <= 14.8 && lng >= 120.9 && lng <= 121.2) {
                    selectedLocationLat = lat.toFixed(6);
                    selectedLocationLng = lng.toFixed(6);

                    // Update display
                    selectedLatDisplay.textContent = selectedLocationLat;
                    selectedLngDisplay.textContent = selectedLocationLng;

                    // Remove old marker and add new one
                    if (locationMarker) {
                        locationPickerMap.removeLayer(locationMarker);
                    }

                    locationMarker = L.circleMarker([lat, lng], {
                        radius: 8,
                        fillColor: '#3b82f6',
                        color: '#1e40af',
                        weight: 3,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(locationPickerMap);

                    // Add popup
                    locationMarker.bindPopup(`
                        <div class="text-center">
                            <strong>Selected Location</strong><br>
                            Lat: ${selectedLocationLat}<br>
                            Lng: ${selectedLocationLng}
                        </div>
                    `).openPopup();

                    console.log('[Location] Selected - Lat:', selectedLocationLat, 'Lng:', selectedLocationLng);
                } else {
                    alert('⚠️ Please select a location within Quezon City boundaries');
                    console.warn('[Location] Click outside QC boundary - Lat:', lat, 'Lng:', lng);
                }
            });

            console.log('[Location] Map initialized successfully');
        }

        // Other Information Section Toggle
        const toggleOtherInfo = document.getElementById('toggleOtherInfo');
        const otherInfoContent = document.getElementById('otherInfoContent');
        const otherInfoChevron = document.getElementById('otherInfoChevron');

        toggleOtherInfo?.addEventListener('click', () => {
            otherInfoContent.classList.toggle('hidden');
            otherInfoChevron.classList.toggle('rotate-90');
        });

        // Clearance Date Field Visibility
        const modalClearanceStatus = document.getElementById('modalClearanceStatus');
        const clearanceDateField = document.getElementById('clearanceDateField');

        modalClearanceStatus?.addEventListener('change', (e) => {
            if (e.target.value === 'cleared') {
                clearanceDateField.classList.remove('hidden');
            } else {
                clearanceDateField.classList.add('hidden');
                document.getElementById('modalClearanceDate').value = ''; // Clear the date when hidden
            }
        });

        // Modal References
        const addIncidentModal = document.getElementById('addIncidentModal');
        const personsInvolvedModal = document.getElementById('personsInvolvedModal');
        const evidenceModal = document.getElementById('evidenceModal');

        // Button References
        const addIncidentBtn = document.getElementById('addIncidentBtn');
        const openPersonsModal = document.getElementById('openPersonsModal');
        const openEvidenceModal = document.getElementById('openEvidenceModal');
        const editPersonsBtn = document.getElementById('editPersonsBtn');
        const editEvidenceBtn = document.getElementById('editEvidenceBtn');

        // Close Buttons
        const closeAddModal = document.getElementById('closeAddModal');
        const closePersonsModal = document.getElementById('closePersonsModal');
        const closeEvidenceModal = document.getElementById('closeEvidenceModal');
        const cancelPersons = document.getElementById('cancelPersons');
        const cancelEvidence = document.getElementById('cancelEvidence');

        // Action Buttons
        const addPersonBtn = document.getElementById('addPersonBtn');
        const addEvidenceBtn = document.getElementById('addEvidenceBtn');

        // Data Storage - Make global so TypeScript can access them
        window.personsInvolvedList = [];
        window.evidenceList = [];

        // Open Add Incident Modal
        addIncidentBtn?.addEventListener('click', () => {
            addIncidentModal.classList.remove('hidden');
        });

        // Open Persons Modal from Add Incident
        openPersonsModal?.addEventListener('click', (e) => {
            e.preventDefault();
            addIncidentModal.classList.add('hidden');
            personsInvolvedModal.classList.remove('hidden');
        });

        // Open Evidence Modal from Add Incident
        openEvidenceModal?.addEventListener('click', (e) => {
            e.preventDefault();
            addIncidentModal.classList.add('hidden');
            evidenceModal.classList.remove('hidden');
            // Reset file uploads for fresh modal
            selectedFiles = [];
            document.getElementById('filePreviewContainer').classList.add('hidden');
            document.getElementById('filePreviewList').innerHTML = '';
            document.getElementById('supportedFilesInfo').classList.add('hidden');
        });

        // Edit Persons (back to modal)
        editPersonsBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            personsInvolvedModal.classList.remove('hidden');
        });

        // Edit Evidence (back to modal)
        editEvidenceBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            evidenceModal.classList.remove('hidden');
        });

        // Close Persons Modal
        closePersonsModal?.addEventListener('click', () => {
            personsInvolvedModal.classList.add('hidden');
            addIncidentModal.classList.remove('hidden');
        });

        // Close Evidence Modal
        closeEvidenceModal?.addEventListener('click', () => {
            evidenceModal.classList.add('hidden');
            addIncidentModal.classList.remove('hidden');
            // Reset file uploads
            selectedFiles = [];
            document.getElementById('filePreviewContainer').classList.add('hidden');
            document.getElementById('filePreviewList').innerHTML = '';
        });

        // Cancel Persons
        cancelPersons?.addEventListener('click', () => {
            personsInvolvedModal.classList.add('hidden');
            addIncidentModal.classList.remove('hidden');
        });

        // Cancel Evidence
        cancelEvidence?.addEventListener('click', () => {
            evidenceModal.classList.add('hidden');
            addIncidentModal.classList.remove('hidden');
            // Reset file uploads
            selectedFiles = [];
            document.getElementById('filePreviewContainer').classList.add('hidden');
            document.getElementById('filePreviewList').innerHTML = '';
        });

        // Close Add Incident Modal
        closeAddModal?.addEventListener('click', () => {
            addIncidentModal.classList.add('hidden');
            resetForms();
        });

        // Add Person
        addPersonBtn?.addEventListener('click', () => {
            const form = document.getElementById('addPersonForm');
            const formData = new FormData(form);

            const personType = formData.get('person_type');
            const firstName = formData.get('first_name');
            const lastName = formData.get('last_name');

            if (!personType || !firstName || !lastName) {
                alert('Please select person type and fill in name fields');
                return;
            }

            // Add to list
            const person = {
                id: Date.now(),
                type: personType,
                firstName: firstName,
                middleName: formData.get('middle_name') || '',
                lastName: lastName,
                contactNumber: formData.get('contact_number') || '',
                otherInfo: formData.get('other_info') || ''
            };

            window.personsInvolvedList.push(person);
            updatePersonsList();
            form.reset();
            document.querySelectorAll('input[name="person_type"]').forEach(el => el.checked = false);
        });

        // Add Evidence
        addEvidenceBtn?.addEventListener('click', async () => {
            // Get evidence type from the label (more reliable method)
            const selectedEvidenceLabel = document.getElementById('selectedEvidenceLabel');
            const labelText = selectedEvidenceLabel?.innerText?.trim() || '';

            // Check if a real selection was made (not the placeholder text)
            if (!labelText || labelText.includes('Select an evidence type')) {
                alert('❌ Please select an evidence type first');
                return;
            }

            // Extract evidence type from label
            const evidenceType = labelText.split('\n')[0]?.trim();

            if (!evidenceType) {
                alert('❌ Please select an evidence type first');
                return;
            }

            // Get description from textarea
            const descriptionElement = document.getElementById('evidenceDescription');
            const description = descriptionElement?.value?.trim() || '';

            // Show loading state
            const btnText = addEvidenceBtn.innerHTML;
            addEvidenceBtn.disabled = true;
            addEvidenceBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading to Cloudinary...';

            try {
                // Upload files to Cloudinary
                let cloudinaryUrls = [];
                if (selectedFiles.length > 0) {
                    console.log(`📤 Uploading ${selectedFiles.length} file(s) to Cloudinary...`);
                    cloudinaryUrls = await uploadFilesToCloudinary(selectedFiles);
                }

                // Create separate evidence record for each uploaded file
                if (cloudinaryUrls.length > 0) {
                    cloudinaryUrls.forEach((urlObj, index) => {
                        const evidence = {
                            id: Date.now() + index, // Unique ID for each file
                            type: evidenceType,
                            description: description,
                            link: urlObj.url // One URL per evidence record
                        };
                        window.evidenceList.push(evidence);
                        console.log(`✅ Evidence item ${index + 1} added:`, evidence);
                    });
                    updateEvidenceList(); // Display the added evidence at the bottom
                    console.log('📋 Total evidence items:', window.evidenceList.length);
                } else {
                    // No files uploaded
                    console.warn('⚠️ No files were uploaded');
                    alert('⚠️ No files were uploaded. Please select files and try again.');
                    return; // Exit early if no files
                }

                // Show success message with number of files added
                alert(`✅ Evidence added! ${cloudinaryUrls.length} file${cloudinaryUrls.length > 1 ? 's' : ''} created ${cloudinaryUrls.length} record${cloudinaryUrls.length > 1 ? 's' : ''}. (Total: ${window.evidenceList.length} items)`);

                // Reset form
                const form = document.getElementById('addEvidenceForm');
                form?.reset();

                const evidenceTypeSelect = document.getElementById('evidenceTypeSelect');
                evidenceTypeSelect.value = '';

                document.getElementById('selectedEvidenceLabel').innerHTML = `
                    <i class="fas fa-chevron-down text-gray-400"></i>
                    <span class="text-gray-600">Select an evidence type...</span>
                `;

                // Reset file uploads
                selectedFiles = [];
                document.getElementById('filePreviewContainer').classList.add('hidden');
                document.getElementById('filePreviewList').innerHTML = '';
                document.getElementById('supportedFilesInfo').classList.add('hidden');

                // Clear description
                descriptionElement.value = '';

                console.log('✅ Evidence form reset - ready for next entry');
            } catch (error) {
                console.error('❌ Error adding evidence:', error);
                alert('Error uploading evidence. Please try again.');
            } finally {
                // Restore button
                addEvidenceBtn.disabled = false;
                addEvidenceBtn.innerHTML = btnText;
            }
        });

        // Update Persons List Display
        function updatePersonsList() {
            const list = document.getElementById('personsList');
            const noMsg = document.getElementById('noPersonsMsg');
            const count = document.getElementById('personsCount');

            count.textContent = window.personsInvolvedList.length;

            if (window.personsInvolvedList.length === 0) {
                noMsg.classList.remove('hidden');
                list.innerHTML = '';
                return;
            }

            noMsg.classList.add('hidden');
            list.innerHTML = window.personsInvolvedList.map(person => {
                const fullName = [person.firstName, person.middleName, person.lastName]
                    .filter(Boolean)
                    .join(' ');
                return `
                <div class="p-3 bg-alertara-50 border border-alertara-200 rounded-lg flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-user-circle text-alertara-600 text-lg"></i>
                        <div>
                            <p class="font-medium text-gray-900">${fullName}</p>
                            <p class="text-sm text-gray-600 capitalize">${person.type}</p>
                            ${person.contactNumber ? `<p class="text-xs text-gray-500">📞 ${person.contactNumber}</p>` : ''}
                        </div>
                    </div>
                    <button type="button" onclick="removePersonWithId(${person.id})" class="text-red-500 hover:text-red-700 transition-colors">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            }).join('');
        }

        // Update Evidence List Display
        function updateEvidenceList() {
            const list = document.getElementById('evidenceList');
            const noMsg = document.getElementById('noEvidenceMsg');
            const count = document.getElementById('evidenceCount');

            count.textContent = window.evidenceList.length;

            if (window.evidenceList.length === 0) {
                noMsg.classList.remove('hidden');
                list.innerHTML = '';
                return;
            }

            noMsg.classList.add('hidden');
            list.innerHTML = window.evidenceList.map(evidence => `
                <div class="p-3 bg-alertara-50 border border-alertara-200 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3 flex-1">
                            <i class="fas fa-box text-alertara-600 text-lg"></i>
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">${evidence.type}</p>
                                <p class="text-sm text-gray-600">${evidence.description || 'No description'}</p>
                                ${evidence.link ? `
                                    <p class="text-xs text-alertara-600 mt-1">
                                        <i class="fas fa-cloud mr-1"></i>
                                        <a href="${evidence.link}" target="_blank" class="hover:underline">View on Cloudinary</a>
                                    </p>
                                ` : ''}
                            </div>
                        </div>
                        <button type="button" onclick="removeEvidenceWithId(${evidence.id})" class="text-red-500 hover:text-red-700 transition-colors ml-2 flex-shrink-0">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    ${evidence.files && evidence.files.length > 0 ? `
                        <div class="ml-8 pt-2 border-t border-alertara-200">
                            <p class="text-xs font-semibold text-alertara-700 mb-2">
                                <i class="fas fa-cloud mr-1"></i>${evidence.files.length} file(s) uploaded to Cloudinary
                            </p>
                            <div class="space-y-1">
                                ${evidence.files.map(file => `
                                    <div class="flex items-center gap-2 text-xs pl-4">
                                        <i class="fas fa-cloud text-alertara-500"></i>
                                        <span class="text-gray-700">${file.name}</span>
                                        <span class="text-gray-500">(${(file.size / 1024 / 1024).toFixed(2)}MB)</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : `
                        <div class="ml-8 pt-2 border-t border-alertara-200">
                            <p class="text-xs text-gray-500 italic">No Cloudinary link provided</p>
                        </div>
                    `}
                </div>
            `).join('');
        }

        // Remove Person
        function removePersonWithId(id) {
            window.personsInvolvedList = window.personsInvolvedList.filter(p => p.id !== id);
            updatePersonsList();
        }

        // Remove Evidence
        function removeEvidenceWithId(id) {
            window.evidenceList = window.evidenceList.filter(e => e.id !== id);
            updateEvidenceList();
        }

        // Reset Forms
        function resetForms() {
            window.personsInvolvedList = [];
            window.evidenceList = [];
            selectedFiles = [];
            document.getElementById('addIncidentForm').reset();
            document.getElementById('addPersonForm').reset();
            document.getElementById('addEvidenceForm').reset();
            document.getElementById('filePreviewContainer').classList.add('hidden');
            document.getElementById('filePreviewList').innerHTML = '';
            document.getElementById('supportedFilesInfo').classList.add('hidden');

            // Reset Other Information section
            otherInfoContent.classList.add('hidden');
            otherInfoChevron.classList.remove('rotate-90');
            clearanceDateField.classList.add('hidden');

            // Reset Gemini status
            geminiStatus.classList.add('hidden');

            updatePersonsList();
            updateEvidenceList(); // Refresh evidence display
        }

        // File Upload Configuration
        const dropZone = document.getElementById('dropZone');
        const evidenceFileInput = document.getElementById('evidenceFileInput');
        let selectedFiles = []; // Store selected files locally

        // Click to upload
        dropZone?.addEventListener('click', () => {
            evidenceFileInput?.click();
        });

        // File input change
        evidenceFileInput?.addEventListener('change', (e) => {
            handleFileSelection(e.target.files);
        });

        // Drag and drop
        dropZone?.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('border-alertara-500', 'bg-alertara-50');
        });

        dropZone?.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('border-alertara-500', 'bg-alertara-50');
        });

        dropZone?.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('border-alertara-500', 'bg-alertara-50');
            handleFileSelection(e.dataTransfer.files);
        });

        // Handle file selection
        function handleFileSelection(files) {
            if (files.length === 0) return;

            const maxSize = 50 * 1024 * 1024; // 50MB
            const invalidFiles = [];

            for (let i = 0; i < files.length; i++) {
                const file = files[i];

                // Validate file size
                if (file.size > maxSize) {
                    invalidFiles.push(`${file.name} (exceeds 50MB limit)`);
                    continue;
                }

                // Add to selected files
                selectedFiles.push({
                    id: Date.now() + i,
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    file: file // Store actual File object for upload
                });

                console.log(`✅ File selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)}MB)`);
            }

            // Show error if any files were invalid
            if (invalidFiles.length > 0) {
                alert(`The following files were not added:\n\n${invalidFiles.join('\n')}`);
            }

            // Update preview
            if (selectedFiles.length > 0) {
                updateFilePreview();
            }

            // Reset file input
            evidenceFileInput.value = '';
        }

        // Update file preview list
        function updateFilePreview() {
            const container = document.getElementById('filePreviewContainer');
            const list = document.getElementById('filePreviewList');

            if (selectedFiles.length === 0) {
                container.classList.add('hidden');
                return;
            }

            container.classList.remove('hidden');
            list.innerHTML = selectedFiles.map(file => `
                <div class="p-3 bg-alertara-50 border border-alertara-200 rounded-lg flex items-center justify-between">
                    <div class="flex items-center gap-2 flex-1">
                        <i class="fas fa-file text-alertara-600"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">${file.name}</p>
                            <p class="text-xs text-gray-500">${(file.size / 1024 / 1024).toFixed(2)}MB</p>
                        </div>
                    </div>
                    <button type="button" onclick="removeSelectedFile(${file.id})" class="text-red-500 hover:text-red-700 transition-colors ml-2 flex-shrink-0">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `).join('');
        }

        // Remove file from selection
        function removeSelectedFile(fileId) {
            selectedFiles = selectedFiles.filter(f => f.id !== fileId);
            updateFilePreview();
        }

        // Upload files to Cloudinary with signed upload
        async function uploadFilesToCloudinary(files) {
            const cloudinaryUrls = [];

            // Get signature from server
            let signatureData;
            try {
                const sigResponse = await fetch('{{ route("cloudinary.signature") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({})
                });

                if (!sigResponse.ok) {
                    throw new Error('Failed to get Cloudinary signature');
                }

                signatureData = await sigResponse.json();
                console.log('[Cloudinary] Signature obtained successfully');
            } catch (error) {
                console.error('[Cloudinary] Error getting signature:', error);
                throw error;
            }

            for (const fileObj of files) {
                const formData = new FormData();
                formData.append('file', fileObj.file);
                formData.append('api_key', signatureData.api_key);
                formData.append('folder', signatureData.folder);
                formData.append('timestamp', signatureData.timestamp);
                formData.append('upload_preset', signatureData.upload_preset);
                formData.append('signature', signatureData.signature);

                try {
                    const response = await fetch(`https://api.cloudinary.com/v1_1/${signatureData.cloud_name}/auto/upload`, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.secure_url) {
                        cloudinaryUrls.push({
                            name: fileObj.name,
                            url: data.secure_url,
                            size: fileObj.size
                        });
                        console.log('✅ File uploaded to Cloudinary:', data.secure_url);
                    } else {
                        console.error('❌ Cloudinary upload failed:', data);
                    }
                } catch (error) {
                    console.error('❌ Upload error:', error);
                }
            }

            return cloudinaryUrls;
        }

        // Supported file extensions mapping
        const supportedExtensionsMap = {
            'Photo': 'JPG, PNG, GIF, BMP, WEBP, TIFF',
            'Video': 'MP4, MKV, AVI, MOV, WMV, FLV, WEBM',
            'Audio': 'MP3, WAV, FLAC, AAC, OGG, M4A, WMA',
            'Document': 'PDF, DOC, DOCX, TXT, XLS, XLSX, PPT, PPTX',
            'Fingerprint': 'JPG, PNG, PDF (high-resolution scans)',
            'Weapon': 'JPG, PNG, PDF (detailed photos and diagrams)',
            'Clothing': 'JPG, PNG, GIF (multiple angles recommended)',
            'Biological Sample': 'PDF, JPG, PNG (lab reports and photos)',
            'Digital File': 'Any file type (ZIP, EXE, ISO, IMG, etc.)',
            'Testimonial': 'MP3, WAV, MP4, MOV, PDF, DOC, DOCX, TXT',
            'Other': 'Any common file type'
        };

        // Evidence Type Descriptions/Notes
        const evidenceTypeNotes = {
            'Weapon': '<i class="fas fa-exclamation-triangle mr-2"></i><strong>Weapon:</strong> Physical weapons used in the crime. Upload clear photos/documentation of the weapon condition, type, and markings. Chain of custody is critical.',
            'Clothing': '<i class="fas fa-shirt mr-2"></i><strong>Clothing:</strong> Clothing worn by suspect, victim, or found at crime scene. Document color, condition, damage, and any stains. Photos of labels and detailed areas are important.',
            'Fingerprint': '<i class="fas fa-fingerprint mr-2"></i><strong>Fingerprint:</strong> Latent or visible fingerprints from crime scene. Submit as high-quality photos, scans, or digital impressions. Reference the location where found.',
            'Biological Sample': '<i class="fas fa-dna mr-2"></i><strong>Biological Sample:</strong> Blood, saliva, hair, or tissue samples. Include collection date, method, storage conditions. Handle with proper biohazard protocols.',
            'Document': '<i class="fas fa-file-alt mr-2"></i><strong>Document:</strong> Written evidence such as notes, contracts, or records. Upload original and copies. Document condition, date found, and chain of custody.',
            'Photo': '<i class="fas fa-camera mr-2"></i><strong>Photo:</strong> Photographs of crime scene, evidence, or persons. High resolution recommended. Include date, time, location metadata when available.',
            'Video': '<i class="fas fa-video mr-2"></i><strong>Video:</strong> CCTV footage, surveillance recordings, or recorded interviews. Include timestamp, source, and duration. Preserve original file format.',
            'Audio': '<i class="fas fa-microphone mr-2"></i><strong>Audio:</strong> Recorded statements, interviews, or surveillance audio. Document speaker, date, time, and duration. Note any background sounds or quality issues.',
            'Digital File': '<i class="fas fa-computer mr-2"></i><strong>Digital File:</strong> Computer files, emails, messages, or digital data. Include metadata, file type, and creation date. Ensure proper chain of custody for digital evidence.',
            'Testimonial': '<i class="fas fa-quote-left mr-2"></i><strong>Testimonial:</strong> Witness statements or recorded interviews. Document witness identity, date, and interviewer. Include video/audio/written transcript as available.',
            'Other': '<i class="fas fa-box mr-2"></i><strong>Other:</strong> Any other evidence not listed above. Provide detailed description of what the evidence is and why it\'s relevant to the case.'
        };

        // Update Cloudinary File Preview
        function updateCloudinaryFilePreview() {
            const container = document.getElementById('filePreviewContainer');
            const list = document.getElementById('filePreviewList');

            if (cloudinaryFileUrls.length === 0) {
                container.classList.add('hidden');
                return;
            }

            container.classList.remove('hidden');
            list.innerHTML = cloudinaryFileUrls.map((file, index) => `
                <div class="p-3 bg-alertara-50 border border-alertara-200 rounded-lg flex items-center justify-between">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <i class="fas fa-cloud text-alertara-600"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">${file.name}</p>
                            <p class="text-xs text-gray-500">${(file.size / 1024 / 1024).toFixed(2)}MB</p>
                            <a href="${file.url}" target="_blank" class="text-xs text-alertara-600 hover:text-alertara-700 underline">View in Cloudinary</a>
                        </div>
                    </div>
                    <button type="button" onclick="removeCloudinaryFile(${index})" class="text-red-500 hover:text-red-700 transition-colors ml-2 flex-shrink-0">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `).join('');
        }

        // Remove file from Cloudinary upload list
        function removeCloudinaryFile(index) {
            cloudinaryFileUrls.splice(index, 1);
            updateCloudinaryFilePreview();
        }

        // Custom Evidence Type Dropdown Handler
        const evidenceTypeBtn = document.getElementById('evidenceTypeBtn');
        const evidenceTypeDropdown = document.getElementById('evidenceTypeDropdown');
        const evidenceOptions = document.querySelectorAll('.evidence-option');
        const selectedEvidenceLabel = document.getElementById('selectedEvidenceLabel');

        // Icon mapping for evidence types
        const evidenceIcons = {
            'Weapon': 'fas fa-gun',
            'Clothing': 'fas fa-shirt',
            'Fingerprint': 'fas fa-fingerprint',
            'Biological Sample': 'fas fa-dna',
            'Document': 'fas fa-file-alt',
            'Photo': 'fas fa-image',
            'Video': 'fas fa-video',
            'Audio': 'fas fa-microphone',
            'Digital File': 'fas fa-laptop',
            'Testimonial': 'fas fa-comment',
            'Other': 'fas fa-box'
        };

        const evidenceColors = {
            'Weapon': 'text-red-600',
            'Clothing': 'text-blue-600',
            'Fingerprint': 'text-purple-600',
            'Biological Sample': 'text-green-600',
            'Document': 'text-yellow-600',
            'Photo': 'text-cyan-600',
            'Video': 'text-pink-600',
            'Audio': 'text-orange-600',
            'Digital File': 'text-indigo-600',
            'Testimonial': 'text-teal-600',
            'Other': 'text-gray-600'
        };

        // Toggle dropdown
        evidenceTypeBtn?.addEventListener('click', () => {
            evidenceTypeDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.relative') && evidenceTypeBtn) {
                evidenceTypeDropdown.classList.add('hidden');
            }
        });

        // Handle evidence type selection
        evidenceOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.preventDefault();
                const value = option.dataset.value;
                const icon = evidenceIcons[value];
                const color = evidenceColors[value];

                // Update hidden select
                document.getElementById('evidenceTypeSelect').value = value;

                // Update button display
                selectedEvidenceLabel.innerHTML = `
                    <i class="${icon} ${color} w-5 text-center"></i>
                    <span class="font-medium text-gray-900">${value}</span>
                `;

                // Close dropdown
                evidenceTypeDropdown.classList.add('hidden');

                // Trigger change event
                const event = new Event('change', { bubbles: true });
                document.getElementById('evidenceTypeSelect').dispatchEvent(event);
            });
        });

        // Update file accept attribute based on evidence type
        const evidenceTypeSelect = document.getElementById('evidenceTypeSelect');

        evidenceTypeSelect?.addEventListener('change', (e) => {
            const selectedType = e.target.value;
            const fileAccept = fileAcceptMap[selectedType] || '*';
            evidenceFileInput.accept = fileAccept;

            // Update evidence type note
            const noteContainer = document.getElementById('evidenceTypeNote');
            const noteText = document.getElementById('noteText');

            if (selectedType && evidenceTypeNotes[selectedType]) {
                noteText.innerHTML = evidenceTypeNotes[selectedType];
                noteContainer.classList.remove('hidden');
            } else {
                noteContainer.classList.add('hidden');
            }

            // Update supported files info
            const supportedFilesInfo = document.getElementById('supportedFilesInfo');
            const supportedExtensions = document.getElementById('supportedExtensions');

            if (selectedType && supportedExtensionsMap[selectedType]) {
                supportedExtensions.textContent = supportedExtensionsMap[selectedType];
                supportedFilesInfo.classList.remove('hidden');
            } else {
                supportedFilesInfo.classList.add('hidden');
            }

            // Store current evidence type for file validation
            evidenceFileInput.dataset.evidenceType = selectedType;

            console.log(`Evidence type selected: ${selectedType}, accept: ${fileAccept}`);
        });


        // Form Submission Handler - GLOBAL submission lock
        // Request desktop notification permission on page load
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    console.log('✅ Desktop notifications enabled');
                }
            });
        }

        // Initialize
        updatePersonsList();
        updateEvidenceList();
    </script>

    @vite(['resources/js/app.js', 'resources/js/crime-page.ts', 'resources/js/notification-manager.ts'])
</body>
</html>
