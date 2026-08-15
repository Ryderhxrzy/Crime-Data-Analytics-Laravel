<section id="home" class="relative overflow-hidden pt-28 pb-20 md:pt-36 md:pb-28">
    <!-- Layered background: blueprint grid, brand glow, and a soft fade-out. -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
        <div class="bg-grid absolute inset-0" style="mask-image: radial-gradient(ellipse 60% 50% at 50% 0%, black, transparent); -webkit-mask-image: radial-gradient(ellipse 60% 50% at 50% 0%, black, transparent);"></div>
        <div class="absolute left-1/2 top-[-14rem] h-[38rem] w-[38rem] -translate-x-1/2 rounded-full bg-brand/20 blur-[110px]"></div>
        <div class="absolute right-[-8rem] top-24 h-[24rem] w-[24rem] rounded-full bg-flag/10 blur-[110px]"></div>
        <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-b from-transparent to-white"></div>
    </div>

    <div class="container-page">
        <div data-reveal class="flex justify-center">
            <p class="inline-flex items-center gap-2.5 rounded-full border border-line bg-white/70 px-4 py-1.5 text-xs font-medium text-ink-muted backdrop-blur-sm sm:text-sm">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-pulse-ring rounded-full bg-brand"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-brand"></span>
                </span>
                Live crime data — Quezon City
            </p>
        </div>

        <div data-reveal style="animation-delay: 80ms" class="mt-8 text-center">
            <h1 class="mx-auto max-w-4xl text-4xl font-bold leading-[1.1] tracking-tight sm:text-5xl md:text-6xl lg:text-7xl">
                <span class="text-ink">Stay Informed.</span><br>
                <span class="text-gradient">Stay Safe.</span>
            </h1>

            <p class="mx-auto mt-6 max-w-3xl text-lg font-medium text-ink/90 sm:text-xl md:text-2xl">
                Monitor crime activity across Quezon City with our interactive heatmap.
            </p>

            <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-ink-muted sm:text-lg">
                See where incidents cluster, follow density patterns over time, and read transparent
                crime statistics — so you know what is happening in your area.
            </p>
        </div>

        <div data-reveal style="animation-delay: 160ms" class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="#map"
               class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-brand px-7 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand/20 transition-all duration-200 hover:bg-brand-hover hover:shadow-xl hover:shadow-brand/30 sm:w-auto">
                <i class="fas fa-map-marked-alt"></i>
                View the heatmap
            </a>
            <a href="#about"
               class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-line bg-white px-7 py-3.5 text-base font-semibold text-ink transition-all duration-200 hover:border-brand hover:text-brand sm:w-auto">
                How the data works
                <i class="fas fa-arrow-right text-sm transition-transform duration-200 group-hover:translate-x-0.5"></i>
            </a>
        </div>

        <!-- Figures come straight from the incident records — nothing here is estimated. -->
        <div data-reveal style="animation-delay: 240ms" class="mt-14">
            <dl class="mx-auto grid max-w-4xl gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex items-center gap-3 bg-white px-5 py-4">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand">
                        <i class="fas fa-database text-sm"></i>
                    </span>
                    <div class="min-w-0">
                        <dt class="text-xs uppercase tracking-wider text-ink-subtle">Total incidents</dt>
                        <dd class="truncate font-display text-sm font-bold text-ink">{{ number_format($totalIncidents) }} recorded</dd>
                    </div>
                </div>

                <div class="flex items-center gap-3 bg-white px-5 py-4">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand">
                        <i class="fas fa-clock-rotate-left text-sm"></i>
                    </span>
                    <div class="min-w-0">
                        <dt class="text-xs uppercase tracking-wider text-ink-subtle">Last 30 days</dt>
                        <dd class="truncate font-display text-sm font-bold text-ink">{{ number_format($recentIncidents) }} recent cases</dd>
                    </div>
                </div>

                <div class="flex items-center gap-3 bg-white px-5 py-4">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand">
                        <i class="fas fa-bolt text-sm"></i>
                    </span>
                    <div class="min-w-0">
                        <dt class="text-xs uppercase tracking-wider text-ink-subtle">Updates</dt>
                        <dd class="truncate font-display text-sm font-bold text-ink">24/7 live data</dd>
                    </div>
                </div>

                <div class="flex items-center gap-3 bg-white px-5 py-4">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand">
                        <i class="fas fa-location-dot text-sm"></i>
                    </span>
                    <div class="min-w-0">
                        <dt class="text-xs uppercase tracking-wider text-ink-subtle">Coverage</dt>
                        <dd class="truncate font-display text-sm font-bold text-ink">Quezon City limits</dd>
                    </div>
                </div>
            </dl>
        </div>
    </div>
</section>
