@extends('layouts.app')

@section('title', 'Saved AI Reports')

@php
    // Helpers for consistent forecast styling
    $dirColor = function ($direction) {
        return [
            'increase' => 'bg-red-100 text-red-800 border-red-200',
            'decrease' => 'bg-green-100 text-green-800 border-green-200',
            'stable'   => 'bg-gray-100 text-gray-700 border-gray-200',
        ][strtolower((string) $direction)] ?? 'bg-gray-100 text-gray-700 border-gray-200';
    };
    $dirIcon = function ($direction) {
        return [
            'increase' => 'fa-arrow-trend-up',
            'decrease' => 'fa-arrow-trend-down',
            'stable'   => 'fa-arrows-left-right',
        ][strtolower((string) $direction)] ?? 'fa-minus';
    };
    $prioColor = function ($priority) {
        return [
            'high'   => 'bg-red-100 text-red-700',
            'medium' => 'bg-amber-100 text-amber-700',
            'low'    => 'bg-blue-100 text-blue-700',
        ][strtolower((string) $priority)] ?? 'bg-gray-100 text-gray-600';
    };
    $fmtPct = function ($n) {
        if (!is_numeric($n)) return null;
        return ($n > 0 ? '+' : '') . rtrim(rtrim(number_format((float) $n, 1, '.', ''), '0'), '.') . '%';
    };
@endphp

@section('content')
<div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12">

    <!-- Page Header -->
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">
                    <i class="fas fa-robot text-alertara-700 mr-2"></i>Saved AI Reports
                </h1>
                <p class="text-gray-600 mt-1 text-sm lg:text-base">
                    Every AI analysis and recommendation you saved from Pattern Detection, grouped by save.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" id="copyApiBtn"
                        data-endpoint="{{ route('saved-ai-reports.data') }}"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-semibold">
                    <i class="fas fa-link mr-2 text-alertara-600"></i><span id="copyApiLabel">Copy API endpoint</span>
                </button>
                <a href="{{ authUrl('pattern-detection') }}"
                   class="inline-flex items-center px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors text-sm font-semibold">
                    <i class="fas fa-magnifying-glass mr-2"></i>Run New Analysis
                </a>
            </div>
        </div>

        <!-- API endpoint hint -->
        <div class="mt-4 pt-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center gap-2">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide flex-shrink-0">
                <i class="fas fa-code mr-1"></i>JSON API
            </span>
            <code id="apiEndpointText" class="text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded px-2 py-1 break-all">{{ route('saved-ai-reports.data') }}</code>
            <span class="text-xs text-gray-400">— returns every saved report with full payloads.</span>
        </div>
    </div>

    @if(empty($batches))
        <!-- Empty state -->
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-alertara-100 flex items-center justify-center">
                <i class="fas fa-box-archive text-alertara-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900">No saved AI reports yet</h3>
            <p class="text-gray-500 text-sm mt-1 max-w-md mx-auto">
                Go to <span class="font-semibold">Pattern Detection</span>, run an AI analysis, and click
                <span class="font-semibold">Save</span>. Anything you save will appear here.
            </p>
            <a href="{{ authUrl('pattern-detection') }}"
               class="inline-flex items-center mt-5 px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors text-sm font-semibold">
                <i class="fas fa-magnifying-glass mr-2"></i>Go to Pattern Detection
            </a>
        </div>
    @else
        <div class="mb-4 text-sm text-gray-500">
            <i class="fas fa-layer-group mr-1"></i>{{ count($batches) }} saved report{{ count($batches) === 1 ? '' : 's' }}
        </div>

        <div class="space-y-6">
            @foreach($batches as $batch)
                @php
                    $analysis = $batch['analysis'];
                    $payload  = $analysis?->payload ?? [];
                    $forecast = $payload['forecast'] ?? [];
                    $findings = $payload['key_findings'] ?? [];
                    $recs     = $batch['recommendations'];
                @endphp

                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <!-- Batch header -->
                    @php $isSim = ($batch['data_source'] ?? 'real') === 'simulation'; @endphp
                    <div class="px-6 py-4 border-b border-gray-200 {{ $isSim ? 'bg-amber-50' : 'bg-gray-50' }} flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3 flex-wrap">
                            @if($isSim)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-200 text-amber-900 text-xs font-bold">
                                    <i class="fas fa-flask mr-1"></i>SIMULATION
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">
                                    <i class="fas fa-database mr-1"></i>REAL DATA
                                </span>
                            @endif
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-alertara-100 text-alertara-800 text-xs font-bold">
                                <i class="fas fa-location-dot mr-1"></i>{{ $batch['barangay_name'] ?? 'San Agustin' }}
                            </span>
                            @if($batch['created_at'])
                                <span class="text-xs text-gray-500">
                                    <i class="far fa-clock mr-1"></i>{{ $batch['created_at']->format('M j, Y g:i A') }}
                                </span>
                            @endif
                            @if($batch['saved_by'])
                                <span class="text-xs text-gray-500"><i class="far fa-user mr-1"></i>{{ $batch['saved_by'] }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ $batch['records_used'] }} records · {{ $batch['period_days'] }}d
                            @if($batch['model']) · {{ $batch['model'] }} @endif
                        </div>
                    </div>

                    <div class="p-6 space-y-6">
                        @if($isSim && !empty($batch['scenario']))
                            @php $sc = $batch['scenario']; @endphp
                            <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 text-xs text-amber-900 flex flex-wrap items-center gap-x-4 gap-y-1">
                                <span class="font-bold uppercase tracking-wide text-amber-700">Scenario</span>
                                <span><i class="fas fa-sliders-h mr-1"></i>{{ ($sc['scenario_type'] ?? '') === 'prevention' ? 'With prevention measures' : 'Risk — no prevention' }}</span>
                                @if(!empty($sc['missing_safeguards']))
                                    <span><i class="fas fa-triangle-exclamation mr-1"></i>Missing: {{ implode(', ', (array) $sc['missing_safeguards']) }}</span>
                                @endif
                                @if(!empty($sc['prevention_measures']))
                                    <span><i class="fas fa-shield-halved mr-1"></i>Measures: {{ implode(', ', (array) $sc['prevention_measures']) }}</span>
                                @endif
                                @if(!empty($sc['crime_types']))
                                    <span><i class="fas fa-tags mr-1"></i>Types: {{ implode(', ', (array) $sc['crime_types']) }}</span>
                                @endif
                                <span><i class="fas fa-location-crosshairs mr-1"></i>{{ ($sc['focus'] ?? '') === 'streets' && !empty($sc['streets']) ? (count((array) $sc['streets']) . ' street(s)') : 'Whole barangay' }}</span>
                            </div>
                        @endif

                        <!-- Forecast -->
                        @if(!empty($forecast))
                            <div>
                                <div class="flex items-center gap-3 flex-wrap mb-2">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full border text-sm font-bold {{ $dirColor($forecast['direction'] ?? '') }}">
                                        <i class="fas {{ $dirIcon($forecast['direction'] ?? '') }} mr-1.5"></i>
                                        {{ ucfirst($forecast['direction'] ?? 'unknown') }}
                                        @if($fmtPct($forecast['expected_change_percent'] ?? null))
                                            <span class="ml-1.5">{{ $fmtPct($forecast['expected_change_percent']) }}</span>
                                        @endif
                                    </span>
                                    @if(!empty($forecast['confidence']))
                                        <span class="text-xs font-medium text-gray-500">
                                            Confidence: <span class="font-bold text-gray-700">{{ ucfirst($forecast['confidence']) }}</span>
                                        </span>
                                    @endif
                                </div>
                                @if(!empty($forecast['summary']))
                                    <p class="text-sm text-gray-700 leading-relaxed">{{ $forecast['summary'] }}</p>
                                @endif
                            </div>
                        @elseif($analysis)
                            <p class="text-sm text-gray-700">{{ $analysis->summary ?? $analysis->title }}</p>
                        @endif

                        <!-- Key findings -->
                        @if(!empty($findings))
                            <div>
                                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">
                                    <i class="fas fa-lightbulb mr-1"></i>Key Findings
                                </h3>
                                <ul class="space-y-1.5">
                                    @foreach($findings as $finding)
                                        <li class="flex items-start gap-2 text-sm text-gray-700">
                                            <i class="fas fa-circle text-[5px] text-alertara-500 mt-2 flex-shrink-0"></i>
                                            <span>{{ is_array($finding) ? ($finding['text'] ?? json_encode($finding)) : $finding }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Recommendations -->
                        @if(!empty($recs))
                            <div>
                                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">
                                    <i class="fas fa-clipboard-check mr-1"></i>Recommendations ({{ count($recs) }})
                                </h3>
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                    @foreach($recs as $rec)
                                        @php
                                            $r = $rec->payload ?? [];
                                            $impact = $r['expected_impact'] ?? [];
                                            $action = $r['action'] ?? $rec->title;
                                            $location = $r['location'] ?? null;
                                            $rationale = $r['rationale'] ?? null;
                                        @endphp
                                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/60 hover:border-alertara-300 transition-colors">
                                            <div class="flex items-start justify-between gap-2 mb-1.5">
                                                <div class="text-sm font-bold text-gray-900">{{ $action }}</div>
                                                @if(!empty($r['priority']))
                                                    <span class="flex-shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $prioColor($r['priority']) }} uppercase">
                                                        {{ $r['priority'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if($location)
                                                <div class="text-xs text-alertara-700 font-medium mb-1.5">
                                                    <i class="fas fa-map-pin mr-1"></i>{{ $location }}
                                                </div>
                                            @endif
                                            @if($rationale)
                                                <p class="text-xs text-gray-600 leading-relaxed mb-2">{{ $rationale }}</p>
                                            @endif
                                            @if(!empty($impact))
                                                <div class="flex items-center gap-2 pt-2 border-t border-gray-200">
                                                    <span class="text-[10px] font-bold text-gray-400 uppercase">If implemented</span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $dirColor($impact['direction'] ?? '') }}">
                                                        <i class="fas {{ $dirIcon($impact['direction'] ?? '') }} mr-1"></i>
                                                        {{ $fmtPct($impact['estimated_change_percent'] ?? null) ?? ucfirst($impact['direction'] ?? '') }}
                                                    </span>
                                                </div>
                                                @if(!empty($impact['explanation']))
                                                    <p class="text-[11px] text-gray-500 mt-1.5 italic">{{ $impact['explanation'] }}</p>
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(empty($forecast) && empty($findings) && empty($recs) && $analysis)
                            <p class="text-sm text-gray-500 italic">No detailed content stored for this report.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
(function () {
    const btn = document.getElementById('copyApiBtn');
    if (!btn) return;
    const label = document.getElementById('copyApiLabel');
    const icon = btn.querySelector('i');

    // The stored path is relative to the app root; copy the absolute URL so it
    // is usable straight from Postman/curl/another service.
    const endpoint = new URL(btn.dataset.endpoint, window.location.origin).href;

    async function copy(text) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            }
        } catch (e) { /* fall through to legacy */ }
        try {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            return ok;
        } catch (e) {
            return false;
        }
    }

    btn.addEventListener('click', async function () {
        const ok = await copy(endpoint);
        const prevLabel = 'Copy API endpoint';
        label.textContent = ok ? 'Copied!' : 'Press Ctrl+C';
        icon.className = ok ? 'fas fa-check mr-2 text-green-600' : 'fas fa-triangle-exclamation mr-2 text-amber-600';
        setTimeout(function () {
            label.textContent = prevLabel;
            icon.className = 'fas fa-link mr-2 text-alertara-600';
        }, 1800);
    });
})();
</script>
@endsection
