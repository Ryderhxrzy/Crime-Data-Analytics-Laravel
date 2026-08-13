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
                        <button id="evaluateBtn" onclick="evaluateAlerts()" class="px-4 py-2 bg-white border border-alertara-600 text-alertara-600 rounded-lg hover:bg-alertara-50 transition-colors flex items-center gap-2 shadow-sm">
                            <i class="fas fa-bolt"></i>
                            <span class="hidden sm:inline">Run Rules</span>
                        </button>
                        <button onclick="loadActiveAlerts()" class="px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2 shadow-sm">
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
                            <p id="statTotalActive" class="text-3xl font-bold text-red-600 mt-1">–</p>
                        </div>
                        <i class="fas fa-bell text-4xl text-red-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Critical</p>
                            <p id="statCritical" class="text-3xl font-bold text-red-700 mt-1">–</p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-4xl text-red-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">High</p>
                            <p id="statHigh" class="text-3xl font-bold text-orange-600 mt-1">–</p>
                        </div>
                        <i class="fas fa-triangle-exclamation text-4xl text-orange-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Medium</p>
                            <p id="statMedium" class="text-3xl font-bold text-yellow-600 mt-1">–</p>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Street</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Crimes behind it</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Rule &amp; condition</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Raised</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="alertsTableBody" class="divide-y divide-gray-200">
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Loading alerts...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="p-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div id="alertsSummaryText" class="text-sm text-gray-700">
                            Loading...
                        </div>
                        <a href="{{ route('alerts.history') }}" class="text-sm font-semibold text-alertara-700 hover:text-alertara-900">
                            View closed alerts <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const SEVERITY_STYLES = {
            critical: { badge: 'bg-red-100 text-red-800', icon: 'fa-exclamation-triangle' },
            high: { badge: 'bg-orange-100 text-orange-800', icon: 'fa-triangle-exclamation' },
            medium: { badge: 'bg-yellow-100 text-yellow-800', icon: 'fa-circle-exclamation' },
            low: { badge: 'bg-blue-100 text-blue-800', icon: 'fa-info-circle' },
        };

        function timeAgo(isoString) {
            if (!isoString) return '—';
            const seconds = Math.floor((Date.now() - new Date(isoString).getTime()) / 1000);
            if (seconds < 60) return 'just now';
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
            const days = Math.floor(hours / 24);
            return `${days} day${days === 1 ? '' : 's'} ago`;
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, c => (
                { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
            ));
        }

        function renderAlertsTable(alerts) {
            const tbody = document.getElementById('alertsTableBody');

            if (!alerts.length) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                    No street in San Agustin currently meets any alert rule.
                    <a href="{{ route('alerts.management') }}" class="text-alertara-700 font-semibold underline">Review the rules</a>
                    or press Refresh to re-evaluate.
                </td></tr>`;
                return;
            }

            tbody.innerHTML = alerts.map(alert => {
                const style = SEVERITY_STYLES[(alert.severity || '').toLowerCase()] || SEVERITY_STYLES.medium;

                // What the count is actually made of
                const breakdown = (alert.breakdown || []).slice(0, 4).map(b =>
                    `<span class="inline-block text-[11px] bg-gray-100 text-gray-700 rounded-full px-2 py-0.5 mr-1 mb-1">
                        ${escapeHtml(b.name)}: <b>${b.count}</b></span>`).join('');

                const acknowledged = alert.status === 'acknowledged';

                return `
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 inline-flex text-xs font-bold rounded-full ${style.badge}">
                                <i class="fas ${style.icon} mr-1"></i>${alert.severity}
                            </span>
                            ${acknowledged ? '<div class="text-[10px] text-blue-600 font-bold mt-1">ACKNOWLEDGED</div>' : ''}
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-gray-900">${escapeHtml(alert.street ?? '—')}</div>
                            <div class="text-xs text-gray-500">Brgy. ${escapeHtml(alert.barangay ?? '')}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-gray-900">${alert.incident_count} incident${alert.incident_count === 1 ? '' : 's'}</div>
                            <div class="mt-1">${breakdown}</div>
                            ${alert.last_incident ? `<div class="text-[11px] text-gray-500">latest ${escapeHtml(alert.last_incident)}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 max-w-xs">
                            <div class="text-sm text-gray-900">${escapeHtml(alert.rule_name ?? '')}</div>
                            <div class="text-xs text-gray-500">${escapeHtml(alert.condition ?? '')}</div>
                            ${alert.time_window ? `<div class="text-[11px] text-gray-400">${escapeHtml(alert.time_window)}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">${timeAgo(alert.triggered_at)}</td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            ${acknowledged ? '' : `<button onclick="alertAction('${alert.alert_id}', 'acknowledge')" class="text-blue-600 hover:text-blue-800 text-sm font-medium mr-3">Acknowledge</button>`}
                            <button onclick="alertAction('${alert.alert_id}', 'resolve')" class="text-green-600 hover:text-green-800 text-sm font-medium mr-3">Resolve</button>
                            <button onclick="alertAction('${alert.alert_id}', 'dismiss')" class="text-gray-500 hover:text-gray-700 text-sm font-medium">False alarm</button>
                        </td>
                    </tr>`;
            }).join('');
        }

        // Acknowledge / resolve / dismiss, then reload the list
        async function alertAction(code, action) {
            let notes = null;

            if (action === 'resolve') {
                notes = prompt('What was done about this alert? (optional)') ?? '';
            } else if (action === 'dismiss' && !confirm('Mark this alert as a false alarm?')) {
                return;
            }

            try {
                const res = await fetch(`/alerts/api/${encodeURIComponent(code)}/${action}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ resolution_notes: notes })
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                await loadActiveAlerts();
            } catch (err) {
                console.error('Alert action failed:', err);
                alert('Could not update the alert. Please try again.');
            }
        }

        async function loadActiveAlerts() {
            const tbody = document.getElementById('alertsTableBody');
            tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Loading alerts...</td></tr>`;

            try {
                const res = await fetch('{{ route('alerts.active-data') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                document.getElementById('statTotalActive').textContent = data.stats.total_active;
                document.getElementById('statCritical').textContent = data.stats.critical;
                document.getElementById('statHigh').textContent = data.stats.high;
                document.getElementById('statMedium').textContent = data.stats.medium;
                const streetsEl = document.getElementById('statStreets');
                if (streetsEl) streetsEl.textContent = data.stats.streets_affected;
                document.getElementById('alertsSummaryText').textContent =
                    `${data.stats.total_active} open alert(s) across ${data.stats.streets_affected} street(s), `
                    + `covering ${data.stats.incidents_covered} recorded incident(s)`;

                renderAlertsTable(data.alerts);
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-sm text-red-500">Failed to load alerts.</td></tr>`;
                console.error(e);
            }
        }

        async function evaluateAlerts() {
            const btn = document.getElementById('evaluateBtn');
            btn.disabled = true;
            btn.classList.add('opacity-60');

            try {
                const res = await fetch('{{ route('alerts.evaluate') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                });
                const data = await res.json();
                await loadActiveAlerts();
                alert(data.created_count > 0
                    ? `${data.created_count} new alert(s) raised. Alerts whose condition has passed were closed and moved to the history.`
                    : 'No new alerts. Anything whose condition has passed was closed and moved to the history.');
            } catch (e) {
                console.error(e);
            } finally {
                btn.disabled = false;
                btn.classList.remove('opacity-60');
            }
        }

        document.addEventListener('DOMContentLoaded', loadActiveAlerts);
    </script>
</body>
</html>
