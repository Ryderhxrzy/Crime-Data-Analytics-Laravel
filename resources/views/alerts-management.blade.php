<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alert Management - Crime Management System</title>
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
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Alert Management</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">Create and manage custom alert rules and conditions</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="openCreateRuleModal()" class="px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2 shadow-sm">
                            <i class="fas fa-plus"></i>
                            <span class="hidden sm:inline">Create Rule</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Rules</p>
                            <p class="text-3xl font-bold text-alertara-600 mt-1">{{ $alertRules->count() }}</p>
                        </div>
                        <i class="fas fa-bell text-4xl text-alertara-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Active Rules</p>
                            <p class="text-3xl font-bold text-green-600 mt-1">{{ $alertRules->where('enabled', true)->count() }}</p>
                        </div>
                        <i class="fas fa-check-circle text-4xl text-green-200"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Disabled Rules</p>
                            <p class="text-3xl font-bold text-gray-600 mt-1">{{ $alertRules->where('enabled', false)->count() }}</p>
                        </div>
                        <i class="fas fa-pause-circle text-4xl text-gray-200"></i>
                    </div>
                </div>
            </div>

            <!-- Alert Rules Table -->
            <div class="mt-6 bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">All Alert Rules</h2>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Rule Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Rule Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Severity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Condition</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        @php
                            $ruleTypeLabels = [
                                'crime_surge' => 'Crime Surge',
                                'hotspot' => 'Hotspot Detection',
                                'pattern' => 'Pattern Detected',
                                'threshold' => 'Threshold Alert',
                            ];
                            $severityBadges = [
                                'critical' => 'bg-red-100 text-red-800',
                                'high' => 'bg-orange-100 text-orange-800',
                                'medium' => 'bg-yellow-100 text-yellow-800',
                                'low' => 'bg-blue-100 text-blue-800',
                            ];
                            $alertEngine = app(\App\Services\CrimeAlertEngine::class);
                        @endphp
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($alertRules as $rule)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $rule->rule_name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $ruleTypeLabels[$rule->rule_type] ?? $rule->rule_type }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $severityBadges[$rule->severity] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ strtoupper($rule->severity) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <code class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">{{ $alertEngine->formatCondition($rule) }}</code>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $rule->rule_condition }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($rule->enabled)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i> Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-800">
                                                <i class="fas fa-pause-circle mr-1"></i> Disabled
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <button onclick='editRule(@json($rule))' class="text-alertara-600 hover:text-alertara-800 font-medium mr-3">Edit</button>
                                        <button onclick="deleteRule({{ $rule->id }})" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No alert rules yet. Click "Create Rule" to add one.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="p-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ $alertRules->count() }} of {{ $alertRules->count() }} rules
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

    <!-- Create/Edit Rule Modal -->
    <div id="ruleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <i class="fas fa-bell mr-2 text-alertara-600"></i><span id="modalTitle">Create New Alert Rule</span>
                    </h3>
                    <button onclick="closeRuleModal()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <form id="ruleForm" method="POST" action="{{ route('alerts.create-rule') }}" class="p-6">
                @csrf
                <div class="space-y-6">
                    <!-- Rule Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-2">
                            <i class="fas fa-heading mr-1 text-alertara-600"></i>Rule Name
                        </label>
                        <input type="text" name="rule_name" id="ruleName" placeholder="e.g., High Crime Surge"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                    </div>

                    <!-- Rule Type -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-2">
                                <i class="fas fa-tag mr-1 text-alertara-600"></i>Rule Type
                            </label>
                            <select name="rule_type" id="ruleType" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                                <option value="">Select a type...</option>
                                <option value="crime_surge">Crime Surge</option>
                                <option value="hotspot">Hotspot Detection</option>
                                <option value="pattern">Pattern Detected</option>
                                <option value="threshold">Threshold Alert</option>
                            </select>
                        </div>

                        <!-- Severity -->
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-2">
                                <i class="fas fa-triangle-exclamation mr-1 text-alertara-600"></i>Severity
                            </label>
                            <select name="severity" id="severity" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                                <option value="">Select severity...</option>
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                    </div>

                    <!-- Condition -->
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-2">
                            <i class="fas fa-filter mr-1 text-alertara-600"></i>Condition Description
                        </label>
                        <textarea name="rule_condition" id="rule_condition" placeholder="Describe the condition that triggers this alert..."
                                  rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required></textarea>
                    </div>

                    <!-- Enable/Disable -->
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-gray-900">Enable this rule</label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enabled" id="enableRule" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-alertara-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-alertara-600"></div>
                        </label>
                    </div>
                </div>

                <div class="mt-8 flex gap-3 justify-end">
                    <button type="button" onclick="closeRuleModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2">
                        <i class="fas fa-save"></i> Save Rule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const CREATE_RULE_URL = '{{ route('alerts.create-rule') }}';

        function openCreateRuleModal() {
            document.getElementById('modalTitle').textContent = 'Create New Alert Rule';
            const form = document.getElementById('ruleForm');
            form.reset();
            form.action = CREATE_RULE_URL;
            document.getElementById('enableRule').checked = true;
            document.getElementById('ruleModal').classList.remove('hidden');
        }

        function editRule(rule) {
            document.getElementById('modalTitle').textContent = 'Edit Alert Rule';
            const form = document.getElementById('ruleForm');
            form.action = `/alerts/update-rule/${rule.id}`;
            document.getElementById('ruleName').value = rule.rule_name;
            document.getElementById('ruleType').value = rule.rule_type;
            document.getElementById('severity').value = rule.severity;
            document.getElementById('rule_condition').value = rule.rule_condition;
            document.getElementById('enableRule').checked = !!rule.enabled;
            document.getElementById('ruleModal').classList.remove('hidden');
        }

        function closeRuleModal() {
            document.getElementById('ruleModal').classList.add('hidden');
        }

        async function deleteRule(ruleId) {
            if (!confirm('Are you sure you want to delete this alert rule?')) return;

            try {
                const res = await fetch(`/alerts/delete-rule/${ruleId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                });
                if (res.ok || res.redirected) {
                    location.reload();
                }
            } catch (e) {
                console.error(e);
            }
        }

        // Close modal on background click
        document.getElementById('ruleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRuleModal();
            }
        });
    </script>

    @include('partials.toastr')

    @stack('scripts')
</body>
</html>
