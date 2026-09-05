{{--
    San Agustin street layer, and (optionally) the street suggestion modal.

    Include with:
        @include('partials.san-agustin-streets')                                -- layer only
        @include('partials.san-agustin-streets', ['withSuggestions' => true])   -- layer + modal

    The host page then calls, once its Leaflet map exists:
        saStreetsAttach(map);
        saStreetsSetVisible(true);      // or tie it to a barangay filter

    Crime Mapping includes the layer alone -- it is a map, so streets are drawn
    and hovered but clicking does nothing. Crime Hotspots includes the modal
    too, which is where a street's crimes and its prevention suggestions live.
--}}
@php($withSuggestions = $withSuggestions ?? false)

@if ($withSuggestions)
    <!-- Street Details Modal (San Agustin street click) -->
    <style>
        #streetModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 99998; padding: 20px; align-items: center; justify-content: center; }
        #streetModal .sm-card { position: relative; background: #fff; border-radius: 16px; width: 100%; max-width: 1180px; height: 92%; display: flex; flex-direction: column; box-shadow: 0 25px 70px rgba(0,0,0,0.35); overflow: hidden; }
        /* Full-width map on top; crimes (left) + AI (right) below, each with
           its own scrollbar. The body itself never scrolls on desktop. */
        #streetModal .sm-body { flex: 1; min-height: 0; display: flex; flex-direction: column; padding: 14px 20px 20px; overflow: hidden; }
        #streetModal .sm-bottom { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(0,1.1fr) minmax(0,1fr); gap: 16px; margin-top: 14px; }
        #streetModal .sm-col { min-width: 0; min-height: 0; overflow-y: auto; padding-right: 4px; }
        /* Pop-out windows: any modal section can detach into its own draggable,
           resizable "browser-like" window (fullscreen on small screens) */
        .sm-popout { display: none; position: fixed; z-index: 100010; width: min(760px, 92vw); height: min(72vh, 660px); background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; box-shadow: 0 25px 70px rgba(0,0,0,0.5); flex-direction: column; overflow: hidden; resize: both; }
        .sm-popout-head { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: #111827; color: #fff; font-size: 12px; font-weight: 700; cursor: move; user-select: none; touch-action: none; flex-shrink: 0; }
        .sm-popout-dock { margin-left: auto; background: rgba(255,255,255,0.15); color: #fff; border: none; border-radius: 8px; padding: 4px 10px; font-size: 11px; font-weight: 700; cursor: pointer; }
        .sm-popout-dock:hover { background: rgba(255,255,255,0.3); }
        .sm-popout-body { flex: 1; min-height: 0; overflow-y: auto; padding: 12px; }
        .sm-popout-body #secMap { height: 100%; display: flex; flex-direction: column; }
        .sm-popout-body #streetModalMap { flex: 1 1 auto; height: auto !important; min-height: 260px; }
        .sm-pop-btn { font-size: 10px; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px 9px; cursor: pointer; }
        .sm-pop-btn:hover { color: #111827; background: #f1f5f9; }
        @media (max-width: 900px) {
            .sm-popout { inset: 0 !important; width: 100% !important; height: 100% !important; border-radius: 0; resize: none; }
        }
        /* Enlarged suggestions: full-width panel + bigger text for readability */
        #streetModal #streetAiCol.sm-ai-big { grid-column: 1 / -1; }
        #streetModal .sm-ai-big #streetAiSummary { font-size: 14px !important; line-height: 1.55 !important; }
        #streetModal .sm-ai-big #streetAiSuggestions,
        #streetModal .sm-ai-big #streetAiSuggestions * { font-size: 13.5px !important; line-height: 1.55 !important; }
        /* Report dashboard: KPI tiles + charts drawn from the same numbers the
           prose describes, so the report reads as a dashboard, not a wall of text */
        .sa-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(74px, 1fr)); gap: 6px; margin-bottom: 8px; }
        .sa-kpi { border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 8px; min-width: 0; background: linear-gradient(180deg, #fff, #f9fafb); }
        .sa-kpi-l { font-size: 9.5px; font-weight: 800; color: #6b7280; text-transform: uppercase; letter-spacing: .02em; }
        .sa-kpi-v { font-size: 19px; font-weight: 800; color: #111827; line-height: 1.15; margin-top: 2px; }
        .sa-kpi-s { font-size: 10px; color: #6b7280; margin-top: 1px; }
        .sa-dash-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(215px, 1fr)); gap: 8px; margin-bottom: 10px; }
        .sa-dash-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 10px; background: #fff; min-width: 0; }
        .sa-dash-card.sa-wide { grid-column: 1 / -1; }
        .sa-dash-t { font-size: 10px; font-weight: 800; color: #374151; text-transform: uppercase; display: flex; align-items: center; gap: 5px; margin-bottom: 4px; }
        .sa-dash-t i { color: #7c3aed; }
        .sa-dash-c { position: relative; height: 150px; }
        .sa-dash-c.sa-tall { height: 190px; }
        .sa-dash-note { font-size: 10px; color: #6b7280; margin-top: 4px; }
        /* Per-street mini visuals inside each street section */
        .sa-sec-viz { display: grid; grid-template-columns: minmax(0, 1fr) 150px; gap: 10px; align-items: center; margin-bottom: 8px; }
        .sa-sec-viz.sa-single { grid-template-columns: 1fr; }
        .sa-stack { display: flex; height: 12px; border-radius: 6px; overflow: hidden; background: #f3f4f6; }
        .sa-stack span { display: block; height: 100%; min-width: 2px; }
        .sa-legend { display: flex; flex-wrap: wrap; gap: 4px 10px; margin-top: 5px; }
        .sa-legend span { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; color: #4b5563; font-weight: 600; }
        .sa-legend i { width: 8px; height: 8px; border-radius: 9999px; display: inline-block; }
        .sa-share { height: 5px; border-radius: 3px; background: #f3f4f6; overflow: hidden; flex: 1; min-width: 60px; }
        .sa-share span { display: block; height: 100%; border-radius: 3px; }
        .sa-impact { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
        .sa-impact-bar { flex: 1; height: 8px; border-radius: 4px; background: #f3f4f6; overflow: hidden; }
        .sa-impact-bar span { display: block; height: 100%; border-radius: 4px; background: linear-gradient(90deg, #22c55e, #15803d); }
        .sa-mini-c { position: relative; height: 64px; }
        .sa-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
        .sa-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 700; padding: 3px 8px; border-radius: 9999px; background: #f3f4f6; color: #374151; }
        .sa-chip i { font-size: 9.5px; opacity: .8; }
        .sa-details-toggle { display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; font-size: 10.5px; font-weight: 800; color: #6d28d9; background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; padding: 4px 10px; cursor: pointer; }
        .sa-details-toggle:hover { background: #ede9fe; }
        .sa-details { display: none; margin-top: 8px; border-top: 1px dashed #e5e7eb; padding-top: 8px; }
        .sa-details.open { display: block; }
        .sa-sugg-head { display: flex; align-items: flex-start; gap: 8px; }
        .sa-sugg-title { font-size: 12.5px; font-weight: 800; color: #111; flex: 1; line-height: 1.35; }
        .sa-sugg-title i { color: #7c3aed; margin-right: 4px; }
        .sa-prio { flex-shrink: 0; font-size: 9.5px; font-weight: 800; padding: 2px 7px; border-radius: 9999px; }
        @media (max-width: 480px) { .sa-sec-viz { grid-template-columns: 1fr; } }
        /* Fullscreen mode (expand button) */
        #streetModal.sm-nopad { padding: 0; }
        #streetModal .sm-card.sm-full { max-width: 100%; width: 100%; height: 100%; border-radius: 0; }
        #streetModal .sm-card.sm-full #streetModalMap { height: 45vh; }
        /* Small screens: the whole modal body scrolls as one column */
        @media (max-width: 900px) {
            #streetModal { padding: 10px; }
            #streetModal .sm-card { height: 96%; }
            #streetModal .sm-body { overflow-y: auto; display: block; -webkit-overflow-scrolling: touch; }
            #streetModal .sm-bottom { grid-template-columns: 1fr; }
            #streetModal .sm-col { overflow-y: visible; }
            #streetModal .sm-card:not(.sm-full) #streetModalMap { height: 260px !important; }
        }
        #streetModal .sm-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; background: #f3f4f6; color: #374151; }
        #streetModal .sm-inc-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; cursor: pointer; transition: box-shadow .15s, border-color .15s; }
        #streetModal .sm-inc-card:hover { border-color: #94a3b8; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        /* Collapsible per-street crime group (right column) */
        #streetModal .sm-acc { border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        #streetModal .sm-acc-head { width: 100%; display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #f9fafb; border: none; cursor: pointer; font-size: 12.5px; font-weight: 800; color: #111827; text-align: left; }
        #streetModal .sm-acc-head:hover { background: #f3f4f6; }
        #streetModal .sm-acc-body { padding: 10px; display: grid; gap: 8px; }
        /* Street filter dropdown (header) */
        #streetFilterPanel { display: none; position: absolute; top: calc(100% + 6px); left: 0; z-index: 50; width: 300px; max-width: 80vw; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 12px 35px rgba(0,0,0,0.18); padding: 10px; }
        /* Street name labels on the mini map: subtle for context streets,
           a bold pill for the active/selected ones */
        .street-name-mini { background: rgba(255,255,255,0.75); border: 1px solid rgba(100,116,139,0.55); color: #475569; font-weight: 600; font-size: 9px; padding: 1px 6px; border-radius: 9999px; white-space: nowrap; box-shadow: none; opacity: 0.85; }
        .street-name-mini::before { display: none; }
        .street-name-mini.snm-active { background: rgba(255,255,255,0.97); border: 1.5px solid #111827; color: #111827; font-weight: 800; font-size: 11px; padding: 2px 9px; box-shadow: 0 1px 5px rgba(0,0,0,0.3); opacity: 1; }
    </style>
    <div id="streetModal" onclick="if(event.target === this) closeStreetModal()">
        <div class="sm-card">
            <button onclick="closeStreetModal()" style="position: absolute; top: 14px; right: 14px; background: none; border: none; font-size: 20px; color: #999; cursor: pointer; z-index: 10; width: 32px; height: 32px;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'"><i class="fas fa-times"></i></button>
            <button onclick="toggleStreetModalFullscreen()" title="Toggle fullscreen" style="position: absolute; top: 14px; right: 50px; background: none; border: none; font-size: 16px; color: #999; cursor: pointer; z-index: 10; width: 32px; height: 32px;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'"><i id="streetModalFsIcon" class="fas fa-expand"></i></button>

            <!-- Header -->
            <div style="padding: 18px 20px 12px; border-bottom: 1px solid #e5e7eb;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <span id="streetModalSwatch" style="display: inline-block; width: 34px; height: 8px; border-radius: 4px; background: #64748b;"></span>
                    <h2 id="streetModalName" style="font-size: 18px; font-weight: 800; color: #111; margin: 0;">Street</h2>
                    <span style="font-size: 12px; color: #6b7280;">Barangay San Agustin, Quezon City</span>
                </div>
                <div id="streetModalPills" style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px;">
                    <span class="sm-pill"><i class="fas fa-spinner fa-spin"></i> Loading…</span>
                </div>
            </div>

            <div class="sm-body">
                <!-- MAP SECTION (can pop out into its own window) -->
                <div id="secMap" style="display: flex; flex-direction: column; flex-shrink: 0; min-height: 0;">
                <!-- Street filter dropdown sits ABOVE the map -->
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-shrink: 0;">
                    <span style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase;">
                        <i class="fas fa-map-location-dot mr-1" style="color: #274d4c;"></i>Street map — crimes plotted where they happened
                    </span>
                    <button type="button" class="sm-pop-btn" onclick="popOutSection('map')" title="Open the map in a separate window" style="margin-left: auto;">
                        <i class="fas fa-up-right-from-square"></i>
                    </button>
                    <div id="streetFilterWrap" style="position: relative;">
                        <button id="streetFilterBtn" type="button" onclick="toggleStreetFilterPanel()"
                                style="display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 700; color: #374151; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 10px; padding: 7px 12px; cursor: pointer;">
                            <i class="fas fa-road" style="color: #b45309;"></i>
                            <span id="streetFilterBtnLabel">Streets (1)</span>
                            <i class="fas fa-chevron-down" style="font-size: 10px; color: #9ca3af;"></i>
                        </button>
                        <div id="streetFilterPanel" style="left: auto; right: 0;">
                            <input type="text" id="streetFilterSearch" placeholder="Search street…" oninput="renderStreetFilterList(this.value)"
                                   style="width: 100%; box-sizing: border-box; padding: 6px 9px; margin-bottom: 6px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 12px;">
                            <div id="streetFilterList" style="max-height: 210px; overflow-y: auto; display: grid; gap: 1px;"></div>
                            <div style="display: flex; justify-content: flex-end; margin-top: 6px;">
                                <button type="button" onclick="clearModalStreets()" style="font-size: 10.5px; font-weight: 700; color: #6b7280; background: none; border: none; cursor: pointer; text-decoration: underline;">Keep first street only</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FULL-WIDTH map -->
                <div id="streetModalMap" style="height: 380px; flex-shrink: 0; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden;"></div>
                </div><!-- /secMap -->

                <div class="sm-bottom">
                <!-- BELOW-LEFT: crimes per selected street (collapsible groups, own scrollbar) -->
                <div class="sm-col" id="secCrimes">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <span style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase;">
                            <i class="fas fa-list-ul mr-1" style="color: #274d4c;"></i>Crimes on selected street(s) <span id="streetIncCount" style="color: #274d4c;"></span>
                        </span>
                        <button type="button" class="sm-pop-btn" onclick="popOutSection('crimes')" title="Open the crime list in a separate window" style="margin-left: auto;">
                            <i class="fas fa-up-right-from-square"></i>
                        </button>
                    </div>
                    <div id="streetIncidentList" style="display: grid; gap: 10px; align-content: start;">
                        <div style="font-size: 12px; color: #9ca3af; padding: 12px;"><i class="fas fa-spinner fa-spin mr-1"></i>Loading crimes…</div>
                    </div>
                </div>

                <!-- BELOW-RIGHT: AI suggestions -->
                <div class="sm-col" id="streetAiCol">
                    <!-- AI suggestions (manual — one Gemini call per Analyze click) -->
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap;">
                            <span style="font-size: 10px; font-weight: 700; color: #999; text-transform: uppercase;">
                                <i class="fas fa-shield-halved mr-1" style="color: #7c3aed;"></i>Prevention suggestions
                            </span>
                            <span id="streetAiRisk" style="display: none; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 9999px;"></span>
                            <span id="streetLangToggle" title="Language of the suggestions" style="display: inline-flex; border: 1px solid #ddd6fe; border-radius: 8px; overflow: hidden; font-size: 10px; font-weight: 800;">
                                <button type="button" data-lang="en" style="border: none; padding: 3px 8px; cursor: pointer;">English</button>
                                <button type="button" data-lang="tl" style="border: none; padding: 3px 8px; cursor: pointer;">Taglish</button>
                            </span>
                            <button type="button" class="sm-pop-btn" onclick="popOutSection('sugg')" title="Open the suggestions in a separate window" style="margin-left: auto;">
                                <i class="fas fa-up-right-from-square"></i>
                            </button>
                            <button id="streetAiZoomBtn" type="button" onclick="toggleStreetAiSize()" title="Enlarge suggestions for readability"
                                    style="font-size: 11px; font-weight: 700; color: #7c3aed; background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; padding: 5px 10px; cursor: pointer;">
                                <i class="fas fa-magnifying-glass-plus"></i>
                            </button>
                            <button id="streetAiSaveBtn" onclick="saveStreetAi()" style="display: none; font-size: 11px; font-weight: 700; color: #fff; background: #7c3aed; border: none; border-radius: 8px; padding: 5px 12px; cursor: pointer;">
                                <i class="fas fa-floppy-disk mr-1"></i>Save
                            </button>
                        </div>

                        <button id="streetAiAnalyzeBtn" onclick="analyzeStreetAi()" style="width: 100%; font-size: 12.5px; font-weight: 700; color: #fff; background: #7c3aed; border: none; border-radius: 10px; padding: 9px 12px; cursor: pointer; margin-bottom: 8px;">
                            <i class="fas fa-wand-magic-sparkles mr-1"></i><span id="streetAiAnalyzeLabel">Generate suggestions</span>
                        </button>

                        <div id="streetAiPlaceholder" style="border: 1px dashed #d1d5db; background: #f9fafb; border-radius: 10px; padding: 12px; font-size: 11.5px; color: #6b7280; text-align: center;">
                            The system reviews the crimes on every selected street and builds a visual report — crime mix, time-of-day, day-of-week, 12-month trend and case status charts — with what to do per street, based on the crime categories most frequently committed there. Press <span style="font-weight: 700; color: #7c3aed;">Generate suggestions</span> — instant, no AI quota used.
                        </div>
                        <div id="streetAiLoading" style="display: none; border: 1px solid #ddd6fe; background: #f5f3ff; border-radius: 10px; padding: 14px; font-size: 12px; color: #6d28d9;">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Analyzing the crimes on the selected street(s)…
                        </div>
                        <div id="streetAiError" style="display: none; border: 1px solid #fecaca; background: #fef2f2; border-radius: 10px; padding: 12px; font-size: 12px; color: #b91c1c;">
                            <span id="streetAiErrorMsg"></span>
                            <button onclick="analyzeStreetAi()" style="margin-left: 8px; font-weight: 700; color: #7c3aed; background: none; border: none; cursor: pointer; text-decoration: underline;">Retry</button>
                        </div>
                        <div id="streetAiResults" style="display: none;">
                            <p id="streetAiSummary" style="font-size: 12.5px; color: #374151; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; margin: 0 0 8px;"></p>
                            <!-- Charts + KPI tiles built from the report numbers -->
                            <div id="streetAiDash"></div>
                            <div id="streetAiSuggestions" style="display: grid; gap: 8px;"></div>
                        </div>
                    </div>
                </div>
                </div><!-- /sm-bottom -->
            </div>
        </div>
    </div>
@endif

<script>
    // Set from the Blade flag above: the click-through to the suggestions modal
    // only exists on pages that also include the modal.
    window.SA_WITH_SUGGESTIONS = @json($withSuggestions);
</script>
<script>
        // ------------------------------------------------------------------
        // San Agustin street layer — every street outlined, hover highlights
        // the whole street and shows its incident stats. Visible only while
        // Barangay San Agustin is the isolated barangay.
        // ------------------------------------------------------------------
        const SA_STREETS_URL = '/data/san_agustin_streets.geojson';
        const SA_STREET_STATS_URL = @json(route('pattern-detection.street-stats'));

        let saStreetLayer = null;       // layer group holding all street polylines
        let saStreetsLoading = null;    // promise guard so we only build once
        let saStreetGroupsAll = null;   // name -> {casing, inner, color}, for the street modal's context view
        let saStreetStatsAll = {};      // name -> stats, shared with the modal's hover tooltips

        const escStreet = s => String(s ?? '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        // Streets are COLOR-CODED by how many crimes they carry. Highest
        // matching threshold wins; the same scale drives the map legend.
        const STREET_SEVERITY = [
            { min: 15, label: 'Critical', range: '15+ crimes',  color: '#7f1d1d' },
            { min: 10, label: 'High',     range: '10-14 crimes', color: '#dc2626' },
            { min: 5,  label: 'Moderate', range: '5-9 crimes',   color: '#f97316' },
            { min: 1,  label: 'Low',      range: '1-4 crimes',   color: '#ca8a04' },
            { min: 0,  label: 'Cleared',  range: 'no crime',     color: '#16a34a' },
        ];
        function severityForCount(n) {
            return STREET_SEVERITY.find(function (s) { return n >= s.min; }) || STREET_SEVERITY[STREET_SEVERITY.length - 1];
        }

        // Legend explaining the color criteria — mounted on whichever map is
        // showing the street layer (main map and the street modal)
        function makeStreetLegend() {
            const ctl = L.control({ position: 'bottomright' });
            ctl.onAdd = function () {
                const div = L.DomUtil.create('div');
                div.style.cssText = 'background:rgba(255,255,255,0.96);border:1px solid #e5e7eb;border-radius:10px;padding:8px 12px;box-shadow:0 2px 10px rgba(0,0,0,0.18);font-size:11px;color:#374151;line-height:1.7;';
                div.innerHTML = '<div style="font-weight:800;font-size:11px;color:#111827;margin-bottom:2px;"><i class="fas fa-road" style="color:#274d4c;"></i> Street crime level</div>' +
                    STREET_SEVERITY.map(function (s) {
                        return '<div style="display:flex;align-items:center;gap:7px;">' +
                            '<span style="display:inline-block;width:20px;height:5px;border-radius:3px;background:' + s.color + ';"></span>' +
                            '<span style="font-weight:700;">' + s.label + '</span>' +
                            '<span style="color:#9ca3af;">' + s.range + '</span>' +
                        '</div>';
                    }).join('');
                return div;
            };
            return ctl;
        }
        let saStreetLegendCtl = null;   // legend instance on the MAIN map

        async function ensureSanAgustinStreets() {
            if (saStreetLayer) return saStreetLayer;
            if (saStreetsLoading) return saStreetsLoading;

            saStreetsLoading = (async () => {
                let geo, stats = {};
                try {
                    // Cache-buster: the geojson gets re-clipped during development,
                    // and a stale cached copy would redraw streets past the boundary
                    const [gRes, sRes] = await Promise.all([
                        fetch(SA_STREETS_URL + '?t=' + Date.now(), { headers: { 'Accept': 'application/json' } }),
                        fetch(SA_STREET_STATS_URL + '?t=' + Date.now(), { headers: { 'Accept': 'application/json' } })
                    ]);
                    geo = await gRes.json();
                    stats = (await sRes.json()).streets || {};
                } catch (e) {
                    console.error('San Agustin street layer failed to load:', e);
                    return null;
                }

                saStreetLayer = L.layerGroup();

                // Group all OSM segments of a street so hover lights up the
                // whole street, not just one piece. The line colour encodes
                // the street's crime level (see STREET_SEVERITY).
                const groups = {};
                (geo.features || []).forEach(f => {
                    const name = f.properties && f.properties.name;
                    if (!name || f.geometry.type !== 'LineString') return;

                    const latlngs = f.geometry.coordinates.map(c => [c[1], c[0]]);
                    const g = groups[name] = groups[name] ||
                        { casing: [], inner: [], color: severityForCount(stats[name] ? stats[name].count : 0).color };

                    // Thin, translucent lines: the faint casing keeps the outline
                    // readable, and the base map's street names show through.
                    // NOT added to the layer here — each street's polylines are
                    // mounted below through one featureGroup, so the group has a
                    // map reference and its tooltip can actually open.
                    g.casing.push(L.polyline(latlngs, { color: '#1e293b', weight: 3.5, opacity: 0.25, pane: 'streetPane' }));
                    g.inner.push(L.polyline(latlngs, { color: g.color, weight: 1.75, opacity: 0.55, pane: 'streetPane' }));
                });

                // The street modal redraws every street (muted) for context and
                // reuses the same per-street stats for its hover tooltips
                saStreetGroupsAll = groups;
                saStreetStatsAll = stats;

                // Nearest point on the street's polylines to a given lat/lng —
                // anchor of the thin pointer line drawn to each crime dot.
                const nearestOnStreet = (g, lat, lng) => {
                    const cosLat = Math.cos(lat * Math.PI / 180);
                    let best = null, bestD = Infinity;
                    g.inner.forEach(l => {
                        const pts = l.getLatLngs();
                        for (let i = 0; i < pts.length - 1; i++) {
                            const ax = pts[i].lng * cosLat, ay = pts[i].lat;
                            const bx = pts[i + 1].lng * cosLat, by = pts[i + 1].lat;
                            const px = lng * cosLat, py = lat;
                            const dx = bx - ax, dy = by - ay;
                            const lsq = dx * dx + dy * dy;
                            const t = lsq < 1e-18 ? 0 : Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / lsq));
                            const qx = ax + t * dx, qy = ay + t * dy;
                            const d = (px - qx) * (px - qx) + (py - qy) * (py - qy);
                            if (d < bestD) { bestD = d; best = [qy, (qx / cosLat)]; }
                        }
                    });
                    return best;
                };

                Object.entries(groups).forEach(([name, g]) => {
                    const st = stats[name];
                    const sev = severityForCount(st ? st.count : 0);
                    const tip = '<div style="font-weight:700;margin-bottom:2px;">' + escStreet(name) + '</div>' +
                        // colour + severity label so the criteria reads on hover
                        '<div style="margin-bottom:3px;"><span style="display:inline-block;width:28px;height:5px;border-radius:3px;background:' + g.color + ';vertical-align:middle;"></span>' +
                        ' <span style="font-weight:700;color:' + g.color + ';">' + sev.label + '</span></div>' +
                        (st
                            ? '<div>' + st.count + ' crime' + (st.count === 1 ? '' : 's') +
                              (st.top_category ? ' · mostly ' + escStreet(st.top_category) : '') + '</div>' +
                              (st.peak_hours && st.peak_hours.length
                                  ? '<div style="color:#c4b5fd;">Peak hours: ' + st.peak_hours.map(escStreet).join(', ') + '</div>' : '')
                            : '<div>No recorded crimes — cleared</div>') +
                        (SA_WITH_SUGGESTIONS
                            ? '<div style="margin-top:3px;color:#93c5fd;font-weight:600;"><i class="fas fa-hand-pointer"></i> ' +
                              (window.SA_CLICK_HINT || 'Click for full details &amp; prevention advice') + '</div>'
                            : '');

                    // No bringToFront() here on purpose: raising the SVG path
                    // while the cursor sits on it re-appends the element, the
                    // pending mouseout never fires, and the tooltip gets stuck.
                    const highlight = on => {
                        g.casing.forEach(l => l.setStyle(on
                            ? { weight: 8, color: '#111827', opacity: 0.85 }
                            : { weight: 3.5, color: '#1e293b', opacity: 0.25 }));
                        g.inner.forEach(l => l.setStyle(on
                            ? { weight: 4.5, color: g.color, opacity: 1 }
                            : { weight: 1.75, color: g.color, opacity: 0.55 }));
                    };

                    // Thin dashed pointer lines from the street to each of its
                    // crime dots, labelled with the crime, shown only while
                    // hovering. Everything is non-interactive so the pointers
                    // can never steal the mouse and flicker the hover state.
                    let connectors = null;
                    const showConnectors = () => {
                        if (connectors || !st || !st.incidents || !st.incidents.length) return;
                        connectors = L.layerGroup();

                        st.incidents.forEach(inc => {
                            if (!isFinite(inc.lat) || !isFinite(inc.lng)) return;
                            const anchor = nearestOnStreet(g, inc.lat, inc.lng);
                            if (anchor) {
                                L.polyline([anchor, [inc.lat, inc.lng]], {
                                    color: '#111827', weight: 1.2, opacity: 0.85,
                                    dashArray: '4,3', interactive: false
                                }).addTo(connectors);
                            }

                            L.circleMarker([inc.lat, inc.lng], {
                                radius: 4.5, color: '#111827', weight: 1.5,
                                fillColor: g.color, fillOpacity: 1, interactive: false
                            }).addTo(connectors);

                            const label = escStreet(inc.category) + (inc.time ? ' · ' + escStreet(inc.time) : '');
                            L.marker([inc.lat, inc.lng], {
                                interactive: false,
                                icon: L.divIcon({
                                    className: '',
                                    iconSize: null,
                                    html: '<div style="transform:translate(-50%,-165%);display:inline-block;white-space:nowrap;' +
                                          'background:#111827;color:#fff;font-size:10px;font-weight:600;' +
                                          'padding:2px 7px;border-radius:9999px;box-shadow:0 1px 4px rgba(0,0,0,.35);' +
                                          'border:1.5px solid ' + g.color + ';">' + label + '</div>'
                                })
                            }).addTo(connectors);
                        });

                        // Mounted on the street layer, not the map, so switching
                        // barangay mid-hover sweeps the pointers away with it
                        connectors.addTo(saStreetLayer);
                    };
                    const hideConnectors = () => {
                        if (connectors) { saStreetLayer.removeLayer(connectors); connectors = null; }
                    };

                    // ONE tooltip per street bound to a featureGroup that is
                    // itself mounted on the layer — the group then has a map
                    // reference, which sticky tooltips need in order to open.
                    const streetGroup = L.featureGroup(g.casing.concat(g.inner)).addTo(saStreetLayer);
                    streetGroup.bindTooltip(tip, { sticky: true, direction: 'top', opacity: 0.95 });
                    streetGroup.on('mouseover', () => { highlight(true); showConnectors(); });
                    streetGroup.on('mouseout', () => {
                        highlight(false);
                        hideConnectors();
                        streetGroup.closeTooltip();   // never leave it hanging open
                    });
                    // Click opens the street modal: filtered mini-map, full
                    // incident details, and prevention suggestions. Only the
                    // page that includes the modal binds it.
                    if (SA_WITH_SUGGESTIONS) {
                        streetGroup.on('click', () => {
                            highlight(false);
                            hideConnectors();
                            streetGroup.closeTooltip();

                            // The host page decides what a street click does —
                            // Crime Hotspots focuses the street and fills its
                            // analysis panel; without a hook, open the modal.
                            if (typeof window.onSanAgustinStreetClick === 'function') {
                                window.onSanAgustinStreetClick(name, g);
                            } else {
                                openStreetModal(name, g);
                            }
                        });
                    }
                });

                return saStreetLayer;
            })();

            return saStreetsLoading;
        }

        // The host page owns its map; the layer is attached to it and toggled
        // by whatever rule that page uses (a barangay filter, or always on).
        let saHostMap = null;
        let saVisibleWant = false;

        function saStreetsAttach(hostMap) {
            saHostMap = hostMap;
            if (!hostMap) return;

            // The street lines render in their own pane, above the boundary
            // polygons but below the incident circles.
            if (!hostMap.getPane('streetPane')) {
                hostMap.createPane('streetPane');
                hostMap.getPane('streetPane').style.zIndex = 360;
            }
        }

        // Frame every San Agustin street. The host map may open on the whole
        // city, where the streets are too small to click.
        function saStreetsFitBounds() {
            if (!saHostMap) return;

            ensureSanAgustinStreets().then(function () {
                if (!saHostMap) return;

                const bounds = L.latLngBounds([]);
                Object.keys(saStreetGroupsAll || {}).forEach(function (name) {
                    saStreetGroupsAll[name].inner.forEach(function (line) {
                        bounds.extend(line.getBounds());
                    });
                });

                if (bounds.isValid()) {
                    saHostMap.invalidateSize();
                    saHostMap.fitBounds(bounds.pad(0.08), { animate: true });
                }
            });
        }

        // Frame ONE street on the host map (used when a street is selected
        // from the map or from a ranked list).
        function saStreetsFitStreet(name) {
            if (!saHostMap) return;

            ensureSanAgustinStreets().then(function () {
                const g = (saStreetGroupsAll || {})[name];
                if (!g || !saHostMap) return;

                const bounds = L.latLngBounds([]);
                g.inner.forEach(function (line) { bounds.extend(line.getBounds()); });

                if (bounds.isValid()) {
                    saHostMap.invalidateSize();
                    saHostMap.fitBounds(bounds.pad(0.45), { maxZoom: 18, animate: true });
                }
            });
        }

        // Draw attention to one street: full-strength line, everything else
        // back to its normal weight.
        function saStreetsHighlight(names) {
            const selected = new Set((Array.isArray(names) ? names : [names])
                .filter(Boolean)
                .map(name => String(name).trim()));
            const isFiltering = selected.size > 0;

            ensureSanAgustinStreets().then(function () {
                Object.keys(saStreetGroupsAll || {}).forEach(function (key) {
                    const g = saStreetGroupsAll[key];
                    const on = !isFiltering || selected.has(key);
                    g.casing.forEach(function (l) {
                        l.setStyle(on ? { weight: isFiltering ? 7 : 3.5, color: isFiltering ? '#111827' : '#1e293b', opacity: isFiltering ? 0.9 : 0.65 }
                                      : { weight: 3.5, color: '#1e293b', opacity: 0.18 });
                    });
                    g.inner.forEach(function (l) {
                        l.setStyle(on ? { weight: isFiltering ? 4 : 1.75, color: g.color, opacity: 1 }
                                      : { weight: 1.75, color: g.color, opacity: 0.32 });
                    });
                });
            });
        }

        function saStreetsSetVisible(show) {
            saVisibleWant = !!show;
            if (!saHostMap) return;

            if (saVisibleWant) {
                ensureSanAgustinStreets().then(function (layer) {
                    // Re-check: the filter may have changed while streets loaded
                    if (!layer || !saHostMap || !saVisibleWant) return;
                    if (!saHostMap.hasLayer(layer)) {
                        layer.addTo(saHostMap);
                        // Color-criteria legend rides along with the street layer
                        if (!saStreetLegendCtl) saStreetLegendCtl = makeStreetLegend();
                        saStreetLegendCtl.addTo(saHostMap);
                    }
                });
            } else if (saStreetLayer && saHostMap.hasLayer(saStreetLayer)) {
                saHostMap.removeLayer(saStreetLayer);
                if (saStreetLegendCtl) saStreetLegendCtl.remove();
            }
        }

</script>

@if ($withSuggestions)
<script>
        // Base map for the modal's mini map. Mirrors the crime map's setup
        // (tiles, zoom limits, inertia) so both behave identically.
        function saCreateMap(containerId, opts) {
            const m = L.map(containerId, Object.assign({
                center: [14.6349, 121.0446],
                zoom: 12,
                minZoom: 10,
                maxZoom: 25,
                zoomControl: true,
                scrollWheelZoom: true,
                bounceAtZoomLimits: true,
                inertia: true,
                inertiaDeceleration: 3000,
                inertiaMaxSpeed: 1500,
                easeLinearity: 0.25
            }, opts || {}));

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 25,
                minZoom: 10
            }).addTo(m);

            return m;
        }

        // Barangay outline for the modal's context view, read from the public
        // barangay geojson so the modal does not depend on the host page having
        // already built a barangay layer of its own.
        let saBoundaryRings = [];
        let saBoundaryDrawn = false;

        function drawSanAgustinRing() {
            if (!streetModalBase || saBoundaryDrawn || !saBoundaryRings.length) return;
            saBoundaryDrawn = true;

            saBoundaryRings.forEach(function (ring) {
                L.polygon(ring.map(function (c) { return [c[1], c[0]]; }), {
                    color: '#274d4c', weight: 2, opacity: 0.75, dashArray: '6,4',
                    fillColor: '#e8f5f3', fillOpacity: 0.08, interactive: false
                }).addTo(streetModalBase);
            });
        }

        fetch('/qc_barangays.geojson', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (geo) {
                const f = (geo.features || []).find(function (feature) {
                    return String((feature.properties || {}).name || '').trim().toLowerCase() === 'san agustin';
                });
                if (!f) return;

                const coords = f.geometry.coordinates || [];
                // Polygon => [ring, hole...]; MultiPolygon => [[ring, hole...], ...]
                if (f.geometry.type === 'MultiPolygon') {
                    coords.forEach(function (poly) { saBoundaryRings.push.apply(saBoundaryRings, poly); });
                } else {
                    saBoundaryRings.push.apply(saBoundaryRings, coords);
                }

                drawSanAgustinRing();   // in case the modal was opened already
            })
            .catch(function (e) { console.warn('San Agustin outline unavailable:', e); });

        // ------------------------------------------------------------------
        // San Agustin street modal — opened by clicking a street. Shows the
        // street alone on a filtered mini-map with a dot where each crime
        // happened, the full details of every incident, and AI suggestions.
        // ------------------------------------------------------------------
        const SA_STREET_DETAIL_URL = @json(route('pattern-detection.street-detail'));
        const SA_STREET_AI_URL = @json(route('pattern-detection.street-ai-suggest'));
        const SA_AI_SAVE_URL = @json(route('pattern-detection.ai-save'));

        let streetModalMap = null;        // mini Leaflet map, created once (same tile setup as the big map)
        let streetModalBase = null;       // static context: boundary + every street + name labels
        let miniStreets = {};             // street name -> {lines, label, color}
        let streetCrimeLayers = {};       // street name -> layerGroup of its crime dots (cached)
        let streetDetailCache = {};       // street name -> /street-detail payload (cached)
        let modalStreets = [];            // ACTIVE selection; [0] = first-clicked street (locked in)
        let currentStreetModalName = null;// first-clicked street
        let streetModalMarkers = {};      // incident code -> circle marker
        let streetModalSeq = 0;           // stale-async guard
        const accCollapsed = new Set();   // streets whose crime group is collapsed

        // Every street stays marked but subtle — in its own crime-level colour
        // at low opacity, so the color coding reads even before selecting.
        // Only selected streets render at full strength.
        function mutedStyleFor(color) {
            return { color: color || '#64748b', weight: 1.8, opacity: 0.35 };
        }

        // "21:35" -> "9:35 PM" — every displayed time is 12-hour format
        function fmt12h(t) {
            const m = /^(\d{1,2}):(\d{2})/.exec(String(t || ''));
            if (!m) return String(t || '');
            let h = parseInt(m[1], 10);
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return h + ':' + m[2] + ' ' + ampm;
        }

        // Stable colour per crime category (same golden-angle trick as streets)
        function colorForCategory(cat) {
            let h = 0;
            const s = String(cat || 'Uncategorized');
            for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
            return 'hsl(' + Math.round((h * 137.508) % 360) + ', 72%, 40%)';
        }

        // Build the static context ONCE: barangay boundary + every street as a
        // subtle low-opacity gray line (NO name labels — 131 labels were
        // unreadable; only ACTIVE streets get one). The map itself comes from
        // saCreateMap() below, which mirrors the main crime map's setup.
        function ensureStreetModalBase() {
            if (streetModalMap) return;

            streetModalMap = saCreateMap('streetModalMap');
            streetModalBase = L.layerGroup().addTo(streetModalMap);
            makeStreetLegend().addTo(streetModalMap);   // same color criteria as the main map

            // Barangay San Agustin boundary — faint dashed ring for context
            drawSanAgustinRing();

            // Every street: subtle gray line. Hover behaves EXACTLY like the
            // main mapping — the whole street highlights and the same stats
            // tooltip appears. Clicking toggles the street in/out of the
            // selection.
            Object.entries(saStreetGroupsAll || {}).forEach(function (entry) {
                const name = entry[0], g = entry[1];

                const lines = g.inner.map(function (src) {
                    return L.polyline(src.getLatLngs(), mutedStyleFor(g.color));
                });

                // Same grouped hover as the main map: one featureGroup per
                // street so any segment lights up the entire street
                const grp = L.featureGroup(lines).addTo(streetModalBase);
                const st = saStreetStatsAll[name];
                const sev = severityForCount(st ? st.count : 0);
                const tip = '<div style="font-weight:700;margin-bottom:2px;">' + escStreet(name) + '</div>' +
                    '<div style="margin-bottom:3px;"><span style="display:inline-block;width:28px;height:5px;border-radius:3px;background:' + g.color + ';vertical-align:middle;"></span>' +
                    ' <span style="font-weight:700;color:' + sev.color + ';">' + sev.label + '</span></div>' +
                    (st
                        ? '<div>' + st.count + ' crime' + (st.count === 1 ? '' : 's') +
                          (st.top_category ? ' · mostly ' + escStreet(st.top_category) : '') + '</div>' +
                          (st.peak_hours && st.peak_hours.length
                              ? '<div style="color:#c4b5fd;">Peak hours: ' + st.peak_hours.map(escStreet).join(', ') + '</div>' : '')
                        : '<div>No recorded crimes — cleared</div>') +
                    '<div style="margin-top:3px;color:#93c5fd;font-weight:600;"><i class="fas fa-hand-pointer"></i> Click to select / unselect</div>';
                grp.bindTooltip(tip, { sticky: true, direction: 'top', opacity: 0.95 });

                grp.on('mouseover', function () {
                    if (modalStreets.indexOf(name) === -1) {
                        lines.forEach(function (l) { l.setStyle({ color: g.color, weight: 4, opacity: 0.95 }); });
                    }
                });
                grp.on('mouseout', function () {
                    if (modalStreets.indexOf(name) === -1) {
                        lines.forEach(function (l) { l.setStyle(mutedStyleFor(g.color)); });
                    }
                    grp.closeTooltip();   // never leave it hanging open
                });
                grp.on('click', function () {
                    grp.closeTooltip();
                    toggleModalStreet(name);
                });

                miniStreets[name] = { lines: lines, label: null, color: g.color };
            });
        }

        function styleModalStreet(name, active) {
            const ms = miniStreets[name];
            if (!ms) return;

            ms.lines.forEach(function (l) {
                l.setStyle(active ? { color: ms.color, weight: 5, opacity: 1 } : mutedStyleFor(ms.color));
                if (active && l.bringToFront) l.bringToFront();
            });

            // Name label rides the line ONLY while the street is active
            if (active && !ms.label) {
                let longest = null, longestLen = -1;
                ms.lines.forEach(function (l) {
                    const pts = l.getLatLngs();
                    let len = 0;
                    for (let i = 0; i < pts.length - 1; i++) len += pts[i].distanceTo(pts[i + 1]);
                    if (len > longestLen) { longestLen = len; longest = l; }
                });
                if (longest) {
                    const pts = longest.getLatLngs();
                    ms.label = L.tooltip({ permanent: true, direction: 'top', className: 'street-name-mini snm-active', offset: [0, -6], interactive: false })
                        .setLatLng(pts[Math.floor(pts.length / 2)])
                        .setContent(escStreet(name))
                        .addTo(streetModalBase);
                }
            } else if (!active && ms.label) {
                streetModalBase.removeLayer(ms.label);
                ms.label = null;
            }
        }

        // Fetch one street's crimes (server + client cached) and build its dot layer
        async function ensureStreetCrimes(name) {
            if (!streetDetailCache[name]) {
                const res = await fetch(SA_STREET_DETAIL_URL + '?street=' + encodeURIComponent(name),
                    { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.error) throw new Error(data.message || data.error);
                streetDetailCache[name] = data;
            }
            if (!streetCrimeLayers[name]) {
                const layer = L.layerGroup();
                (streetDetailCache[name].incidents || []).forEach(function (inc) {
                    if (!isFinite(inc.lat) || !isFinite(inc.lng)) return;
                    const c = colorForCategory(inc.category);
                    const marker = L.circleMarker([inc.lat, inc.lng], {
                        radius: 8, color: '#111827', weight: 1.5, fillColor: c, fillOpacity: 0.95
                    });
                    marker.bindPopup(
                        '<div style="min-width:190px;">' +
                            '<div style="font-weight:800;font-size:12px;color:' + c + ';">' + escStreet(inc.category) + '</div>' +
                            '<div style="font-weight:700;font-size:12px;margin:2px 0;">' + escStreet(inc.title || inc.code) + '</div>' +
                            '<div style="font-size:11px;color:#4b5563;">' + escStreet(inc.date || '') + (inc.time ? ' · ' + escStreet(fmt12h(inc.time)) : '') + '</div>' +
                            '<div style="font-size:11px;color:#4b5563;">Status: ' + escStreet(inc.status || '—') + '</div>' +
                            '<div style="font-size:10px;color:#9ca3af;font-family:monospace;margin-top:2px;">' + escStreet(inc.code) + '</div>' +
                        '</div>');
                    marker.addTo(layer);
                    streetModalMarkers[inc.code] = marker;
                });
                streetCrimeLayers[name] = layer;
            }
            return streetDetailCache[name];
        }

        async function activateModalStreet(name) {
            styleModalStreet(name, true);
            const seq = streetModalSeq;
            try {
                await ensureStreetCrimes(name);
            } catch (e) {
                console.error('Street detail failed:', e);
            }
            // Only mount if the street is still selected and the modal not reopened
            if (seq !== streetModalSeq || modalStreets.indexOf(name) === -1) return;
            if (streetCrimeLayers[name] && !streetModalMap.hasLayer(streetCrimeLayers[name])) {
                streetCrimeLayers[name].addTo(streetModalMap);
            }
            renderCrimeAccordions();
            updateStreetModalHeader();
        }

        function deactivateModalStreet(name) {
            styleModalStreet(name, false);
            if (streetCrimeLayers[name] && streetModalMap && streetModalMap.hasLayer(streetCrimeLayers[name])) {
                streetModalMap.removeLayer(streetCrimeLayers[name]);
            }
        }

        // Toggle a street from the mini map or the filter dropdown. The FIRST
        // clicked street is locked in — selecting more never removes it.
        // Deliberately NO re-fit here: the map keeps its current view so
        // selecting streets never yanks the focus away.
        function toggleModalStreet(name) {
            const idx = modalStreets.indexOf(name);
            if (idx === -1) {
                modalStreets.push(name);
                activateModalStreet(name);
            } else {
                if (idx === 0) return;               // the first street stays
                modalStreets.splice(idx, 1);
                deactivateModalStreet(name);
            }
            renderCrimeAccordions();
            updateStreetModalHeader();
            renderStreetFilterList(document.getElementById('streetFilterSearch').value);
        }

        // Auto-zoom: frame every ACTIVE street (snap, no animation)
        function fitModalToActive() {
            if (!streetModalMap) return;
            const bounds = L.latLngBounds([]);
            modalStreets.forEach(function (name) {
                const ms = miniStreets[name];
                if (ms) ms.lines.forEach(function (l) { bounds.extend(l.getBounds()); });
            });
            if (bounds.isValid()) {
                streetModalMap.invalidateSize();
                streetModalMap.fitBounds(bounds.pad(0.2), { maxZoom: 18, animate: false });
            }
        }

        function openStreetModal(name, g) {
            streetModalSeq++;
            currentStreetModalName = name;

            document.getElementById('streetModal').style.display = 'flex';
            ensureStreetModalBase();

            // Reset the selection: mute everything, then activate only the
            // clicked street (more can be added via map clicks or the filter)
            modalStreets.slice().forEach(function (n) { deactivateModalStreet(n); });
            modalStreets = [name];
            accCollapsed.clear();

            document.getElementById('streetModalSwatch').style.background =
                (miniStreets[name] || g || {}).color || '#64748b';
            document.getElementById('streetModalPills').innerHTML =
                '<span class="sm-pill"><i class="fas fa-spinner fa-spin"></i> Loading…</span>';
            document.getElementById('streetIncCount').textContent = '';
            document.getElementById('streetIncidentList').innerHTML =
                '<div style="font-size:12px;color:#9ca3af;padding:12px;"><i class="fas fa-spinner fa-spin mr-1"></i>Loading crimes…</div>';
            document.getElementById('streetFilterSearch').value = '';
            document.getElementById('streetFilterPanel').style.display = 'none';

            updateStreetModalHeader();
            renderStreetFilterList('');
            activateModalStreet(name);

            // Auto-zoom to the clicked street; double pass because the modal
            // was hidden a moment ago and the container needs to settle
            setTimeout(fitModalToActive, 80);
            setTimeout(fitModalToActive, 300);

            resetStreetAiSection();   // AI runs ONLY when Analyze is pressed
        }

        // ---------- header: title, aggregate pills, filter button ----------
        function updateStreetModalHeader() {
            const first = modalStreets[0] || '';
            document.getElementById('streetModalName').textContent =
                modalStreets.length > 1 ? first + ' +' + (modalStreets.length - 1) + ' more' : first;
            document.getElementById('streetFilterBtnLabel').textContent = 'Streets (' + modalStreets.length + ')';
            document.getElementById('streetAiAnalyzeLabel').textContent =
                'Generate suggestions (' + modalStreets.length + ' street' + (modalStreets.length === 1 ? '' : 's') + ')';

            // Aggregate the pills over every active street whose data is in
            let total = 0, unresolved = 0, loaded = 0;
            const cats = {};
            modalStreets.forEach(function (n) {
                const d = streetDetailCache[n];
                if (!d) return;
                loaded++;
                total += (d.summary && d.summary.count) || 0;
                unresolved += (d.summary && d.summary.unresolved) || 0;
                Object.entries((d.summary && d.summary.categories) || {}).forEach(function (e) {
                    cats[e[0]] = (cats[e[0]] || 0) + e[1];
                });
            });
            const top = Object.keys(cats).sort(function (a, b) { return cats[b] - cats[a]; })[0];

            const pills = [];
            if (total === 0 && loaded > 0 && loaded === modalStreets.length) {
                pills.push('<span class="sm-pill" style="background:#dcfce7;color:#15803d;"><i class="fas fa-circle-check"></i>Cleared — no crime</span>');
            } else {
                pills.push('<span class="sm-pill"><i class="fas fa-triangle-exclamation" style="color:#b45309;"></i>' +
                    total + ' crime' + (total === 1 ? '' : 's') + '</span>');
            }
            if (top) {
                pills.push('<span class="sm-pill"><span style="width:9px;height:9px;border-radius:50%;background:' +
                    colorForCategory(top) + ';display:inline-block;"></span>Mostly ' + escStreet(top) + '</span>');
            }
            if (unresolved > 0) {
                pills.push('<span class="sm-pill" style="background:#fef2f2;color:#b91c1c;"><i class="fas fa-folder-open"></i>' +
                    unresolved + ' unresolved</span>');
            }
            if (loaded < modalStreets.length) {
                pills.push('<span class="sm-pill"><i class="fas fa-spinner fa-spin"></i> Loading…</span>');
            }

            // Per-category counts for the selected street(s), so the header
            // reads as a breakdown and not just a total
            const catEntries = Object.keys(cats).sort(function (a, b) { return cats[b] - cats[a]; });
            catEntries.slice(0, 4).forEach(function (name) {
                pills.push('<span class="sm-pill"><span style="width:9px;height:9px;border-radius:50%;background:' +
                    colorForCategory(name) + ';display:inline-block;"></span>' +
                    escStreet(name) + ': ' + cats[name] + '</span>');
            });
            if (catEntries.length > 4) {
                const others = catEntries.slice(4).reduce(function (sum, n) { return sum + cats[n]; }, 0);
                pills.push('<span class="sm-pill"><span style="width:9px;height:9px;border-radius:50%;background:#9ca3af;display:inline-block;"></span>Others: ' + others + '</span>');
            }

            // The host page can add its own badges (peak hours, trend, risk...)
            if (modalStreets.length === 1 && typeof window.saExtraStreetPills === 'function') {
                try {
                    pills.push(window.saExtraStreetPills(modalStreets[0]) || '');
                } catch (e) {
                    console.warn('Extra street pills failed:', e);
                }
            }

            document.getElementById('streetModalPills').innerHTML = pills.join('');
        }

        // ---------- street filter dropdown (top of the modal) ----------
        function toggleStreetFilterPanel() {
            const p = document.getElementById('streetFilterPanel');
            const show = p.style.display !== 'block';
            p.style.display = show ? 'block' : 'none';
            if (show) {
                renderStreetFilterList(document.getElementById('streetFilterSearch').value);
                document.getElementById('streetFilterSearch').focus();
            }
        }
        document.addEventListener('click', function (e) {
            const wrap = document.getElementById('streetFilterWrap');
            const panel = document.getElementById('streetFilterPanel');
            if (wrap && panel && !wrap.contains(e.target)) {
                panel.style.display = 'none';
            }
        });

        function renderStreetFilterList(filter) {
            const q = String(filter || '').toLowerCase().trim();
            const names = Object.keys(saStreetGroupsAll || {}).sort(function (a, b) { return a.localeCompare(b); });
            const rows = [];

            names.forEach(function (n) {
                const first = n === modalStreets[0];
                const checked = modalStreets.indexOf(n) !== -1;
                if (!checked && q && !n.toLowerCase().includes(q)) return;
                const row = '<label style="display:flex;align-items:center;gap:7px;font-size:12px;color:#374151;cursor:pointer;padding:3px 2px;' + (first ? 'font-weight:700;' : '') + '">' +
                    '<input type="checkbox" class="street-filter-cb" value="' + escStreet(n) + '"' +
                        (checked ? ' checked' : '') + (first ? ' disabled' : '') + '>' +
                    '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + escStreet(n) + '">' + escStreet(n) +
                        (first ? ' <span style="color:#7c3aed;font-size:9px;">(clicked street)</span>' : '') + '</span>' +
                '</label>';
                if (checked) rows.unshift(row); else rows.push(row);
            });

            document.getElementById('streetFilterList').innerHTML =
                rows.join('') || '<div style="font-size:11px;color:#9ca3af;padding:4px 0;">No matching streets.</div>';
        }

        document.getElementById('streetFilterList').addEventListener('change', function (e) {
            const cb = e.target;
            if (!cb.classList || !cb.classList.contains('street-filter-cb')) return;
            toggleModalStreet(cb.value);
        });

        function clearModalStreets() {
            modalStreets.slice(1).forEach(function (n) { deactivateModalStreet(n); });
            modalStreets = modalStreets.slice(0, 1);
            renderCrimeAccordions();
            updateStreetModalHeader();
            renderStreetFilterList(document.getElementById('streetFilterSearch').value);
        }

        function closeStreetModal() {
            dockAllSections();   // pull every popped-out section back first
            const m = document.getElementById('streetModal');
            if (m) m.style.display = 'none';
        }

        // ------------------------------------------------------------------
        // Section pop-outs: the map, the crime list and the suggestions can
        // each detach into their own draggable, resizable window — like
        // separate browser windows. On small screens the window goes
        // fullscreen, which also frees the page from the map's touch-trap.
        // ------------------------------------------------------------------
        const POPOUT_META = {
            map:    { section: 'secMap',      title: 'Street map',                    icon: 'fa-map-location-dot' },
            crimes: { section: 'secCrimes',   title: 'Crimes on selected street(s)',  icon: 'fa-list-ul' },
            sugg:   { section: 'streetAiCol', title: 'Prevention suggestions',        icon: 'fa-shield-halved' }
        };
        const activePopouts = {};   // key -> {win, ph, sec}

        function createPopoutWindow(key) {
            const meta = POPOUT_META[key];
            const win = document.createElement('div');
            win.id = 'popout-' + key;
            win.className = 'sm-popout';
            win.innerHTML =
                '<div class="sm-popout-head">' +
                    '<i class="fas ' + meta.icon + '"></i><span>' + meta.title + '</span>' +
                    '<button type="button" class="sm-popout-dock"><i class="fas fa-window-minimize mr-1"></i>Return to modal</button>' +
                '</div>' +
                '<div class="sm-popout-body"></div>';
            document.body.appendChild(win);

            win.querySelector('.sm-popout-dock').addEventListener('click', function () { dockSection(key); });

            // Drag by the header (desktop; fullscreen on mobile so no-op there)
            const head = win.querySelector('.sm-popout-head');
            let dragging = false, sx = 0, sy = 0, ox = 0, oy = 0;
            head.addEventListener('pointerdown', function (e) {
                if (e.target.closest('button')) return;
                dragging = true;
                sx = e.clientX; sy = e.clientY;
                const r = win.getBoundingClientRect();
                ox = r.left; oy = r.top;
                head.setPointerCapture(e.pointerId);
            });
            head.addEventListener('pointermove', function (e) {
                if (!dragging) return;
                win.style.left = Math.max(0, Math.min(window.innerWidth - 90, ox + e.clientX - sx)) + 'px';
                win.style.top = Math.max(0, Math.min(window.innerHeight - 44, oy + e.clientY - sy)) + 'px';
            });
            head.addEventListener('pointerup', function () { dragging = false; });

            // Resizing the window must redraw the map inside it
            if (window.ResizeObserver) {
                new ResizeObserver(function () {
                    if (key === 'map' && streetModalMap && activePopouts[key]) streetModalMap.invalidateSize();
                }).observe(win);
            }

            const off = { map: 36, crimes: 72, sugg: 108 }[key] || 36;
            win.style.left = off + 'px';
            win.style.top = off + 'px';

            return win;
        }

        function popOutSection(key) {
            const meta = POPOUT_META[key];
            const sec = meta ? document.getElementById(meta.section) : null;
            if (!sec || activePopouts[key]) return;

            const win = document.getElementById('popout-' + key) || createPopoutWindow(key);

            // Leave an invisible placeholder so docking puts the section back
            // in exactly the same spot
            const ph = document.createElement('div');
            ph.style.display = 'none';
            sec.parentNode.insertBefore(ph, sec);
            win.querySelector('.sm-popout-body').appendChild(sec);
            win.style.display = 'flex';
            activePopouts[key] = { win: win, ph: ph, sec: sec };

            if (key === 'map' && streetModalMap) setTimeout(function () { streetModalMap.invalidateSize(); }, 60);
        }

        function dockSection(key) {
            const p = activePopouts[key];
            if (!p) return;
            p.ph.parentNode.insertBefore(p.sec, p.ph);
            p.ph.remove();
            p.win.style.display = 'none';
            delete activePopouts[key];
            if (key === 'map' && streetModalMap) setTimeout(function () { streetModalMap.invalidateSize(); }, 60);
        }

        function dockAllSections() {
            Object.keys(activePopouts).forEach(dockSection);
        }

        // Enlarge/shrink the suggestions panel: full-width + bigger text so
        // it stays readable on small screens
        function toggleStreetAiSize() {
            const col = document.getElementById('streetAiCol');
            if (!col) return;
            const big = col.classList.toggle('sm-ai-big');
            const icon = document.querySelector('#streetAiZoomBtn i');
            if (icon) icon.className = 'fas ' + (big ? 'fa-magnifying-glass-minus' : 'fa-magnifying-glass-plus');
        }

        // Expand/compress the modal to fill the screen. The map needs a size
        // recalc after the container changes, or tiles render half-blank.
        function toggleStreetModalFullscreen() {
            const modal = document.getElementById('streetModal');
            const card = modal ? modal.querySelector('.sm-card') : null;
            if (!modal || !card) return;

            const full = card.classList.toggle('sm-full');
            modal.classList.toggle('sm-nopad', full);

            const icon = document.getElementById('streetModalFsIcon');
            if (icon) icon.className = 'fas ' + (full ? 'fa-compress' : 'fa-expand');

            if (streetModalMap) {
                setTimeout(function () { streetModalMap.invalidateSize(); }, 60);
            }
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeStreetModal();
        });

        // ---------- right column: crimes grouped per street (collapsible) ----------
        function crimeCardHtml(inc) {
            const c = colorForCategory(inc.category);
            const meta = [];
            if (inc.victim_count > 0) meta.push(inc.victim_count + ' victim' + (inc.victim_count === 1 ? '' : 's'));
            if (inc.suspect_count > 0) meta.push(inc.suspect_count + ' suspect' + (inc.suspect_count === 1 ? '' : 's'));
            if (inc.weather) meta.push(escStreet(inc.weather));
            return '<div class="sm-inc-card" data-code="' + escStreet(inc.code) + '">' +
                '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">' +
                    '<span style="font-size:10px;font-weight:800;color:#fff;background:' + c + ';padding:2px 8px;border-radius:9999px;">' + escStreet(inc.category) + '</span>' +
                    '<span style="font-size:10px;color:#9ca3af;font-family:monospace;">' + escStreet(inc.code) + '</span>' +
                    '<span style="margin-left:auto;font-size:10px;font-weight:700;color:' +
                        (['solved','resolved','closed','cleared'].indexOf(String(inc.status || '').toLowerCase()) >= 0 ? '#15803d' : '#b45309') + ';">' +
                        escStreet(String(inc.status || '—').toUpperCase()) + '</span>' +
                '</div>' +
                '<div style="font-size:13px;font-weight:700;color:#111;margin-top:6px;">' + escStreet(inc.title || 'Crime') + '</div>' +
                '<div style="font-size:11px;color:#6b7280;margin-top:2px;"><i class="fas fa-calendar mr-1"></i>' +
                    escStreet(inc.date || '—') + (inc.time ? ' · <i class="fas fa-clock mr-1"></i>' + escStreet(fmt12h(inc.time)) : '') + '</div>' +
                (inc.description ? '<div style="font-size:11.5px;color:#4b5563;margin-top:6px;line-height:1.45;">' + escStreet(inc.description) + '</div>' : '') +
                (inc.modus_operandi ? '<div style="font-size:11px;color:#6b7280;margin-top:4px;"><span style="font-weight:700;">Modus:</span> ' + escStreet(inc.modus_operandi) + '</div>' : '') +
                (meta.length ? '<div style="font-size:11px;color:#6b7280;margin-top:4px;">' + meta.join(' · ') + '</div>' : '') +
                (inc.clearance_status ? '<div style="font-size:11px;color:#6b7280;margin-top:2px;"><span style="font-weight:700;">Clearance:</span> ' +
                    escStreet(inc.clearance_status) + (inc.clearance_date ? ' (' + escStreet(inc.clearance_date) + ')' : '') + '</div>' : '') +
                (inc.assigned_officer ? '<div style="font-size:11px;color:#6b7280;margin-top:2px;"><span style="font-weight:700;">Officer:</span> ' + escStreet(inc.assigned_officer) + '</div>' : '') +
                '<div style="font-size:10.5px;color:#9ca3af;margin-top:4px;"><i class="fas fa-location-dot mr-1"></i>' + escStreet(inc.address || '') + '</div>' +
            '</div>';
        }

        // One collapsible group per selected street, newest selection last
        function renderCrimeAccordions() {
            let grand = 0;
            const html = modalStreets.map(function (n) {
                const ms = miniStreets[n] || { color: '#64748b' };
                const d = streetDetailCache[n];
                const collapsed = accCollapsed.has(n);

                let countChip, bodyHtml;
                if (!d) {
                    countChip = '<i class="fas fa-spinner fa-spin" style="color:#9ca3af;"></i>';
                    bodyHtml = '<div style="font-size:12px;color:#9ca3af;">Loading crimes…</div>';
                } else {
                    const incs = d.incidents || [];
                    grand += incs.length;
                    if (incs.length) {
                        countChip = '<span style="font-size:10px;font-weight:800;background:' + ms.color + ';color:#fff;border-radius:9999px;padding:2px 8px;">' + incs.length + '</span>';
                        bodyHtml = incs.map(crimeCardHtml).join('');
                    } else {
                        // A selected street with zero crimes stays active — it
                        // simply reads as CLEARED
                        countChip = '<span style="font-size:10px;font-weight:800;background:#dcfce7;color:#15803d;border-radius:9999px;padding:2px 8px;"><i class="fas fa-circle-check mr-1"></i>CLEARED</span>';
                        bodyHtml = '<div style="display:flex;align-items:center;gap:8px;font-size:12px;color:#15803d;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px 12px;">' +
                            '<i class="fas fa-circle-check"></i><span><span style="font-weight:700;">No crime on this street — cleared.</span> Keep routine patrol coverage to sustain it.</span></div>';
                    }
                }

                return '<div class="sm-acc">' +
                    '<button type="button" class="sm-acc-head" data-street="' + escStreet(n) + '">' +
                        '<span style="width:14px;height:5px;border-radius:3px;background:' + ms.color + ';flex-shrink:0;"></span>' +
                        '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escStreet(n) + '</span>' +
                        countChip +
                        '<i class="fas fa-chevron-' + (collapsed ? 'down' : 'up') + '" style="font-size:10px;color:#9ca3af;"></i>' +
                    '</button>' +
                    (collapsed ? '' : '<div class="sm-acc-body">' + bodyHtml + '</div>') +
                '</div>';
            }).join('');

            document.getElementById('streetIncCount').textContent = '(' + grand + ')';
            document.getElementById('streetIncidentList').innerHTML =
                html || '<div style="font-size:12px;color:#9ca3af;padding:12px;">No street selected.</div>';
        }

        // One delegated listener covers accordion headers AND crime cards
        document.getElementById('streetIncidentList').addEventListener('click', function (e) {
            const head = e.target.closest('.sm-acc-head');
            if (head) {
                const n = head.dataset.street;
                if (accCollapsed.has(n)) accCollapsed.delete(n); else accCollapsed.add(n);
                renderCrimeAccordions();
                return;
            }
            const card = e.target.closest('.sm-inc-card');
            if (card) {
                const marker = streetModalMarkers[card.dataset.code];
                if (marker) {
                    streetModalMap.setView(marker.getLatLng(), Math.max(streetModalMap.getZoom(), 18));
                    marker.openPopup();
                }
            }
        });

        // ---------- street AI: manual, analyzes the modal's selected streets ----------
        let latestStreetAi = null;         // last successful result, for Save
        let streetAiSeq = 0;               // stale-response guard

        // Language of the suggestions (shared with pattern detection via localStorage)
        // Seeded from the user's Settings, then remembered per browser once
        // they use the toggle in the panel.
        const SA_LANG_PREF = @json(\App\Models\UserPreference::current()['suggestion_language']);
        let saLang = localStorage.getItem('sa_sugg_lang') || SA_LANG_PREF;
        saLang = saLang === 'tl' ? 'tl' : 'en';
        function isTl() { return saLang === 'tl'; }
        function applyStreetLangToggle() {
            document.querySelectorAll('#streetLangToggle [data-lang]').forEach(function (b) {
                b.style.background = b.dataset.lang === saLang ? '#7c3aed' : '#fff';
                b.style.color = b.dataset.lang === saLang ? '#fff' : '#6d28d9';
            });
        }
        document.querySelectorAll('#streetLangToggle [data-lang]').forEach(function (b) {
            b.addEventListener('click', function () {
                if (saLang === b.dataset.lang) return;
                saLang = b.dataset.lang;
                localStorage.setItem('sa_sugg_lang', saLang);
                applyStreetLangToggle();
                renderStreetAiPanel();
            });
        });
        applyStreetLangToggle();

        // Fresh AI state each time the modal opens: nothing analyzed yet
        function resetStreetAiSection() {
            latestStreetAi = null;
            streetAiSeq++;
            destroyStreetAiCharts();
            const dash = document.getElementById('streetAiDash');
            if (dash) dash.innerHTML = '';

            document.getElementById('streetAiPlaceholder').style.display = 'block';
            document.getElementById('streetAiLoading').style.display = 'none';
            document.getElementById('streetAiError').style.display = 'none';
            document.getElementById('streetAiResults').style.display = 'none';
            document.getElementById('streetAiRisk').style.display = 'none';
            const save = document.getElementById('streetAiSaveBtn');
            save.style.display = 'none';
            save.disabled = false;
            save.style.background = '#7c3aed';
            save.innerHTML = '<i class="fas fa-floppy-disk mr-1"></i>Save';
        }

        async function analyzeStreetAi() {
            const seq = ++streetAiSeq;
            const loading = document.getElementById('streetAiLoading');
            const errBox = document.getElementById('streetAiError');
            const results = document.getElementById('streetAiResults');
            const risk = document.getElementById('streetAiRisk');
            const analyzeBtn = document.getElementById('streetAiAnalyzeBtn');

            // Analyze exactly what is selected in the modal (map + filter)
            const picked = modalStreets.slice(0, 10);
            if (!picked.length && currentStreetModalName) picked.push(currentStreetModalName);
            if (!picked.length) return;

            document.getElementById('streetAiPlaceholder').style.display = 'none';
            document.getElementById('streetAiSaveBtn').style.display = 'none';
            loading.style.display = 'block';
            errBox.style.display = 'none';
            results.style.display = 'none';
            risk.style.display = 'none';
            analyzeBtn.disabled = true;
            analyzeBtn.style.opacity = '0.6';

            try {
                const params = new URLSearchParams();
                picked.forEach(function (s) { params.append('streets[]', s); });
                const res = await fetch(SA_STREET_AI_URL + '?' + params,
                    { headers: { 'Accept': 'application/json' } });

                // Gateway timeouts / proxy errors answer with an HTML page, not
                // JSON — surface a readable error instead of a parse crash
                let data;
                try {
                    data = JSON.parse(await res.text());
                } catch (parseErr) {
                    throw new Error(res.status === 504
                        ? 'The server took too long to respond. Press Retry.'
                        : 'Server error (HTTP ' + res.status + '). Press Retry.');
                }
                if (!data.success) throw new Error(data.error || (res.status === 429
                    ? 'Too many requests — wait a minute and press Retry.' : 'HTTP ' + res.status));
                if (seq !== streetAiSeq) return;   // user switched streets meanwhile

                latestStreetAi = data;

                // Show the panel first: the charts size themselves from the
                // laid-out canvas, which a display:none container can't give
                loading.style.display = 'none';
                results.style.display = 'block';
                renderStreetAiPanel();
                document.getElementById('streetAiSaveBtn').style.display = 'inline-block';
            } catch (e) {
                console.error('Street AI failed:', e);
                if (seq === streetAiSeq) {
                    loading.style.display = 'none';
                    document.getElementById('streetAiErrorMsg').textContent = e.message;
                    errBox.style.display = 'block';
                }
            } finally {
                analyzeBtn.disabled = false;
                analyzeBtn.style.opacity = '1';
            }
        }

        // ------------------------------------------------------------------
        // Report charts. Every Chart.js instance the report creates is kept
        // here so a re-render (language toggle, new analysis) can destroy
        // them before the canvases are replaced.
        // ------------------------------------------------------------------
        let streetAiCharts = [];
        function destroyStreetAiCharts() {
            streetAiCharts.forEach(function (c) { try { c.destroy(); } catch (e) {} });
            streetAiCharts = [];
        }
        function saChart(id, cfg) {
            if (typeof Chart === 'undefined') return null;
            const el = document.getElementById(id);
            if (!el) return null;
            const c = new Chart(el, cfg);
            streetAiCharts.push(c);
            return c;
        }
        const SA_HOUR_LABELS = Array.from({ length: 24 }, function (_, h) {
            return (h % 12 || 12) + (h < 12 ? 'AM' : 'PM');
        });
        function saDayLabels() {
            return isTl() ? ['Lun', 'Mar', 'Miy', 'Huw', 'Biy', 'Sab', 'Lin'] : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        }
        const SA_RISK_COLOR = { high: '#dc2626', medium: '#f59e0b', low: '#16a34a' };
        function saBaseOpts(extra) {
            return Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 350 },
                plugins: { legend: { display: false } }
            }, extra || {});
        }
        function saAxis(opts) {
            return Object.assign({ grid: { display: false }, ticks: { font: { size: 9 }, color: '#6b7280' } }, opts || {});
        }
        function saYAxis(opts) {
            return Object.assign({ beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { precision: 0, font: { size: 9 }, color: '#6b7280' } }, opts || {});
        }

        // KPI tiles + the chart grid at the top of the report, aggregated
        // across every analyzed street. Numbers come from the section stats
        // the rules engine returns; labels follow the language toggle.
        function renderStreetAiDashboard(a, secs) {
            const dash = document.getElementById('streetAiDash');
            if (!dash) return;
            const T = isTl();
            const list = (secs || []).filter(function (x) { return x && typeof x === 'object'; });
            if (!list.length) { dash.innerHTML = ''; return; }

            // ---- aggregate ----
            let total = 0, resolved = 0, unresolved = 0, night = 0, day = 0, recent = 0, earlier = 0;
            const hourly = new Array(24).fill(0);
            const weekday = new Array(7).fill(0);
            let monthLabels = null;
            const monthly = new Array(12).fill(0);
            const cats = {};
            let hasHour = false;
            list.forEach(function (sec) {
                total += Number(sec.total) || 0;
                const st = sec.stats || {};
                resolved += Number(st.resolved) || 0;
                unresolved += Number(st.unresolved) || 0;
                night += Number(st.night) || 0;
                day += Number(st.day) || 0;
                recent += Number(st.recent) || 0;
                earlier += Number(st.earlier) || 0;
                (st.hourly || []).forEach(function (v, i) { if (i < 24) { hourly[i] += Number(v) || 0; if (v) hasHour = true; } });
                (st.weekday || []).forEach(function (v, i) { if (i < 7) weekday[i] += Number(v) || 0; });
                if (st.monthly && st.monthly.values) {
                    if (!monthLabels) monthLabels = st.monthly.labels || [];
                    st.monthly.values.forEach(function (v, i) { if (i < 12) monthly[i] += Number(v) || 0; });
                }
                (sec.categories || []).forEach(function (cb) {
                    const key = cb.category || cb.category_label || '?';
                    if (!cats[key]) cats[key] = { label: cb.category_label || cb.category, count: 0, unresolved: 0 };
                    cats[key].count += Number(cb.count) || 0;
                    cats[key].unresolved += Number(cb.unresolved) || 0;
                });
            });
            const catList = Object.keys(cats).map(function (k) { return Object.assign({ key: k }, cats[k]); })
                .sort(function (x, y) { return y.count - x.count; });
            const allSugg = (a.suggestions && a.suggestions.length) ? a.suggestions
                : list.reduce(function (acc, sec) {
                    return acc.concat((sec.suggestions || []).map(function (sg) { return Object.assign({ street: sec.street }, sg); }));
                }, []);
            const highPrio = allSugg.filter(function (sg) { return String(sg.priority || '').toLowerCase() === 'high'; }).length;
            const withTime = night + day;
            const nightPct = withTime ? Math.round(night / withTime * 100) : null;
            const unresolvedPct = total ? Math.round(unresolved / total * 100) : 0;
            const trendPct = earlier > 0 ? Math.round((recent - earlier) / earlier * 100) : (recent > 0 ? 100 : 0);
            const trendUp = recent > earlier;
            const trendFlat = recent === earlier;
            const trendColor = trendFlat ? '#6b7280' : (trendUp ? '#b91c1c' : '#15803d');
            const trendIcon = trendFlat ? 'fa-arrows-left-right' : (trendUp ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down');
            const peakHourIdx = hasHour ? hourly.indexOf(Math.max.apply(null, hourly)) : -1;
            const peakDayIdx = Math.max.apply(null, weekday) > 0 ? weekday.indexOf(Math.max.apply(null, weekday)) : -1;
            const multi = list.length > 1;

            const kpi = function (label, value, sub, color) {
                return '<div class="sa-kpi"><div class="sa-kpi-l">' + label + '</div>' +
                    '<div class="sa-kpi-v"' + (color ? ' style="color:' + color + '"' : '') + '>' + value + '</div>' +
                    (sub ? '<div class="sa-kpi-s">' + sub + '</div>' : '') + '</div>';
            };
            const card = function (id, title, icon, note, cls) {
                return '<div class="sa-dash-card' + (cls ? ' ' + cls : '') + '"><div class="sa-dash-t"><i class="fas ' + icon + '"></i>' + title + '</div>' +
                    '<div class="sa-dash-c' + (cls === 'sa-wide' ? ' sa-tall' : '') + '"><canvas id="' + id + '"></canvas></div>' +
                    (note ? '<div class="sa-dash-note">' + note + '</div>' : '') + '</div>';
            };

            let html = '<div class="sa-kpis">' +
                kpi(T ? 'Kabuuang krimen' : 'Total crimes', total, T ? list.length + ' kalye' : list.length + ' street' + (multi ? 's' : '')) +
                kpi(T ? 'Di pa resolbado' : 'Unresolved', unresolved, unresolvedPct + '%' + (T ? ' ng kaso' : ' of cases'), unresolved ? '#b91c1c' : '#15803d') +
                kpi(T ? 'Sa gabi' : 'Night-time', nightPct === null ? '—' : nightPct + '%', T ? '6PM – 6AM' : '6 PM – 6 AM', '#4338ca') +
                kpi('Trend', '<i class="fas ' + trendIcon + '" style="font-size:14px;margin-right:4px;"></i>' + (trendFlat ? (T ? 'steady' : 'flat') : (trendPct > 0 ? '+' : '') + trendPct + '%'),
                    T ? recent + ' kamakailan vs ' + earlier + ' nauna' : recent + ' recent vs ' + earlier + ' earlier', trendColor) +
                kpi(T ? 'Mga aksyon' : 'Actions', allSugg.length, highPrio ? highPrio + ' high priority' : (T ? 'walang high priority' : 'no high priority'), highPrio ? '#7c3aed' : undefined) +
            '</div>';

            html += '<div class="sa-dash-grid">';
            if (catList.length) html += card('saDashCats', T ? 'Uri ng krimen' : 'Crime mix', 'fa-chart-pie',
                (T ? 'Pinaka-madalas: ' : 'Most common: ') + escStreet(catList[0].label) + ' (' + catList[0].count + ')');
            if (multi) html += card('saDashStreets', T ? 'Krimen bawat kalye' : 'Crimes per street', 'fa-road',
                T ? 'Kulay = antas ng panganib' : 'Bar colour = risk level');
            else html += card('saDashStatus', T ? 'Estado ng kaso' : 'Case status', 'fa-folder-open',
                T ? resolved + ' resolbado · ' + unresolved + ' di pa' : resolved + ' resolved · ' + unresolved + ' open');
            if (hasHour) html += card('saDashHours', T ? 'Oras ng araw' : 'Time of day', 'fa-clock',
                peakHourIdx >= 0 ? 'Peak: ' + SA_HOUR_LABELS[peakHourIdx] + (T ? ' · madilim = gabi' : ' · dark bars = night') : '');
            html += card('saDashDays', T ? 'Araw ng linggo' : 'Day of week', 'fa-calendar-week',
                peakDayIdx >= 0 ? (T ? 'Pinaka-abala: ' : 'Busiest: ') + saDayLabels()[peakDayIdx] : '');
            if (monthLabels) html += card('saDashMonths', T ? '12-buwang trend' : '12-month trend', 'fa-chart-line',
                T ? 'Krimen kada buwan sa napiling kalye' : 'Crimes per month on the selected street' + (multi ? 's' : ''), 'sa-wide');
            if (multi) html += card('saDashStatus', T ? 'Estado ng kaso bawat kalye' : 'Case status per street', 'fa-folder-open',
                T ? 'Berde = resolbado · pula = di pa' : 'Green = resolved · red = unresolved', 'sa-wide');
            const impactRows = allSugg.map(function (sg) {
                const pct = Number((sg.expected_impact || {}).estimated_change_percent);
                return { label: sg.action || '', street: sg.street || '', pct: isFinite(pct) ? Math.abs(pct) : 0, priority: String(sg.priority || 'low').toLowerCase() };
            }).filter(function (r) { return r.pct > 0; }).sort(function (x, y) { return y.pct - x.pct; }).slice(0, 8);
            if (impactRows.length) html += card('saDashImpact', T ? 'Inaasahang pagbaba kada aksyon' : 'Expected reduction per action', 'fa-arrow-trend-down',
                T ? 'Tinatayang % na pagbaba ng krimen kung ipapatupad · pula = high priority' : 'Estimated % fewer crimes if implemented · red = high priority', 'sa-wide');
            html += '</div>';
            dash.innerHTML = html;

            // ---- draw ----
            const PRIO_COLOR = { high: '#dc2626', medium: '#f59e0b', low: '#9ca3af' };
            if (catList.length) saChart('saDashCats', {
                type: 'doughnut',
                data: { labels: catList.map(function (c) { return c.label; }),
                        datasets: [{ data: catList.map(function (c) { return c.count; }),
                                     backgroundColor: catList.map(function (c) { return colorForCategory(c.key); }),
                                     borderColor: '#fff', borderWidth: 2 }] },
                options: saBaseOpts({ cutout: '58%', plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 9, font: { size: 9.5 }, padding: 6 } },
                    tooltip: { callbacks: { label: function (it) { return ' ' + it.label + ': ' + it.parsed + ' (' + Math.round(it.parsed / Math.max(1, total) * 100) + '%)'; } } } } })
            });
            if (multi) saChart('saDashStreets', {
                type: 'bar',
                data: { labels: list.map(function (sec) { return sec.street; }),
                        datasets: [{ data: list.map(function (sec) { return sec.total || 0; }),
                                     backgroundColor: list.map(function (sec) { return SA_RISK_COLOR[String(sec.risk_level || 'low').toLowerCase()] || '#9ca3af'; }),
                                     borderRadius: 4, maxBarThickness: 18 }] },
                options: saBaseOpts({ indexAxis: 'y', scales: { x: saYAxis(), y: saAxis({ ticks: { font: { size: 9 }, color: '#374151', autoSkip: false } }) } })
            });
            saChart('saDashStatus', multi ? {
                type: 'bar',
                data: { labels: list.map(function (sec) { return sec.street; }),
                        datasets: [
                            { label: T ? 'Resolbado' : 'Resolved', data: list.map(function (sec) { return (sec.stats || {}).resolved || 0; }), backgroundColor: '#22c55e', borderRadius: 3, maxBarThickness: 16 },
                            { label: T ? 'Di pa resolbado' : 'Unresolved', data: list.map(function (sec) { return (sec.stats || {}).unresolved || 0; }), backgroundColor: '#ef4444', borderRadius: 3, maxBarThickness: 16 }
                        ] },
                options: saBaseOpts({ indexAxis: 'y', plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 9, font: { size: 9.5 } } } },
                    scales: { x: saYAxis({ stacked: true }), y: saAxis({ stacked: true, ticks: { font: { size: 9 }, color: '#374151', autoSkip: false } }) } })
            } : {
                type: 'doughnut',
                data: { labels: [T ? 'Resolbado' : 'Resolved', T ? 'Di pa resolbado' : 'Unresolved'],
                        datasets: [{ data: [resolved, unresolved], backgroundColor: ['#22c55e', '#ef4444'], borderColor: '#fff', borderWidth: 2 }] },
                options: saBaseOpts({ cutout: '60%', plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 9, font: { size: 9.5 } } } } })
            });
            if (hasHour) saChart('saDashHours', {
                type: 'bar',
                data: { labels: SA_HOUR_LABELS,
                        datasets: [{ data: hourly, backgroundColor: hourly.map(function (_, h) { return (h >= 18 || h < 6) ? '#4338ca' : '#93c5fd'; }), borderRadius: 2 }] },
                options: saBaseOpts({ scales: { x: saAxis({ ticks: { font: { size: 8 }, color: '#6b7280', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } }), y: saYAxis() },
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (it) { return ' ' + it.parsed.y + (T ? ' krimen' : ' crime(s)'); } } } } })
            });
            saChart('saDashDays', {
                type: 'bar',
                data: { labels: saDayLabels(),
                        datasets: [{ data: weekday, backgroundColor: weekday.map(function (v, i) { return i === peakDayIdx ? '#7c3aed' : '#c4b5fd'; }), borderRadius: 4, maxBarThickness: 26 }] },
                options: saBaseOpts({ scales: { x: saAxis(), y: saYAxis() } })
            });
            if (monthLabels) saChart('saDashMonths', {
                type: 'line',
                data: { labels: monthLabels,
                        datasets: [{ data: monthly, borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,0.12)', fill: true, tension: 0.35,
                                     pointRadius: 3, pointBackgroundColor: '#7c3aed', pointBorderColor: '#fff', pointBorderWidth: 1.5 }] },
                options: saBaseOpts({ scales: { x: saAxis({ ticks: { font: { size: 9 }, color: '#6b7280', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } }), y: saYAxis() } })
            });
            if (impactRows.length) saChart('saDashImpact', {
                type: 'bar',
                data: { labels: impactRows.map(function (r) { return r.label.length > 42 ? r.label.slice(0, 41) + '…' : r.label; }),
                        datasets: [{ data: impactRows.map(function (r) { return r.pct; }),
                                     backgroundColor: impactRows.map(function (r) { return PRIO_COLOR[r.priority] || PRIO_COLOR.low; }), borderRadius: 4, maxBarThickness: 14 }] },
                options: saBaseOpts({ indexAxis: 'y',
                    scales: { x: saYAxis({ ticks: { precision: 0, font: { size: 9 }, callback: function (v) { return '-' + v + '%'; } } }),
                              y: saAxis({ ticks: { font: { size: 9 }, color: '#374151', autoSkip: false } }) },
                    plugins: { legend: { display: false }, tooltip: { callbacks: {
                        title: function (its) { const r = impactRows[its[0].dataIndex]; return (multi && r.street ? r.street + ' — ' : '') + r.label; },
                        label: function (it) { return ' ~' + it.parsed.x + '% ' + (T ? 'mas kaunting krimen' : 'fewer crimes'); } } } } })
            });
        }

        // Per-street mini charts (hourly bars) drawn after the street sections
        // are in the DOM. Only for multi-street reports: with one street the
        // dashboard above already shows the same thing at full size.
        function renderStreetAiMiniCharts(secs) {
            (secs || []).forEach(function (sec, idx) {
                const st = sec.stats || {};
                if (!st.hourly || !document.getElementById('saSecHour' + idx)) return;
                saChart('saSecHour' + idx, {
                    type: 'bar',
                    data: { labels: SA_HOUR_LABELS,
                            datasets: [{ data: st.hourly, backgroundColor: st.hourly.map(function (_, h) { return (h >= 18 || h < 6) ? '#4338ca' : '#93c5fd'; }), borderRadius: 1 }] },
                    options: saBaseOpts({ animation: false,
                        scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { title: function (its) { return its[0].label; }, label: function (it) { return ' ' + it.parsed.y; } } } } })
                });
            });
        }

        // Renders latestStreetAi into the modal panel — separated from the
        // fetch so the language toggle can re-render without a new request
        function renderStreetAiPanel() {
                if (!latestStreetAi) return;
                destroyStreetAiCharts();
                const risk = document.getElementById('streetAiRisk');
                const a = latestStreetAi.analysis || {};

                const RISK_CHIP = {
                    high:   'background:#fee2e2;color:#b91c1c;',
                    medium: 'background:#fef3c7;color:#b45309;',
                    low:    'background:#dcfce7;color:#15803d;'
                };
                const lvl = String(a.risk_level || 'low').toLowerCase();
                risk.style.cssText = 'display:inline-block;font-size:10px;font-weight:800;padding:2px 8px;border-radius:9999px;' +
                    (RISK_CHIP[lvl] || 'background:#f3f4f6;color:#374151;');
                risk.textContent = lvl.toUpperCase() + ' RISK';

                document.getElementById('streetAiSummary').textContent =
                    (isTl() && a.summary_tl) ? a.summary_tl : (a.summary || '');

                const prioStyle = {
                    high:   'background:#fee2e2;color:#b91c1c;',
                    medium: 'background:#fef3c7;color:#b45309;',
                    low:    'background:#f3f4f6;color:#374151;'
                };
                // "Basis — recorded crimes" box: what actually happened, how,
                // and how bad — grounds every suggestion in the real cases
                const SEV_CHIP = {
                    critical: 'background:#7f1d1d;color:#fff;',
                    high:     'background:#fee2e2;color:#b91c1c;',
                    moderate: 'background:#fef3c7;color:#b45309;',
                    low:      'background:#f3f4f6;color:#4b5563;'
                };
                const sevChip = function (sev) {
                    const s2 = String(sev || '').toLowerCase();
                    if (!SEV_CHIP[s2]) return '';
                    return '<span style="font-size:9.5px;font-weight:800;padding:2px 7px;border-radius:9999px;' + SEV_CHIP[s2] + '">' + s2.toUpperCase() + '</span>';
                };
                const evidenceBlock = function (ev) {
                    if (!ev || !ev.cases) return '';
                    const T = isTl();
                    const row = function (icon, html) {
                        return '<div style="display:flex;gap:6px;font-size:11px;color:#78350f;line-height:1.5;margin-top:2px;">' +
                            '<i class="fas ' + icon + '" style="color:#d97706;margin-top:2px;flex-shrink:0;"></i><span>' + html + '</span></div>';
                    };
                    return '<div style="margin-top:6px;padding:8px 10px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;">' +
                        '<div style="font-size:9.5px;font-weight:800;color:#92400e;text-transform:uppercase;margin-bottom:3px;"><i class="fas fa-magnifying-glass mr-1"></i>' + (T ? 'Basehan — mga naitalang krimen' : 'Basis — recorded crimes') + '</div>' +
                        (ev.modus && ev.modus.length ? row('fa-user-ninja', (T ? 'Paano ginawa: ' : 'How they were committed: ') + ev.modus.map(escStreet).join('; ') + '.') : '') +
                        caseLog(ev.cases_list) +
                    '</div>';
                };
                // Every recorded case with its date, day and exact time
                const caseLog = function (cases) {
                    if (!cases || !cases.length) return '';
                    const T = isTl();
                    const MAX = 8;
                    const rows = cases.slice(0, MAX).map(function (c) {
                        return '<div style="display:flex;flex-wrap:wrap;align-items:baseline;column-gap:7px;font-size:10.5px;color:#78350f;border-top:1px dashed #fde68a;padding:3px 0;">' +
                            '<span style="font-weight:800;">' + escStreet(c.date || '') + '</span>' +
                            (c.day ? '<span>(' + escStreet(c.day) + ')</span>' : '') +
                            (c.time ? '<span style="font-weight:700;color:#b45309;"><i class="fas fa-clock" style="margin-right:2px;"></i>' + escStreet(c.time) + '</span>' : '') +
                            (c.modus ? '<span style="color:#92400e;">— ' + escStreet(c.modus) + '</span>' : '') +
                            '<span style="margin-left:auto;font-weight:800;color:' + (c.resolved ? '#15803d' : '#b91c1c') + ';">' +
                                (c.resolved ? (T ? 'RESOLBADO' : 'RESOLVED') : (T ? 'DI PA RESOLBADO' : 'UNRESOLVED')) + '</span>' +
                        '</div>';
                    }).join('');
                    return '<div style="margin-top:5px;">' +
                        '<div style="font-size:9.5px;font-weight:800;color:#92400e;text-transform:uppercase;"><i class="fas fa-list-ul mr-1"></i>' + (T ? 'Talaan ng kaso (petsa · araw · oras)' : 'Case log (date · day · time)') + '</div>' +
                        rows +
                        (cases.length > MAX ? '<div style="font-size:10px;color:#b45309;padding-top:3px;">' + (T
                            ? '+' + (cases.length - MAX) + ' pa — pindutin ang "Tingnan ang mga krimen" sa itaas para sa buong listahan.'
                            : '+' + (cases.length - MAX) + ' more — press "View crimes" above for the full list.') + '</div>' : '') +
                    '</div>';
                };

                const suggCard = function (s, showStreet) {
                    const imp = s.expected_impact || {};
                    const d = s.details || {};
                    const ev = d.evidence || {};
                    const pct = Number(imp.estimated_change_percent);
                    const pr = String(s.priority || 'low').toLowerCase();
                    const T = isTl();
                    const chip = function (icon, html, style) {
                        return '<span class="sa-chip"' + (style ? ' style="' + style + '"' : '') + '><i class="fas ' + icon + '"></i>' + html + '</span>';
                    };

                    // What you see at a glance: the action, its priority, and
                    // the facts as chips (no sentences)
                    let chips = '';
                    if (showStreet && s.street) chips += chip('fa-road', escStreet(s.street), 'background:#fff7ed;color:#b45309;');
                    if (s.time_window) chips += chip('fa-clock', escStreet(s.time_window), 'background:#f5f3ff;color:#6d28d9;');
                    if (ev.cases) chips += chip('fa-hashtag', ev.cases + (T ? ' kaso' : ' case' + (ev.cases === 1 ? '' : 's')) + (ev.share ? ' · ' + ev.share + '%' : ''));
                    if (typeof ev.unresolved === 'number') chips += chip('fa-folder-open', ev.unresolved + (T ? ' bukas' : ' open'),
                        ev.unresolved > 0 ? 'background:#fee2e2;color:#b91c1c;' : 'background:#dcfce7;color:#15803d;');
                    if (ev.busiest_day) chips += chip('fa-calendar-day', escStreet(ev.busiest_day));
                    if (ev.latest) chips += chip('fa-calendar-check', escStreet(ev.latest));
                    if (d.lead) chips += chip('fa-user-shield', escStreet(d.lead));
                    if (d.timeline) chips += chip('fa-hourglass-half', escStreet(d.timeline));

                    const gauge = isFinite(pct) ? '<div class="sa-impact">' +
                            '<span style="font-size:10.5px;font-weight:700;color:#374151;white-space:nowrap;"><i class="fas ' + (pct < 0 ? 'fa-arrow-trend-down' : 'fa-arrows-left-right') + ' mr-1" style="color:' + (pct < 0 ? '#15803d' : '#6b7280') + ';"></i>' + (T ? 'Kapag ipinatupad' : 'If implemented') + '</span>' +
                            '<div class="sa-impact-bar"><span style="width:' + Math.min(100, Math.abs(pct)) + '%;"></span></div>' +
                            '<span style="font-size:12px;font-weight:800;color:' + (pct < 0 ? '#15803d' : '#374151') + ';white-space:nowrap;">' + (pct < 0 ? '−' + Math.abs(pct) + '%' : (T ? 'steady' : 'stable')) + '</span>' +
                        '</div>' : '';

                    // Everything written out lives behind the Details toggle
                    let details = '';
                    if (s.rationale) details += '<div style="font-size:11.5px;color:#4b5563;line-height:1.45;">' + escStreet(s.rationale) + '</div>';
                    if (imp.explanation) details += '<div style="font-size:11px;color:#6b7280;margin-top:3px;">' + escStreet(imp.explanation) + '</div>';
                    details += evidenceBlock(ev);
                    if (d.coverage) details += '<div style="font-size:11px;color:#374151;margin-top:5px;"><i class="fas fa-location-crosshairs mr-1" style="color:#7c3aed;"></i>' + escStreet(d.coverage) + '</div>';
                    if (d.steps && d.steps.length) details += '<div style="margin-top:6px;padding:8px 10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">' +
                        '<div style="font-size:9.5px;font-weight:800;color:#6b7280;text-transform:uppercase;margin-bottom:3px;">' + (T ? 'Paano ipapatupad' : 'How to implement') + '</div>' +
                        d.steps.map(function (st, i2) {
                            return '<div style="display:flex;gap:6px;font-size:11px;color:#4b5563;line-height:1.5;margin-top:2px;">' +
                                '<span style="flex-shrink:0;font-weight:800;color:#7c3aed;">' + (i2 + 1) + '.</span><span>' + escStreet(st) + '</span></div>';
                        }).join('') + '</div>';
                    if (d.resources) details += '<div style="font-size:11px;color:#4b5563;margin-top:5px;"><i class="fas fa-toolbox mr-1" style="color:#7c3aed;"></i><span style="font-weight:700;color:#374151;">' + (T ? 'Kailangan:' : 'Needs:') + '</span> ' + escStreet(d.resources) + '</div>';
                    if (d.tips && d.tips.length) details += '<div style="margin-top:6px;padding:8px 10px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">' +
                        '<div style="font-size:9.5px;font-weight:800;color:#0369a1;text-transform:uppercase;margin-bottom:3px;"><i class="fas fa-people-roof mr-1"></i>' + (T ? 'Mga tip para sa mga residente' : 'Tips for residents') + '</div>' +
                        d.tips.map(function (tp) {
                            return '<div style="display:flex;gap:6px;font-size:11px;color:#0c4a6e;line-height:1.5;margin-top:2px;">' +
                                '<i class="fas fa-check" style="color:#0284c7;margin-top:2px;flex-shrink:0;"></i><span>' + escStreet(tp) + '</span></div>';
                        }).join('') + '</div>';
                    if (d.kpi) details += '<div style="font-size:11px;color:#15803d;font-weight:600;margin-top:5px;"><i class="fas fa-bullseye mr-1"></i>' + escStreet(d.kpi) + '</div>';

                    return '<div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;">' +
                        '<div class="sa-sugg-head">' +
                            '<div class="sa-sugg-title"><i class="fas fa-shield-halved"></i>' + escStreet(s.action) + '</div>' +
                            '<span class="sa-prio" style="' + (prioStyle[pr] || prioStyle.low) + '">' + pr.toUpperCase() + '</span>' +
                        '</div>' +
                        (chips ? '<div class="sa-chips">' + chips + '</div>' : '') +
                        gauge +
                        (details ? '<button type="button" class="sa-details-toggle"><i class="fas fa-chevron-down"></i>' + (T ? 'Detalye, hakbang at tips' : 'Details, steps & tips') + '</button>' +
                            '<div class="sa-details">' + details + '</div>' : '') +
                    '</div>';
                };

                // Rule-engine responses carry one SECTION PER STREET; render
                // each street separately with its own risk chip and summary
                // Street facts as chips: peak hours, busiest day, trend, open
                // cases. Replaces the one-paragraph street summary.
                const secChips = function (sec) {
                    const st = sec.stats || {};
                    const T = isTl();
                    const chip = function (icon, html, style) {
                        return '<span class="sa-chip"' + (style ? ' style="' + style + '"' : '') + '><i class="fas ' + icon + '"></i>' + html + '</span>';
                    };
                    let out = '';
                    if (sec.peak_hours && sec.peak_hours.length) out += chip('fa-clock', escStreet(sec.peak_hours.join(', ')), 'background:#f5f3ff;color:#6d28d9;');
                    if (st.weekday && st.weekday.some(function (v) { return v > 0; })) {
                        const idx = st.weekday.indexOf(Math.max.apply(null, st.weekday));
                        out += chip('fa-calendar-day', saDayLabels()[idx]);
                    }
                    if (typeof st.recent === 'number' && typeof st.earlier === 'number' && (st.recent || st.earlier)) {
                        const up = st.recent > st.earlier, flat = st.recent === st.earlier;
                        out += chip(flat ? 'fa-arrows-left-right' : (up ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'),
                            (T ? (flat ? 'steady' : (up ? 'tumataas' : 'bumababa')) : (flat ? 'steady' : (up ? 'rising' : 'falling'))) + ' · ' + st.recent + ' vs ' + st.earlier,
                            flat ? '' : (up ? 'background:#fee2e2;color:#b91c1c;' : 'background:#dcfce7;color:#15803d;'));
                    }
                    if (typeof st.unresolved === 'number') out += chip('fa-folder-open', st.unresolved + (T ? ' bukas' : ' open'),
                        st.unresolved > 0 ? 'background:#fee2e2;color:#b91c1c;' : 'background:#dcfce7;color:#15803d;');
                    if (st.night + st.day > 0) out += chip('fa-moon', Math.round(st.night / (st.night + st.day) * 100) + '% ' + (T ? 'gabi' : 'night'), 'background:#e0e7ff;color:#3730a3;');
                    return out ? '<div class="sa-chips" style="margin:0 0 8px;">' + out + '</div>' : '';
                };

                let out;
                const streetSecs = (isTl() && a.streets_tl && a.streets_tl.length) ? a.streets_tl : a.streets;
                const multiStreet = !!(streetSecs && streetSecs.length > 1);
                renderStreetAiDashboard(a, streetSecs);
                if (streetSecs && streetSecs.length) {
                    out = streetSecs.map(function (sec, secIdx) {
                        const sLvl = String(sec.risk_level || 'low').toLowerCase();

                        // Visual header for the street: a stacked bar of its
                        // crime mix (+ legend) and, for multi-street reports,
                        // a small hourly profile so streets can be compared
                        let viz = '';
                        if (sec.categories && sec.categories.length && sec.total > 0) {
                            const stack = sec.categories.map(function (cb) {
                                return '<span style="width:' + (Number(cb.count) / sec.total * 100) + '%;background:' + colorForCategory(cb.category) + ';" title="' + escStreet(cb.category_label || cb.category) + ': ' + cb.count + '"></span>';
                            }).join('');
                            const legend = sec.categories.map(function (cb) {
                                return '<span><i style="background:' + colorForCategory(cb.category) + ';"></i>' + escStreet(cb.category_label || cb.category) + ' <b>' + cb.count + '</b></span>';
                            }).join('');
                            const st = sec.stats || {};
                            const hasHours = st.hourly && st.hourly.some(function (v) { return v > 0; });
                            viz = '<div class="sa-sec-viz' + (multiStreet && hasHours ? '' : ' sa-single') + '">' +
                                '<div><div class="sa-stack">' + stack + '</div><div class="sa-legend">' + legend + '</div></div>' +
                                (multiStreet && hasHours ? '<div><div class="sa-mini-c"><canvas id="saSecHour' + secIdx + '"></canvas></div>' +
                                    '<div style="font-size:9px;color:#6b7280;text-align:center;margin-top:1px;">' + (isTl() ? 'Oras ng araw' : 'Time of day') +
                                    (sec.peak_hours && sec.peak_hours.length ? ' · peak ' + escStreet(sec.peak_hours[0]) : '') + '</div></div>' : '') +
                            '</div>';
                        }

                        // One block PER CRIME TYPE — the counts add up to the
                        // street total, and each type carries its own tailored
                        // suggestion + a toggle listing that type's crimes
                        let body;
                        if (sec.categories && sec.categories.length) {
                            body = sec.categories.map(function (cb) {
                                const cc = colorForCategory(cb.category);
                                return '<div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">' +
                                    '<div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:#fafafa;flex-wrap:wrap;">' +
                                        '<span style="font-size:10px;font-weight:800;color:#fff;background:' + cc + ';padding:2px 8px;border-radius:9999px;">' + escStreet(cb.category_label || cb.category) + '</span>' +
                                        sevChip(cb.severity) +
                                        '<span style="font-size:11px;font-weight:700;color:#374151;">' + (isTl()
                                            ? cb.count + ' sa ' + sec.total + ' krimen (' + cb.share + '%)'
                                            : cb.count + ' of ' + sec.total + ' crime' + (sec.total === 1 ? '' : 's') + ' (' + cb.share + '%)') + '</span>' +
                                        (cb.peak_hours && cb.peak_hours.length ? '<span style="font-size:10.5px;color:#6d28d9;font-weight:600;"><i class="fas fa-clock mr-1"></i>' + cb.peak_hours.map(escStreet).join(', ') + '</span>' : '') +
                                        '<span class="sa-share" title="' + cb.share + '%"><span style="width:' + Math.min(100, Number(cb.share) || 0) + '%;background:' + cc + ';"></span></span>' +
                                        (typeof cb.unresolved === 'number' && cb.unresolved > 0 ? '<span style="font-size:10px;font-weight:800;color:#b91c1c;"><i class="fas fa-folder-open mr-1"></i>' + cb.unresolved + (isTl() ? ' bukas' : ' open') + '</span>' : '') +
                                        '<button type="button" class="cat-crimes-toggle" data-street="' + escStreet(sec.street) + '" data-cat="' + escStreet(cb.category) + '"' +
                                            ' style="margin-left:auto;font-size:10px;font-weight:700;color:#7c3aed;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:3px 9px;cursor:pointer;">' +
                                            '<i class="fas fa-list mr-1"></i>' + (isTl() ? 'Tingnan ang mga krimen' : 'View crimes') + '</button>' +
                                    '</div>' +
                                    '<div class="cat-crimes-list" style="display:none;padding:8px 10px;border-bottom:1px solid #f3f4f6;background:#fcfcfd;"></div>' +
                                    '<div style="padding:8px 10px;">' + suggCard(cb.suggestion || {}) + '</div>' +
                                '</div>';
                            }).join('');
                        } else {
                            body = (sec.suggestions || []).map(function (s) { return suggCard(s); }).join('')
                                || '<div style="font-size:11px;color:#9ca3af;">No suggestions.</div>';
                        }

                        return '<div style="border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px;background:#fcfcfd;">' +
                            '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">' +
                                '<span style="width:14px;height:5px;border-radius:3px;background:' + ((miniStreets[sec.street] || {}).color || '#64748b') + ';flex-shrink:0;"></span>' +
                                '<span style="font-size:12.5px;font-weight:800;color:#111;">' + escStreet(sec.street) + '</span>' +
                                '<span style="font-size:9.5px;font-weight:800;padding:2px 7px;border-radius:9999px;' + (RISK_CHIP[sLvl] || '') + '">' + sLvl.toUpperCase() + ' RISK</span>' +
                                (typeof sec.total === 'number' ? '<span style="font-size:10.5px;color:#6b7280;font-weight:700;">' + (isTl()
                                    ? sec.total + ' kabuuang krimen'
                                    : sec.total + ' total crime' + (sec.total === 1 ? '' : 's')) + '</span>' : '') +
                            '</div>' +
                            secChips(sec) +
                            viz +
                            '<div style="display:grid;gap:8px;">' + body + '</div>' +
                        '</div>';
                    }).join('');
                } else {
                    // AI-engine (fallback) shape: one flat list, street shown per card
                    out = (a.suggestions || []).map(function (s) { return suggCard(s, true); }).join('');
                }
                document.getElementById('streetAiSuggestions').innerHTML =
                    out || '<div style="font-size:12px;color:#9ca3af;">No suggestions returned.</div>';
                if (streetSecs && streetSecs.length) renderStreetAiMiniCharts(streetSecs);

                risk.style.display = 'inline-block';
        }

        // "View crimes" toggle inside a crime-type block: lists that type's
        // actual crimes (from the already-fetched street detail cache)
        document.getElementById('streetAiSuggestions').addEventListener('click', function (e) {
            const dt = e.target.closest('.sa-details-toggle');
            if (dt) {
                const box = dt.nextElementSibling;
                const open = box && box.classList.toggle('open');
                dt.querySelector('i').className = 'fas ' + (open ? 'fa-chevron-up' : 'fa-chevron-down');
                return;
            }
            const btn = e.target.closest('.cat-crimes-toggle');
            if (!btn) return;
            const block = btn.closest('div').parentNode;
            const list = block ? block.querySelector('.cat-crimes-list') : null;
            if (!list) return;

            if (list.style.display !== 'none') {
                list.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-list mr-1"></i>' + (isTl() ? 'Tingnan ang mga krimen' : 'View crimes');
                return;
            }

            const d = streetDetailCache[btn.dataset.street];
            const incs = ((d && d.incidents) || []).filter(function (i) { return i.category === btn.dataset.cat; });
            list.innerHTML = incs.length
                ? '<div style="font-size:9.5px;font-weight:800;color:#6b7280;text-transform:uppercase;margin-bottom:3px;">' + (isTl()
                      ? 'Mga ' + escStreet(btn.dataset.cat) + ' na krimen sa ' + escStreet(btn.dataset.street)
                      : escStreet(btn.dataset.cat) + ' crimes on ' + escStreet(btn.dataset.street)) + '</div>' +
                  incs.map(function (i) {
                    const done = ['solved', 'resolved', 'closed', 'cleared'].indexOf(String(i.status || '').toLowerCase()) >= 0;
                    return '<div style="display:flex;gap:8px;align-items:baseline;font-size:11px;color:#4b5563;padding:2.5px 0;flex-wrap:wrap;border-top:1px dashed #f3f4f6;">' +
                        '<span style="font-family:monospace;color:#9ca3af;">' + escStreet(i.code) + '</span>' +
                        '<span style="font-weight:600;color:#111827;">' + escStreet(i.title || 'Crime') + '</span>' +
                        '<span>' + escStreet(i.date || '') + (i.time ? ' · ' + escStreet(fmt12h(i.time)) : '') + '</span>' +
                        '<span style="margin-left:auto;font-weight:700;color:' + (done ? '#15803d' : '#b45309') + ';">' + escStreet(String(i.status || '').toUpperCase()) + '</span>' +
                    '</div>';
                }).join('')
                : '<div style="font-size:11px;color:#9ca3af;">' + (isTl()
                    ? 'Hindi pa na-load ang listahan — nilo-load pa ang detalye ng kalyeng ito.'
                    : 'Crime list not loaded yet — this street\'s details are still loading.') + '</div>';
            list.style.display = 'block';
            btn.innerHTML = '<i class="fas fa-chevron-up mr-1"></i>' + (isTl() ? 'Itago ang mga krimen' : 'Hide crimes');
        });

        // Save the street AI report to the database — same endpoint and row
        // layout as the pattern-detection Save button
        async function saveStreetAi() {
            if (!latestStreetAi) return;
            const btn = document.getElementById('streetAiSaveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving…';

            try {
                const res = await fetch(SA_AI_SAVE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        meta: latestStreetAi.meta,
                        analysis: latestStreetAi.analysis,
                        data_source: 'real'
                    })
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || ('HTTP ' + res.status));

                btn.style.background = '#16a34a';
                btn.innerHTML = '<i class="fas fa-circle-check mr-1"></i>Saved (' + data.saved_rows + ' rows)'
                    + (data.received_by ? ' &middot; Received by ' + data.received_by : '');
                btn.title = data.received_by ? 'Receipt logged in the audit trail' : '';
            } catch (e) {
                console.error('Street AI save failed:', e);
                btn.disabled = false;
                btn.style.background = '#dc2626';
                btn.innerHTML = '<i class="fas fa-triangle-exclamation mr-1"></i>Save failed — retry';
            }
        }
</script>
@endif
