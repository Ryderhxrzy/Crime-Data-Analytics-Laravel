@extends('layouts.app')
@section('title', 'Staff Management')

@push('styles')
<style>
    .staff-modal { display: none; position: fixed; inset: 0; background: rgba(17,24,39,.55); z-index: 9999; align-items: center; justify-content: center; padding: 16px; }
    .staff-modal.open { display: flex; }
    .staff-modal .card { background: #fff; border-radius: 16px; width: 100%; max-width: 540px; box-shadow: 0 25px 60px rgba(0,0,0,.35); overflow: hidden; }
    .kpi { border: 1px solid #e5e7eb; border-radius: 14px; padding: 18px 20px; background: linear-gradient(180deg,#fff,#f9fafb); }
    .kpi .v { font-size: 30px; font-weight: 800; line-height: 1.1; color: #111827; }
    .kpi .l { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
    .kpi .s { font-size: 12px; color: #6b7280; margin-top: 4px; }
    .chart-box { position: relative; height: 220px; }
    .action-btn { display: inline-flex; align-items: center; gap: 4px; padding: 5px 9px; border-radius: 8px; font-size: 11.5px; font-weight: 700; border: 1px solid #e5e7eb; background: #fff; color: #374151; cursor: pointer; }
    .action-btn:hover { background: #f3f4f6; }
    .action-btn.danger { color: #b91c1c; border-color: #fecaca; }
    .action-btn.danger:hover { background: #fef2f2; }
</style>
@endpush

@section('content')
<div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
    <!-- Page Header -->
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                    <i class="fas fa-users-gear mr-3" style="color: #274d4c;"></i>Staff Management
                </h1>
                <p class="text-gray-600 mt-1 text-sm lg:text-base">
                    Staff accounts for this system. New accounts receive their login details by email.
                </p>
            </div>
            <button type="button" onclick="openModal('addStaffModal')"
                    class="px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors flex items-center gap-2 text-sm font-semibold {{ $tableReady ? '' : 'opacity-50 cursor-not-allowed' }}"
                    {{ $tableReady ? '' : 'disabled' }}>
                <i class="fas fa-user-plus"></i>Add Staff
            </button>
        </div>
    </div>

    @if (!$tableReady)
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <i class="fas fa-triangle-exclamation mr-2"></i>
            The staff table has not been created yet. Run <code class="bg-white px-1 rounded">php artisan migrate</code> and reload this page.
        </div>
    @endif

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
            <i class="fas fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if (session('warning'))
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <i class="fas fa-triangle-exclamation mr-2"></i>{{ session('warning') }}
        </div>
    @endif
    @if (session('temporary_credentials'))
        @php($tc = session('temporary_credentials'))
        <div class="mb-6 rounded-lg border border-alertara-300 bg-white p-4 text-sm">
            <p class="font-bold text-gray-900 mb-2"><i class="fas fa-key mr-2 text-alertara-700"></i>Temporary login details (shown once)</p>
            <div class="grid sm:grid-cols-2 gap-3">
                <div class="rounded-lg bg-gray-50 border border-gray-200 p-3">
                    <p class="text-[11px] font-bold uppercase text-gray-500">Email</p>
                    <p class="font-mono text-gray-900">{{ $tc['email'] }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 p-3">
                    <p class="text-[11px] font-bold uppercase text-gray-500">Temporary password</p>
                    <p class="font-mono text-lg font-bold text-gray-900 tracking-wider">{{ $tc['password'] }}</p>
                </div>
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <i class="fas fa-triangle-exclamation mr-2"></i>{{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <!-- KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="kpi">
            <p class="l">Total staff</p>
            <p class="v">{{ $stats['total'] }}</p>
            <p class="s">Accounts in this system</p>
        </div>
        <div class="kpi">
            <p class="l">Active</p>
            <p class="v text-emerald-700">{{ $stats['active'] }}</p>
            <p class="s">Can sign in today</p>
        </div>
        <div class="kpi">
            <p class="l">Pending password</p>
            <p class="v text-amber-600">{{ $stats['pending'] }}</p>
            <p class="s">Still on the emailed temporary password</p>
        </div>
        <div class="kpi">
            <p class="l">Signed in, 7 days</p>
            <p class="v text-alertara-700">{{ $stats['recent'] }}</p>
            <p class="s">Recently active staff</p>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <p class="text-xs font-bold uppercase text-gray-700 mb-2"><i class="fas fa-chart-pie mr-1 text-alertara-700"></i>Account status</p>
            <div class="chart-box"><canvas id="statusChart"></canvas></div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <p class="text-xs font-bold uppercase text-gray-700 mb-2"><i class="fas fa-chart-column mr-1 text-alertara-700"></i>Accounts added per month</p>
            <div class="chart-box"><canvas id="monthlyChart"></canvas></div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <p class="text-xs font-bold uppercase text-gray-700 mb-2"><i class="fas fa-id-badge mr-1 text-alertara-700"></i>By position</p>
            <div class="chart-box"><canvas id="positionChart"></canvas></div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="text-sm font-bold text-gray-900"><i class="fas fa-users mr-2 text-alertara-700"></i>Staff accounts</h2>
            <input type="search" id="staffSearch" placeholder="Search name, email or position"
                   class="w-full sm:w-72 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
        </div>

        @if ($staff->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-gray-500">
                <i class="fas fa-user-plus text-3xl text-gray-300 mb-3 block"></i>
                No staff accounts yet. Click <strong>Add Staff</strong> to create the first one.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="staffTable">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-left text-[11px] font-bold uppercase tracking-wider text-gray-600">
                            <th class="px-5 py-3">Staff</th>
                            <th class="px-5 py-3">Position</th>
                            <th class="px-5 py-3">Contact</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Last sign-in</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($staff as $s)
                            <tr class="hover:bg-gray-50" data-search="{{ strtolower($s->full_name . ' ' . $s->email . ' ' . $s->position) }}">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-alertara-500 to-alertara-700 text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                                            {{ strtoupper(mb_substr($s->full_name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 truncate">{{ $s->full_name }}</p>
                                            <p class="text-xs text-gray-500 truncate">{{ $s->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-gray-700">{{ $s->position ?: '—' }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ $s->contact_number ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    @if (!$s->is_active)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-gray-200 text-gray-700"><i class="fas fa-ban mr-1"></i>Deactivated</span>
                                    @elseif ($s->must_change_password)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800"><i class="fas fa-key mr-1"></i>Temp password</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800"><i class="fas fa-circle-check mr-1"></i>Active</span>
                                    @endif
                                    @if ($s->isLocked())
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-bold bg-red-100 text-red-800 ml-1" title="Locked until {{ $s->locked_until->format('g:i A') }}"><i class="fas fa-lock"></i></span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-600 text-xs whitespace-nowrap">
                                    {{ $s->last_login ? $s->last_login->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                        <button type="button" class="action-btn edit-staff-btn"
                                                data-id="{{ $s->id }}" data-name="{{ $s->full_name }}"
                                                data-position="{{ $s->position }}" data-contact="{{ $s->contact_number }}">
                                            <i class="fas fa-pen"></i>Edit
                                        </button>
                                        {{-- Every action that changes an account goes through the confirm modal; the
                                             form itself is submitted by the modal's confirm button. --}}
                                        <form method="POST" action="{{ route('staff.reset-password', $s->id) }}" class="confirm-form">
                                            @csrf
                                            <button type="button" class="action-btn confirm-btn"
                                                    data-kind="reset" data-name="{{ $s->full_name }}" data-email="{{ $s->email }}">
                                                <i class="fas fa-key"></i>Reset password
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('staff.toggle', $s->id) }}" class="confirm-form">
                                            @csrf
                                            <button type="button" class="action-btn confirm-btn"
                                                    data-kind="{{ $s->is_active ? 'deactivate' : 'activate' }}" data-name="{{ $s->full_name }}" data-email="{{ $s->email }}">
                                                <i class="fas {{ $s->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>{{ $s->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('staff.destroy', $s->id) }}" class="confirm-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="action-btn danger confirm-btn" title="Delete account"
                                                    data-kind="delete" data-name="{{ $s->full_name }}" data-email="{{ $s->email }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- Confirm action modal (reset password / activate / deactivate / delete) -->
<div class="staff-modal" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="card" style="max-width: 460px;">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div id="confirmIcon" class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 text-xl"></div>
                <div class="min-w-0 flex-1">
                    <h3 id="confirmTitle" class="text-lg font-bold text-gray-900"></h3>
                    <p id="confirmBody" class="text-sm text-gray-600 mt-1.5 leading-relaxed"></p>
                    <div class="mt-3 flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                        <i class="fas fa-user text-gray-400 text-xs"></i>
                        <div class="min-w-0">
                            <div id="confirmName" class="text-sm font-semibold text-gray-900 truncate"></div>
                            <div id="confirmEmail" class="text-xs text-gray-500 truncate"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
            <button type="button" onclick="closeModal('confirmModal')"
                    class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-100">
                Cancel
            </button>
            <button type="button" id="confirmOk" class="px-4 py-2 rounded-lg text-sm font-semibold text-white"></button>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="staff-modal" id="addStaffModal">
    <div class="card">
        <div class="px-6 py-4 bg-alertara-700 text-white flex items-center justify-between">
            <h3 class="font-bold"><i class="fas fa-user-plus mr-2"></i>Add staff account</h3>
            <button type="button" onclick="closeModal('addStaffModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('staff.store') }}" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-900 mb-1">Full name <span class="text-red-600">*</span></label>
                <input type="text" name="full_name" required maxlength="150" value="{{ old('full_name') }}" placeholder="e.g. Maria Santos"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-900 mb-1">Email address <span class="text-red-600">*</span></label>
                <input type="email" name="email" required maxlength="150" value="{{ old('email') }}" placeholder="staff@alertaraqc.com"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                <p class="text-xs text-gray-500 mt-1">The temporary password is sent to this address.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Position</label>
                    <input type="text" name="position" maxlength="120" value="{{ old('position') }}" placeholder="e.g. Crime Analyst"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Contact number</label>
                    <input type="text" name="contact_number" maxlength="40" value="{{ old('contact_number') }}" placeholder="0917 000 0000"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                </div>
            </div>
            <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs text-gray-600">
                <i class="fas fa-envelope mr-1 text-alertara-700"></i>
                A temporary password is generated and emailed. The staff member must set their own password on first sign-in.
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('addStaffModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-alertara-700 text-white rounded-lg text-sm font-semibold hover:bg-alertara-800"><i class="fas fa-paper-plane mr-1"></i>Create &amp; email credentials</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="staff-modal" id="editStaffModal">
    <div class="card">
        <div class="px-6 py-4 bg-gray-900 text-white flex items-center justify-between">
            <h3 class="font-bold"><i class="fas fa-pen mr-2"></i>Edit staff details</h3>
            <button type="button" onclick="closeModal('editStaffModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="editStaffForm" action="#" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-900 mb-1">Full name <span class="text-red-600">*</span></label>
                <input type="text" name="full_name" id="editFullName" required maxlength="150"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Position</label>
                    <input type="text" name="position" id="editPosition" maxlength="120"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-1">Contact number</label>
                    <input type="text" name="contact_number" id="editContact" maxlength="40"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('editStaffModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-semibold hover:bg-black">Save changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const STAFF_CHARTS = @json($charts);
    const STAFF_UPDATE_URL = @json(url('/staff')) + '/__ID__/update';

    function openModal(id) { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }
    document.querySelectorAll('.staff-modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.staff-modal.open').forEach(m => m.classList.remove('open')); });

    function openEdit(btn) {
        const d = btn.dataset;
        document.getElementById('editStaffForm').action = STAFF_UPDATE_URL.replace('__ID__', d.id);
        document.getElementById('editFullName').value = d.name || '';
        document.getElementById('editPosition').value = d.position || '';
        document.getElementById('editContact').value = d.contact || '';
        openModal('editStaffModal');
    }
    document.querySelectorAll('.edit-staff-btn').forEach(b => b.addEventListener('click', () => openEdit(b)));

    // One modal for every account action. What it says, and how loud the
    // confirm button is, depends on the action.
    const CONFIRM_KINDS = {
        reset: {
            title: 'Reset this password?',
            body: 'A new temporary password will be generated and emailed to the staff member. Their current password stops working immediately and they must set a new one on their next sign-in.',
            icon: 'fa-key', iconClass: 'bg-amber-100 text-amber-700',
            ok: 'Reset & email password', okClass: 'bg-alertara-600 hover:bg-alertara-700',
        },
        deactivate: {
            title: 'Deactivate this account?',
            body: 'The staff member will be signed out and can no longer log in. Their records and history are kept, and the account can be activated again at any time.',
            icon: 'fa-user-slash', iconClass: 'bg-orange-100 text-orange-700',
            ok: 'Deactivate account', okClass: 'bg-orange-600 hover:bg-orange-700',
        },
        activate: {
            title: 'Activate this account?',
            body: 'The staff member will be able to log in again with their existing password.',
            icon: 'fa-user-check', iconClass: 'bg-emerald-100 text-emerald-700',
            ok: 'Activate account', okClass: 'bg-emerald-600 hover:bg-emerald-700',
        },
        delete: {
            title: 'Delete this account?',
            body: 'This permanently removes the staff account. It cannot be undone. If you only want to stop them from logging in, deactivate the account instead.',
            icon: 'fa-trash', iconClass: 'bg-red-100 text-red-700',
            ok: 'Delete permanently', okClass: 'bg-red-600 hover:bg-red-700',
        },
    };
    let confirmForm = null;
    const okBtn = document.getElementById('confirmOk');

    document.querySelectorAll('.confirm-btn').forEach(btn => btn.addEventListener('click', () => {
        const k = CONFIRM_KINDS[btn.dataset.kind];
        if (!k) return;
        confirmForm = btn.closest('form');
        document.getElementById('confirmTitle').textContent = k.title;
        document.getElementById('confirmBody').textContent = k.body;
        document.getElementById('confirmName').textContent = btn.dataset.name || '';
        document.getElementById('confirmEmail').textContent = btn.dataset.email || '';
        const icon = document.getElementById('confirmIcon');
        icon.className = 'w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 text-xl ' + k.iconClass;
        icon.innerHTML = '<i class="fas ' + k.icon + '"></i>';
        okBtn.className = 'px-4 py-2 rounded-lg text-sm font-semibold text-white ' + k.okClass;
        okBtn.textContent = k.ok;
        okBtn.disabled = false;
        openModal('confirmModal');
        okBtn.focus();
    }));

    okBtn.addEventListener('click', () => {
        if (!confirmForm) return;
        // Guard against a double click while the request is on its way
        okBtn.disabled = true;
        okBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Working...';
        confirmForm.submit();
    });

    @if ($errors->any() && old('email'))
        openModal('addStaffModal');
    @endif

    document.getElementById('staffSearch')?.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('#staffTable tbody tr').forEach(tr => {
            tr.style.display = !q || tr.dataset.search.includes(q) ? '' : 'none';
        });
    });

    window.addEventListener('load', function () {
        if (typeof Chart === 'undefined') return;
        const gray = '#6b7280';

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Temporary password', 'Deactivated'],
                datasets: [{ data: STAFF_CHARTS.status, backgroundColor: ['#10b981', '#f59e0b', '#9ca3af'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 }, color: gray } } } }
        });

        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: STAFF_CHARTS.monthly.labels,
                datasets: [{ label: 'Accounts added', data: STAFF_CHARTS.monthly.values, backgroundColor: '#3a6b6a', borderRadius: 6, maxBarThickness: 34 }]
            },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0, color: gray }, grid: { color: '#f3f4f6' } }, x: { ticks: { color: gray }, grid: { display: false } } } }
        });

        new Chart(document.getElementById('positionChart'), {
            type: 'bar',
            data: {
                labels: STAFF_CHARTS.positions.labels.length ? STAFF_CHARTS.positions.labels : ['No staff yet'],
                datasets: [{ label: 'Staff', data: STAFF_CHARTS.positions.values.length ? STAFF_CHARTS.positions.values : [0], backgroundColor: '#6366f1', borderRadius: 6, maxBarThickness: 22 }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0, color: gray }, grid: { color: '#f3f4f6' } }, y: { ticks: { color: gray, font: { size: 11 } }, grid: { display: false } } } }
        });
    });
</script>
@endpush
