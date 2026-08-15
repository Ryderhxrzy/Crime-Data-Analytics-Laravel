<section id="map" class="py-20 md:py-28">
    <div class="container-page">
        <div data-reveal class="mx-auto max-w-3xl text-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-line bg-white px-3 py-1 text-xs font-semibold uppercase tracking-widest text-brand">
                <span class="h-1.5 w-1.5 rounded-full bg-brand" aria-hidden="true"></span>
                Heatmap
            </span>

            <h2 class="mt-5 text-3xl font-bold leading-tight tracking-tight text-ink sm:text-4xl md:text-[2.75rem]">
                Crime heatmap — Quezon City
            </h2>

            <p class="mx-auto mt-4 text-base leading-relaxed text-ink-muted sm:text-lg">
                Crime density across the city, rendered as a single heat layer. The teal boundary
                marks the official Quezon City limits — the map stays inside it.
            </p>
        </div>

        <!-- Map controls -->
        <div data-reveal style="animation-delay: 80ms" class="mt-12 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-2">
                <label for="dateRangeFilter" class="text-xs font-semibold uppercase tracking-widest text-ink-subtle">
                    Filter by date range
                </label>
                <select id="dateRangeFilter"
                        class="cursor-pointer rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-medium text-ink transition-colors hover:border-brand focus:border-brand focus:outline-none">
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 3 Months</option>
                    <option value="180">Last 6 Months</option>
                    <option value="all" selected>All Time</option>
                </select>
            </div>

            <p class="text-xs text-ink-subtle sm:text-right">
                Pan and zoom to explore. Density only — individual incidents are not pinned.
            </p>
        </div>

        <!-- Map container -->
        <div data-reveal style="animation-delay: 140ms" class="mt-6">
            <div id="crimeMap" class="relative z-0 h-96 w-full overflow-hidden rounded-card border border-line shadow-xl shadow-brand/5 md:h-[550px]">
                <div id="mapLoader" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white">
                    <i class="fas fa-spinner fa-spin mb-3 text-3xl text-brand"></i>
                    <p class="text-sm text-ink-muted">Loading heatmap…</p>
                </div>
            </div>
        </div>

        <!-- Legend & info -->
        <div class="mt-8 grid grid-cols-1 gap-5 md:grid-cols-2">
            <div data-reveal class="rounded-card border border-line bg-white p-6">
                <h3 class="text-sm font-semibold text-ink">Crime density scale</h3>

                <div class="mt-4 h-5 w-full rounded-lg"
                     style="background: linear-gradient(to right, #3498db 0%, #2ecc71 20%, #f39c12 40%, #e74c3c 60%, #c0392b 80%, #8b0000 100%);"
                     role="img"
                     aria-label="Colour scale running from blue at low density through green, orange and red to dark red at very high density"></div>

                <div class="mt-2.5 flex justify-between px-1 text-xs text-ink-subtle">
                    <span>Low density</span>
                    <span>Medium</span>
                    <span>High density</span>
                </div>

                <p class="mt-4 text-xs leading-relaxed text-ink-muted">
                    Colour reflects how many incidents were recorded in an area, not how severe
                    they were. Zooming changes the visible detail, not the underlying data.
                </p>
            </div>

            <div data-reveal style="animation-delay: 70ms" class="rounded-card border border-line bg-surface p-6">
                <h3 class="text-sm font-semibold text-ink">Reading this map</h3>

                <ul class="mt-4 space-y-2.5 text-xs leading-relaxed text-ink-muted">
                    <li class="flex gap-2.5">
                        <i class="fas fa-check mt-0.5 shrink-0 text-brand"></i>
                        <span><strong class="font-semibold text-ink">City boundary:</strong> the exact Quezon City limits, drawn from the official map data.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <i class="fas fa-check mt-0.5 shrink-0 text-brand"></i>
                        <span><strong class="font-semibold text-ink">Heat layer only:</strong> incidents are aggregated into density — no individual case is identifiable.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <i class="fas fa-check mt-0.5 shrink-0 text-brand"></i>
                        <span><strong class="font-semibold text-ink">Date filter:</strong> narrow the view to the last 30 days, 3 months, 6 months, or all records.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <i class="fas fa-check mt-0.5 shrink-0 text-brand"></i>
                        <span><strong class="font-semibold text-ink">Emergencies:</strong> this map is for information. For an emergency, call the QC hotline 122.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
