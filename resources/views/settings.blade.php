@php
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

@extends('layouts.app')
@section('title', 'Settings')
@section('content')
    <div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">
        <!-- Page Header -->
        <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                        <i class="fas fa-sliders-h mr-3" style="color: #274d4c;"></i>Settings
                    </h1>
                    <p class="text-gray-600 mt-1 text-sm lg:text-base">
                        How the crime pages open for you. Every setting here is read by a page — nothing is decorative.
                    </p>
                </div>
                <a href="{{ route('profile') }}"
                   class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 text-sm font-semibold">
                    <i class="fas fa-user-circle"></i>My Profile
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

        <form method="POST" action="{{ route('settings.update') }}">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Map defaults -->
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-1">
                        <i class="fas fa-map mr-2 text-alertara-700"></i>Map defaults
                    </h2>
                    <p class="text-sm text-gray-600 mb-4">
                        Applied when Crime Mapping first loads, so you stop re-setting the same filters.
                    </p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">View mode</label>
                            <select name="default_view_mode" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 bg-white">
                                @foreach (['markers' => 'Individual markers', 'heatmap' => 'Heat map', 'clusters' => 'Cluster view (per street)'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('default_view_mode', $preferences['default_view_mode']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Time period</label>
                            <select name="default_time_period" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 bg-white">
                                @foreach (['30' => 'Last 30 days', '90' => 'Last 90 days', '180' => 'Last 6 months', 'all' => 'All time'] as $value => $label)
                                    <option value="{{ $value }}" @selected((string) old('default_time_period', $preferences['default_time_period']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Barangay the map opens on</label>
                            <select name="default_barangay" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 bg-white">
                                <option value="">All barangays</option>
                                @foreach ($barangays as $name)
                                    <option value="{{ $name }}" @selected(old('default_barangay', $preferences['default_barangay']) === $name)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-500 mt-1">
                                Recorded crime data only covers San Agustin; other barangays will show an empty map.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Lists and language -->
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-1">
                        <i class="fas fa-list mr-2 text-alertara-700"></i>Lists &amp; language
                    </h2>
                    <p class="text-sm text-gray-600 mb-4">
                        Applies to crime lists and to the street prevention suggestions.
                    </p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Rows per page</label>
                            <select name="rows_per_page" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 bg-white">
                                @foreach ([10, 25, 50, 100] as $value)
                                    <option value="{{ $value }}" @selected((int) old('rows_per_page', $preferences['rows_per_page']) === $value)>{{ $value }} rows</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Suggestion language</label>
                            <select name="suggestion_language" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 bg-white">
                                <option value="en" @selected(old('suggestion_language', $preferences['suggestion_language']) === 'en')>English</option>
                                <option value="tl" @selected(old('suggestion_language', $preferences['suggestion_language']) === 'tl')>Taglish</option>
                            </select>
                            <p class="text-[11px] text-gray-500 mt-1">
                                Used by the street prevention suggestions on Crime Hotspots.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-1">
                        <i class="fas fa-bell mr-2 text-alertara-700"></i>Alerts
                    </h2>
                    <p class="text-sm text-gray-600 mb-4">
                        How alerts reach you. The rules that raise them live in
                        <a href="{{ route('alerts.management') }}" class="text-alertara-700 font-semibold underline">Alert Management</a>.
                    </p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Only show me alerts of at least</label>
                            <select name="alert_min_severity" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 bg-white">
                                @foreach (['low' => 'Low — show everything', 'medium' => 'Medium and above', 'high' => 'High and above', 'critical' => 'Critical only'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('alert_min_severity', $preferences['alert_min_severity']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <label class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900">Play a sound for new alerts</span>
                            <input type="checkbox" name="alert_sound" value="1" class="w-4 h-4"
                                   @checked(old('alert_sound', $preferences['alert_sound']))>
                        </label>
                    </div>
                </div>

                <!-- Where things live -->
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-1">
                        <i class="fas fa-circle-info mr-2 text-alertara-700"></i>Managed elsewhere
                    </h2>
                    <p class="text-sm text-gray-600 mb-4">Settings that belong to another part of the system.</p>

                    <ul class="space-y-3 text-sm">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-arrow-right text-gray-400 mt-1"></i>
                            <span>
                                <a href="{{ route('alerts.settings') }}" class="text-alertara-700 font-semibold underline">Alert thresholds</a>
                                — hotspot density and response-time targets.
                            </span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-arrow-right text-gray-400 mt-1"></i>
                            <span>
                                <a href="{{ config('services.central_auth.login_url', 'https://login.alertaraqc.com') }}"
                                   target="_blank" rel="noopener" class="text-alertara-700 font-semibold underline">Email, password and role</a>
                                — held by the centralized Alertara login.
                            </span>
                        </li>
                    </ul>

                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <p class="text-xs text-gray-500 mb-2">Signed in as {{ $identity['email'] }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button type="submit" class="px-5 py-2.5 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 font-semibold">
                    <i class="fas fa-floppy-disk mr-2"></i>Save settings
                </button>
                <a href="{{ route('settings.reset') }}"
                   onclick="return confirm('Restore all settings to their defaults?')"
                   class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-semibold">
                    Restore defaults
                </a>
            </div>
        </form>
    </div>
@endsection
