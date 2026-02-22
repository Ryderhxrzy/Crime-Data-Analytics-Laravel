<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Active Alerts - Crime Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/js/app.js'])

    <style>
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
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
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Active Alerts</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Monitor active alert notifications in real-time</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="location.reload()" class="px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2 shadow-sm">
                            <i class="fas fa-refresh"></i>
                            <span class="hidden sm:inline">Refresh</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Active</p>
                            <p class="text-3xl font-bold text-red-600 mt-1">12</p>
                        </div>
                        <i class="fas fa-bell text-4xl text-red-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Critical</p>
                            <p class="text-3xl font-bold text-red-700 mt-1">3</p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-4xl text-red-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">High</p>
                            <p class="text-3xl font-bold text-orange-600 mt-1">5</p>
                        </div>
                        <i class="fas fa-triangle-exclamation text-4xl text-orange-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Medium</p>
                            <p class="text-3xl font-bold text-yellow-600 mt-1">4</p>
                        </div>
                        <i class="fas fa-circle-exclamation text-4xl text-yellow-200"></i>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Severity</label>
                        <select class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Severities</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Location</label>
                        <select class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Locations</option>
                            <option value="downtown">Downtown District</option>
                            <option value="port">Port Area</option>
                            <option value="cbd">Central Business District</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Alert Type</label>
                        <select class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Types</option>
                            <option value="surge">Crime Surge</option>
                            <option value="hotspot">Hotspot Detection</option>
                            <option value="pattern">Pattern Detected</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">&nbsp;</label>
                        <button class="w-full px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors text-sm font-medium">
                            <i class="fas fa-filter mr-2"></i> Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active Alerts Table -->
            <div class="mt-6 bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">All Active Alerts</h2>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">Show:</span>
                            <select class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Severity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Alert Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Triggered</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <!-- Alert 1 - Critical -->
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> CRITICAL
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 font-medium">Crime Surge Detected</td>
                                <td class="px-6 py-4 text-sm text-gray-900">Downtown District</td>
                                <td class="px-6 py-4 text-sm text-gray-600">Unauthorized crime incidents exceeded 15 in last 2 hours</td>
                                <td class="px-6 py-4 text-sm text-gray-600">2 minutes ago</td>
                                <td class="px-6 py-4 text-sm">
                                    <button class="text-alertara-600 hover:text-alertara-800 font-medium mr-3">View</button>
                                    <button class="text-green-600 hover:text-green-800 font-medium">Resolve</button>
                                </td>
                            </tr>

                            <!-- Alert 2 - Critical -->
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> CRITICAL
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 font-medium">Hotspot Detected</td>
                                <td class="px-6 py-4 text-sm text-gray-900">Port Area</td>
                                <td class="px-6 py-4 text-sm text-gray-600">High crime concentration detected in this area</td>
                                <td class="px-6 py-4 text-sm text-gray-600">5 minutes ago</td>
                                <td class="px-6 py-4 text-sm">
                                    <button class="text-alertara-600 hover:text-alertara-800 font-medium mr-3">View</button>
                                    <button class="text-green-600 hover:text-green-800 font-medium">Resolve</button>
                                </td>
                            </tr>

                            <!-- Alert 3 - High -->
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-800">
                                        <i class="fas fa-triangle-exclamation mr-1"></i> HIGH
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 font-medium">Pattern Detected</td>
                                <td class="px-6 py-4 text-sm text-gray-900">Central Business District</td>
                                <td class="px-6 py-4 text-sm text-gray-600">Repeat offender pattern identified in theft cases</td>
                                <td class="px-6 py-4 text-sm text-gray-600">12 minutes ago</td>
                                <td class="px-6 py-4 text-sm">
                                    <button class="text-alertara-600 hover:text-alertara-800 font-medium mr-3">View</button>
                                    <button class="text-green-600 hover:text-green-800 font-medium">Resolve</button>
                                </td>
                            </tr>

                            <!-- Alert 4 - High -->
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-800">
                                        <i class="fas fa-triangle-exclamation mr-1"></i> HIGH
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 font-medium">Crime Surge Detected</td>
                                <td class="px-6 py-4 text-sm text-gray-900">Residential Zone</td>
                                <td class="px-6 py-4 text-sm text-gray-600">Burglary incidents increased by 40% in past 24 hours</td>
                                <td class="px-6 py-4 text-sm text-gray-600">18 minutes ago</td>
                                <td class="px-6 py-4 text-sm">
                                    <button class="text-alertara-600 hover:text-alertara-800 font-medium mr-3">View</button>
                                    <button class="text-green-600 hover:text-green-800 font-medium">Resolve</button>
                                </td>
                            </tr>

                            <!-- Alert 5 - Medium -->
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-circle-exclamation mr-1"></i> MEDIUM
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 font-medium">Threshold Alert</td>
                                <td class="px-6 py-4 text-sm text-gray-900">Shopping District</td>
                                <td class="px-6 py-4 text-sm text-gray-600">Property crimes approaching alert threshold</td>
                                <td class="px-6 py-4 text-sm text-gray-600">25 minutes ago</td>
                                <td class="px-6 py-4 text-sm">
                                    <button class="text-alertara-600 hover:text-alertara-800 font-medium mr-3">View</button>
                                    <button class="text-green-600 hover:text-green-800 font-medium">Resolve</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="p-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing 5 of 12 active alerts
                        </div>
                        <div class="flex gap-2">
                            <button class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Previous</button>
                            <button class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @stack('scripts')
</body>
</html>
