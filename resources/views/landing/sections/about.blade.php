<section id="about" class="border-y border-line bg-surface py-20 md:py-28">
    <div class="container-page">
        <div data-reveal class="mx-auto max-w-3xl text-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-line bg-white px-3 py-1 text-xs font-semibold uppercase tracking-widest text-brand">
                <span class="h-1.5 w-1.5 rounded-full bg-brand" aria-hidden="true"></span>
                About
            </span>

            <h2 class="mt-5 text-3xl font-bold leading-tight tracking-tight text-ink sm:text-4xl md:text-[2.75rem]">
                Crime data, made readable
            </h2>

            <p class="mx-auto mt-4 text-base leading-relaxed text-ink-muted sm:text-lg">
                Crime Analytics is the public view of AlerTaraQC's crime records for Quezon City.
                It turns individual incident reports into a density map and plain statistics, so
                residents can see the pattern instead of a spreadsheet.
            </p>
        </div>

        <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div data-reveal class="group h-full rounded-card border border-line bg-white p-6 transition-all duration-300 hover:-translate-y-1 hover:border-brand/40 hover:shadow-xl hover:shadow-brand/5">
                <span class="grid h-12 w-12 place-items-center rounded-xl border border-brand/15 bg-brand-soft text-brand transition-colors duration-300 group-hover:bg-brand group-hover:text-white">
                    <i class="fas fa-fire text-lg"></i>
                </span>
                <h3 class="mt-5 text-lg font-semibold text-ink">Live Heatmap</h3>
                <p class="mt-2.5 text-sm leading-relaxed text-ink-muted">
                    Incident density rendered as colour intensity — the warmer the area, the more
                    incidents recorded there.
                </p>
            </div>

            <div data-reveal style="animation-delay: 70ms" class="group h-full rounded-card border border-line bg-white p-6 transition-all duration-300 hover:-translate-y-1 hover:border-brand/40 hover:shadow-xl hover:shadow-brand/5">
                <span class="grid h-12 w-12 place-items-center rounded-xl border border-brand/15 bg-brand-soft text-brand transition-colors duration-300 group-hover:bg-brand group-hover:text-white">
                    <i class="fas fa-chart-bar text-lg"></i>
                </span>
                <h3 class="mt-5 text-lg font-semibold text-ink">Transparent Statistics</h3>
                <p class="mt-2.5 text-sm leading-relaxed text-ink-muted">
                    Total and recent case counts are published as recorded — no filtering,
                    smoothing, or estimates applied.
                </p>
            </div>

            <div data-reveal style="animation-delay: 140ms" class="group h-full rounded-card border border-line bg-white p-6 transition-all duration-300 hover:-translate-y-1 hover:border-brand/40 hover:shadow-xl hover:shadow-brand/5">
                <span class="grid h-12 w-12 place-items-center rounded-xl border border-brand/15 bg-brand-soft text-brand transition-colors duration-300 group-hover:bg-brand group-hover:text-white">
                    <i class="fas fa-location-dot text-lg"></i>
                </span>
                <h3 class="mt-5 text-lg font-semibold text-ink">Quezon City Coverage</h3>
                <p class="mt-2.5 text-sm leading-relaxed text-ink-muted">
                    The map is bounded to the official Quezon City limits, so every point you see
                    falls inside the city.
                </p>
            </div>
        </div>

        <!-- Data provenance note -->
        <div data-reveal style="animation-delay: 200ms" class="mt-8">
            <div class="flex gap-3 rounded-card border border-flag/30 bg-flag-soft p-5">
                <i class="fas fa-lightbulb mt-0.5 shrink-0 text-flag"></i>
                <div>
                    <p class="text-sm font-semibold text-ink">Where the data comes from</p>
                    <p class="mt-1 text-sm leading-relaxed text-ink-muted">
                        Incidents are drawn from official records logged in the AlerTaraQC system.
                        The heatmap shows recorded incidents only — areas with few reports may be
                        safer, or simply under-reported.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
