@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>View History - Crime Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/js/app.js'])

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
    </style>

    @stack('styles')
</head>
<body class="bg-gray-50">
    <!-- Store JWT token in sessionStorage for API calls -->
    @php
        $jwtToken = session('jwt_token');
    @endphp
    @if($jwtToken)
    <script>
        // Store JWT token in sessionStorage for JavaScript API calls
        sessionStorage.setItem('jwt_token', '{{ $jwtToken }}');
    </script>
    @endif

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
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">View History</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Track all admin actions and audit logs</p>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Start Date</label>
                        <input type="date" id="startDateFilter"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">End Date</label>
                        <input type="date" id="endDateFilter"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                    </div>

                    <!-- Action Type Filter -->
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Action Type</label>
                        <select id="actionTypeFilter" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                            <option value="">All Actions</option>
                            @foreach($actionTypes as $type)
                                <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Search IP -->
                    <div>
                        <label class="block text-sm font-medium text-alertara-800 mb-2">Search IP Address</label>
                        <input type="text" id="searchIpFilter" placeholder="e.g., 192.168.1.1"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500 bg-white">
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-end gap-2">
                        <button id="applyFiltersBtn" class="px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors w-full">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <button id="resetFiltersBtn" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Audit Logs Table -->
            <div class="mt-6 bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">Audit Logs</h2>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">Show:</span>
                            <select id="tablePageSize" class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-alertara-500 focus:border-alertara-500">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
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
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date & Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Action</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Admin ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Target</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">IP Address</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Browser Info</th>
                            </tr>
                        </thead>
                        <tbody id="auditLogsTableBody" class="divide-y divide-gray-200">
                            @foreach($auditLogs as $log)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <span class="font-medium">{{ $log->created_at->format('M d, Y') }}</span>
                                    <span class="text-gray-500 text-xs block">{{ $log->created_at->format('H:i:s') }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold
                                        @if(str_contains($log->action_type, 'INSERT')) bg-green-100 text-green-800
                                        @elseif(str_contains($log->action_type, 'UPDATE')) bg-blue-100 text-blue-800
                                        @elseif(str_contains($log->action_type, 'DELETE')) bg-red-100 text-red-800
                                        @elseif(str_contains($log->action_type, 'VIEW')) bg-purple-100 text-purple-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ str_replace('_', ' ', $log->action_type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ $log->admin_id }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="text-gray-700">{{ $log->target_table }}</span>
                                    <span class="text-gray-500 text-xs block">(ID: {{ $log->target_id }})</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $log->ip_address }}</code>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <span class="text-xs truncate block max-w-xs" title="{{ $log->user_agent }}">
                                        {{ Str::limit($log->user_agent, 50) ?? 'N/A' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <p class="text-sm text-gray-600">
                        Showing <span id="showingStart">1</span> to <span id="showingEnd">{{ count($auditLogs) }}</span> of <span id="totalRecords">{{ $auditLogs->total() }}</span> records
                    </p>
                    <div id="pagination" class="flex gap-1 flex-wrap">
                        <!-- Pagination will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        let currentPage = 1;
        let pageSize = 25;
        let allAuditLogs = [];

        document.addEventListener('DOMContentLoaded', function() {
            // Load initial data
            loadAuditLogs();

            // Filter listeners
            document.getElementById('applyFiltersBtn').addEventListener('click', function() {
                currentPage = 1;
                loadAuditLogs();
            });

            document.getElementById('resetFiltersBtn').addEventListener('click', function() {
                document.getElementById('startDateFilter').value = '';
                document.getElementById('endDateFilter').value = '';
                document.getElementById('actionTypeFilter').value = '';
                document.getElementById('searchIpFilter').value = '';
                currentPage = 1;
                loadAuditLogs();
            });

            // Page size change
            document.getElementById('tablePageSize').addEventListener('change', function() {
                pageSize = parseInt(this.value);
                currentPage = 1;
                renderTable();
            });
        });

        function loadAuditLogs() {
            const filters = {
                start_date: document.getElementById('startDateFilter').value || '',
                end_date: document.getElementById('endDateFilter').value || '',
                action_type: document.getElementById('actionTypeFilter').value || '',
                search_ip: document.getElementById('searchIpFilter').value || ''
            };

            fetch('{{ route("audit-logs.filtered") }}?' + new URLSearchParams(filters), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allAuditLogs = data.data;
                    currentPage = 1;
                    renderTable();
                    console.log('✅ Loaded ' + allAuditLogs.length + ' audit logs');
                } else {
                    console.error('❌ Failed to load audit logs');
                }
            })
            .catch(error => {
                console.error('❌ Error loading audit logs:', error);
            });
        }

        function renderTable() {
            const tbody = document.getElementById('auditLogsTableBody');
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;
            const paginatedLogs = allAuditLogs.slice(startIndex, endIndex);

            tbody.innerHTML = paginatedLogs.map(log => `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-sm text-gray-900">
                        <span class="font-medium">${new Date(log.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</span>
                        <span class="text-gray-500 text-xs block">${new Date(log.created_at).toLocaleTimeString('en-US')}</span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold ${getActionBadgeClass(log.action_type)}">
                            ${log.action_type.replace(/_/g, ' ')}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900 font-medium">${log.admin_id}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="text-gray-700">${log.target_table}</span>
                        <span class="text-gray-500 text-xs block">(ID: ${log.target_id})</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">
                        <code class="bg-gray-100 px-2 py-1 rounded text-xs">${log.ip_address}</code>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <span class="text-xs truncate block max-w-xs" title="${log.user_agent || 'N/A'}">
                            ${(log.user_agent || 'N/A').substring(0, 50)}
                        </span>
                    </td>
                </tr>
            `).join('');

            // Update pagination info
            document.getElementById('showingStart').textContent = allAuditLogs.length > 0 ? startIndex + 1 : 0;
            document.getElementById('showingEnd').textContent = Math.min(endIndex, allAuditLogs.length);
            document.getElementById('totalRecords').textContent = allAuditLogs.length;

            // Update pagination buttons
            updatePagination();
        }

        function getActionBadgeClass(actionType) {
            if (actionType.includes('INSERT')) return 'bg-green-100 text-green-800';
            if (actionType.includes('UPDATE')) return 'bg-blue-100 text-blue-800';
            if (actionType.includes('DELETE')) return 'bg-red-100 text-red-800';
            if (actionType.includes('VIEW')) return 'bg-purple-100 text-purple-800';
            return 'bg-gray-100 text-gray-800';
        }

        function updatePagination() {
            const container = document.getElementById('pagination');
            const totalPages = Math.ceil(allAuditLogs.length / pageSize);

            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let paginationHTML = '';

            // First page button
            if (currentPage > 1) {
                paginationHTML += `
                    <button onclick="goToPage(1)" class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors" title="First page">
                        <i class="fas fa-step-backward"></i>
                    </button>
                `;
            }

            // Previous button
            if (currentPage > 1) {
                paginationHTML += `
                    <button onclick="goToPage(${currentPage - 1})" class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                `;
            }

            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);

            for (let i = startPage; i <= endPage; i++) {
                const isActive = i === currentPage;
                const className = isActive
                    ? 'px-3 py-1 text-sm bg-alertara-600 text-white border-alertara-600 rounded'
                    : 'px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors';

                paginationHTML += `<button onclick="goToPage(${i})" class="${className}">${i}</button>`;
            }

            // Next button
            if (currentPage < totalPages) {
                paginationHTML += `
                    <button onclick="goToPage(${currentPage + 1})" class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                `;
            }

            // Last page button
            if (currentPage < totalPages) {
                paginationHTML += `
                    <button onclick="goToPage(${totalPages})" class="px-3 py-1 text-sm border border-gray-300 hover:bg-gray-50 transition-colors rounded-r" title="Last page">
                        <i class="fas fa-step-forward"></i>
                    </button>
                `;
            }

            container.innerHTML = paginationHTML;
        }

        function goToPage(page) {
            const totalPages = Math.ceil(allAuditLogs.length / pageSize);
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                renderTable();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }
    </script>
</body>
</html>
