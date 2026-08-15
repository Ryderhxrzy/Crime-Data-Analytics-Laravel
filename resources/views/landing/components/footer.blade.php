<footer class="border-t border-line bg-surface">
    <div class="container-page">
        <div class="grid gap-10 py-14 md:grid-cols-[1.5fr_1fr_1fr]">
            <div class="max-w-sm">
                <a href="#home" class="inline-flex items-center gap-0.5 rounded-lg">
                    <img src="{{ asset('images/alertara.png') }}" alt="" class="h-8 w-8 object-contain">
                    <span class="font-display text-xl font-bold tracking-tight text-ink" aria-hidden="true">
                        lerTara<span class="text-brand">QC</span>
                    </span>
                    <span class="sr-only">AlerTaraQC</span>
                </a>

                <p class="mt-4 text-sm leading-relaxed text-ink-muted">
                    Crime Analytics &amp; Heatmap — the public crime data view of AlerTaraQC,
                    the integrated public safety platform built for Quezon City.
                </p>

                <p class="mt-5 inline-flex items-center gap-2 rounded-xl border border-line bg-white px-4 py-2.5 text-sm text-ink-muted">
                    <i class="fas fa-phone text-brand"></i>
                    Emergency hotline
                    <a href="tel:122" class="font-semibold text-ink transition-colors hover:text-brand">122</a>
                </p>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-widest text-ink">This Page</h2>
                <ul class="mt-4 space-y-2.5">
                    <li><a href="#home" class="text-sm text-ink-muted transition-colors hover:text-brand">Overview</a></li>
                    <li><a href="#about" class="text-sm text-ink-muted transition-colors hover:text-brand">About the data</a></li>
                    <li><a href="#map" class="text-sm text-ink-muted transition-colors hover:text-brand">Crime heatmap</a></li>
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-widest text-ink">Access</h2>
                <ul class="mt-4 space-y-2.5">
                    <li><a href="{{ route('login') }}" class="text-sm text-ink-muted transition-colors hover:text-brand">Staff sign in</a></li>
                    <li><a href="#" class="text-sm text-ink-muted transition-colors hover:text-brand">Privacy</a></li>
                    <li><a href="#" class="text-sm text-ink-muted transition-colors hover:text-brand">Terms</a></li>
                </ul>
            </div>
        </div>

        <div class="flex flex-col items-center gap-3 border-t border-line py-6 sm:flex-row sm:justify-between">
            <p class="text-xs text-ink-subtle">
                &copy; {{ date('Y') }} AlerTaraQC — Crime Analytics. Built for Quezon City public safety.
            </p>
            <p class="text-xs text-ink-subtle">
                Data shown reflects recorded incidents only.
            </p>
        </div>
    </div>
</footer>
