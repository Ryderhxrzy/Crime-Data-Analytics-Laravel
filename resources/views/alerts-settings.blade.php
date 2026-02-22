<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Threshold Settings - Crime Management System</title>
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
        .tab-btn.active {
            @apply text-gray-900 bg-alertara-50;
        }
        .tab-btn {
            @apply text-gray-700;
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
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Threshold Settings</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Configure alert thresholds, triggers, and notification preferences</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button class="px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2 shadow-sm">
                            <i class="fas fa-save"></i>
                            <span class="hidden sm:inline">Save Settings</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Left: Settings Navigation -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden sticky top-24">
                        <div class="bg-alertara-50 border-b border-gray-200 p-4">
                            <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                <i class="fas fa-sliders-h text-alertara-600"></i>Settings Menu
                            </h3>
                        </div>
                        <nav class="divide-y divide-gray-200">
                            <button onclick="switchTab('general')" class="w-full text-left px-4 py-3 text-sm font-medium bg-alertara-50 text-gray-900 hover:bg-alertara-100 transition-colors tab-btn active flex items-center gap-2" style="background-color: #f8f4f0; color: #1f2937;">
                                <i class="fas fa-sliders-h text-alertara-600"></i> General
                            </button>
                            <button onclick="switchTab('crime-thresholds')" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 hover:bg-alertara-50 transition-colors tab-btn flex items-center gap-2">
                                <i class="fas fa-chart-line text-gray-400"></i> Crime Thresholds
                            </button>
                            <button onclick="switchTab('location-thresholds')" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 hover:bg-alertara-50 transition-colors tab-btn flex items-center gap-2">
                                <i class="fas fa-map-pin text-gray-400"></i> Location Thresholds
                            </button>
                            <button onclick="switchTab('notifications')" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-700 hover:bg-alertara-50 transition-colors tab-btn flex items-center gap-2">
                                <i class="fas fa-bell text-gray-400"></i> Notifications
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Right: Settings Content -->
                <div class="lg:col-span-3">
                    <!-- General Settings Tab -->
                    <div id="general-tab" class="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-sliders-h text-alertara-600"></i> General Alert Settings
                        </h2>

                        <div class="space-y-6">
                            <!-- Enable/Disable Alerts -->
                            <div class="flex items-center justify-between pb-6 border-b border-gray-200">
                                <div>
                                    <h3 class="font-medium text-gray-900">Enable All Alerts</h3>
                                    <p class="text-sm text-gray-600 mt-1">Master control to enable or disable all alert types</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" checked class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-alertara-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-alertara-600"></div>
                                </label>
                            </div>

                            <!-- Alert Sensitivity -->
                            <div class="pb-6 border-b border-gray-200">
                                <h3 class="font-medium text-gray-900 mb-3">Alert Sensitivity</h3>
                                <p class="text-sm text-gray-600 mb-4">Adjust how strict the alert criteria should be</p>
                                <div class="flex items-center gap-4">
                                    <input type="range" min="1" max="5" value="3" class="flex-1" id="sensitivity-slider">
                                    <div class="text-sm font-medium text-gray-700 min-w-fit">
                                        <span id="sensitivity-label">Medium</span>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 mt-2">Low ← → High</div>
                            </div>

                            <!-- Retry Attempts -->
                            <div class="pb-6 border-b border-gray-200">
                                <label class="block text-sm font-medium text-gray-900 mb-2">Validation Retry Attempts</label>
                                <input type="number" min="1" max="5" value="3" class="w-20 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                <p class="text-sm text-gray-600 mt-2">Number of times to recheck before triggering alert</p>
                            </div>

                            <!-- Quiet Hours -->
                            <div>
                                <h3 class="font-medium text-gray-900 mb-3">Quiet Hours (Optional)</h3>
                                <p class="text-sm text-gray-600 mb-4">Suppress alerts during specified times</p>
                                <div class="flex items-center gap-2 mb-4">
                                    <input type="checkbox" id="quiet-hours-enable" class="w-4 h-4 rounded border-gray-300">
                                    <label for="quiet-hours-enable" class="text-sm text-gray-700">Enable Quiet Hours</label>
                                </div>
                                <div class="grid grid-cols-2 gap-4" id="quiet-hours-section" style="display: none;">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Time</label>
                                        <input type="time" value="22:00" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">End Time</label>
                                        <input type="time" value="06:00" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Crime Thresholds Tab -->
                    <div id="crime-thresholds-tab" class="bg-white rounded-xl border border-gray-200 p-6" style="display: none;">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-chart-line text-alertara-600"></i> Crime Type Thresholds
                        </h2>

                        <div class="space-y-6">
                            <div class="pb-6 border-b border-gray-200">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-gray-900">Homicides per 24 hours</label>
                                    <span class="text-sm font-bold text-red-600">Critical</span>
                                </div>
                                <input type="number" value="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                <p class="text-xs text-gray-500 mt-2">Alert when homicides exceed this threshold</p>
                            </div>

                            <div class="pb-6 border-b border-gray-200">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-gray-900">Theft incidents per 24 hours</label>
                                    <span class="text-sm font-bold text-orange-600">High</span>
                                </div>
                                <input type="number" value="15" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                <p class="text-xs text-gray-500 mt-2">Alert when theft incidents exceed this threshold</p>
                            </div>

                            <div class="pb-6 border-b border-gray-200">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-gray-900">Robbery incidents per 24 hours</label>
                                    <span class="text-sm font-bold text-orange-600">High</span>
                                </div>
                                <input type="number" value="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                <p class="text-xs text-gray-500 mt-2">Alert when robbery incidents exceed this threshold</p>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-gray-900">Assault incidents per 24 hours</label>
                                    <span class="text-sm font-bold text-orange-600">High</span>
                                </div>
                                <input type="number" value="12" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                <p class="text-xs text-gray-500 mt-2">Alert when assault incidents exceed this threshold</p>
                            </div>
                        </div>
                    </div>

                    <!-- Location Thresholds Tab -->
                    <div id="location-thresholds-tab" class="bg-white rounded-xl border border-gray-200 p-6 overflow-x-auto" style="display: none;">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-map-pin text-alertara-600"></i> Location-Based Thresholds
                        </h2>

                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700">Barangay</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700">Crime Threshold</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700">Sensitivity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">Downtown District</td>
                                    <td class="px-4 py-3">
                                        <input type="number" value="20" class="w-20 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <select class="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                            <option>Low</option>
                                            <option selected>Medium</option>
                                            <option>High</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">Port Area</td>
                                    <td class="px-4 py-3">
                                        <input type="number" value="15" class="w-20 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <select class="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                            <option>Low</option>
                                            <option selected>Medium</option>
                                            <option>High</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">Central Business District</td>
                                    <td class="px-4 py-3">
                                        <input type="number" value="18" class="w-20 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <select class="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                            <option>Low</option>
                                            <option selected>Medium</option>
                                            <option>High</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">Residential Zone</td>
                                    <td class="px-4 py-3">
                                        <input type="number" value="10" class="w-20 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <select class="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-alertara-500">
                                            <option selected>Low</option>
                                            <option>Medium</option>
                                            <option>High</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Notifications Tab -->
                    <div id="notifications-tab" class="bg-white rounded-xl border border-gray-200 p-6" style="display: none;">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-bell text-alertara-600"></i> Notification Preferences
                        </h2>

                        <div class="space-y-6">
                            <!-- Email Notifications -->
                            <div class="pb-6 border-b border-gray-200">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="font-medium text-gray-900">Email Notifications</h3>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" checked class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-alertara-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-alertara-600"></div>
                                    </label>
                                </div>
                                <div class="space-y-3">
                                    <label class="flex items-center gap-3">
                                        <input type="checkbox" checked class="w-4 h-4 rounded border-gray-300">
                                        <span class="text-sm text-gray-700">Critical alerts</span>
                                    </label>
                                    <label class="flex items-center gap-3">
                                        <input type="checkbox" checked class="w-4 h-4 rounded border-gray-300">
                                        <span class="text-sm text-gray-700">High priority alerts</span>
                                    </label>
                                    <label class="flex items-center gap-3">
                                        <input type="checkbox" class="w-4 h-4 rounded border-gray-300">
                                        <span class="text-sm text-gray-700">Daily summary report</span>
                                    </label>
                                </div>
                            </div>

                            <!-- SMS Notifications -->
                            <div class="pb-6 border-b border-gray-200">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="font-medium text-gray-900">SMS Notifications</h3>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-alertara-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-alertara-600"></div>
                                    </label>
                                </div>
                                <p class="text-sm text-gray-600">Send critical alerts via SMS to your registered number</p>
                            </div>

                            <!-- System Notifications -->
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="font-medium text-gray-900">System Notifications</h3>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" checked class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-alertara-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-alertara-600"></div>
                                    </label>
                                </div>
                                <p class="text-sm text-gray-600">Display notifications in the system dashboard</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.getElementById('general-tab').style.display = 'none';
            document.getElementById('crime-thresholds-tab').style.display = 'none';
            document.getElementById('location-thresholds-tab').style.display = 'none';
            document.getElementById('notifications-tab').style.display = 'none';

            // Remove active styling from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.style.backgroundColor = 'transparent';
                btn.style.color = '#374151';
                btn.classList.remove('active');
                btn.innerHTML = btn.innerHTML.replace('text-alertara-600', 'text-gray-400');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';

            // Add active styling to clicked button
            const activeBtn = event.target.closest('.tab-btn');
            activeBtn.style.backgroundColor = '#f8f4f0';
            activeBtn.style.color = '#1f2937';
            activeBtn.classList.add('active');
            activeBtn.innerHTML = activeBtn.innerHTML.replace('text-gray-400', 'text-alertara-600');
        }

        // Update sensitivity label
        document.getElementById('sensitivity-slider').addEventListener('input', function(e) {
            const labels = ['Very Low', 'Low', 'Medium', 'High', 'Very High'];
            document.getElementById('sensitivity-label').textContent = labels[this.value - 1];
        });

        // Show/hide quiet hours section
        document.getElementById('quiet-hours-enable').addEventListener('change', function() {
            document.getElementById('quiet-hours-section').style.display = this.checked ? 'grid' : 'none';
        });
    </script>

    @stack('scripts')
</body>
</html>
