@extends('layouts.app')

@section('title', 'Pattern Detection')

@section('content')
<div class="p-4 lg:p-6 pt-0 lg:pt-0 pb-12" id="patternApp">

    <!-- Page Header -->
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Pattern Detection</h1>
        <p class="text-gray-600 mt-1 text-sm lg:text-base">
            Analyze recorded crimes (real data), or run a what-if <span class="font-semibold text-amber-700">simulation</span> to model how crime could rise or be prevented.
        </p>

        <!-- Mode tabs: real data vs simulation are fully separate views -->
        <div class="mt-4 flex gap-2 border-b border-gray-200 -mb-6 pb-0">
            <button id="tabRealBtn" class="px-4 py-2.5 text-sm font-bold rounded-t-lg border border-b-0 flex items-center gap-2">
                <i class="fas fa-database"></i> Real Data
            </button>
            <button id="tabSimBtn" class="px-4 py-2.5 text-sm font-bold rounded-t-lg border border-b-0 flex items-center gap-2">
                <i class="fas fa-flask"></i> Simulation (What-If)
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-200 text-amber-900">SIM</span>
            </button>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- TAB 1 — REAL DATA ANALYSIS                                     -->
    <!-- ============================================================= -->
    <div id="tabRealPanel">
    <div class="mb-6 bg-white rounded-xl border-2 border-blue-200 p-4">
        <div class="mb-4 pb-4 border-b border-gray-200 flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-blue-100 text-blue-700"><i class="fas fa-database text-sm"></i></span>
            <h3 class="text-sm font-bold text-gray-900">Real Data Analysis</h3>
            <span class="text-xs text-gray-400">— recorded crimes only</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-alertara-800 mb-2">Analysis Period</label>
                <select id="daysSelect" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-alertara-500 bg-white">
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="180">Last 6 months</option>
                    <option value="365">Last 12 months</option>
                    <option value="730" selected>Last 24 months</option>
                </select>
            </div>
            <div class="flex items-end">
                <button id="runRealBtn" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2 font-semibold">
                    <i class="fas fa-database"></i> Analyze Real Data
                </button>
            </div>
        </div>
    </div>

    <!-- AI Analysis (Gemini) — REAL DATA -->
    <div class="mb-6 bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-lg font-bold text-gray-900">
                <i class="fas fa-shield-halved mr-2 text-violet-600"></i>Crime Analysis &amp; Prevention Suggestions &mdash; Real Data
                <span class="ml-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 align-middle">REAL</span>
            </h2>
            <div class="flex items-center gap-2">
                <div id="aiLangToggle" class="flex rounded-lg border border-violet-300 overflow-hidden text-[11px] font-bold" title="Language of the suggestions">
                    <button type="button" data-lang="en" class="px-2.5 py-1">English</button>
                    <button type="button" data-lang="tl" class="px-2.5 py-1">Taglish</button>
                </div>
                <span id="aiMetaBadge" class="hidden text-[10px] font-bold px-2 py-1 rounded-full bg-violet-100 text-violet-800"></span>
                <button id="aiSaveBtn" class="hidden px-3 py-1.5 bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition-colors text-xs font-semibold">
                    <i class="fas fa-floppy-disk mr-1"></i>Save
                </button>
                <button id="aiDownloadBtn" class="hidden px-3 py-1.5 bg-white text-violet-700 border border-violet-300 rounded-lg hover:bg-violet-50 transition-colors text-xs font-semibold">
                    <i class="fas fa-download mr-1"></i>Download
                </button>
            </div>
        </div>
        <p class="text-xs text-gray-500 mb-4">The system reviews recorded San Agustin crimes street by street, forecasts whether crime will rise or fall, and suggests what to do per street — with a detailed suggestion for every crime type committed there. Instant, no AI quota used.</p>

        <!-- placeholder -->
        <div id="aiPlaceholder" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">
            <i class="fas fa-wand-magic-sparkles text-violet-400 text-xl mb-2 block"></i>
            Click <span class="font-semibold text-blue-700">Analyze Real Data</span> to generate the forecast and per-street prevention suggestions from recorded crimes.
        </div>

        <!-- loading -->
        <div id="aiLoading" class="hidden rounded-lg bg-violet-50 border border-violet-200 p-6 text-center">
            <i class="fas fa-spinner fa-spin text-2xl text-violet-600 mb-2"></i>
            <div class="text-sm font-semibold text-violet-900">Analyzing San Agustin crimes street by street&hellip;</div>
        </div>

        <!-- error -->
        <div id="aiError" class="hidden rounded-lg bg-red-50 border border-red-200 p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-triangle-exclamation text-red-600 mt-0.5"></i>
                <div>
                    <div class="font-bold text-red-900 text-sm">AI analysis failed</div>
                    <p id="aiErrorMessage" class="text-red-800 text-xs mt-1"></p>
                </div>
            </div>
        </div>

        <!-- results -->
        <div id="aiResults" class="hidden space-y-5">
            <!-- Forecast -->
            <div id="aiForecastCard" class="rounded-xl border-2 p-5">
                <div class="flex flex-wrap items-center gap-3 mb-2">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Crime Forecast</span>
                    <span id="aiForecastBadge" class="px-3 py-1 rounded-full text-xs font-bold"></span>
                    <span id="aiForecastPercent" class="text-sm font-bold"></span>
                    <span id="aiConfidence" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-200 text-gray-700"></span>
                </div>
                <p id="aiForecastSummary" class="text-sm text-gray-800"></p>
            </div>

            <!-- Key findings -->
            <div>
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2"><i class="fas fa-magnifying-glass-chart mr-1"></i>Key Findings</h3>
                <ul id="aiFindings" class="space-y-2"></ul>
            </div>

            <!-- Recommendations -->
            <div>
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2"><i class="fas fa-clipboard-check mr-1"></i>Prevention Suggestions &mdash; Per Street, Per Crime Type</h3>
                <div id="aiRecommendations" class="grid grid-cols-1 lg:grid-cols-2 gap-3"></div>
            </div>

        </div>

        <!-- Saved AI reports -->
        <div id="savedReportsWrap" class="hidden mt-6 pt-4 border-t border-gray-200">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2"><i class="fas fa-box-archive mr-1"></i>Saved AI Reports</h3>
            <div id="savedReportsList" class="space-y-2 max-h-72 overflow-y-auto"></div>
        </div>
    </div>

    <!-- Loading -->
    <div id="loadingState" class="hidden bg-white rounded-xl border border-gray-200 p-12 text-center">
        <i class="fas fa-spinner fa-spin text-3xl text-alertara-700 mb-3"></i>
        <div class="text-sm font-semibold text-gray-900">Analyzing crime patterns&hellip;</div>
    </div>

    <!-- Error -->
    <div id="errorState" class="hidden bg-red-50 border border-red-200 rounded-xl p-6">
        <div class="flex items-start gap-3">
            <i class="fas fa-triangle-exclamation text-red-600 mt-0.5"></i>
            <div>
                <div class="font-bold text-red-900 text-sm">Analysis failed</div>
                <p id="errorMessage" class="text-red-800 text-xs mt-1"></p>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div id="results" class="hidden space-y-6">

        <!-- Data provenance -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="bg-gradient-to-br from-alertara-700 to-alertara-600 text-white p-4 rounded-lg">
                <div class="text-xs opacity-90 mb-1">Real Records</div>
                <div id="statReal" class="text-2xl font-bold">0</div>
            </div>
            <div class="bg-gradient-to-br from-blue-600 to-blue-500 text-white p-4 rounded-lg">
                <div class="text-xs opacity-90 mb-1">Analyzed Total</div>
                <div id="statTotal" class="text-2xl font-bold">0</div>
            </div>
            <div class="bg-gradient-to-br from-gray-700 to-gray-600 text-white p-4 rounded-lg">
                <div class="text-xs opacity-90 mb-1">Period</div>
                <div id="statPeriod" class="text-lg font-bold">&mdash;</div>
            </div>
        </div>

        <!-- Low-confidence warning -->
        <div id="confidenceWarning" class="hidden rounded-xl border border-orange-300 bg-orange-50 p-4">
            <div class="flex items-start gap-3">
                <i class="fas fa-circle-info text-orange-600 mt-0.5"></i>
                <p id="confidenceNote" class="text-orange-900 text-xs"></p>
            </div>
        </div>

        <!-- Summary insights -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4"><i class="fas fa-lightbulb mr-2 text-alertara-600"></i>Summary Insights</h2>
            <div id="insightsList" class="space-y-3"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Trends -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 class="text-lg font-bold text-gray-900"><i class="fas fa-chart-line mr-2 text-alertara-600"></i>Crime Trends</h2>
                    <span id="trendBadge" class="px-3 py-1 rounded-full text-xs font-bold">&mdash;</span>
                </div>
                <p id="trendExplanation" class="text-xs text-gray-600 mb-4"></p>

                <div id="trendStats" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4"></div>

                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <button class="trend-tab px-3 py-1 text-xs font-semibold rounded-lg border" data-grain="daily">Daily</button>
                    <button class="trend-tab px-3 py-1 text-xs font-semibold rounded-lg border" data-grain="weekly">Weekly</button>
                    <button class="trend-tab px-3 py-1 text-xs font-semibold rounded-lg border" data-grain="monthly">Monthly</button>
                    <div id="trendLegend" class="sm:ml-auto flex flex-wrap items-center"></div>
                </div>
                <p id="trendDetailHint" class="text-[11px] text-gray-500 mb-2"></p>
                <div style="height: 290px;"><canvas id="trendChart"></canvas></div>
            </div>

            <!-- Crime type distribution -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4"><i class="fas fa-chart-pie mr-2 text-alertara-600"></i>Crime Type Distribution</h2>
                <div id="typeDistribution" class="space-y-2 max-h-96 overflow-y-auto"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Time patterns -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900"><i class="fas fa-clock mr-2 text-alertara-600"></i>Time-Based Patterns</h2>
                    <div id="timeLegend" class="flex items-center"></div>
                </div>
                <div id="timeSummary" class="grid grid-cols-2 gap-3 mb-5"></div>
                <div class="text-xs font-bold text-gray-500 uppercase mb-2">By hour of day</div>
                <div style="height: 190px;" class="mb-5"><canvas id="hourChart"></canvas></div>
                <div class="text-xs font-bold text-gray-500 uppercase mb-2">By day of week</div>
                <div style="height: 190px;"><canvas id="dowChart"></canvas></div>
            </div>

            <!-- Hotspots -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4"><i class="fas fa-location-crosshairs mr-2 text-alertara-600"></i>Ranked Hotspots</h2>
                <div id="hotspotList" class="space-y-2 max-h-[28rem] overflow-y-auto"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Repeat clusters -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2"><i class="fas fa-layer-group mr-2 text-alertara-600"></i>Repeat / Cluster Crimes</h2>
                <p id="clusterRule" class="text-xs text-gray-500 mb-4"></p>
                <div id="clusterList" class="space-y-3 max-h-96 overflow-y-auto"></div>
            </div>

            <!-- Anomalies -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2"><i class="fas fa-wave-square mr-2 text-alertara-600"></i>Anomaly Detection</h2>
                <p id="anomalyRule" class="text-xs text-gray-500 mb-4"></p>
                <div id="anomalyList" class="space-y-2 max-h-96 overflow-y-auto"></div>
            </div>
        </div>
    </div>
    </div><!-- /tabRealPanel -->

    <!-- ============================================================= -->
    <!-- TAB 2 — SIMULATION (WHAT-IF)                                   -->
    <!-- ============================================================= -->
    <div id="tabSimPanel" class="hidden">
    <div class="mb-6 bg-amber-50/40 rounded-xl border-2 border-amber-300 p-4 lg:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-4 pb-4 border-b border-amber-200">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-amber-200 text-amber-800"><i class="fas fa-flask text-sm"></i></span>
                <h3 class="text-sm font-bold text-gray-900">Simulation (What-If)</h3>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-200 text-amber-900">SIMULATION</span>
            </div>
            <p class="text-xs text-amber-800"><i class="fas fa-circle-info mr-1"></i>Projected scenario &mdash; not operational data.</p>
        </div>

        <!-- Configuration -->
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Baseline period</label>
                    <select id="simDaysSelect" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="180">Last 6 months</option>
                        <option value="365">Last 12 months</option>
                        <option value="730" selected>Last 24 months</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Scenario type</label>
                    <select id="simScenarioType" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                        <option value="risk" selected>Risk &mdash; no prevention</option>
                        <option value="prevention">With prevention measures</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Surge level</label>
                    <select id="surgeLevel" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                        <option value="0.25">Minor (+25%)</option>
                        <option value="0.5" selected>Moderate (+50%)</option>
                        <option value="1">Major (+100%)</option>
                        <option value="2">Severe (+200%)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Focus area</label>
                    <select id="focusMode" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                        <option value="barangay" selected>Whole barangay</option>
                        <option value="streets">Selected streets</option>
                    </select>
                </div>
            </div>

            <!-- Risk mode: missing safeguards -->
            <div id="simMissingWrap" class="rounded-lg border border-amber-200 bg-white p-3">
                <label class="block text-xs font-bold text-amber-800 mb-2">
                    <i class="fas fa-triangle-exclamation mr-1"></i>Missing safeguards <span class="text-gray-400 font-normal">(what's absent that raises crime)</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach(['No street lighting','No CCTV','No police patrol','No community watch','No checkpoints'] as $sg)
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" class="sim-missing rounded border-gray-300 text-amber-600 focus:ring-amber-500" value="{{ $sg }}"
                                   @if($sg === 'No street lighting' || $sg === 'No CCTV') checked @endif>
                            <span>{{ $sg }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Prevention mode: measures deployed -->
            <div id="simPreventionWrap" class="hidden rounded-lg border border-emerald-200 bg-white p-3">
                <label class="block text-xs font-bold text-emerald-800 mb-2">
                    <i class="fas fa-shield-halved mr-1"></i>Prevention measures deployed <span class="text-gray-400 font-normal">(each reduces crime)</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1"><i class="fas fa-shield mr-1 text-emerald-600"></i>Police patrol</label>
                        <select id="prevPatrol" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                            <option value="0" selected>None</option>
                            <option value="1">Standard (~10%)</option>
                            <option value="2">Intensive (~15%)</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-emerald-50">
                        <input type="checkbox" id="prevCctv" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span><i class="fas fa-video mr-1 text-emerald-600"></i>CCTV <span class="text-gray-400 text-xs">(~18%)</span></span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-emerald-50">
                        <input type="checkbox" id="prevLighting" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span><i class="fas fa-lightbulb mr-1 text-emerald-600"></i>Street lighting <span class="text-gray-400 text-xs">(~20%)</span></span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-emerald-50">
                        <input type="checkbox" id="prevCommunity" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span><i class="fas fa-people-group mr-1 text-emerald-600"></i>Community watch <span class="text-gray-400 text-xs">(~9%)</span></span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-emerald-50">
                        <input type="checkbox" id="prevCheckpoints" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span><i class="fas fa-car-burst mr-1 text-emerald-600"></i>Checkpoints <span class="text-gray-400 text-xs">(~13%)</span></span>
                    </label>
                </div>
            </div>

            <!-- Crime types (multi-select) -->
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <label class="block text-xs font-bold text-gray-700 mb-2">
                    <i class="fas fa-tags mr-1 text-alertara-600"></i>Crime types <span class="text-gray-400 font-normal">(leave all unchecked = all types)</span>
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                    @foreach($crimeCategories as $category)
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" class="sim-crime-type rounded border-gray-300 text-alertara-600 focus:ring-alertara-500" value="{{ $category->category_name }}">
                            <span class="truncate" title="{{ $category->category_name }}">{{ $category->category_name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Street picker (only when Focus area = Selected streets) -->
            <div id="streetPickerWrap" class="hidden rounded-lg border border-amber-200 bg-white p-3">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-bold text-gray-700">
                        <i class="fas fa-road mr-1 text-amber-700"></i>Target streets
                        <span id="streetCount" class="ml-1 text-amber-700 font-semibold"></span>
                    </label>
                    <div class="flex items-center gap-2 text-xs">
                        <button type="button" id="streetSelectAll" class="text-alertara-700 hover:underline font-semibold">Select all</button>
                        <span class="text-gray-300">|</span>
                        <button type="button" id="streetClear" class="text-gray-500 hover:underline font-semibold">Clear</button>
                    </div>
                </div>
                <input type="text" id="streetSearch" placeholder="Search street…"
                       class="w-full px-3 py-1.5 mb-2 border border-gray-200 rounded-lg text-sm bg-white">
                <div id="streetList" class="max-h-44 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-1 pr-1">
                    <div class="text-xs text-gray-400 py-2">Loading streets…</div>
                </div>
                <p id="streetHint" class="hidden text-[11px] text-amber-700 mt-2"><i class="fas fa-circle-info mr-1"></i>No streets selected — the surge will fall back to the whole barangay.</p>
            </div>

            <div>
                <button id="runSimBtn" class="px-5 py-2.5 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors flex items-center justify-center gap-2 font-semibold">
                    <i class="fas fa-flask"></i> Run Simulation
                </button>
            </div>
        </div>

        <!-- Numeric surge / prevention summary -->
        <div id="simSummary" class="hidden mt-5 pt-5 border-t border-amber-200">
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white rounded-lg border border-gray-200 p-3 text-center">
                    <div class="text-[10px] font-bold text-gray-400 uppercase">Real</div>
                    <div id="simStatReal" class="text-xl font-bold text-gray-800">0</div>
                </div>
                <div class="bg-white rounded-lg border border-amber-200 p-3 text-center">
                    <div class="text-[10px] font-bold text-amber-500 uppercase">Simulated</div>
                    <div id="simStatSim" class="text-xl font-bold text-amber-700">0</div>
                </div>
                <div class="bg-white rounded-lg border border-emerald-200 p-3 text-center">
                    <div class="text-[10px] font-bold text-emerald-500 uppercase">Prevented</div>
                    <div id="simStatPrevented" class="text-xl font-bold text-emerald-700">0</div>
                </div>
            </div>
            <div id="preventionResult" class="hidden mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                <i class="fas fa-arrow-trend-down mr-1"></i><span id="preventionResultText"></span>
            </div>
        </div>

        <!-- AI simulation analysis -->
        <div class="mt-5 pt-5 border-t border-amber-200">
            <div class="flex items-center justify-between mb-1">
                <h4 class="text-base font-bold text-gray-900"><i class="fas fa-robot mr-2 text-amber-600"></i>AI Simulation Analysis</h4>
                <div class="flex items-center gap-2">
                    <span id="simMetaBadge" class="hidden text-[10px] font-bold px-2 py-1 rounded-full bg-amber-100 text-amber-800"></span>
                    <button id="simSaveBtn" class="hidden px-3 py-1.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors text-xs font-semibold">
                        <i class="fas fa-floppy-disk mr-1"></i>Save
                    </button>
                    <button id="simDownloadBtn" class="hidden px-3 py-1.5 bg-white text-amber-700 border border-amber-300 rounded-lg hover:bg-amber-50 transition-colors text-xs font-semibold">
                        <i class="fas fa-download mr-1"></i>Download
                    </button>
                </div>
            </div>
            <p id="simIntro" class="text-xs text-gray-500 mb-4">Run the simulation to see how high crime could go under this scenario and what to do about it.</p>

            <div id="simPlaceholder" class="rounded-lg border border-dashed border-amber-300 bg-white p-6 text-center text-sm text-gray-500">
                <i class="fas fa-wand-magic-sparkles text-amber-400 text-xl mb-2 block"></i>
                Click <span class="font-semibold text-amber-700">Run Simulation</span> to generate the what-if AI forecast.
            </div>

            <div id="simLoading" class="hidden rounded-lg bg-amber-50 border border-amber-200 p-6 text-center">
                <i class="fas fa-spinner fa-spin text-2xl text-amber-600 mb-2"></i>
                <div class="text-sm font-semibold text-amber-900">Gemini is analyzing the scenario&hellip;</div>
            </div>

            <div id="simError" class="hidden rounded-lg bg-red-50 border border-red-200 p-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-triangle-exclamation text-red-600 mt-0.5"></i>
                    <div>
                        <div class="font-bold text-red-900 text-sm">Simulation analysis failed</div>
                        <p id="simErrorMessage" class="text-red-800 text-xs mt-1"></p>
                    </div>
                </div>
            </div>

            <div id="simResults" class="hidden space-y-5">
                <div id="simForecastCard" class="rounded-xl border-2 p-5">
                    <div class="flex flex-wrap items-center gap-3 mb-2">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Scenario Forecast</span>
                        <span id="simForecastBadge" class="px-3 py-1 rounded-full text-xs font-bold"></span>
                        <span id="simForecastPercent" class="text-sm font-bold"></span>
                        <span id="simConfidence" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-200 text-gray-700"></span>
                    </div>
                    <p id="simForecastSummary" class="text-sm text-gray-800"></p>
                </div>

                <div>
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2"><i class="fas fa-magnifying-glass-chart mr-1"></i>Key Findings</h3>
                    <ul id="simFindings" class="space-y-2"></ul>
                </div>

                <div>
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2"><i class="fas fa-clipboard-check mr-1"></i>Recommended Interventions &amp; Projected Effect</h3>
                    <div id="simRecommendations" class="grid grid-cols-1 lg:grid-cols-2 gap-3"></div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /tabSimPanel -->
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const ANALYZE_URL = @json(route('pattern-detection.analyze'));
    const AI_ANALYZE_URL = @json(route('pattern-detection.ai-analyze'));
    const AI_SIMULATE_URL = @json(route('pattern-detection.ai-simulate'));
    const AI_SAVE_URL = @json(route('pattern-detection.ai-save'));
    const AI_REPORTS_URL = @json(route('pattern-detection.ai-reports'));
    const STREET_DETAIL_URL = @json(route('pattern-detection.street-detail'));
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    let latest = null;
    let trendGrain = 'daily';
    let trendPeriodKey = null;

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const SIM_TAG = '<span class="inline-block px-1.5 py-0.5 bg-amber-200 text-amber-900 rounded text-[10px] font-bold ml-2">SIMULATED</span>';

    // ---------- simulation scenario helpers ----------
    // Show the right config for the chosen scenario type (risk vs prevention)
    function syncScenarioType() {
        const prevention = $('simScenarioType').value === 'prevention';
        $('simMissingWrap').classList.toggle('hidden', prevention);
        $('simPreventionWrap').classList.toggle('hidden', !prevention);
    }

    // The whole simulation configuration, read from the form
    function simScenario() {
        const type = $('simScenarioType').value === 'prevention' ? 'prevention' : 'risk';
        const focus = $('focusMode').value === 'streets' ? 'streets' : 'barangay';
        const crimeTypes = Array.from(document.querySelectorAll('.sim-crime-type:checked')).map(c => c.value);
        const missing = Array.from(document.querySelectorAll('.sim-missing:checked')).map(c => c.value);
        const streets = focus === 'streets' ? selectedStreets() : [];

        const measures = [];
        if (type === 'prevention') {
            if (Number($('prevPatrol').value) > 0) measures.push('Police patrol');
            if ($('prevCctv').checked)        measures.push('CCTV');
            if ($('prevLighting').checked)    measures.push('Street lighting');
            if ($('prevCommunity').checked)   measures.push('Community watch');
            if ($('prevCheckpoints').checked) measures.push('Checkpoints');
        }

        return { type, focus, crimeTypes, missing, streets, measures };
    }

    // ---------- street picker (San Agustin) ----------
    const STREETS_URL = @json(asset('data/san_agustin_streets.geojson'));
    let streetNames = [];                    // unique, sorted
    const selectedStreetSet = new Set();     // selection survives search filtering

    function selectedStreets() { return Array.from(selectedStreetSet); }

    function updateStreetCount() {
        const n = selectedStreetSet.size;
        $('streetCount').textContent = n ? '(' + n + ' selected)' : '';
        $('streetHint').classList.toggle('hidden', !($('focusMode').value === 'streets' && n === 0));
    }

    function renderStreetList(filter) {
        const q = String(filter || '').toLowerCase().trim();
        const list = streetNames.filter(n => !q || n.toLowerCase().includes(q));
        if (!list.length) {
            $('streetList').innerHTML = '<div class="text-xs text-gray-400 py-2 col-span-full">No matching streets.</div>';
            return;
        }
        $('streetList').innerHTML = list.map(n =>
            '<label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer py-0.5">' +
                '<input type="checkbox" value="' + esc(n) + '"' + (selectedStreetSet.has(n) ? ' checked' : '') +
                    ' class="street-cb rounded border-gray-300 text-amber-600 focus:ring-amber-500">' +
                '<span class="truncate" title="' + esc(n) + '">' + esc(n) + '</span>' +
            '</label>').join('');
    }

    async function loadStreets() {
        try {
            const res = await fetch(STREETS_URL, { headers: { 'Accept': 'application/json' } });
            const geo = await res.json();
            const names = new Set();
            (geo.features || []).forEach(f => {
                const nm = f && f.properties && f.properties.name;
                if (nm) names.add(String(nm).trim());
            });
            streetNames = Array.from(names).sort((a, b) => a.localeCompare(b));
            renderStreetList('');
            updateStreetCount();
        } catch (e) {
            console.error('Loading streets failed:', e);
            $('streetList').innerHTML = '<div class="text-xs text-red-500 py-2 col-span-full">Could not load street list.</div>';
        }
    }

    // ---------- REAL DATA: statistical pattern detection ----------
    async function runReal() {
        $('loadingState').classList.remove('hidden');
        $('results').classList.add('hidden');
        $('errorState').classList.add('hidden');

        const params = new URLSearchParams({ days: $('daysSelect').value });

        try {
            const res = await fetch(ANALYZE_URL + '?' + params, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (data.error) throw new Error(data.message || data.error);

            latest = data;
            render(data);

            $('loadingState').classList.add('hidden');
            $('results').classList.remove('hidden');
        } catch (e) {
            console.error('Pattern detection failed:', e);
            $('loadingState').classList.add('hidden');
            $('errorMessage').textContent = e.message;
            $('errorState').classList.remove('hidden');
        }
    }

    // ---------- SIMULATION: statistical surge / prevention summary ----------
    async function runSimStats() {
        const sc = simScenario();
        const params = new URLSearchParams({ days: $('simDaysSelect').value });
        params.set('simulation', '1');
        params.set('volume_multiplier', $('surgeLevel').value);

        sc.crimeTypes.forEach(function (t) { params.append('crime_types[]', t); });
        if (sc.focus === 'streets') {
            sc.streets.forEach(function (name) { params.append('focus_streets[]', name); });
        }

        // Prevention only bites in the "with prevention" scenario
        if (sc.type === 'prevention') {
            params.set('prev_patrol', $('prevPatrol').value);
            if ($('prevCctv').checked)        params.set('prev_cctv', '1');
            if ($('prevLighting').checked)    params.set('prev_lighting', '1');
            if ($('prevCommunity').checked)   params.set('prev_community', '1');
            if ($('prevCheckpoints').checked) params.set('prev_checkpoints', '1');
        }

        try {
            const res = await fetch(ANALYZE_URL + '?' + params, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (data.error) throw new Error(data.message || data.error);

            const m = data.meta || {};
            $('simStatReal').textContent = (m.real_count || 0).toLocaleString();
            $('simStatSim').textContent = (m.simulated_count || 0).toLocaleString();
            const pr = m.scenarios && m.scenarios.prevention_result;
            $('simStatPrevented').textContent = (pr && pr.prevented > 0 ? pr.prevented : 0).toLocaleString();
            renderPreventionResult(pr);
            $('simSummary').classList.remove('hidden');
        } catch (e) {
            console.error('Simulation stats failed:', e);
        }
    }

    // ---------- AI analysis (Gemini) ----------
    let aiBusy = false, simBusy = false;
    let latestAi = null, latestSimAi = null;

    // REAL DATA AI
    async function runAi() {
        if (aiBusy) return;        // one in-flight call at a time — conserves quota
        aiBusy = true;
        showAiLoading('ai');
        try {
            const res = await fetch(AI_ANALYZE_URL + '?days=' + encodeURIComponent($('daysSelect').value),
                { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (!data.success) throw new Error(data.error || ('HTTP ' + res.status));

            latestAi = data;
            renderAiInto('ai', data, 'violet');
            showAiResults('ai');
            resetSaveBtn('aiSaveBtn', 'violet');
        } catch (e) {
            console.error('AI analysis failed:', e);
            showAiError('ai', e.message);
        } finally {
            aiBusy = false;
        }
    }

    // SIMULATION AI (what-if scenario)
    async function runSimAi() {
        if (simBusy) return;
        simBusy = true;
        showAiLoading('sim');
        const sc = simScenario();
        try {
            const res = await fetch(AI_SIMULATE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    days:                $('simDaysSelect').value,
                    scenario_type:       sc.type,
                    missing_safeguards:  sc.missing,
                    prevention_measures: sc.measures,
                    crime_types:         sc.crimeTypes,
                    focus:               sc.focus,
                    streets:             sc.streets
                })
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.error || ('HTTP ' + res.status));

            latestSimAi = data;
            renderAiInto('sim', data, 'amber');
            showAiResults('sim');
            resetSaveBtn('simSaveBtn', 'amber');
        } catch (e) {
            console.error('AI simulation failed:', e);
            showAiError('sim', e.message);
        } finally {
            simBusy = false;
        }
    }

    // ---------- AI block show/hide (shared by 'ai' and 'sim') ----------
    function showAiLoading(p) {
        $(p + 'Placeholder').classList.add('hidden');
        $(p + 'Results').classList.add('hidden');
        $(p + 'Error').classList.add('hidden');
        $(p + 'MetaBadge').classList.add('hidden');
        $(p + 'Loading').classList.remove('hidden');
    }
    function showAiResults(p) {
        $(p + 'Loading').classList.add('hidden');
        $(p + 'Results').classList.remove('hidden');
        $(p + 'SaveBtn').classList.remove('hidden');
        $(p + 'DownloadBtn').classList.remove('hidden');
    }
    function showAiError(p, msg) {
        $(p + 'Loading').classList.add('hidden');
        $(p + 'ErrorMessage').textContent = msg;
        $(p + 'Error').classList.remove('hidden');
    }

    // ---------- AI result renderer (shared) ----------
    function renderAiInto(p, data, accent) {
        const a = data.analysis, f = a.forecast || {}, meta = data.meta || {};

        $(p + 'MetaBadge').textContent =
            (meta.records_used || 0).toLocaleString() + ' records · ' + (meta.period_days || 0) + 'd';
        $(p + 'MetaBadge').classList.remove('hidden');

        const dir = String(f.direction || 'stable').toLowerCase();
        const style = {
            increase: { card: 'border-red-300 bg-red-50',   badge: 'bg-red-200 text-red-900',   label: 'RECORDED ACTIVITY INCREASING', icon: 'fa-arrow-trend-up' },
            decrease: { card: 'border-green-300 bg-green-50', badge: 'bg-green-200 text-green-900', label: 'RECORDED ACTIVITY DECREASING', icon: 'fa-arrow-trend-down' },
            stable:   { card: 'border-gray-300 bg-gray-50',  badge: 'bg-gray-200 text-gray-800',  label: 'RECORDED ACTIVITY STABLE', icon: 'fa-arrows-left-right' }
        }[dir] || { card: 'border-gray-300 bg-gray-50', badge: 'bg-gray-200 text-gray-800', label: dir.toUpperCase(), icon: 'fa-minus' };

        $(p + 'ForecastCard').className = 'rounded-xl border-2 p-5 ' + style.card;
        $(p + 'ForecastBadge').className = 'px-3 py-1 rounded-full text-xs font-bold ' + style.badge;
        $(p + 'ForecastBadge').innerHTML = '<i class="fas ' + style.icon + ' mr-1"></i>' + style.label;

        const pct = Number(f.expected_change_percent);
        $(p + 'ForecastPercent').textContent = isFinite(pct) ? ((pct > 0 ? '+' : '') + pct + '% observed change') : '';
        $(p + 'ForecastPercent').className = 'text-sm font-bold ' + (pct > 0 ? 'text-red-700' : pct < 0 ? 'text-green-700' : 'text-gray-700');

        $(p + 'Confidence').textContent = 'CONFIDENCE: ' + String(f.confidence || 'low').toUpperCase();
        $(p + 'ForecastSummary').textContent = (isTl() && f.summary_tl) ? f.summary_tl : (f.summary || '');

        const findings = (isTl() && a.key_findings_tl) ? a.key_findings_tl : (a.key_findings || []);
        $(p + 'Findings').innerHTML = findings.map(k =>
            '<li class="flex items-start gap-2 text-sm text-gray-800 bg-gray-50 border border-gray-200 rounded-lg p-3">' +
                '<i class="fas fa-circle-check text-' + accent + '-600 mt-0.5"></i><span>' + esc(k) + '</span>' +
            '</li>').join('') || '<li class="text-sm text-gray-500">No findings returned.</li>';

        const prio = {
            high:   'bg-red-100 text-red-800 border-red-200',
            medium: 'bg-amber-100 text-amber-800 border-amber-200',
            low:    'bg-gray-100 text-gray-700 border-gray-200'
        };

        const recWrap = $(p + 'Recommendations');
        const streetSecs = (isTl() && a.streets_tl && a.streets_tl.length) ? a.streets_tl : a.streets;
        if (streetSecs && streetSecs.length) {
            // Rule-engine shape: one section per street, one block per crime
            // type inside — same structure as the crime-mapping street modal
            recWrap.className = 'space-y-3';
            recWrap.innerHTML = streetSecs.map((sec, i) => pdStreetSection(sec, i)).join('');
            return;
        }

        recWrap.className = 'grid grid-cols-1 lg:grid-cols-2 gap-3';
        recWrap.innerHTML = (a.recommendations || []).map(r => {
            const imp = r.expected_impact || {};
            const impDir = String(imp.direction || '').toLowerCase();
            const impPct = Number(imp.estimated_change_percent);
            const good = impDir === 'decrease' || impPct < 0;
            const pr = String(r.priority || 'low').toLowerCase();

            return '<div class="rounded-xl border border-gray-200 p-4 flex flex-col gap-2">' +
                '<div class="flex items-start justify-between gap-2">' +
                    '<div class="text-sm font-bold text-gray-900"><i class="fas fa-shield-halved text-' + accent + '-600 mr-1"></i>' + esc(r.action) + '</div>' +
                    '<span class="flex-shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full border ' + (prio[pr] || prio.low) + '">' + pr.toUpperCase() + '</span>' +
                '</div>' +
                (r.location ? '<div class="text-xs text-gray-600"><i class="fas fa-location-dot text-gray-400 mr-1"></i>' + esc(r.location) + '</div>' : '') +
                (r.rationale ? '<p class="text-xs text-gray-600">' + esc(r.rationale) + '</p>' : '') +
                '<div class="mt-auto rounded-lg p-3 border ' + (good ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200') + '">' +
                    '<div class="text-[10px] font-bold uppercase tracking-wide ' + (good ? 'text-green-700' : 'text-red-700') + '">If implemented</div>' +
                    '<div class="text-sm font-bold ' + (good ? 'text-green-800' : 'text-red-800') + '">' +
                        '<i class="fas ' + (good ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up') + ' mr-1"></i>' +
                        'Crime expected to ' + (good ? 'decrease' : impDir === 'increase' ? 'increase' : 'stay stable') +
                        (isFinite(impPct) ? ' by ~' + Math.abs(impPct) + '%' : '') +
                    '</div>' +
                    (imp.explanation ? '<p class="text-[11px] mt-1 ' + (good ? 'text-green-700' : 'text-red-700') + '">' + esc(imp.explanation) + '</p>' : '') +
                '</div>' +
            '</div>';
        }).join('') || '<p class="text-sm text-gray-500">No recommendations returned.</p>';
    }

    // ---------- per-street / per-crime-type renderer (rule engine) ----------
    const PD_RISK_CHIP = {
        high:   'bg-red-100 text-red-800 border-red-200',
        medium: 'bg-amber-100 text-amber-800 border-amber-200',
        low:    'bg-green-100 text-green-800 border-green-200'
    };
    const PD_CAT_COLORS = {
        'Theft': '#2563eb', 'Robbery': '#dc2626', 'Assault': '#ea580c',
        'Burglary': '#7c3aed', 'Vehicle Theft': '#0891b2', 'Domestic Violence': '#be185d',
        'Fraud': '#ca8a04', 'Sexual Offense': '#9333ea', 'Homicide': '#111827'
    };
    const PD_SEV_CHIP = {
        critical: 'bg-red-900 text-white border-red-900',
        high:     'bg-red-100 text-red-800 border-red-200',
        moderate: 'bg-amber-100 text-amber-800 border-amber-200',
        low:      'bg-gray-100 text-gray-600 border-gray-200'
    };

    // ---------- language (English | Taglish) ----------
    let saLang = localStorage.getItem('sa_sugg_lang') === 'tl' ? 'tl' : 'en';
    const isTl = () => saLang === 'tl';

    function pdApplyLangButtons() {
        document.querySelectorAll('#aiLangToggle [data-lang]').forEach(b => {
            b.className = 'px-2.5 py-1 ' + (b.dataset.lang === saLang
                ? 'bg-violet-600 text-white'
                : 'bg-white text-violet-700 hover:bg-violet-50');
        });
    }
    document.querySelectorAll('#aiLangToggle [data-lang]').forEach(b => b.addEventListener('click', () => {
        if (saLang === b.dataset.lang) return;
        saLang = b.dataset.lang;
        localStorage.setItem('sa_sugg_lang', saLang);
        pdApplyLangButtons();
        if (latestAi) renderAiInto('ai', latestAi, 'violet');
    }));
    pdApplyLangButtons();
    function pdSeverityChip(sev) {
        const s = String(sev || '').toLowerCase();
        if (!PD_SEV_CHIP[s]) return '';
        return '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ' + PD_SEV_CHIP[s] + '">' + s.toUpperCase() + '</span>';
    }

    // "Basis — recorded crimes": what actually happened, how, and how bad —
    // so the barangay / police station can study the area, not just the advice
    function pdEvidenceBlock(ev) {
        if (!ev || !ev.cases) return '';
        const T = isTl();
        const row = (icon, html) =>
            '<div class="flex gap-1.5 text-[11px] text-amber-900 leading-relaxed"><i class="fas ' + icon + ' text-amber-600 mt-0.5 flex-shrink-0"></i><span>' + html + '</span></div>';
        const sev = esc(String(ev.severity || '').toUpperCase());
        return '<div class="mt-2 rounded-lg bg-amber-50 border border-amber-200 p-2.5">' +
            '<div class="text-[9.5px] font-bold text-amber-800 uppercase tracking-wide mb-1"><i class="fas fa-magnifying-glass mr-1"></i>' + (T ? 'Basehan — mga naitalang krimen' : 'Basis — recorded crimes') + '</div>' +
            row('fa-hashtag', T
                ? '<b>' + ev.cases + ' naitalang kaso</b> (' + ev.share + '% ng krimen sa kalyeng ito) — ang tindi ay <b>' + sev + '</b>.'
                : '<b>' + ev.cases + ' recorded case' + (ev.cases === 1 ? '' : 's') + '</b> (' + ev.share + '% of this street\'s crimes) — severity assessed as <b>' + sev + '</b>.') +
            (ev.modus && ev.modus.length ? row('fa-user-ninja', (T ? 'Paano ginawa: ' : 'How they were committed: ') + ev.modus.map(esc).join('; ') + '.') : '') +
            (typeof ev.unresolved === 'number' ? row('fa-folder-open', (ev.unresolved > 0
                ? (T
                    ? '<b>' + ev.unresolved + ' sa ' + ev.cases + ' ang hindi pa naresolba</b> — i-follow up sa mga nakatalagang opisyal.'
                    : '<b>' + ev.unresolved + ' of ' + ev.cases + ' still unresolved</b> — follow up with the assigned officers.')
                : (T
                    ? 'Lahat ng ' + ev.cases + ' kaso ay naresolba na.'
                    : 'All ' + ev.cases + ' cases already resolved.'))) : '') +
            ((ev.busiest_day || ev.latest) ? row('fa-calendar-day',
                (ev.busiest_day ? (T
                    ? 'Karamihan ng kaso ay tuwing <b>' + esc(ev.busiest_day) + '</b>. '
                    : 'Most cases fall on <b>' + esc(ev.busiest_day) + 's</b>. ') : '') +
                (ev.latest ? (T
                    ? 'Pinakahuling kaso: <b>' + esc(ev.latest) + '</b>.'
                    : 'Most recent case: <b>' + esc(ev.latest) + '</b>.') : '')) : '') +
            pdCaseLog(ev.cases_list) +
        '</div>';
    }

    // Every recorded case with its date, day and exact time — the raw material
    // behind the suggestion, ready for the barangay / police to study
    function pdCaseLog(cases) {
        if (!cases || !cases.length) return '';
        const T = isTl();
        const MAX = 8;
        const rows = cases.slice(0, MAX).map(c =>
            '<div class="flex flex-wrap items-baseline gap-x-2 text-[10.5px] text-amber-900 border-t border-dashed border-amber-200 py-1">' +
                '<span class="font-bold">' + esc(c.date || '') + '</span>' +
                (c.day ? '<span>(' + esc(c.day) + ')</span>' : '') +
                (c.time ? '<span class="font-semibold text-amber-700"><i class="fas fa-clock mr-0.5"></i>' + esc(c.time) + '</span>' : '') +
                (c.modus ? '<span class="text-amber-800">— ' + esc(c.modus) + '</span>' : '') +
                '<span class="ml-auto font-bold ' + (c.resolved ? 'text-green-700' : 'text-red-700') + '">' +
                    (c.resolved ? (T ? 'RESOLBADO' : 'RESOLVED') : (T ? 'DI PA RESOLBADO' : 'UNRESOLVED')) + '</span>' +
            '</div>').join('');
        return '<div class="mt-1.5">' +
            '<div class="text-[9.5px] font-bold text-amber-800 uppercase tracking-wide"><i class="fas fa-list-ul mr-1"></i>' + (T ? 'Talaan ng kaso (petsa · araw · oras)' : 'Case log (date · day · time)') + '</div>' +
            rows +
            (cases.length > MAX ? '<div class="text-[10px] text-amber-700 pt-1">' + (T
                ? '+' + (cases.length - MAX) + ' pa — pindutin ang "Tingnan ang mga krimen" sa itaas para sa buong listahan.'
                : '+' + (cases.length - MAX) + ' more — press "View crimes" above for the full list.') + '</div>' : '') +
        '</div>';
    }

    function pdSuggCard(s) {
        const imp = s.expected_impact || {};
        const d = s.details || {};
        const pct = Number(imp.estimated_change_percent);
        const pr = String(s.priority || 'low').toLowerCase();
        const prio = {
            high:   'bg-red-100 text-red-800 border-red-200',
            medium: 'bg-amber-100 text-amber-800 border-amber-200',
            low:    'bg-gray-100 text-gray-700 border-gray-200'
        };
        const infoRow = (icon, label, text) => text
            ? '<div class="text-[11px] text-gray-600 mt-1"><i class="fas ' + icon + ' mr-1 text-violet-500"></i><span class="font-semibold text-gray-700">' + label + ':</span> ' + esc(text) + '</div>'
            : '';

        return '<div class="rounded-lg border border-gray-200 bg-white p-3">' +
            '<div class="flex items-start justify-between gap-2">' +
                '<div class="text-sm font-bold text-gray-900"><i class="fas fa-shield-halved text-violet-600 mr-1"></i>' + esc(s.action) + '</div>' +
                '<span class="flex-shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full border ' + (prio[pr] || prio.low) + '">' + pr.toUpperCase() + '</span>' +
            '</div>' +
            (s.time_window ? '<div class="text-[11px] font-semibold text-violet-700 mt-1"><i class="fas fa-clock mr-1"></i>' + esc(s.time_window) + '</div>' : '') +
            (s.rationale ? '<p class="text-xs text-gray-600 mt-1">' + esc(s.rationale) + '</p>' : '') +
            pdEvidenceBlock(d.evidence) +
            (d.coverage ? '<div class="text-[11px] text-gray-700 mt-1.5"><i class="fas fa-location-crosshairs mr-1 text-violet-500"></i>' + esc(d.coverage) + '</div>' : '') +
            (d.steps && d.steps.length ? '<div class="mt-2 rounded-lg bg-gray-50 border border-gray-200 p-2.5">' +
                '<div class="text-[9.5px] font-bold text-gray-500 uppercase tracking-wide mb-1">' + (isTl() ? 'Paano ipapatupad' : 'How to implement') + '</div>' +
                d.steps.map((st, i) => '<div class="flex gap-1.5 text-[11px] text-gray-600 leading-relaxed"><span class="font-bold text-violet-600 flex-shrink-0">' + (i + 1) + '.</span><span>' + esc(st) + '</span></div>').join('') +
            '</div>' : '') +
            infoRow('fa-toolbox', isTl() ? 'Kailangan' : 'Needs', d.resources) +
            infoRow('fa-user-shield', isTl() ? 'Mamumuno' : 'Lead', d.lead) +
            infoRow('fa-calendar-check', 'Timeline', d.timeline) +
            (d.tips && d.tips.length ? '<div class="mt-2 rounded-lg bg-sky-50 border border-sky-200 p-2.5">' +
                '<div class="text-[9.5px] font-bold text-sky-700 uppercase tracking-wide mb-1"><i class="fas fa-people-roof mr-1"></i>' + (isTl() ? 'Mga tip sa pag-iwas para sa mga residente' : 'Prevention tips for residents') + '</div>' +
                d.tips.map(t => '<div class="flex gap-1.5 text-[11px] text-sky-900 leading-relaxed"><i class="fas fa-check text-sky-500 mt-0.5 flex-shrink-0"></i><span>' + esc(t) + '</span></div>').join('') +
            '</div>' : '') +
            (isFinite(pct) ? '<div class="text-[11px] font-bold mt-2 ' + (pct < 0 ? 'text-green-700' : 'text-gray-700') + '">' +
                '<i class="fas ' + (pct < 0 ? 'fa-arrow-trend-down' : 'fa-arrows-left-right') + ' mr-1"></i>' +
                (isTl() ? 'Kapag ipinatupad: ' : 'If implemented: ') + (pct < 0 ? '~' + Math.abs(pct) + '% ' + (isTl() ? 'mas kaunting krimen' : 'fewer crimes') : 'stable') +
                (imp.explanation ? ' <span class="font-normal text-gray-500">— ' + esc(imp.explanation) + '</span>' : '') +
            '</div>' : '') +
            (d.kpi ? '<div class="text-[11px] font-semibold text-green-700 mt-1"><i class="fas fa-bullseye mr-1"></i>' + esc(d.kpi) + '</div>' : '') +
        '</div>';
    }

    function pdStreetSection(sec, idx) {
        const lvl = String(sec.risk_level || 'low').toLowerCase();
        const open = idx === 0;   // first (busiest) street starts expanded

        let body;
        if (sec.categories && sec.categories.length) {
            body = sec.categories.map(cb => {
                const cc = PD_CAT_COLORS[cb.category] || '#64748b';
                const T = isTl();
                return '<div class="pd-cat-block rounded-lg border border-gray-200 overflow-hidden">' +
                    '<div class="flex flex-wrap items-center gap-2 px-3 py-2 bg-gray-50">' +
                        '<span class="text-[10px] font-bold text-white px-2 py-0.5 rounded-full" style="background:' + cc + ';">' + esc(cb.category_label || cb.category) + '</span>' +
                        pdSeverityChip(cb.severity) +
                        '<span class="text-[11px] font-bold text-gray-700">' + (T
                            ? cb.count + ' sa ' + sec.total + ' krimen (' + cb.share + '%)'
                            : cb.count + ' of ' + sec.total + ' crime' + (sec.total === 1 ? '' : 's') + ' (' + cb.share + '%)') + '</span>' +
                        (cb.peak_hours && cb.peak_hours.length ? '<span class="text-[10.5px] font-semibold text-violet-700"><i class="fas fa-clock mr-1"></i>Peak: ' + cb.peak_hours.map(esc).join(', ') + '</span>' : '') +
                        '<button type="button" class="pd-cat-crimes ml-auto text-[10px] font-bold text-violet-700 bg-violet-50 border border-violet-200 rounded-lg px-2.5 py-1 hover:bg-violet-100"' +
                            ' data-street="' + esc(sec.street) + '" data-cat="' + esc(cb.category) + '">' +
                            '<i class="fas fa-list mr-1"></i>' + (T ? 'Tingnan ang mga krimen' : 'View crimes') + '</button>' +
                    '</div>' +
                    '<div class="pd-cat-list hidden px-3 py-2 bg-white border-b border-gray-100"></div>' +
                    '<div class="p-2.5">' + pdSuggCard(cb.suggestion || {}) + '</div>' +
                '</div>';
            }).join('');
        } else {
            body = (sec.suggestions || []).map(s => pdSuggCard(s)).join('')
                || '<div class="text-xs text-gray-400">No suggestions.</div>';
        }

        return '<div class="rounded-xl border border-gray-200 bg-gray-50/50 overflow-hidden">' +
            '<button type="button" class="pd-street-toggle w-full flex flex-wrap items-center gap-2 px-4 py-3 text-left hover:bg-gray-100/70">' +
                '<span class="text-sm font-extrabold text-gray-900"><i class="fas fa-road text-gray-400 mr-1.5"></i>' + esc(sec.street) + '</span>' +
                '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ' + (PD_RISK_CHIP[lvl] || PD_RISK_CHIP.low) + '">' + lvl.toUpperCase() + ' RISK</span>' +
                (typeof sec.total === 'number' ? '<span class="text-[11px] font-bold text-gray-500">' + (isTl()
                    ? sec.total + ' kabuuang krimen'
                    : sec.total + ' total crime' + (sec.total === 1 ? '' : 's')) + '</span>' : '') +
                '<i class="fas fa-chevron-' + (open ? 'up' : 'down') + ' pd-chev ml-auto text-gray-400 text-xs"></i>' +
            '</button>' +
            '<div class="pd-street-body px-4 pb-4' + (open ? '' : ' hidden') + '">' +
                (sec.summary ? '<p class="text-xs text-gray-600 mb-3">' + esc(sec.summary) + '</p>' : '') +
                '<div class="grid grid-cols-1 lg:grid-cols-2 gap-3">' + body + '</div>' +
            '</div>' +
        '</div>';
    }

    // ---------- crime details per category (lazy-loaded street detail) ----------
    function fmt12h(t) {
        const m = /^(\d{1,2}):(\d{2})/.exec(String(t || ''));
        if (!m) return t || '';
        const h = parseInt(m[1], 10);
        return ((h % 12) || 12) + ':' + m[2] + ' ' + (h >= 12 ? 'PM' : 'AM');
    }

    const pdDetailCache = {};
    async function pdStreetDetail(street) {
        if (pdDetailCache[street]) return pdDetailCache[street];
        const res = await fetch(STREET_DETAIL_URL + '?street=' + encodeURIComponent(street),
            { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || ('HTTP ' + res.status));
        pdDetailCache[street] = data;
        return data;
    }

    // Compact stats bar above the crime list — mirrors the mapping modal's
    // street summary (count, unresolved, peak hours, busiest day)
    function pdCrimeSummary(incs) {
        const DONE = ['solved', 'resolved', 'closed', 'cleared'];
        const unresolved = incs.filter(i => !DONE.includes(String(i.status || '').toLowerCase())).length;

        const hourCounts = {};
        const dayCounts = {};
        incs.forEach(i => {
            const m = /^(\d{1,2}):/.exec(String(i.time || ''));
            if (m) hourCounts[parseInt(m[1], 10)] = (hourCounts[parseInt(m[1], 10)] || 0) + 1;
            if (i.date) {
                const day = new Date(i.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long' });
                dayCounts[day] = (dayCounts[day] || 0) + 1;
            }
        });
        const topHours = Object.entries(hourCounts).sort((a, b) => b[1] - a[1]).slice(0, 2)
            .map(e => fmt12h(e[0] + ':00'));
        const topDay = (Object.entries(dayCounts).sort((a, b) => b[1] - a[1])[0] || [])[0];

        const stat = (icon, html) =>
            '<span class="inline-flex items-center gap-1"><i class="fas ' + icon + ' text-gray-400"></i>' + html + '</span>';

        const T = isTl();
        return '<div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] font-semibold text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5 mb-1.5">' +
            stat('fa-hashtag', incs.length + (T ? ' krimen' : ' crime' + (incs.length === 1 ? '' : 's'))) +
            stat('fa-folder-open', '<span class="' + (unresolved > 0 ? 'text-amber-700' : 'text-green-700') + '">' + unresolved + (T ? ' di pa resolba' : ' unresolved') + '</span>') +
            (topHours.length ? stat('fa-clock', 'Peak: ' + topHours.map(esc).join(', ')) : '') +
            (topDay ? stat('fa-calendar-day', (T ? 'Pinaka-madalas: ' : 'Busiest: ') + esc(topDay) + (T ? '' : 's')) : '') +
        '</div>';
    }

    function pdCrimeRow(i) {
        const done = ['solved', 'resolved', 'closed', 'cleared'].includes(String(i.status || '').toLowerCase());
        return '<div class="border-t border-dashed border-gray-100 py-1.5">' +
            '<div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-[11px]">' +
                '<span class="font-mono text-gray-400">' + esc(i.code) + '</span>' +
                '<span class="font-semibold text-gray-900">' + esc(i.title || 'Crime') + '</span>' +
                '<span class="text-gray-500">' + esc(i.date || '') + (i.time ? ' · ' + esc(fmt12h(i.time)) : '') + '</span>' +
                '<span class="ml-auto font-bold ' + (done ? 'text-green-700' : 'text-amber-700') + '">' + esc(String(i.status || '').toUpperCase().replace(/_/g, ' ')) + '</span>' +
            '</div>' +
            '<div class="text-[10.5px] text-gray-500 mt-0.5">' +
                [
                    i.victim_count ? (isTl() ? 'Biktima: ' : 'Victims: ') + i.victim_count : null,
                    i.suspect_count ? (isTl() ? 'Suspek: ' : 'Suspects: ') + i.suspect_count : null,
                    i.weather ? (isTl() ? 'Panahon: ' : 'Weather: ') + esc(i.weather) : null,
                    i.assigned_officer ? (isTl() ? 'Opisyal: ' : 'Officer: ') + esc(i.assigned_officer) : null
                ].filter(Boolean).join(' · ') +
            '</div>' +
            (i.description ? '<div class="text-[10.5px] text-gray-400 mt-0.5">' + esc(i.description) + '</div>' : '') +
        '</div>';
    }

    // One delegated listener drives both the street accordions and the
    // per-category "View crimes" toggles (survives innerHTML re-renders)
    $('aiRecommendations').addEventListener('click', async e => {
        const tog = e.target.closest('.pd-street-toggle');
        if (tog) {
            const box = tog.parentNode.querySelector('.pd-street-body');
            const chev = tog.querySelector('.pd-chev');
            const willOpen = box.classList.contains('hidden');
            box.classList.toggle('hidden');
            if (chev) chev.className = 'fas fa-chevron-' + (willOpen ? 'up' : 'down') + ' pd-chev ml-auto text-gray-400 text-xs';
            return;
        }

        const btn = e.target.closest('.pd-cat-crimes');
        if (!btn) return;
        const block = btn.closest('.pd-cat-block');
        const list = block ? block.querySelector('.pd-cat-list') : null;
        if (!list) return;

        const T = isTl();
        if (!list.classList.contains('hidden')) {
            list.classList.add('hidden');
            btn.innerHTML = '<i class="fas fa-list mr-1"></i>' + (T ? 'Tingnan ang mga krimen' : 'View crimes');
            return;
        }

        list.classList.remove('hidden');
        btn.innerHTML = '<i class="fas fa-chevron-up mr-1"></i>' + (T ? 'Itago ang mga krimen' : 'Hide crimes');
        if (!list.dataset.loaded) {
            list.innerHTML = '<div class="text-[11px] text-gray-400 py-1"><i class="fas fa-spinner fa-spin mr-1"></i>' + (T ? 'Nilo-load ang mga krimen&hellip;' : 'Loading crimes&hellip;') + '</div>';
            try {
                const d = await pdStreetDetail(btn.dataset.street);
                const incs = ((d && d.incidents) || []).filter(i => i.category === btn.dataset.cat);
                list.innerHTML = incs.length
                    ? '<div class="text-[9.5px] font-bold text-gray-500 uppercase tracking-wide mb-1">' + (T
                          ? 'Mga ' + esc(btn.dataset.cat) + ' na krimen sa ' + esc(btn.dataset.street)
                          : esc(btn.dataset.cat) + ' crimes on ' + esc(btn.dataset.street)) + '</div>' +
                      pdCrimeSummary(incs) +
                      incs.map(pdCrimeRow).join('')
                    : '<div class="text-[11px] text-gray-400 py-1">' + (T ? 'Walang naitalang krimen para sa kategoryang ito.' : 'No recorded crimes found for this category.') + '</div>';
                list.dataset.loaded = '1';
            } catch (err) {
                console.error('Street detail failed:', err);
                list.innerHTML = '<div class="text-[11px] text-red-600 py-1">' + (T ? 'Hindi ma-load ang listahan — i-click ulit para subukan muli.' : 'Could not load the crime list — click again to retry.') + '</div>';
            }
        }
    });

    // ---------- save AI report to database (shared) ----------
    function resetSaveBtn(btnId, accent) {
        const btn = $(btnId);
        btn.disabled = false;
        btn.className = 'px-3 py-1.5 bg-' + accent + '-600 text-white rounded-lg hover:bg-' + accent + '-700 transition-colors text-xs font-semibold';
        btn.innerHTML = '<i class="fas fa-floppy-disk mr-1"></i>Save';
    }

    async function saveToDb(opts) {
        if (!opts.latest) return;
        const btn = $(opts.btnId);
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving…';

        try {
            const body = {
                meta: opts.latest.meta,
                analysis: opts.latest.analysis,
                data_source: opts.dataSource
            };
            if (opts.dataSource === 'simulation') {
                body.scenario = (opts.latest.meta && opts.latest.meta.scenario) || null;
            }

            const res = await fetch(AI_SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || ('HTTP ' + res.status));

            btn.className = 'px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs font-semibold cursor-default';
            btn.innerHTML = '<i class="fas fa-circle-check mr-1"></i>Saved (' + data.saved_rows + ' rows)';
            loadSavedReports();
        } catch (e) {
            console.error('Save failed:', e);
            btn.disabled = false;
            btn.className = 'px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-xs font-semibold';
            btn.innerHTML = '<i class="fas fa-triangle-exclamation mr-1"></i>Save failed — retry';
        }
    }

    async function loadSavedReports() {
        try {
            const res = await fetch(AI_REPORTS_URL, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.success || !data.reports || !data.reports.length) return;

            $('savedReportsWrap').classList.remove('hidden');
            $('savedReportsList').innerHTML = data.reports.map(r => {
                const isRec = r.report_type === 'recommendation';
                const isSim = r.data_source === 'simulation';
                const isMapping = r.scenario && r.scenario.type === 'street_advice';
                const when = new Date(r.created_at).toLocaleString();
                return '<div class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 bg-gray-50">' +
                    '<span class="flex-shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full ' +
                        (isMapping ? 'bg-indigo-100 text-indigo-800' : 'bg-violet-100 text-violet-800') + '">' +
                        (isMapping ? 'MAPPING' : 'PATTERN') + '</span>' +
                    '<span class="flex-shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full ' +
                        (isSim ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') + '">' +
                        (isSim ? 'SIM' : 'REAL') + '</span>' +
                    '<span class="flex-shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full ' +
                        (isRec ? 'bg-blue-100 text-blue-800' : 'bg-violet-100 text-violet-800') + '">' +
                        (isRec ? 'RECOMMENDATION' : 'ANALYSIS') + '</span>' +
                    '<div class="flex-1 min-w-0">' +
                        '<div class="text-sm font-semibold text-gray-900 truncate">' + esc(r.title) + '</div>' +
                        (r.summary ? '<div class="text-xs text-gray-600 mt-0.5 line-clamp-2">' + esc(r.summary) + '</div>' : '') +
                        '<div class="text-[10px] text-gray-400 mt-1">' + esc(when) +
                            (r.saved_by ? ' · ' + esc(r.saved_by) : '') +
                            ' · ' + r.records_used + ' records / ' + r.period_days + 'd</div>' +
                    '</div>' +
                '</div>';
            }).join('');
        } catch (e) {
            console.error('Loading saved reports failed:', e);
        }
    }

    // ---------- download AI report as file ----------
    function downloadReport(latestReport) {
        if (!latestReport) return;
        const a = latestReport.analysis, f = a.forecast || {}, m = latestReport.meta;
        const pct = Number(f.expected_change_percent);
        const dirColor = { increase: '#b91c1c', decrease: '#15803d', stable: '#374151' }[String(f.direction).toLowerCase()] || '#374151';

        const rec = r => {
            const imp = r.expected_impact || {};
            const good = String(imp.direction).toLowerCase() === 'decrease' || Number(imp.estimated_change_percent) < 0;
            return '<div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:10px;">' +
                '<div style="font-weight:700;">' + esc(r.action) + ' <span style="float:right;font-size:11px;color:#6b7280;text-transform:uppercase;">' + esc(r.priority || '') + ' priority</span></div>' +
                (r.location ? '<div style="font-size:12px;color:#4b5563;margin-top:2px;">📍 ' + esc(r.location) + '</div>' : '') +
                (r.rationale ? '<div style="font-size:12px;color:#4b5563;margin-top:6px;">' + esc(r.rationale) + '</div>' : '') +
                '<div style="margin-top:8px;padding:10px;border-radius:8px;background:' + (good ? '#f0fdf4' : '#fef2f2') + ';color:' + (good ? '#15803d' : '#b91c1c') + ';font-size:12px;">' +
                    '<strong>If implemented:</strong> crime expected to ' + (good ? 'decrease' : 'increase') +
                    (isFinite(Number(imp.estimated_change_percent)) ? ' by ~' + Math.abs(Number(imp.estimated_change_percent)) + '%' : '') +
                    (imp.explanation ? ' — ' + esc(imp.explanation) : '') +
                '</div></div>';
        };

        const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>AI Crime Analysis — Barangay San Agustin</title></head>' +
            '<body style="font-family:Segoe UI,Arial,sans-serif;max-width:800px;margin:32px auto;padding:0 16px;color:#111827;">' +
            '<h1 style="margin-bottom:4px;">AI Crime Pattern Analysis</h1>' +
            '<div style="color:#6b7280;font-size:13px;margin-bottom:24px;">Barangay San Agustin, Quezon City · ' +
                esc(m.period_start) + ' to ' + esc(m.period_end) + ' (' + m.period_days + ' days) · ' +
                m.records_used.toLocaleString() + ' records · Generated ' + new Date(m.generated_at).toLocaleString() + '</div>' +
            '<div style="border:2px solid ' + dirColor + ';border-radius:12px;padding:16px;margin-bottom:24px;">' +
                '<div style="font-size:11px;text-transform:uppercase;color:#6b7280;font-weight:700;">Crime Forecast</div>' +
                '<div style="font-size:20px;font-weight:800;color:' + dirColor + ';margin:4px 0;">' + esc(String(f.direction || '').toUpperCase()) +
                    (isFinite(pct) ? ' (' + (pct > 0 ? '+' : '') + pct + '%)' : '') +
                    ' <span style="font-size:12px;color:#6b7280;font-weight:600;">confidence: ' + esc(f.confidence || '-') + '</span></div>' +
                '<div style="font-size:14px;">' + esc(f.summary || '') + '</div>' +
            '</div>' +
            '<h2 style="font-size:16px;">Key Findings</h2><ul>' +
            (a.key_findings || []).map(k => '<li style="margin-bottom:6px;font-size:13px;">' + esc(k) + '</li>').join('') +
            '</ul>' +
            '<h2 style="font-size:16px;">Recommended Interventions &amp; Projected Effect</h2>' +
            (a.recommendations || []).map(rec).join('') +
            '</body></html>';

        const stamp = new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-');
        const blob = new Blob([html], { type: 'text/html' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'ai-crime-analysis-san-agustin-' + stamp + '.html';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
    }

    // ---------- render ----------
    function render(d) {
        const m = d.meta;

        $('statReal').textContent = m.real_count.toLocaleString();
        $('statTotal').textContent = m.total_count.toLocaleString();
        $('statPeriod').textContent = m.period_days + 'd';

        $('confidenceWarning').classList.toggle('hidden', !m.low_confidence);
        if (m.low_confidence) $('confidenceNote').textContent = m.confidence_note;

        renderInsights(d.insights);
        renderTrend(d.trends);
        renderTypes(d.type_distribution, m.total_count);
        renderTime(d.time_patterns);
        renderHotspots(d.hotspots);
        renderClusters(d.repeat_clusters);
        renderAnomalies(d.anomalies);
    }

    function renderPreventionResult(pr) {
        const box = $('preventionResult');
        if (!pr || !pr.active || !pr.active.length || !(pr.prevented > 0)) {
            box.classList.add('hidden');
            return;
        }
        $('preventionResultText').textContent =
            pr.active.join(', ') + ' cut the surge by ~' + pr.percent + '% — about ' +
            pr.prevented.toLocaleString() + ' of ' + pr.target.toLocaleString() +
            ' simulated crimes prevented on the targeted area.';
        box.classList.remove('hidden');
    }

    function renderInsights(list) {
        if (!list || !list.length) {
            $('insightsList').innerHTML = '<p class="text-sm text-gray-500">No insights available.</p>';
            return;
        }

        $('insightsList').innerHTML = list.map(i => {
            const lowConf = i.confidence === 'low';
            return '<div class="flex items-start gap-3 p-3 rounded-lg border ' +
                (i.simulated ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50') + '">' +
                '<i class="fas ' + (i.simulated ? 'fa-flask text-amber-600' : 'fa-circle-check text-alertara-600') + ' mt-0.5"></i>' +
                '<div class="flex-1 min-w-0">' +
                    '<div class="text-sm font-semibold text-gray-900">' + esc(i.text) +
                        (i.simulated ? SIM_TAG : '') +
                        (lowConf ? '<span class="inline-block px-1.5 py-0.5 bg-orange-200 text-orange-900 rounded text-[10px] font-bold ml-2">LOW CONFIDENCE</span>' : '') +
                    '</div>' +
                    '<div class="text-xs text-gray-500 mt-1">Based on: ' + esc(i.based_on) + '</div>' +
                '</div></div>';
        }).join('');
    }

    function renderTrend(t) {
        const dir = t.direction;
        const badge = $('trendBadge');
        const styles = {
            increasing: 'bg-red-100 text-red-800',
            decreasing: 'bg-green-100 text-green-800',
            stable: 'bg-gray-100 text-gray-700'
        };
        badge.className = 'px-3 py-1 rounded-full text-xs font-bold ' + (styles[dir.label] || 'bg-gray-100 text-gray-700');
        badge.textContent = {
            increasing: 'RECENT ACTIVITY UP',
            decreasing: 'RECENT ACTIVITY DOWN',
            stable: 'RECENT ACTIVITY STABLE',
            'insufficient data': 'INSUFFICIENT DATA'
        }[dir.label] || dir.label.toUpperCase();
        $('trendExplanation').textContent = dir.explanation;

        // Start with a readable aggregation, while keeping all granularities
        // available through the tabs.
        const periodDays = latest?.meta?.period_days || 0;
        const periodKey = periodDays + ':' + (t.daily || []).length;
        if (trendPeriodKey !== periodKey) {
            trendGrain = periodDays > 365 ? 'monthly' : periodDays > 90 ? 'weekly' : 'daily';
            trendPeriodKey = periodKey;
        }

        document.querySelectorAll('.trend-tab').forEach(tab => {
            const active = tab.dataset.grain === trendGrain;
            tab.className = 'trend-tab px-3 py-1 text-xs font-semibold rounded-lg border ' +
                (active ? 'bg-alertara-700 text-white border-alertara-700' : 'bg-white text-gray-600 border-gray-300');
        });

        const series = t[trendGrain] || [];
        renderTrendStats(series, dir);
        $('trendDetailHint').textContent = trendGrain === 'daily' && series.length > 60
            ? 'Daily detail is shown; date labels are reduced to keep the chart readable.'
            : 'Showing ' + trendGrain + ' totals for the selected analysis period.';

        const spansMultipleYears = new Set(series.map(point => String(point.label).slice(0, 4))).size > 1;
        const chartSeries = series.map(point => ({
            ...point,
            label: formatTrendLabel(point.label, trendGrain, spansMultipleYears)
        }));
        // Daily over a long window is far too dense for bars — a line reads it
        drawChart('trendChart', chartSeries, trendGrain === 'daily' ? 'line' : 'bar');
        renderChartLegend('trendLegend', series.some(s => (s.simulated || 0) > 0));
    }

    function formatTrendLabel(label, grain, showYear = false) {
        const date = new Date(label + (grain === 'monthly' ? '-01' : '') + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return label;

        if (grain === 'monthly') {
            return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
        }
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            ...(showYear ? { year: '2-digit' } : {})
        });
    }

    function renderTrendStats(series, direction) {
        const total = series.reduce((sum, item) => sum + (item.count || 0), 0);
        const change = Number(direction.change_percent || 0);
        const changeClass = change > 0 ? 'text-red-700' : change < 0 ? 'text-green-700' : 'text-gray-700';
        const cards = [
            ['Selected period', total + ' crimes'],
            ['First half', (direction.first_half ?? 0) + ' crimes'],
            ['Second half', (direction.second_half ?? 0) + ' crimes'],
            ['Change', (change > 0 ? '+' : '') + change + '%', changeClass]
        ];

        $('trendStats').innerHTML = cards.map(([label, value, valueClass = 'text-gray-900']) =>
            '<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">' +
                '<div class="text-[10px] font-bold uppercase tracking-wide text-gray-500">' + label + '</div>' +
                '<div class="text-sm font-bold ' + valueClass + '">' + value + '</div>' +
            '</div>'
        ).join('');
    }

    // ---------- charts (Chart.js, loaded globally by the layout) ----------

    // Palette validated with the data-viz colour checks (lightness band, chroma
    // floor, CVD separation, normal-vision floor, contrast vs surface).
    // The brand teal #274d4c fails as a data colour — too dark and near-gray.
    const SERIES_REAL = '#2a78d6';   // categorical slot 1
    const SERIES_SIM  = '#eb6834';   // categorical slot 2
    const GRID  = 'rgba(0,0,0,0.06)';
    const TICK  = '#52514e';

    const charts = {};

    function destroyChart(id) {
        if (charts[id]) { charts[id].destroy(); delete charts[id]; }
    }

    const baseOptions = (stacked) => ({
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(17,17,17,0.92)',
                padding: 10,
                titleFont: { size: 12 },
                bodyFont: { size: 12 },
                displayColors: true,
                callbacks: {
                    footer: items => {
                        const total = items.reduce((s, i) => s + i.parsed.y, 0);
                        return items.length > 1 ? 'Total: ' + total : '';
                    }
                }
            }
        },
        scales: {
            x: {
                stacked: stacked,
                grid: { display: false },
                ticks: { color: TICK, font: { size: 10 }, maxRotation: 0, autoSkipPadding: 16 }
            },
            y: {
                stacked: stacked,
                beginAtZero: true,
                grid: { color: GRID, drawBorder: false },
                ticks: { color: TICK, font: { size: 10 }, precision: 0 },
                title: { display: true, text: 'Crimes', color: TICK, font: { size: 10 } }
            }
        }
    });

    /** Two datasets so real and simulated stay visually separate, never merged */
    function datasetsFor(series, type) {
        const hasSim = series.some(s => (s.simulated || 0) > 0);

        const real = {
            label: hasSim ? 'Real' : 'Crimes',
            data: series.map(s => (s.real !== undefined ? s.real : s.count)),
            backgroundColor: type === 'line' ? 'rgba(42,120,214,0.12)' : SERIES_REAL,
            borderColor: SERIES_REAL,
            borderWidth: 2,
            borderRadius: type === 'bar' ? 4 : 0,
            fill: type === 'line',
            tension: 0,
            pointRadius: series.length > 60 ? 0 : 3,
            pointHoverRadius: 5
        };

        if (!hasSim) return [real];

        return [real, {
            label: 'Simulated',
            data: series.map(s => s.simulated || 0),
            backgroundColor: type === 'line' ? 'rgba(235,104,52,0.12)' : SERIES_SIM,
            borderColor: SERIES_SIM,
            borderWidth: 2,
            borderRadius: type === 'bar' ? 4 : 0,
            fill: type === 'line',
            tension: 0,
            pointRadius: series.length > 60 ? 0 : 3,
            pointHoverRadius: 5
        }];
    }

    function drawChart(canvasId, series, type) {
        destroyChart(canvasId);
        const canvas = $(canvasId);
        if (!canvas) return;

        const empty = !series.length || series.every(s => s.count === 0);
        const wrap = canvas.parentElement;
        let note = wrap.querySelector('.chart-empty');

        if (empty) {
            canvas.style.display = 'none';
            if (!note) {
                note = document.createElement('p');
                note.className = 'chart-empty text-sm text-gray-500 text-center pt-16';
                wrap.appendChild(note);
            }
            note.textContent = 'No data in this period.';
            return;
        }

        canvas.style.display = '';
        if (note) note.remove();

        charts[canvasId] = new Chart(canvas.getContext('2d'), {
            type: type,
            data: { labels: series.map(s => s.label), datasets: datasetsFor(series, type) },
            options: baseOptions(true)
        });
    }

    /** Legend rendered in HTML so identity is never colour-alone */
    function renderChartLegend(targetId, hasSim) {
        const el = $(targetId);
        if (!el) return;
        el.innerHTML =
            '<span class="inline-flex items-center gap-1.5 mr-4">' +
                '<span style="width:10px;height:10px;border-radius:2px;background:' + SERIES_REAL + '" class="inline-block"></span>' +
                '<span class="text-[11px] text-gray-600">' + (hasSim ? 'Real' : 'Crimes') + '</span>' +
            '</span>' +
            (hasSim ?
            '<span class="inline-flex items-center gap-1.5">' +
                '<span style="width:10px;height:10px;border-radius:2px;background:' + SERIES_SIM + '" class="inline-block"></span>' +
                '<span class="text-[11px] text-gray-600">Simulated</span>' +
            '</span>' : '');
    }

    function renderTypes(types, total) {
        if (!types.length) {
            $('typeDistribution').innerHTML = '<p class="text-sm text-gray-500">No records.</p>';
            return;
        }

        $('typeDistribution').innerHTML = types.map(t =>
            '<div>' +
                '<div class="flex justify-between items-center text-xs mb-1">' +
                    '<span class="font-semibold text-gray-800">' + esc(t.category) +
                        (t.simulated_count > 0 ? ' <span class="text-amber-700 font-normal">(' + t.simulated_count + ' sim)</span>' : '') +
                    '</span>' +
                    '<span class="text-gray-500">' + t.count + ' · ' + t.percent + '%</span>' +
                '</div>' +
                '<div class="h-2 bg-gray-100 rounded-full overflow-hidden flex">' +
                    '<div style="width:' + ((t.real_count / Math.max(1, total)) * 100) + '%" class="bg-alertara-600 h-full"></div>' +
                    '<div style="width:' + ((t.simulated_count / Math.max(1, total)) * 100) + '%" class="bg-amber-400 h-full"></div>' +
                '</div>' +
            '</div>').join('');
    }

    function renderTime(tp) {
        const wk = tp.weekday_vs_weekend;

        $('timeSummary').innerHTML =
            '<div class="bg-gray-50 border border-gray-200 rounded-lg p-3">' +
                '<div class="text-[10px] font-bold text-gray-400 uppercase">Peak Hour</div>' +
                '<div class="text-lg font-bold text-gray-900">' + (tp.peak_hour_label || '—') + '</div>' +
                '<div class="text-[11px] text-gray-500">' + tp.peak_hour_count + ' crimes</div>' +
            '</div>' +
            '<div class="bg-gray-50 border border-gray-200 rounded-lg p-3">' +
                '<div class="text-[10px] font-bold text-gray-400 uppercase">Peak Day</div>' +
                '<div class="text-lg font-bold text-gray-900">' + esc(tp.peak_day || '—') + '</div>' +
                '<div class="text-[11px] text-gray-500">' + tp.peak_day_count + ' crimes</div>' +
            '</div>' +
            '<div class="bg-gray-50 border border-gray-200 rounded-lg p-3 col-span-2">' +
                '<div class="text-[10px] font-bold text-gray-400 uppercase">Weekday vs Weekend</div>' +
                '<div class="text-sm font-bold text-gray-900 capitalize">' + esc(wk.busier) + 's are busier</div>' +
                '<div class="text-[11px] text-gray-500">' + wk.weekday_daily_avg + '/weekday vs ' +
                    wk.weekend_daily_avg + '/weekend day (' + (wk.difference_percent > 0 ? '+' : '') + wk.difference_percent + '%)</div>' +
            '</div>' +
            (tp.missing_time_count > 0 ?
                '<div class="col-span-2 text-[11px] text-orange-700 bg-orange-50 border border-orange-200 rounded-lg p-2">' +
                '<i class="fas fa-circle-info mr-1"></i>' + tp.missing_time_count +
                ' record(s) have no recorded time and are excluded from hourly figures.</div>' : '');

        drawChart('hourChart', tp.by_hour, 'bar');
        drawChart('dowChart', tp.by_day_of_week.map(d => ({
            label: d.day.slice(0, 3), count: d.count, real: d.real, simulated: d.simulated
        })), 'bar');
        renderChartLegend('timeLegend', tp.by_hour.some(h => (h.simulated || 0) > 0));
    }

    function renderHotspots(hotspots) {
        if (!hotspots.length) {
            $('hotspotList').innerHTML = '<p class="text-sm text-gray-500">No location clusters found — crimes are too scattered, or there are too few records.</p>';
            return;
        }

        $('hotspotList').innerHTML = hotspots.map(h =>
            '<div class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50">' +
                '<div class="flex-shrink-0 w-7 h-7 rounded-full bg-alertara-700 text-white text-xs font-bold flex items-center justify-center">' + h.rank + '</div>' +
                '<div class="flex-1 min-w-0">' +
                    '<div class="text-sm font-semibold text-gray-900">' + esc(h.dominant_category) +
                        (h.simulated_count > 0 ? ' <span class="text-amber-700 text-xs font-normal">(' + h.simulated_count + ' sim)</span>' : '') +
                    '</div>' +
                    '<div class="text-xs font-semibold text-alertara-700 mt-0.5"><i class="fas fa-road mr-1"></i>' + esc(h.area_name || 'Approximate mapped area') + '</div>' +
                    '<div class="text-xs text-gray-500 mt-0.5">' + h.count + ' crimes · ' + h.share_percent + '% of all · ~' + h.radius_meters + 'm radius</div>' +
                    '<div class="text-[11px] text-gray-400 font-mono mt-0.5">' + h.latitude + ', ' + h.longitude + '</div>' +
                '</div>' +
            '</div>').join('') +
            '<p class="text-[11px] text-gray-400 mt-3"><i class="fas fa-circle-info mr-1"></i>Nearby map cells are combined into one area. Locations use recorded incident coordinates.</p>';
    }

    function renderClusters(clusters) {
        $('clusterRule').textContent = clusters.length
            ? 'Crimes within ' + clusters[0].radius_meters + 'm of each other and inside a ' + clusters[0].window_hours + '-hour window.'
            : 'Crimes within 250m of each other and inside a 72-hour window.';

        if (!clusters.length) {
            $('clusterList').innerHTML = '<p class="text-sm text-gray-500">No repeat clusters detected in this period.</p>';
            return;
        }

        $('clusterList').innerHTML = clusters.map(c =>
            '<div class="p-3 rounded-lg border border-gray-200">' +
                '<div class="flex justify-between items-start mb-2">' +
                    '<div class="text-sm font-bold text-gray-900">' + c.incident_count + ' crimes in ' +
                        (c.span_days === 0 ? 'the same day' : c.span_days + ' day(s)') + '</div>' +
                    (c.simulated_count > 0 ? '<span class="px-1.5 py-0.5 bg-amber-200 text-amber-900 rounded text-[10px] font-bold">' + c.simulated_count + ' SIM</span>' : '') +
                '</div>' +
                '<div class="text-xs text-gray-500 mb-2">' + esc(c.first_date) + ' → ' + esc(c.last_date) + ' · ' + esc(c.categories.join(', ')) + '</div>' +
                '<div class="space-y-1">' +
                    c.incidents.slice(0, 5).map(i =>
                        '<div class="text-[11px] text-gray-600 flex items-center gap-2">' +
                            '<span class="w-1.5 h-1.5 rounded-full ' + (i.is_simulated ? 'bg-amber-500' : 'bg-alertara-600') + '"></span>' +
                            '<span class="font-mono">' + esc(i.incident_code) + '</span>' +
                            '<span class="truncate">' + esc(i.title) + '</span>' +
                        '</div>').join('') +
                    (c.incidents.length > 5 ? '<div class="text-[11px] text-gray-400">+' + (c.incidents.length - 5) + ' more</div>' : '') +
                '</div>' +
            '</div>').join('');
    }

    function renderAnomalies(a) {
        $('anomalyRule').textContent = a.note
            ? a.note
            : 'Days beyond 2 standard deviations from the period mean of ' + a.mean + ' crimes/day (threshold ' + a.threshold + ').';

        if (!a.detected.length) {
            $('anomalyList').innerHTML = '<p class="text-sm text-gray-500">No statistical outliers detected in this period.</p>';
            return;
        }

        $('anomalyList').innerHTML = a.detected.map(x =>
            '<div class="p-3 rounded-lg border ' + (x.severity === 'high' ? 'border-red-300 bg-red-50' : 'border-orange-200 bg-orange-50') + '">' +
                '<div class="flex justify-between items-center mb-1">' +
                    '<span class="text-sm font-bold ' + (x.severity === 'high' ? 'text-red-900' : 'text-orange-900') + '">' +
                        '<i class="fas ' + (x.type === 'spike' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down') + ' mr-1"></i>' + esc(x.date) +
                    '</span>' +
                    '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full ' +
                        (x.severity === 'high' ? 'bg-red-200 text-red-900' : 'bg-orange-200 text-orange-900') + '">' +
                        x.severity.toUpperCase() + ' · z=' + x.z_score +
                    '</span>' +
                '</div>' +
                '<div class="text-xs text-gray-700">' + esc(x.explanation) + '</div>' +
            '</div>').join('');
    }

    // ---------- tabs: real data vs simulation ----------
    function switchTab(which) {
        const real = which === 'real';
        $('tabRealPanel').classList.toggle('hidden', !real);
        $('tabSimPanel').classList.toggle('hidden', real);

        $('tabRealBtn').className = 'px-4 py-2.5 text-sm font-bold rounded-t-lg border border-b-0 flex items-center gap-2 ' +
            (real ? 'bg-blue-600 text-white border-blue-600' : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100');
        $('tabSimBtn').className = 'px-4 py-2.5 text-sm font-bold rounded-t-lg border border-b-0 flex items-center gap-2 ' +
            (!real ? 'bg-amber-500 text-white border-amber-500' : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100');

        // Charts drawn while a tab was hidden have zero width — redraw on show
        if (real && latest) render(latest);
    }

    // ---------- wiring ----------
    function init() {
        $('tabRealBtn').addEventListener('click', function () { switchTab('real'); });
        $('tabSimBtn').addEventListener('click', function () { switchTab('sim'); });
        switchTab('real');
        // REAL DATA: statistical + AI fire together on an explicit click
        $('runRealBtn').addEventListener('click', function () { runReal(); runAi(); });
        $('aiSaveBtn').addEventListener('click', function () {
            saveToDb({ btnId: 'aiSaveBtn', latest: latestAi, dataSource: 'real' });
        });
        $('aiDownloadBtn').addEventListener('click', function () { downloadReport(latestAi); });
        $('daysSelect').addEventListener('change', runReal);

        // SIMULATION: its own button runs the surge summary + AI scenario
        $('runSimBtn').addEventListener('click', function () { runSimStats(); runSimAi(); });
        $('simSaveBtn').addEventListener('click', function () {
            saveToDb({ btnId: 'simSaveBtn', latest: latestSimAi, dataSource: 'simulation' });
        });
        $('simDownloadBtn').addEventListener('click', function () { downloadReport(latestSimAi); });

        // Scenario type switches which config block is shown
        $('simScenarioType').addEventListener('change', syncScenarioType);

        // Focus area toggle shows/hides the street picker
        $('focusMode').addEventListener('change', function () {
            $('streetPickerWrap').classList.toggle('hidden', this.value !== 'streets');
            updateStreetCount();
        });

        // Street picker: search, select-all, clear, and per-checkbox selection
        $('streetSearch').addEventListener('input', function () { renderStreetList(this.value); });
        $('streetSelectAll').addEventListener('click', function () {
            streetNames.forEach(n => selectedStreetSet.add(n));
            renderStreetList($('streetSearch').value);
            updateStreetCount();
        });
        $('streetClear').addEventListener('click', function () {
            selectedStreetSet.clear();
            renderStreetList($('streetSearch').value);
            updateStreetCount();
        });
        $('streetList').addEventListener('change', function (e) {
            const cb = e.target;
            if (!cb.classList || !cb.classList.contains('street-cb')) return;
            if (cb.checked) selectedStreetSet.add(cb.value); else selectedStreetSet.delete(cb.value);
            updateStreetCount();
        });

        document.querySelectorAll('.trend-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                trendGrain = tab.dataset.grain;
                if (latest) renderTrend(latest.trends);
            });
        });

        loadSavedReports();
        loadStreets();
        syncScenarioType();

        // Auto-run the real-data statistical analysis on load (AI stays manual)
        runReal();
    }

    // Chart.js is loaded with defer by the layout, so it is only guaranteed to
    // exist once the document has finished parsing.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endpush
