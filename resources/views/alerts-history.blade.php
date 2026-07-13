<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alert History - Crime Management System</title>
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
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Alert History</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">View historical alert records and resolution details</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button class="px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2 shadow-sm">
                            <i class="fas fa-download"></i>
                            <span class="hidden sm:inline">Export</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Resolved</p>
                            <p id="statTotalResolved" class="text-3xl font-bold text-green-600 mt-1">–</p>
                        </div>
                        <i class="fas fa-check-circle text-4xl text-green-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">This Month</p>
                            <p id="statThisMonth" class="text-3xl font-bold text-alertara-600 mt-1">–</p>
                        </div>
                        <i class="fas fa-calendar text-4xl text-alertara-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Avg Resolution Time</p>
                            <p id="statAvgResolution" class="text-3xl font-bold text-purple-600 mt-1">–</p>
                        </div>
                        <i class="fas fa-hourglass-end text-4xl text-purple-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">False Alerts</p>
                            <p id="statFalseAlarms" class="text-3xl font-bold text-orange-600 mt-1">–</p>
                        </div>
                        <i class="fas fa-triangle-exclamation text-4xl text-orange-200"></i>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Search</label>
                        <div class="relative">
                            <input type="text" placeholder="Search by location or alert type..."
                                   class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <i class="fas fa-search absolute left-3 top-3 text-alertara-500"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Date From</label>
                        <input type="date" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Date To</label>
                        <input type="date" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Status</label>
                        <select class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Status</option>
                            <option value="resolved">Resolved</option>
                            <option value="acknowledged">Acknowledged</option>
                            <option value="false_alarm">False Alarm</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">&nbsp;</label>
                        <button class="w-full px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors text-sm font-medium">
                            <i class="fas fa-search mr-2"></i> Search
                        </button>
                    </div>
                </div>
            </div>

            <!-- Alert History Table -->
            <div class="mt-6 bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">Historical Records</h2>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Alert ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Triggered</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Resolved</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody" class="divide-y divide-gray-200">
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">Loading history...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="p-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div id="historySummaryText" class="text-sm text-gray-700">
                            Loading...
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

    <script>
        const STATUS_STYLES = {
            resolved: { badge: 'bg-green-100 text-green-800', icon: 'fa-check-circle', label: 'Resolved' },
            dismissed: { badge: 'bg-orange-100 text-orange-800', icon: 'fa-exclamation-circle', label: 'False Alarm' },
            acknowledged: { badge: 'bg-alertara-100 text-alertara-800', icon: 'fa-circle-check', label: 'Acknowledged' },
        };

        function formatDateTime(isoString) {
            if (!isoString) return '—';
            return new Date(isoString).toLocaleString('en-US', {
                month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
            });
        }

        function formatDuration(startIso, endIso) {
            if (!startIso || !endIso) return '—';
            const minutes = Math.max(0, Math.round((new Date(endIso) - new Date(startIso)) / 60000));
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            if (hours === 0) return `${mins}m`;
            return `${hours}h ${mins}m`;
        }

        function renderHistoryTable(alerts) {
            const tbody = document.getElementById('historyTableBody');
            if (!alerts.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No historical alerts yet.</td></tr>`;
                return;
            }

            tbody.innerHTML = alerts.map(alert => {
                const status = STATUS_STYLES[alert.status] || STATUS_STYLES.resolved;
                return `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${alert.alert_id}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">${alert.rule_type ?? ''}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">${alert.area_name ?? ''}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">${formatDateTime(alert.triggered_at)}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">${formatDateTime(alert.resolved_at)}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">${formatDuration(alert.triggered_at, alert.resolved_at)}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold ${status.badge}">
                                <i class="fas ${status.icon} mr-1"></i> ${status.label}
                            </span>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function loadAlertHistory() {
            const tbody = document.getElementById('historyTableBody');
            tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">Loading history...</td></tr>`;

            try {
                const res = await fetch('{{ route('alerts.history-data') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                document.getElementById('statTotalResolved').textContent = data.stats.total_resolved;
                document.getElementById('statThisMonth').textContent = data.stats.this_month;
                document.getElementById('statAvgResolution').textContent =
                    data.stats.avg_resolution_minutes >= 60
                        ? `${(data.stats.avg_resolution_minutes / 60).toFixed(1)}h`
                        : `${data.stats.avg_resolution_minutes}m`;
                document.getElementById('statFalseAlarms').textContent = data.stats.false_alarms;
                document.getElementById('historySummaryText').textContent =
                    `Showing ${data.alerts.length} of ${data.alerts.length} historical alerts`;

                renderHistoryTable(data.alerts);
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-red-500">Failed to load alert history.</td></tr>`;
                console.error(e);
            }
        }

        document.addEventListener('DOMContentLoaded', loadAlertHistory);
    </script>
</body>
</html>
