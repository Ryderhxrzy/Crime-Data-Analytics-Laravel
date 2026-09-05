<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Crime Analytics & Heatmap — real-time crime density visualization for Quezon City, part of the AlerTaraQC public safety platform.">
    <title>Crime Analytics & Heatmap — AlerTaraQC</title>

    <link rel="icon" href="{{ asset('images/alertara.png') }}">

    <!-- Brand typography: same pairing as the AlerTaraQC landing page -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        /*
         * This page carries its own Tailwind config rather than js/tailwind-config.js —
         * the tokens below mirror the AlerTaraQC landing page so the public site and this
         * system read as one product. The shared admin config is left untouched.
         */
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#2f7d7b',
                            hover: '#266562',
                            soft: '#e6f2f1',
                        },
                        ink: {
                            DEFAULT: '#0b132b',
                            muted: '#4f5b6b',
                            subtle: '#5f6b7a',
                        },
                        surface: {
                            DEFAULT: '#f5f8f8',
                            strong: '#eaf1f0',
                        },
                        line: {
                            DEFAULT: '#e2e8e8',
                            strong: '#cbd8d7',
                        },
                        flag: {
                            DEFAULT: '#b45309',
                            soft: '#fef3c7',
                        },
                        // Kept so any shared partial using the old palette still renders.
                        alertara: {
                            50: '#f0f9f8', 100: '#e1f3f1', 200: '#c3e7e4', 300: '#95d5d0',
                            400: '#5fbfb8', 500: '#3a7675', 600: '#2f5f5e', 700: '#274d4c',
                            800: '#214040', 900: '#1c3636',
                        },
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
                        display: ['Sora', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    borderRadius: {
                        card: '1rem',
                    },
                    keyframes: {
                        'fade-up': {
                            from: { opacity: '0', transform: 'translateY(1.25rem)' },
                            to: { opacity: '1', transform: 'translateY(0)' },
                        },
                        'pulse-ring': {
                            '0%, 100%': { opacity: '1', transform: 'scale(1)' },
                            '50%': { opacity: '0.35', transform: 'scale(1.9)' },
                        },
                    },
                    animation: {
                        'fade-up': 'fade-up 0.7s cubic-bezier(0.22, 1, 0.36, 1) both',
                        'pulse-ring': 'pulse-ring 2.4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                },
            },
        };
    </script>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Leaflet Maps CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Leaflet Heatmap Plugin -->
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

    <!-- Street-Segment Heatmap (default): road segments coloured by crime count -->
    <script src="{{ asset('js/street-segment-heatmap.js') }}"></script>
    <!-- 3D view: MapLibre GL with free OpenFreeMap vector tiles (no API key) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.min.js"></script>
    <script src="{{ asset('js/crime-map-3d.js') }}"></script>
    <style>
        .map-3d-btn.on { background: #2f7d7b !important; color: #fff !important; border-color: #2f7d7b !important; }
    </style>

    <style>
        html {
            scroll-padding-top: 6rem;
            -webkit-text-size-adjust: 100%;
        }

        body {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        h1, h2, h3, h4 {
            font-family: 'Sora', 'Inter', ui-sans-serif, system-ui, sans-serif;
            text-wrap: balance;
        }

        p { text-wrap: pretty; }

        :focus-visible {
            outline: 2px solid #4c8a89;
            outline-offset: 2px;
            border-radius: 0.25rem;
        }

        ::selection {
            background-color: #2f7d7b;
            color: #ffffff;
        }

        /* Page shell — matches the landing page container width and gutters. */
        .container-page {
            width: 100%;
            max-width: 78rem;
            margin-inline: auto;
            padding-inline: 1.25rem;
        }

        @media (min-width: 768px) {
            .container-page { padding-inline: 2rem; }
        }

        /* Faint blueprint grid used behind the hero. */
        .bg-grid {
            background-image:
                linear-gradient(to right, rgba(11, 19, 43, 0.06) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(11, 19, 43, 0.06) 1px, transparent 1px);
            background-size: 64px 64px;
        }

        .text-gradient {
            background-image: linear-gradient(110deg, #0b132b 0%, #0b132b 40%, #2f7d7b 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* Scroll reveal — content stays in the DOM for crawlers and no-JS users. */
        [data-reveal] { opacity: 0; }
        [data-reveal].is-visible {
            animation: fade-up 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes fade-up {
            from { opacity: 0; transform: translateY(1.25rem); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Leaflet has to sit under the fixed header. */
        .leaflet-pane, .leaflet-top, .leaflet-bottom { z-index: 10 !important; }

        /* Barangay name pinned to the centre of the highlighted boundary —
           same treatment as the authenticated crime mapping page. */
        .brgy-label-selected {
            background: transparent;
            border: none;
            box-shadow: none;
            color: #123332;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.01em;
            text-shadow: 0 0 4px #fff, 0 0 8px #fff, 0 1px 2px #fff;
            white-space: nowrap;
        }

        .brgy-label-selected::before { display: none; }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            [data-reveal] { opacity: 1; }
        }
    </style>

    <noscript>
        <style>[data-reveal] { opacity: 1; }</style>
    </noscript>
</head>
<body class="bg-white font-sans text-ink">
    <a href="#home" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Skip to content
    </a>

    <!-- Navigation -->
    @include('landing.components.navigation')

    <main>
        <!-- Hero Section -->
        @include('landing.sections.hero')

        <!-- About Section -->
        @include('landing.sections.about')

        <!-- Crime Heatmap Section -->
        @include('landing.sections.map')
    </main>

    <!-- Footer -->
    @include('landing.components.footer')

    <!-- Toast Notifications -->
    @if ($message = Session::get('success'))
        <div class="fixed bottom-4 right-4 z-50 flex items-center gap-2 rounded-xl bg-brand px-6 py-3 text-white shadow-lg" id="successToast">
            <i class="fas fa-check-circle"></i>
            <span>{{ $message }}</span>
            <button onclick="document.getElementById('successToast').remove()" class="ml-4" aria-label="Dismiss">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    @if ($message = Session::get('error'))
        <div class="fixed bottom-4 right-4 z-50 flex items-center gap-2 rounded-xl bg-red-600 px-6 py-3 text-white shadow-lg" id="errorToast">
            <i class="fas fa-exclamation-circle"></i>
            <span>{{ $message }}</span>
            <button onclick="document.getElementById('errorToast').remove()" class="ml-4" aria-label="Dismiss">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    <!-- Map Initialization Script -->
    <script src="{{ asset('js/landing-map.js') }}"></script>

    <script>
        // Reveal-on-scroll, mirroring the landing page's Reveal component.
        (function () {
            const items = document.querySelectorAll('[data-reveal]');
            if (!('IntersectionObserver' in window)) {
                items.forEach(el => el.classList.add('is-visible'));
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });

            items.forEach(el => observer.observe(el));
        })();
    </script>
</body>
</html>
