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
    @stack('styles')
</head>
<body class="bg-gray-50">
    @include('components.header')

    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

    @include('components.sidebar')

    <main class="lg:ml-72 ml-0 lg:mt-16 mt-16 min-h-screen bg-gray-50">
        <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
            <!-- Page Header -->
            <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Alert Management</h1>
                        <p class="text-gray-600 mt-1 text-sm lg:text-base">
                            Rules are real conditions the system evaluates against San Agustin's recorded crimes —
                            count, crime type, street and time window. Every rule here can be tested against live data before you save it.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="openCreateRuleModal()" class="px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors flex items-center gap-2 shadow-sm">
                            <i class="fas fa-plus"></i>
                            <span class="hidden sm:inline">Create Rule</span>
                        </button>
                    </div>
                </div>
            </div>

            @if (session('success'))
                <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                    <i class="fas fa-circle-check mr-2"></i>{{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <i class="fas fa-triangle-exclamation mr-2"></i>
                    <span class="font-semibold">The rule could not be saved:</span>
                    <ul class="mt-2 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Quick Stats -->
            @php
                $configured = $alertRules->where('is_configured', true)->count();
                $enabled = $alertRules->where('enabled', true)->count();
            @endphp
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase">Total Rules</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $alertRules->count() }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase">Enabled</p>
                    <p class="text-2xl font-bold text-green-600">{{ $enabled }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase">Times Triggered</p>
                    <p class="text-2xl font-bold text-alertara-700">{{ $alertRules->sum('trigger_count') }}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase">Without a Condition</p>
                    <p class="text-2xl font-bold {{ $alertRules->count() - $configured > 0 ? 'text-red-600' : 'text-gray-400' }}">
                        {{ $alertRules->count() - $configured }}
                    </p>
                    <p class="text-[11px] text-gray-500 mt-1">These never fire</p>
                </div>
            </div>

            <!-- Rules -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-list-check mr-2 text-alertara-700"></i>Alert Rules
                    </h2>
                    <p class="text-xs text-gray-600 mt-1">
                        Windows are counted back from the most recent recorded incident, so rules keep working even when the data lags the calendar.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Rule</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Fires when</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Evaluated</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Severity</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Triggered</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($alertRules as $rule)
                                @php
                                    $sev = ['critical' => 'bg-red-100 text-red-700', 'high' => 'bg-orange-100 text-orange-700',
                                            'medium' => 'bg-yellow-100 text-yellow-700', 'low' => 'bg-green-100 text-green-700'][$rule->severity] ?? 'bg-gray-100 text-gray-700';

                                    // Built here rather than inline: @json() cannot take a
                                    // multi-line array literal — it truncates the argument.
                                    $editPayload = [
                                        'id' => $rule->id,
                                        'rule_name' => $rule->rule_name,
                                        'rule_type' => $rule->rule_type,
                                        'severity' => $rule->severity,
                                        'scope' => $rule->scope,
                                        'operator' => $rule->operator,
                                        'threshold' => $rule->threshold,
                                        'time_window_hours' => $rule->window_hours,
                                        'category_id' => $rule->category_id,
                                        'severity_filter' => $rule->severity_filter,
                                        'enabled' => (bool) $rule->enabled,
                                    ];
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900">{{ $rule->rule_name }}</div>
                                        <div class="text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', $rule->rule_type)) }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 max-w-md">
                                        @if ($rule->is_configured)
                                            {{ ucfirst($rule->readable_condition) }}
                                        @else
                                            <span class="text-red-600 font-semibold">
                                                <i class="fas fa-triangle-exclamation mr-1"></i>No condition set — this rule never fires
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 text-xs">{{ $rule->scope_label }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-bold {{ $sev }}">{{ strtoupper($rule->severity) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">
                                        {{ $rule->trigger_count }}
                                        @if ($rule->last_triggered_at)
                                            <div class="text-[11px] text-gray-400">{{ $rule->last_triggered_at->diffForHumans() }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-bold {{ $rule->enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $rule->enabled ? 'ENABLED' : 'OFF' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <button type="button" onclick='editRule(@json($editPayload))'
                                                class="text-alertara-700 hover:text-alertara-900 font-medium mr-3">Edit</button>
                                        <button type="button" onclick="deleteRule({{ $rule->id }})" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                                        No alert rules yet. Create one to start monitoring streets.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Rule builder -->
    <div id="ruleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white">
                <h3 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-bell mr-2 text-alertara-600"></i><span id="modalTitle">Create Alert Rule</span>
                </h3>
                <button onclick="closeRuleModal()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="ruleForm" method="POST" action="{{ route('alerts.create-rule') }}" class="p-6">
                @csrf
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-2">Rule name</label>
                        <input type="text" name="rule_name" id="ruleName" required
                               placeholder="e.g. Repeat theft on one street"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-2">Rule type</label>
                            <select name="rule_type" id="ruleType" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                                <option value="crime_surge">Crime Surge</option>
                                <option value="hotspot">Hotspot</option>
                                <option value="pattern">Pattern</option>
                                <option value="threshold">Threshold</option>
                            </select>
                            <p class="text-[11px] text-gray-500 mt-1">A label for reporting — the condition below is what actually runs.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-2">Alert severity</label>
                            <select name="severity" id="severity" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                    </div>

                    <!-- The actual condition -->
                    <div class="rounded-lg border border-alertara-200 bg-alertara-50 p-4 space-y-4">
                        <div class="text-sm font-bold text-alertara-900">
                            <i class="fas fa-filter mr-1"></i>Condition — when should this fire?
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Count crimes</label>
                            <select name="scope" id="scope" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                @foreach ($scopes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">When there are</label>
                                <select name="operator" id="operator" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                    <option value=">=">at least</option>
                                    <option value=">">more than</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Incidents</label>
                                <input type="number" name="threshold" id="threshold" min="1" max="500" value="3" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Within</label>
                                <select name="time_window_hours" id="timeWindow" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                    @foreach ($windows as $hours => $label)
                                        <option value="{{ $hours }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div id="categoryRow" class="hidden">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Crime type</label>
                            <select name="category_id" id="categoryId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                <option value="">Any crime type (each type counted separately)</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div id="severityFilterRow" class="hidden">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Only count these severities</label>
                            <div class="flex flex-wrap gap-3">
                                @foreach (['critical', 'high', 'medium', 'low'] as $level)
                                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                        <input type="checkbox" name="severity_filter[]" value="{{ $level }}" class="sev-filter">
                                        {{ ucfirst($level) }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-lg bg-white border border-gray-200 p-3">
                            <div class="text-[11px] font-bold text-gray-500 uppercase mb-1">This rule reads as</div>
                            <p id="conditionPreview" class="text-sm font-semibold text-gray-900">—</p>
                            <button type="button" id="testRuleBtn"
                                    class="mt-3 px-3 py-1.5 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 text-xs font-bold">
                                <i class="fas fa-flask mr-1"></i>Test against real data
                            </button>
                            <div id="testResult" class="mt-3 text-xs"></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-gray-900">Enable this rule</label>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enabled" id="enableRule" value="1" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-alertara-600"></div>
                        </label>
                    </div>
                </div>

                <div class="mt-8 flex gap-3 justify-end">
                    <button type="button" onclick="closeRuleModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 font-semibold">Save rule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const CREATE_URL = @json(route('alerts.create-rule'));
        const PREVIEW_URL = @json(route('alerts.preview-rule'));
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;

        const SCOPE_NEEDS_CATEGORY = ['street_category', 'category'];
        const SCOPE_NEEDS_SEVERITY = ['street_severity'];

        function syncConditionFields() {
            const scope = document.getElementById('scope').value;
            document.getElementById('categoryRow').classList.toggle('hidden', !SCOPE_NEEDS_CATEGORY.includes(scope));
            document.getElementById('severityFilterRow').classList.toggle('hidden', !SCOPE_NEEDS_SEVERITY.includes(scope));
            renderPreview();
        }

        // Mirrors CrimeAlertEngine::formatCondition so the sentence you read
        // here is the sentence the server will store.
        function renderPreview() {
            const scope = document.getElementById('scope').value;
            const threshold = parseInt(document.getElementById('threshold').value || '1', 10);
            const operator = document.getElementById('operator').value === '>' ? 'more than' : 'at least';
            const windowLabel = document.getElementById('timeWindow').selectedOptions[0].text
                .replace(/^Last /, '').toLowerCase();

            const categorySelect = document.getElementById('categoryId');
            const category = SCOPE_NEEDS_CATEGORY.includes(scope) && categorySelect.value
                ? categorySelect.selectedOptions[0].text : null;

            const severities = SCOPE_NEEDS_SEVERITY.includes(scope)
                ? [...document.querySelectorAll('.sev-filter:checked')].map(c => c.value) : [];

            const plural = threshold === 1 ? '' : 's';
            const what = category
                ? `${category} incident${plural}`
                : (severities.length ? `${severities.join('/')}-severity incident${plural}` : `incident${plural}`);

            const where = {
                street: 'on the same street',
                street_severity: 'on the same street',
                street_category: 'of the same type on the same street',
                category: 'of the same type in the barangay'
            }[scope] || 'anywhere in the barangay';

            document.getElementById('conditionPreview').textContent =
                `Raise an alert when there are ${operator} ${threshold} ${what} ${where} within ${windowLabel}.`;
        }

        document.getElementById('scope').addEventListener('change', syncConditionFields);
        ['threshold', 'operator', 'timeWindow', 'categoryId'].forEach(id =>
            document.getElementById(id).addEventListener('change', renderPreview));
        document.getElementById('threshold').addEventListener('input', renderPreview);
        document.querySelectorAll('.sev-filter').forEach(c => c.addEventListener('change', renderPreview));

        // Run the condition against real incidents without saving anything
        document.getElementById('testRuleBtn').addEventListener('click', async function () {
            const box = document.getElementById('testResult');
            box.innerHTML = '<span class="text-gray-500"><i class="fas fa-spinner fa-spin mr-1"></i>Checking recorded crimes...</span>';

            const form = new FormData(document.getElementById('ruleForm'));
            form.set('rule_name', document.getElementById('ruleName').value || 'Preview rule');

            try {
                const res = await fetch(PREVIEW_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: form
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Could not test this rule');

                if (!data.match_count) {
                    box.innerHTML = '<span class="text-amber-700"><i class="fas fa-circle-info mr-1"></i>'
                        + 'Nothing in the current data meets this condition. It would stay quiet until crime picks up — '
                        + 'lower the count or widen the window if you expected a match.</span>';
                    return;
                }

                const rows = data.matches.map(m =>
                    `<div class="flex justify-between border-t border-gray-100 py-1">
                        <span>${m.label}${m.category ? ' · ' + m.category : ''}</span>
                        <span class="font-bold">${m.count}</span>
                     </div>`).join('');

                box.innerHTML = `<div class="text-green-700 font-semibold mb-1">
                        <i class="fas fa-circle-check mr-1"></i>${data.match_count} alert(s) would be raised right now
                    </div>${rows}`;
            } catch (err) {
                box.innerHTML = `<span class="text-red-600"><i class="fas fa-triangle-exclamation mr-1"></i>${err.message}</span>`;
            }
        });

        function openCreateRuleModal() {
            document.getElementById('modalTitle').textContent = 'Create Alert Rule';
            document.getElementById('ruleForm').action = CREATE_URL;
            document.getElementById('ruleForm').reset();
            document.getElementById('testResult').innerHTML = '';
            syncConditionFields();
            document.getElementById('ruleModal').classList.remove('hidden');
        }

        function editRule(rule) {
            document.getElementById('modalTitle').textContent = 'Edit Alert Rule';
            document.getElementById('ruleForm').action = `/alerts/update-rule/${rule.id}`;

            document.getElementById('ruleName').value = rule.rule_name ?? '';
            document.getElementById('ruleType').value = rule.rule_type ?? 'crime_surge';
            document.getElementById('severity').value = rule.severity ?? 'medium';
            document.getElementById('scope').value = rule.scope ?? 'street';
            document.getElementById('operator').value = rule.operator ?? '>=';
            document.getElementById('threshold').value = rule.threshold ?? 3;
            document.getElementById('timeWindow').value = rule.time_window_hours ?? 720;
            document.getElementById('categoryId').value = rule.category_id ?? '';
            document.getElementById('enableRule').checked = !!rule.enabled;

            const picked = rule.severity_filter || [];
            document.querySelectorAll('.sev-filter').forEach(c => { c.checked = picked.includes(c.value); });

            document.getElementById('testResult').innerHTML = '';
            syncConditionFields();
            document.getElementById('ruleModal').classList.remove('hidden');
        }

        function closeRuleModal() {
            document.getElementById('ruleModal').classList.add('hidden');
        }

        async function deleteRule(ruleId) {
            if (!confirm('Delete this rule? Any alerts it raised will be closed.')) return;

            const res = await fetch(`/alerts/delete-rule/${ruleId}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            });

            if (res.ok) location.reload();
            else alert('Could not delete this rule.');
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeRuleModal();
        });

        syncConditionFields();
    </script>
</body>
</html>
