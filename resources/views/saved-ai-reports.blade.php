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
    $dirHex = function ($direction) {
        return [
            'increase' => '#b91c1c',
            'decrease' => '#15803d',
            'stable'   => '#374151',
        ][strtolower((string) $direction)] ?? '#374151';
    };
    $prioColor = function ($priority) {
        return [
            'high'   => 'bg-red-100 text-red-700',
            'medium' => 'bg-amber-100 text-amber-700',
            'low'    => 'bg-gray-100 text-gray-600',
        ][strtolower((string) $priority)] ?? 'bg-gray-100 text-gray-600';
    };
    $riskColor = function ($risk) {
        return [
            'high'   => 'bg-red-100 text-red-800',
            'medium' => 'bg-amber-100 text-amber-800',
            'low'    => 'bg-green-100 text-green-800',
        ][strtolower((string) $risk)] ?? 'bg-gray-100 text-gray-700';
    };
    $fmtPct = function ($n) {
        if (!is_numeric($n)) return null;
        return ($n > 0 ? '+' : '') . rtrim(rtrim(number_format((float) $n, 1, '.', ''), '0'), '.') . '%';
    };
    // Icons for the findings cards, picked from the sentence itself
    $findingIcon = function ($text) {
        $t = strtolower((string) $text);
        foreach ([
            ['night', 'fa-moon', 'text-indigo-600 bg-indigo-50'],
            ['unresolved', 'fa-folder-open', 'text-red-600 bg-red-50'],
            ['peak', 'fa-clock', 'text-violet-600 bg-violet-50'],
            ['busiest', 'fa-calendar-day', 'text-sky-600 bg-sky-50'],
            ['street', 'fa-road', 'text-orange-600 bg-orange-50'],
            ['increas', 'fa-arrow-trend-up', 'text-red-600 bg-red-50'],
            ['decreas', 'fa-arrow-trend-down', 'text-green-600 bg-green-50'],
        ] as [$needle, $icon, $cls]) {
            if (str_contains($t, $needle)) return [$icon, $cls];
        }
        return ['fa-lightbulb', 'text-alertara-600 bg-alertara-50'];
    };
    $chip = fn ($icon, $text, $cls = 'bg-gray-100 text-gray-700') =>
        '<span class="inline-flex items-center gap-1 text-[10.5px] font-bold px-2 py-0.5 rounded-full ' . $cls . '"><i class="fas ' . $icon . ' text-[9.5px] opacity-80"></i>' . e($text) . '</span>';
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
                    Every report you saved from Pattern Detection and Crime Mapping, with its charts and prevention plan.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" id="copyApiBtn"
                        data-endpoint="{{ route('saved-ai-reports.data') }}"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-semibold"
                        title="{{ route('saved-ai-reports.data') }} — JSON of every saved report with full payloads">
                    <i class="fas fa-link mr-2 text-alertara-600"></i><span id="copyApiLabel">Copy API endpoint</span>
                </button>
                <a href="{{ authUrl('pattern-detection') }}"
                   class="inline-flex items-center px-4 py-2 bg-alertara-700 text-white rounded-lg hover:bg-alertara-800 transition-colors text-sm font-semibold">
                    <i class="fas fa-magnifying-glass mr-2"></i>Run New Analysis
                </a>
            </div>
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
        @php
            $isMappingBatch = fn ($b) => (($b['scenario']['type'] ?? '') === 'street_advice');
            $mapCount = count(array_filter($batches, $isMappingBatch));
            $patCount = count($batches) - $mapCount;
            $simCount = count(array_filter($batches, fn ($b) => ($b['data_source'] ?? 'real') === 'simulation'));
            $recTotal = array_sum(array_map(fn ($b) => count($b['recommendations']), $batches));
            $highTotal = 0;
            foreach ($batches as $b) {
                foreach ($b['recommendations'] as $rec) {
                    if (strtolower((string) (($rec->payload ?? [])['priority'] ?? '')) === 'high') $highTotal++;
                }
            }
        @endphp

        <!-- Overview strip: what is in the archive -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
            <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Saved reports</div>
                <div class="text-2xl font-extrabold text-gray-900">{{ count($batches) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Recommendations</div>
                <div class="text-2xl font-extrabold text-gray-900">{{ $recTotal }}</div>
                <div class="mt-1.5 h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full bg-red-500" style="width: {{ $recTotal ? round($highTotal / $recTotal * 100) : 0 }}%"></div></div>
                <div class="text-[10px] text-gray-500 mt-1"><b class="text-red-700">{{ $highTotal }}</b> high priority</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Source mix</div>
                <div class="mt-2 flex h-2.5 rounded-full overflow-hidden bg-gray-100">
                    <div class="bg-violet-500" style="width: {{ count($batches) ? round($patCount / count($batches) * 100) : 0 }}%" title="Pattern Detection: {{ $patCount }}"></div>
                    <div class="bg-indigo-400" style="width: {{ count($batches) ? round($mapCount / count($batches) * 100) : 0 }}%" title="Crime Mapping: {{ $mapCount }}"></div>
                </div>
                <div class="flex gap-3 text-[10px] text-gray-600 mt-1.5">
                    <span><span class="inline-block w-2 h-2 rounded-full bg-violet-500 mr-1"></span>Pattern <b>{{ $patCount }}</b></span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-1"></span>Mapping <b>{{ $mapCount }}</b></span>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Real vs simulated</div>
                <div class="mt-2 flex h-2.5 rounded-full overflow-hidden bg-gray-100">
                    <div class="bg-emerald-500" style="width: {{ count($batches) ? round((count($batches) - $simCount) / count($batches) * 100) : 0 }}%"></div>
                    <div class="bg-amber-400" style="width: {{ count($batches) ? round($simCount / count($batches) * 100) : 0 }}%"></div>
                </div>
                <div class="flex gap-3 text-[10px] text-gray-600 mt-1.5">
                    <span><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1"></span>Real <b>{{ count($batches) - $simCount }}</b></span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-amber-400 mr-1"></span>Sim <b>{{ $simCount }}</b></span>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Latest save</div>
                <div class="text-sm font-extrabold text-gray-900 mt-1">{{ $batches[0]['created_at']?->format('M j, Y') ?? '—' }}</div>
                <div class="text-[10px] text-gray-500">{{ $batches[0]['created_at']?->format('g:i A') ?? '' }}</div>
            </div>
        </div>

        <!-- Source filter: where each saved report came from -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide mr-1"><i class="fas fa-filter mr-1"></i>Source</span>
            <button type="button" class="report-filter px-3 py-1.5 rounded-lg text-xs font-bold border" data-filter="all">
                <i class="fas fa-layer-group mr-1"></i>All ({{ count($batches) }})
            </button>
            <button type="button" class="report-filter px-3 py-1.5 rounded-lg text-xs font-bold border" data-filter="pattern">
                <i class="fas fa-magnifying-glass-chart mr-1"></i>Pattern Detection ({{ $patCount }})
            </button>
            <button type="button" class="report-filter px-3 py-1.5 rounded-lg text-xs font-bold border" data-filter="mapping">
                <i class="fas fa-map-location-dot mr-1"></i>Crime Mapping ({{ $mapCount }})
            </button>
            <span id="filterEmptyNote" class="hidden text-xs text-gray-400 ml-2">No saved reports from this source yet.</span>
        </div>

        <div class="space-y-4">
            @foreach($batches as $bi => $batch)
                @php
                    $analysis = $batch['analysis'];
                    $payload  = $analysis?->payload ?? [];
                    $forecast = $payload['forecast'] ?? [];
                    $risk     = $payload['risk_level'] ?? null;
                    $findings = $payload['key_findings'] ?? [];
                    $chart    = $payload['chart_data'] ?? null;
                    $recs     = $batch['recommendations'];
                    $fromMapping = $isMappingBatch($batch);
                    $isSim = ($batch['data_source'] ?? 'real') === 'simulation';
                    $open = $bi === 0;

                    $recRows = [];
                    $highCount = 0;
                    foreach ($recs as $rec) {
                        $r = $rec->payload ?? [];
                        $pr = strtolower((string) ($r['priority'] ?? 'low'));
                        if ($pr === 'high') $highCount++;
                        $recRows[] = [
                            'action'   => (string) ($r['action'] ?? $rec->title),
                            'priority' => $pr,
                            'pct'      => is_numeric($r['expected_impact']['estimated_change_percent'] ?? null) ? (float) $r['expected_impact']['estimated_change_percent'] : null,
                            'location' => (string) ($r['street'] ?? $r['location'] ?? ''),
                        ];
                    }
                    $streetCount = is_array($chart['streets'] ?? null) ? count($chart['streets']) : (is_array($batch['scenario']['streets'] ?? null) ? count($batch['scenario']['streets']) : null);
                    $batchJson = ['forecast' => $forecast, 'risk_level' => $risk, 'chart' => $chart, 'recs' => $recRows];
                @endphp

                <div class="report-batch bg-white rounded-xl border border-gray-200 overflow-hidden" data-source="{{ $fromMapping ? 'mapping' : 'pattern' }}" data-batch="{{ $batch['batch_key'] }}">
                    <!-- Batch header -->
                    <div class="px-5 py-3.5 border-b border-gray-200 {{ $isSim ? 'bg-amber-50' : ($fromMapping ? 'bg-indigo-50/50' : 'bg-gray-50') }} flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if($fromMapping)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-800 text-xs font-bold">
                                    <i class="fas fa-map-location-dot mr-1"></i>CRIME MAPPING
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-violet-100 text-violet-800 text-xs font-bold">
                                    <i class="fas fa-magnifying-glass-chart mr-1"></i>PATTERN DETECTION
                                </span>
                            @endif
                            @if($isSim)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-200 text-amber-900 text-xs font-bold">
                                    <i class="fas fa-flask mr-1"></i>SIMULATION
                                </span>
                            @elseif(!$fromMapping)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">
                                    <i class="fas fa-database mr-1"></i>REAL DATA
                                </span>
                            @endif
                            @if(!empty($forecast['direction']))
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-bold {{ $dirColor($forecast['direction']) }}">
                                    <i class="fas {{ $dirIcon($forecast['direction']) }} mr-1"></i>{{ strtoupper($forecast['direction']) }}
                                    @if($fmtPct($forecast['expected_change_percent'] ?? null)) <span class="ml-1">{{ $fmtPct($forecast['expected_change_percent']) }}</span> @endif
                                </span>
                            @elseif($risk)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $riskColor($risk) }}">
                                    <i class="fas fa-gauge-high mr-1"></i>{{ strtoupper($risk) }} RISK
                                </span>
                            @endif
                            @if($batch['created_at'])
                                <span class="text-xs text-gray-500"><i class="far fa-clock mr-1"></i>{{ $batch['created_at']->format('M j, Y g:i A') }}</span>
                            @endif
                            @if($batch['saved_by'])
                                <span class="text-xs text-gray-500"><i class="far fa-user mr-1"></i>{{ $batch['saved_by'] }}</span>
                            @endif
                            @if(!empty($batch['received_by']))
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-teal-100 text-teal-800 text-xs font-bold"
                                      title="Received {{ $batch['received_at'] ? $batch['received_at']->format('M j, Y g:i A') : '' }}">
                                    <i class="fas fa-inbox mr-1"></i>RECEIVED &middot; {{ $batch['received_by'] }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button type="button" class="batch-toggle inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-xs font-semibold" aria-expanded="{{ $open ? 'true' : 'false' }}">
                                <i class="fas {{ $open ? 'fa-chevron-up' : 'fa-chevron-down' }} mr-1"></i><span>{{ $open ? 'Hide' : 'Show' }} report</span>
                            </button>
                            <button type="button" class="batch-pdf inline-flex items-center px-3 py-1.5 bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition-colors text-xs font-semibold">
                                <i class="fas fa-file-pdf mr-1"></i>PDF
                            </button>
                        </div>
                    </div>

                    <!-- KPI strip: always visible, even when the report is collapsed -->
                    <div class="px-5 py-3 border-b border-gray-100" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;">
                        @if(!empty($forecast['direction']))
                            <div class="rounded-lg border border-gray-200 bg-gradient-to-b from-white to-gray-50 px-3 py-2">
                                <div class="text-[9.5px] font-bold text-gray-500 uppercase tracking-wide">Forecast</div>
                                <div class="text-lg font-extrabold leading-tight" style="color: {{ $dirHex($forecast['direction']) }}">
                                    <i class="fas {{ $dirIcon($forecast['direction']) }} text-sm mr-1"></i>{{ $fmtPct($forecast['expected_change_percent'] ?? null) ?? ucfirst($forecast['direction']) }}
                                </div>
                                @if(!empty($forecast['confidence']))<div class="text-[10px] text-gray-500">{{ ucfirst($forecast['confidence']) }} confidence</div>@endif
                            </div>
                        @elseif($risk)
                            <div class="rounded-lg border border-gray-200 bg-gradient-to-b from-white to-gray-50 px-3 py-2">
                                <div class="text-[9.5px] font-bold text-gray-500 uppercase tracking-wide">Risk level</div>
                                <div class="text-lg font-extrabold leading-tight" style="color: {{ ['high' => '#b91c1c', 'medium' => '#b45309', 'low' => '#15803d'][strtolower($risk)] ?? '#374151' }}">{{ strtoupper($risk) }}</div>
                            </div>
                        @endif
                        <div class="rounded-lg border border-gray-200 bg-gradient-to-b from-white to-gray-50 px-3 py-2">
                            <div class="text-[9.5px] font-bold text-gray-500 uppercase tracking-wide">Records</div>
                            <div class="text-lg font-extrabold text-gray-900 leading-tight">{{ number_format((int) $batch['records_used']) }}</div>
                            <div class="text-[10px] text-gray-500">{{ $batch['period_days'] }}-day period</div>
                        </div>
                        @if($streetCount !== null)
                            <div class="rounded-lg border border-gray-200 bg-gradient-to-b from-white to-gray-50 px-3 py-2">
                                <div class="text-[9.5px] font-bold text-gray-500 uppercase tracking-wide">Streets</div>
                                <div class="text-lg font-extrabold text-gray-900 leading-tight">{{ $streetCount }}</div>
                                @if(is_array($chart['streets'] ?? null))
                                    <div class="text-[10px] text-gray-500">{{ array_sum(array_column($chart['streets'], 'total')) }} crimes</div>
                                @endif
                            </div>
                        @endif
                        <div class="rounded-lg border border-gray-200 bg-gradient-to-b from-white to-gray-50 px-3 py-2">
                            <div class="text-[9.5px] font-bold text-gray-500 uppercase tracking-wide">Actions</div>
                            <div class="text-lg font-extrabold leading-tight {{ $highCount ? 'text-violet-700' : 'text-gray-900' }}">{{ count($recs) }}</div>
                            <div class="mt-1 flex h-1.5 rounded-full overflow-hidden bg-gray-100" title="high / medium / low priority">
                                @php $cnt = ['high' => 0, 'medium' => 0, 'low' => 0]; foreach ($recRows as $rr) { $cnt[$rr['priority']] = ($cnt[$rr['priority']] ?? 0) + 1; } $n = max(1, count($recRows)); @endphp
                                <div class="bg-red-500" style="width: {{ $cnt['high'] / $n * 100 }}%"></div>
                                <div class="bg-amber-400" style="width: {{ $cnt['medium'] / $n * 100 }}%"></div>
                                <div class="bg-gray-400" style="width: {{ ($cnt['low'] ?? 0) / $n * 100 }}%"></div>
                            </div>
                            <div class="text-[10px] text-gray-500 mt-0.5"><b class="text-red-700">{{ $cnt['high'] }}</b> high · <b class="text-amber-700">{{ $cnt['medium'] }}</b> med · <b>{{ $cnt['low'] ?? 0 }}</b> low</div>
                        </div>
                        @if($isSim && !empty($batch['scenario']))
                            @php $sc = $batch['scenario']; @endphp
                            <div class="rounded-lg border border-amber-200 bg-amber-50/60 px-3 py-2">
                                <div class="text-[9.5px] font-bold text-amber-700 uppercase tracking-wide">Scenario</div>
                                <div class="text-sm font-extrabold text-amber-900 leading-tight">{{ ($sc['scenario_type'] ?? '') === 'prevention' ? 'With prevention' : 'No prevention' }}</div>
                                <div class="text-[10px] text-amber-800">{{ ($sc['focus'] ?? '') === 'streets' && !empty($sc['streets']) ? count((array) $sc['streets']) . ' street(s)' : 'Whole barangay' }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="batch-body {{ $open ? '' : 'hidden' }}">
                        <div class="p-5 space-y-5">
                            {{-- Scenario / streets as chips --}}
                            @if($fromMapping && !empty($batch['scenario']['streets']))
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-[10px] font-bold text-indigo-700 uppercase tracking-wide mr-1">Streets analyzed</span>
                                    @foreach((array) $batch['scenario']['streets'] as $st)
                                        {!! $chip('fa-road', $st, 'bg-indigo-50 text-indigo-800') !!}
                                    @endforeach
                                </div>
                            @endif
                            @if($isSim && !empty($batch['scenario']))
                                @php $sc = $batch['scenario']; @endphp
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wide mr-1">Scenario</span>
                                    @foreach((array) ($sc['missing_safeguards'] ?? []) as $m) {!! $chip('fa-triangle-exclamation', $m, 'bg-red-50 text-red-800') !!} @endforeach
                                    @foreach((array) ($sc['prevention_measures'] ?? []) as $m) {!! $chip('fa-shield-halved', $m, 'bg-emerald-50 text-emerald-800') !!} @endforeach
                                    @foreach((array) ($sc['crime_types'] ?? []) as $m) {!! $chip('fa-tag', $m, 'bg-amber-50 text-amber-900') !!} @endforeach
                                    @foreach((array) ($sc['streets'] ?? []) as $st) {!! $chip('fa-road', $st, 'bg-indigo-50 text-indigo-800') !!} @endforeach
                                </div>
                            @endif

                            @if(!empty($forecast['summary']))
                                <p class="text-sm text-gray-700 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">{{ $forecast['summary'] }}</p>
                            @elseif($analysis && $analysis->summary)
                                <p class="text-sm text-gray-700 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">{{ $analysis->summary }}</p>
                            @endif

                            {{-- Charts are drawn by JS from the JSON below --}}
                            <div class="batch-charts" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;"></div>

                            <!-- Key findings -->
                            @if(!empty($findings))
                                <div>
                                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">
                                        <i class="fas fa-lightbulb mr-1"></i>Key Findings
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
                                        @foreach($findings as $finding)
                                            @php $ft = is_array($finding) ? ($finding['text'] ?? json_encode($finding)) : $finding; [$fi, $fc] = $findingIcon($ft); @endphp
                                            <div class="flex items-start gap-2.5 text-xs text-gray-800 bg-white border border-gray-200 rounded-lg p-3 leading-snug">
                                                <span class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center {{ $fc }}"><i class="fas {{ $fi }}"></i></span>
                                                <span>{{ $ft }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Recommendations -->
                            @if(!empty($recs))
                                <div>
                                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">
                                        <i class="fas fa-clipboard-check mr-1"></i>Recommendations ({{ count($recs) }})
                                    </h3>
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                        @foreach($recs as $rec)
                                            @php
                                                $r = $rec->payload ?? [];
                                                $d = $r['details'] ?? [];
                                                $ev = $d['evidence'] ?? [];
                                                $impact = $r['expected_impact'] ?? [];
                                                $pct = is_numeric($impact['estimated_change_percent'] ?? null) ? (float) $impact['estimated_change_percent'] : null;
                                                $good = ($pct !== null && $pct < 0) || strtolower((string) ($impact['direction'] ?? '')) === 'decrease';
                                                $action = $r['action'] ?? $rec->title;
                                                $where = $r['street'] ?? $r['location'] ?? null;
                                                $hasDetails = !empty($r['rationale']) || !empty($impact['explanation']) || !empty($d['steps']) || !empty($d['tips']) || !empty($d['kpi']) || !empty($d['coverage']) || !empty($d['resources']) || !empty($ev['modus']);
                                            @endphp
                                            <div class="border border-gray-200 rounded-lg p-3 bg-white hover:border-alertara-300 transition-colors">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="text-sm font-bold text-gray-900 leading-snug"><i class="fas fa-shield-halved text-violet-600 mr-1"></i>{{ $action }}</div>
                                                    @if(!empty($r['priority']))
                                                        <span class="flex-shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $prioColor($r['priority']) }} uppercase">{{ $r['priority'] }}</span>
                                                    @endif
                                                </div>
                                                <div class="flex flex-wrap gap-1 mt-2">
                                                    @if($where) {!! $chip('fa-road', $where, 'bg-orange-50 text-orange-800') !!} @endif
                                                    @if(!empty($r['time_window'])) {!! $chip('fa-clock', $r['time_window'], 'bg-violet-50 text-violet-800') !!} @endif
                                                    @if(!empty($ev['cases'])) {!! $chip('fa-hashtag', $ev['cases'] . ' case' . ($ev['cases'] == 1 ? '' : 's') . (!empty($ev['share']) ? ' · ' . $ev['share'] . '%' : '')) !!} @endif
                                                    @if(isset($ev['unresolved'])) {!! $chip('fa-folder-open', $ev['unresolved'] . ' open', $ev['unresolved'] > 0 ? 'bg-red-50 text-red-800' : 'bg-green-50 text-green-800') !!} @endif
                                                    @if(!empty($ev['busiest_day'])) {!! $chip('fa-calendar-day', $ev['busiest_day']) !!} @endif
                                                    @if(!empty($ev['latest'])) {!! $chip('fa-calendar-check', $ev['latest']) !!} @endif
                                                    @if(!empty($d['lead'])) {!! $chip('fa-user-shield', $d['lead']) !!} @endif
                                                    @if(!empty($d['timeline'])) {!! $chip('fa-hourglass-half', $d['timeline']) !!} @endif
                                                </div>
                                                @if($pct !== null || !empty($impact['direction']))
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <span class="text-[10.5px] font-bold text-gray-700 whitespace-nowrap"><i class="fas {{ $good ? 'fa-arrow-trend-down text-green-600' : 'fa-arrows-left-right text-gray-500' }} mr-1"></i>If implemented</span>
                                                        <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full" style="width: {{ min(100, abs($pct ?? 0)) }}%; background: linear-gradient(90deg, #22c55e, #15803d);"></div></div>
                                                        <span class="text-xs font-extrabold whitespace-nowrap {{ $good ? 'text-green-700' : 'text-gray-700' }}">{{ $pct ? (($pct > 0 ? '+' : '−') . abs($pct) . '%') : ucfirst($impact['direction'] ?? 'stable') }}</span>
                                                    </div>
                                                @endif
                                                @if($hasDetails)
                                                    <button type="button" class="rec-details-toggle inline-flex items-center gap-1.5 mt-2 text-[10.5px] font-bold text-violet-700 bg-violet-50 border border-violet-200 rounded-lg px-2.5 py-1 hover:bg-violet-100"><i class="fas fa-chevron-down"></i>Details, steps &amp; tips</button>
                                                    <div class="rec-details hidden mt-2 pt-2 border-t border-dashed border-gray-200 space-y-1.5">
                                                        @if(!empty($r['rationale']))<p class="text-xs text-gray-600 leading-relaxed">{{ $r['rationale'] }}</p>@endif
                                                        @if(!empty($impact['explanation']))<p class="text-[11px] text-gray-500">{{ $impact['explanation'] }}</p>@endif
                                                        @if(!empty($ev['modus']))<div class="text-[11px] text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-2"><i class="fas fa-user-ninja text-amber-600 mr-1"></i>{{ implode('; ', (array) $ev['modus']) }}</div>@endif
                                                        @if(!empty($d['coverage']))<div class="text-[11px] text-gray-700"><i class="fas fa-location-crosshairs mr-1 text-violet-500"></i>{{ $d['coverage'] }}</div>@endif
                                                        @if(!empty($d['steps']))
                                                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-2.5">
                                                                <div class="text-[9.5px] font-bold text-gray-500 uppercase tracking-wide mb-1">How to implement</div>
                                                                @foreach((array) $d['steps'] as $i => $step)
                                                                    <div class="flex gap-1.5 text-[11px] text-gray-600 leading-relaxed"><span class="font-bold text-violet-600 flex-shrink-0">{{ $i + 1 }}.</span><span>{{ $step }}</span></div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                        @if(!empty($d['resources']))<div class="text-[11px] text-gray-600"><i class="fas fa-toolbox mr-1 text-violet-500"></i><span class="font-semibold text-gray-700">Needs:</span> {{ $d['resources'] }}</div>@endif
                                                        @if(!empty($d['tips']))
                                                            <div class="rounded-lg bg-sky-50 border border-sky-200 p-2.5">
                                                                <div class="text-[9.5px] font-bold text-sky-700 uppercase tracking-wide mb-1"><i class="fas fa-people-roof mr-1"></i>Tips for residents</div>
                                                                @foreach((array) $d['tips'] as $tip)
                                                                    <div class="flex gap-1.5 text-[11px] text-sky-900 leading-relaxed"><i class="fas fa-check text-sky-500 mt-0.5 flex-shrink-0"></i><span>{{ $tip }}</span></div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                        @if(!empty($d['kpi']))<div class="text-[11px] font-semibold text-green-700"><i class="fas fa-bullseye mr-1"></i>{{ $d['kpi'] }}</div>@endif
                                                    </div>
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
                    <script type="application/json" class="batch-json">{!! json_encode($batchJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Source filter: show only Pattern Detection or Crime Mapping saves
    const filterBtns = document.querySelectorAll('.report-filter');
    function applyFilter(which) {
        filterBtns.forEach(function (b) {
            const on = b.dataset.filter === which;
            b.className = 'report-filter px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors ' +
                (on ? 'bg-alertara-700 text-white border-alertara-700' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50');
        });
        let visible = 0;
        document.querySelectorAll('.report-batch').forEach(function (el) {
            const show = which === 'all' || el.dataset.source === which;
            el.classList.toggle('hidden', !show);
            if (show) visible++;
        });
        const note = document.getElementById('filterEmptyNote');
        if (note) note.classList.toggle('hidden', visible > 0);
    }
    filterBtns.forEach(function (b) {
        b.addEventListener('click', function () { applyFilter(b.dataset.filter); });
    });
    if (filterBtns.length) applyFilter('all');
})();

(function () {
    const btn = document.getElementById('copyApiBtn');
    if (!btn) return;
    const label = document.getElementById('copyApiLabel');
    const icon = btn.querySelector('i');
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
        label.textContent = ok ? 'Copied!' : 'Press Ctrl+C';
        icon.className = ok ? 'fas fa-check mr-2 text-green-600' : 'fas fa-triangle-exclamation mr-2 text-amber-600';
        setTimeout(function () {
            label.textContent = 'Copy API endpoint';
            icon.className = 'fas fa-link mr-2 text-alertara-600';
        }, 1800);
    });
})();

// ---------- Charts per saved report (drawn from the JSON stored with the batch) ----------
// Chart.js is loaded deferred by the layout, so this waits for DOMContentLoaded
function initSavedReportCharts() {
    const HOURS = Array.from({ length: 24 }, (_, h) => (h % 12 || 12) + (h < 12 ? 'AM' : 'PM'));
    const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const RISK = { high: '#dc2626', medium: '#f59e0b', low: '#16a34a' };
    const PRIO = { high: '#dc2626', medium: '#f59e0b', low: '#9ca3af' };
    const CAT = { 'Theft': '#2563eb', 'Robbery': '#dc2626', 'Assault': '#ea580c', 'Burglary': '#7c3aed', 'Vehicle Theft': '#0891b2',
                  'Domestic Violence': '#be185d', 'Fraud': '#ca8a04', 'Sexual Offense': '#9333ea', 'Homicide': '#111827', 'Vandalism': '#f472b6' };
    const catColor = (c) => CAT[c] || ['#64748b', '#0ea5e9', '#84cc16', '#a78bfa', '#fb923c'][String(c).length % 5];
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const opts = (extra) => Object.assign({ responsive: true, maintainAspectRatio: false, animation: { duration: 250 }, plugins: { legend: { display: false } } }, extra || {});
    const axis = (o) => Object.assign({ grid: { display: false }, ticks: { font: { size: 9 }, color: '#6b7280' } }, o || {});
    const yaxis = (o) => Object.assign({ beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { precision: 0, font: { size: 9 }, color: '#6b7280' } }, o || {});
    const charts = {};   // canvas id -> Chart, so a redraw never stacks
    let seq = 0;

    function card(id, title, icon, note, wide) {
        return '<div class="rounded-lg border border-gray-200 bg-white p-3 min-w-0" style="overflow:hidden;' + (wide ? 'grid-column:1 / -1;' : '') + '">' +
            '<div class="text-[10px] font-bold text-gray-700 uppercase flex items-center gap-1.5 mb-1"><i class="fas ' + icon + ' text-violet-600"></i>' + title + '</div>' +
            '<div style="position:relative;height:' + (wide ? 190 : 160) + 'px;"><canvas id="' + id + '"></canvas></div>' +
            (note ? '<div class="text-[10px] text-gray-500 mt-1">' + note + '</div>' : '') + '</div>';
    }
    function draw(id, cfg) {
        const el = document.getElementById(id);
        if (!el || typeof Chart === 'undefined') return;
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(el, cfg);
    }

    function render(batchEl) {
        if (batchEl.dataset.drawn === '1') return;
        const wrap = batchEl.querySelector('.batch-charts');
        const json = batchEl.querySelector('.batch-json');
        if (!wrap || !json) return;
        let d;
        try { d = JSON.parse(json.textContent); } catch (e) { return; }
        const p = 'b' + (++seq) + '_';
        const streets = (d.chart && d.chart.streets) || [];
        const recs = d.recs || [];

        // ---- aggregate the per-street chart data ----
        let total = 0, resolved = 0, unresolved = 0, hasHour = false;
        const hourly = new Array(24).fill(0), weekday = new Array(7).fill(0), monthly = new Array(12).fill(0);
        let monthLabels = null;
        const cats = {};
        streets.forEach(s => {
            total += s.total || 0; resolved += s.resolved || 0; unresolved += s.unresolved || 0;
            (s.hourly || []).forEach((v, i) => { if (i < 24) { hourly[i] += v || 0; if (v) hasHour = true; } });
            (s.weekday || []).forEach((v, i) => { if (i < 7) weekday[i] += v || 0; });
            if (s.monthly && s.monthly.values && s.monthly.values.length) { if (!monthLabels) monthLabels = s.monthly.labels; s.monthly.values.forEach((v, i) => { if (i < 12) monthly[i] += v || 0; }); }
            (s.categories || []).forEach(c => { cats[c.category] = (cats[c.category] || 0) + (c.count || 0); });
        });
        const catList = Object.keys(cats).map(k => ({ label: k, count: cats[k] })).sort((a, b) => b.count - a.count);
        const ranked = streets.slice().sort((a, b) => (b.total || 0) - (a.total || 0)).slice(0, 10);
        const impact = recs.map(r => ({ label: r.action, street: r.location, pct: Math.abs(Number(r.pct) || 0), priority: r.priority || 'low' }))
            .filter(r => r.pct > 0).sort((a, b) => b.pct - a.pct).slice(0, 8);
        const prioCounts = ['high', 'medium', 'low'].map(k => recs.filter(r => (r.priority || 'low') === k).length);
        const peakDay = Math.max(...weekday) > 0 ? weekday.indexOf(Math.max(...weekday)) : -1;
        const peakHour = hasHour ? hourly.indexOf(Math.max(...hourly)) : -1;

        let html = '';
        if (impact.length) html += card(p + 'impact', 'Expected reduction per action', 'fa-arrow-trend-down', 'Estimated % fewer crimes if implemented · red = high priority', true);
        if (ranked.length > 1) html += card(p + 'streets', 'Crimes per street', 'fa-road', 'Bar colour = risk level', ranked.length > 4);
        if (catList.length) html += card(p + 'cats', 'Crime mix', 'fa-chart-pie', 'Most common: ' + esc(catList[0].label) + ' (' + catList[0].count + ')');
        if (streets.length) html += card(p + 'status', ranked.length > 1 ? 'Case status per street' : 'Case status', 'fa-folder-open', resolved + ' resolved · ' + unresolved + ' open', ranked.length > 1);
        if (hasHour) html += card(p + 'hours', 'Time of day', 'fa-clock', 'Peak ' + HOURS[peakHour] + ' · dark = night');
        if (peakDay >= 0) html += card(p + 'days', 'Day of week', 'fa-calendar-week', 'Busiest: ' + DAYS[peakDay]);
        if (monthLabels) html += card(p + 'months', '12-month trend', 'fa-chart-line', 'Crimes per month', true);
        if (!impact.length && recs.length) html += card(p + 'prio', 'Action priorities', 'fa-flag', '');
        wrap.innerHTML = html;
        batchEl.dataset.drawn = '1';
        if (!html) { wrap.style.display = 'none'; return; }

        if (impact.length) draw(p + 'impact', {
            type: 'bar',
            data: { labels: impact.map(r => r.label.length > 48 ? r.label.slice(0, 47) + '…' : r.label),
                    datasets: [{ data: impact.map(r => r.pct), backgroundColor: impact.map(r => PRIO[r.priority] || PRIO.low), borderRadius: 4, maxBarThickness: 14 }] },
            options: opts({ indexAxis: 'y', scales: { x: yaxis({ ticks: { precision: 0, font: { size: 9 }, callback: v => '-' + v + '%' } }), y: axis({ ticks: { font: { size: 9 }, color: '#374151', autoSkip: false } }) },
                plugins: { legend: { display: false }, tooltip: { callbacks: { title: its => { const r = impact[its[0].dataIndex]; return (r.street ? r.street + ' — ' : '') + r.label; }, label: it => ' ~' + it.parsed.x + '% fewer crimes' } } } })
        });
        if (ranked.length > 1) draw(p + 'streets', {
            type: 'bar',
            data: { labels: ranked.map(s => s.street), datasets: [{ data: ranked.map(s => s.total || 0), backgroundColor: ranked.map(s => RISK[s.risk_level] || '#9ca3af'), borderRadius: 4, maxBarThickness: 16 }] },
            options: opts({ indexAxis: 'y', scales: { x: yaxis(), y: axis({ ticks: { font: { size: 9.5 }, color: '#374151', autoSkip: false } }) } })
        });
        if (catList.length) draw(p + 'cats', {
            type: 'doughnut',
            data: { labels: catList.map(c => c.label), datasets: [{ data: catList.map(c => c.count), backgroundColor: catList.map(c => catColor(c.label)), borderColor: '#fff', borderWidth: 2 }] },
            options: opts({ cutout: '58%', plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 9, font: { size: 9.5 }, padding: 6 } },
                tooltip: { callbacks: { label: it => ' ' + it.label + ': ' + it.parsed + ' (' + Math.round(it.parsed / Math.max(1, total) * 100) + '%)' } } } })
        });
        if (streets.length) draw(p + 'status', ranked.length > 1 ? {
            type: 'bar',
            data: { labels: ranked.map(s => s.street), datasets: [
                { label: 'Resolved', data: ranked.map(s => s.resolved || 0), backgroundColor: '#22c55e', borderRadius: 3, maxBarThickness: 14 },
                { label: 'Unresolved', data: ranked.map(s => s.unresolved || 0), backgroundColor: '#ef4444', borderRadius: 3, maxBarThickness: 14 }] },
            options: opts({ indexAxis: 'y', plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 9, font: { size: 9.5 } } } },
                scales: { x: yaxis({ stacked: true }), y: axis({ stacked: true, ticks: { font: { size: 9 }, color: '#374151', autoSkip: false } }) } })
        } : {
            type: 'doughnut',
            data: { labels: ['Resolved', 'Unresolved'], datasets: [{ data: [resolved, unresolved], backgroundColor: ['#22c55e', '#ef4444'], borderColor: '#fff', borderWidth: 2 }] },
            options: opts({ cutout: '60%', plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 9, font: { size: 9.5 } } } } })
        });
        if (hasHour) draw(p + 'hours', {
            type: 'bar',
            data: { labels: HOURS, datasets: [{ data: hourly, backgroundColor: hourly.map((_, h) => (h >= 18 || h < 6) ? '#4338ca' : '#93c5fd'), borderRadius: 2 }] },
            options: opts({ scales: { x: axis({ ticks: { font: { size: 8 }, color: '#6b7280', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } }), y: yaxis() } })
        });
        if (peakDay >= 0) draw(p + 'days', {
            type: 'bar',
            data: { labels: DAYS, datasets: [{ data: weekday, backgroundColor: weekday.map((_, i) => i === peakDay ? '#7c3aed' : '#c4b5fd'), borderRadius: 4, maxBarThickness: 26 }] },
            options: opts({ scales: { x: axis(), y: yaxis() } })
        });
        if (monthLabels) draw(p + 'months', {
            type: 'line',
            data: { labels: monthLabels, datasets: [{ data: monthly, borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,0.12)', fill: true, tension: 0.35, pointRadius: 3, pointBackgroundColor: '#7c3aed', pointBorderColor: '#fff', pointBorderWidth: 1.5 }] },
            options: opts({ scales: { x: axis({ ticks: { font: { size: 9 }, color: '#6b7280', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } }), y: yaxis() } })
        });
        if (!impact.length && recs.length) draw(p + 'prio', {
            type: 'doughnut',
            data: { labels: ['High', 'Medium', 'Low'], datasets: [{ data: prioCounts, backgroundColor: ['#dc2626', '#f59e0b', '#9ca3af'], borderColor: '#fff', borderWidth: 2 }] },
            options: opts({ cutout: '60%', plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 9, font: { size: 9.5 } } } } })
        });
    }

    // Expand / collapse a report; charts are drawn the first time it opens
    document.querySelectorAll('.report-batch').forEach(function (batchEl) {
        const toggle = batchEl.querySelector('.batch-toggle');
        const body = batchEl.querySelector('.batch-body');
        if (toggle && body) {
            toggle.addEventListener('click', function () {
                const open = body.classList.toggle('hidden') === false;
                toggle.setAttribute('aria-expanded', String(open));
                toggle.innerHTML = '<i class="fas ' + (open ? 'fa-chevron-up' : 'fa-chevron-down') + ' mr-1"></i><span>' + (open ? 'Hide' : 'Show') + ' report</span>';
                if (open) render(batchEl);
            });
        }
        if (body && !body.classList.contains('hidden')) render(batchEl);
    });

    // Details toggle on recommendation cards
    document.addEventListener('click', function (e) {
        const t = e.target.closest('.rec-details-toggle');
        if (!t) return;
        const box = t.nextElementSibling;
        const open = box && box.classList.toggle('hidden') === false;
        t.querySelector('i').className = 'fas ' + (open ? 'fa-chevron-up' : 'fa-chevron-down');
    });

    // ---------- Download a saved report as PDF (print-ready window) ----------
    document.querySelectorAll('.batch-pdf').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const card = btn.closest('.report-batch');
            if (!card) return;
            render(card);   // charts must exist before they can be printed

            const clone = card.cloneNode(true);
            clone.querySelectorAll('.batch-pdf, .batch-toggle, .batch-json, .rec-details-toggle').forEach(function (b) { b.remove(); });
            clone.querySelectorAll('.batch-body, .rec-details').forEach(function (el) { el.classList.remove('hidden'); });
            // Canvases do not clone their pixels: swap each for a snapshot image
            const live = card.querySelectorAll('canvas');
            clone.querySelectorAll('canvas').forEach(function (c, i) {
                const img = document.createElement('img');
                try { img.src = live[i].toDataURL('image/png'); } catch (e) { return; }
                img.style.cssText = 'width:100%;height:100%;object-fit:contain;';
                c.replaceWith(img);
            });

            const w = window.open('', '_blank');
            if (!w) { alert('Pop-up blocked — allow pop-ups for this site to download the PDF.'); return; }

            const styles = Array.prototype.map.call(
                document.querySelectorAll('link[rel="stylesheet"], style'),
                function (el) { return el.outerHTML; }
            ).join('');

            w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Saved AI Report</title>' +
                '<script src="https://cdn.tailwindcss.com"><\/script>' + styles +
                '<style>body{background:#fff !important;padding:24px;} @media print{body{padding:0;} .report-batch{break-inside:avoid;}}</style>' +
                '</head><body>' +
                '<div style="border-bottom:3px solid #274d4c;padding-bottom:10px;margin-bottom:16px;">' +
                    '<h1 style="margin:0;font-size:20px;color:#274d4c;font-weight:800;">Saved AI Report</h1>' +
                    '<p style="margin:4px 0 0;font-size:11px;color:#555;">Barangay San Agustin, Quezon City · Generated ' +
                        new Date().toLocaleString() + '</p>' +
                '</div>' +
                clone.outerHTML +
                '<div style="margin-top:16px;border-top:1px solid #ddd;padding-top:8px;font-size:10px;color:#777;">' +
                    'Generated by the Crime Data Analytics system. For official use.</div>' +
                '</body></html>');
            w.document.close();
            setTimeout(function () { w.print(); }, 1200);
        });
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initSavedReportCharts);
else initSavedReportCharts();
</script>
@endpush
