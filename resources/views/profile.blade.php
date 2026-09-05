@php
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
    <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
        <!-- Page Header -->
        <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                        <i class="fas fa-user-circle mr-3" style="color: #274d4c;"></i>My Profile
                    </h1>
                    <p class="text-gray-600 mt-1 text-sm lg:text-base">
                        Your account and what you have done in this system.
                    </p>
                </div>
                <a href="{{ route('settings') }}"
                   class="px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors flex items-center gap-2 text-sm font-semibold">
                    <i class="fas fa-sliders-h"></i>Settings
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <i class="fas fa-circle-check mr-2"></i>{{ session('success') }}
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Identity -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white border border-gray-200 rounded-lg p-6 text-center">
                    <div class="w-20 h-20 mx-auto bg-gradient-to-br from-alertara-500 to-alertara-700 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-user text-white text-2xl"></i>
                    </div>
                    <h2 class="text-lg font-bold text-gray-900 break-words">
                        {{ $preferences['display_name'] ?: $identity['email'] }}
                    </h2>
                    @if ($preferences['display_name'])
                        <p class="text-sm text-gray-500 break-words">{{ $identity['email'] }}</p>
                    @endif
                    @if ($preferences['position'])
                        <p class="text-sm text-gray-700 mt-1 font-medium">{{ $preferences['position'] }}</p>
                    @endif

                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-alertara-100 text-alertara-800">
                            {{ strtoupper($identity['role']) }}
                        </span>
                        @if ($identity['department_name'])
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700">
                                {{ $identity['department_name'] }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 class="text-sm font-bold text-gray-900 mb-3">
                        <i class="fas fa-id-badge mr-2 text-alertara-700"></i>Account
                    </h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-600">Signed in via</dt>
                            <dd class="font-medium text-gray-900 text-right">{{ $identity['source'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-600">Account ID</dt>
                            <dd class="font-medium text-gray-900">{{ $identity['id'] ?? '—' }}</dd>
                        </div>
                        @if ($account?->last_login)
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-600">Last login</dt>
                                <dd class="font-medium text-gray-900 text-right">
                                    {{ \Carbon\Carbon::parse($account->last_login)->format('M j, Y g:i A') }}
                                </dd>
                            </div>
                        @endif
                        @if ($preferences['contact_number'])
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-600">Contact</dt>
                                <dd class="font-medium text-gray-900">{{ $preferences['contact_number'] }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($identity['is_staff'])
                        <div class="mt-4 rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs text-gray-600">
                            <i class="fas fa-lock mr-1"></i>
                            Your password is managed in this system. Change it any time from the
                            <a href="#changePassword" class="text-alertara-700 font-semibold underline">Change password</a> card.
                        </div>
                    @else
                        <div class="mt-4 rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs text-gray-600">
                            <i class="fas fa-lock mr-1"></i>
                            Your email, password and role are managed by the centralized Alertara login,
                            not by this system. Change them at
                            <a href="{{ config('services.central_auth.login_url', 'https://login.alertaraqc.com') }}"
                               target="_blank" rel="noopener"
                               class="text-alertara-700 font-semibold underline">login.alertaraqc.com</a>.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Editable details + activity -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-1">
                        <i class="fas fa-pen-to-square mr-2 text-alertara-700"></i>Your details
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">
                        How you appear inside this system. Saved here, not sent to the centralized login.
                    </p>

                    <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-900 mb-1">Display name</label>
                                <input type="text" name="display_name" maxlength="120"
                                       value="{{ old('display_name', $preferences['display_name']) }}"
                                       placeholder="e.g. PO2 Juan dela Cruz"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-900 mb-1">Position</label>
                                <input type="text" name="position" maxlength="120"
                                       value="{{ old('position', $preferences['position']) }}"
                                       placeholder="e.g. Crime Analyst"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Contact number</label>
                            <input type="text" name="contact_number" maxlength="40"
                                   value="{{ old('contact_number', $preferences['contact_number']) }}"
                                   placeholder="e.g. 0917 000 0000"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 font-semibold text-sm">
                                Save details
                            </button>
                        </div>
                    </form>
                </div>

                @if ($identity['is_staff'])
                    <!-- Change password (staff accounts) -->
                    <div id="changePassword" class="bg-white border rounded-lg p-6 {{ $identity['must_change_password'] ? 'border-amber-300 ring-2 ring-amber-100' : 'border-gray-200' }}">
                        <h3 class="text-lg font-bold text-gray-900 mb-1">
                            <i class="fas fa-key mr-2 text-alertara-700"></i>Change password
                        </h3>
                        @if ($identity['must_change_password'])
                            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                <i class="fas fa-triangle-exclamation mr-1"></i>
                                You are still using the temporary password that was emailed to you. Set a new one to unlock the rest of the system.
                            </div>
                        @else
                            <p class="text-sm text-gray-600 mb-4">
                                Use at least 8 characters. You stay signed in after the change.
                            </p>
                        @endif

                        <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-900 mb-1">Current password</label>
                                <input type="password" name="current_password" required autocomplete="current-password"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 @error('current_password') border-red-400 @enderror">
                                @error('current_password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-900 mb-1">New password</label>
                                    <input type="password" name="password" id="newPassword" required minlength="8" autocomplete="new-password"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 @error('password') border-red-400 @enderror">
                                    <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden"><div id="pwStrength" class="h-full w-0 rounded-full bg-gray-300 transition-all"></div></div>
                                    <p id="pwStrengthLabel" class="text-[11px] text-gray-500 mt-1">Strength</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-900 mb-1">Confirm new password</label>
                                    <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 font-semibold text-sm">
                                    <i class="fas fa-shield-halved mr-1"></i>Update password
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                <!-- Real activity -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white border border-gray-200 rounded-lg p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase">Logged actions</p>
                        <p class="text-3xl font-bold text-alertara-700">{{ $activity['audit_entries'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">Entries in the audit log</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-lg p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase">Crime reports</p>
                        <p class="text-3xl font-bold text-alertara-700">{{ $activity['crime_reports'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">Saved selections you created</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-lg p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase">AI reports</p>
                        <p class="text-3xl font-bold text-alertara-700">{{ $activity['ai_reports'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">Analyses you saved</p>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">
                            <i class="fas fa-clock-rotate-left mr-2 text-alertara-700"></i>Your recent activity
                        </h3>
                        <a href="{{ route('audit-logs.index') }}" class="text-xs font-semibold text-alertara-700 hover:underline">
                            Full audit log
                        </a>
                    </div>

                    @if ($recentActions->isEmpty())
                        <div class="px-6 py-8 text-center text-sm text-gray-500">
                            Nothing recorded against this account yet. Actions such as adding a crime,
                            decrypting data or importing reports appear here.
                        </div>
                    @else
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($recentActions as $action)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 font-medium text-gray-900">
                                            {{ ucwords(strtolower(str_replace('_', ' ', $action->action_type))) }}
                                        </td>
                                        <td class="px-6 py-3 text-gray-600 text-xs">
                                            {{ str_replace('crime_department_', '', $action->target_table) }}
                                            @if ($action->target_id) #{{ $action->target_id }} @endif
                                        </td>
                                        <td class="px-6 py-3 text-right text-gray-500 text-xs whitespace-nowrap">
                                            {{ $action->created_at ? \Carbon\Carbon::parse($action->created_at)->diffForHumans() : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const input = document.getElementById('newPassword');
        if (!input) return;
        const bar = document.getElementById('pwStrength');
        const label = document.getElementById('pwStrengthLabel');
        input.addEventListener('input', function () {
            const v = input.value;
            let score = 0;
            if (v.length >= 8) score++;
            if (v.length >= 12) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/\d/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            const levels = [
                ['0%', '#d1d5db', 'Strength'],
                ['25%', '#ef4444', 'Weak'],
                ['45%', '#f59e0b', 'Fair'],
                ['70%', '#10b981', 'Good'],
                ['85%', '#059669', 'Strong'],
                ['100%', '#047857', 'Very strong'],
            ];
            const l = levels[Math.min(score, 5)];
            bar.style.width = l[0]; bar.style.background = l[1]; label.textContent = l[2];
        });
    })();
</script>
@endpush
