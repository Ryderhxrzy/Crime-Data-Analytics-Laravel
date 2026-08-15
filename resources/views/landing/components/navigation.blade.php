<nav id="mainNav" class="fixed inset-x-0 top-0 z-50 border-b border-transparent bg-white/40 backdrop-blur-sm transition-all duration-300">
    <div class="container-page flex h-16 items-center justify-between gap-4 md:h-[4.5rem]">
        <!-- Brand lockup — the glyph doubles as the leading "A". -->
        <a href="#home" class="flex shrink-0 items-center gap-0.5 rounded-lg">
            <img src="{{ asset('images/alertara.png') }}" alt="" class="h-8 w-8 object-contain">
            <span class="font-display text-xl font-bold tracking-tight text-ink" aria-hidden="true">
                lerTara<span class="text-brand">QC</span>
            </span>
            <span class="sr-only">AlerTaraQC</span>
        </a>

        <!-- Desktop navigation -->
        <div class="hidden lg:block">
            <ul class="flex items-center gap-1">
                <li>
                    <a href="#home" class="nav-link relative rounded-full px-3.5 py-2 text-sm font-medium text-ink-muted transition-colors duration-200 hover:text-ink">
                        Home
                        <span class="nav-underline absolute inset-x-3.5 -bottom-0.5 h-px origin-center scale-x-0 bg-brand transition-transform duration-300"></span>
                    </a>
                </li>
                <li>
                    <a href="#about" class="nav-link relative rounded-full px-3.5 py-2 text-sm font-medium text-ink-muted transition-colors duration-200 hover:text-ink">
                        About
                        <span class="nav-underline absolute inset-x-3.5 -bottom-0.5 h-px origin-center scale-x-0 bg-brand transition-transform duration-300"></span>
                    </a>
                </li>
                <li>
                    <a href="#map" class="nav-link relative rounded-full px-3.5 py-2 text-sm font-medium text-ink-muted transition-colors duration-200 hover:text-ink">
                        Heatmap
                        <span class="nav-underline absolute inset-x-3.5 -bottom-0.5 h-px origin-center scale-x-0 bg-brand transition-transform duration-300"></span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('login') }}"
               class="hidden items-center gap-1.5 rounded-full bg-brand px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:bg-brand-hover hover:shadow-lg hover:shadow-brand/25 lg:inline-flex">
                Sign In
                <i class="fas fa-arrow-right text-xs"></i>
            </a>

            <button type="button"
                    id="mobileMenuBtn"
                    aria-expanded="false"
                    aria-controls="mobileMenu"
                    aria-label="Open menu"
                    class="grid size-10 place-items-center rounded-full border border-line text-ink transition-colors duration-200 hover:bg-surface lg:hidden">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

    <!-- Mobile drawer -->
    <div id="mobileMenu" class="hidden border-b border-line bg-white lg:hidden">
        <div class="container-page py-4">
            <ul class="flex flex-col gap-1">
                <li><a href="#home" class="block rounded-xl px-4 py-3 text-base font-medium text-ink transition-colors hover:bg-surface">Home</a></li>
                <li><a href="#about" class="block rounded-xl px-4 py-3 text-base font-medium text-ink transition-colors hover:bg-surface">About</a></li>
                <li><a href="#map" class="block rounded-xl px-4 py-3 text-base font-medium text-ink transition-colors hover:bg-surface">Heatmap</a></li>
            </ul>

            <div class="mt-4 border-t border-line pt-4">
                <a href="{{ route('login') }}" class="flex items-center justify-center gap-1.5 rounded-full bg-brand px-4 py-3 text-sm font-semibold text-white">
                    Sign In
                    <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
    (function () {
        const navbar = document.getElementById('mainNav');
        const menuBtn = document.getElementById('mobileMenuBtn');
        const menu = document.getElementById('mobileMenu');

        // Mobile drawer
        menuBtn.addEventListener('click', function () {
            const isOpen = menu.classList.toggle('hidden') === false;
            menuBtn.setAttribute('aria-expanded', String(isOpen));
            menuBtn.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
            menuBtn.innerHTML = isOpen ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        });

        menu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function () {
                menu.classList.add('hidden');
                menuBtn.setAttribute('aria-expanded', 'false');
                menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            });
        });

        // Compact, glassy header once the page has scrolled past the hero edge.
        function onScroll() {
            if (window.scrollY > 16) {
                navbar.classList.remove('bg-white/40', 'backdrop-blur-sm', 'border-transparent');
                navbar.classList.add('bg-white/80', 'backdrop-blur-xl', 'border-line');
            } else {
                navbar.classList.remove('bg-white/80', 'backdrop-blur-xl', 'border-line');
                navbar.classList.add('bg-white/40', 'backdrop-blur-sm', 'border-transparent');
            }
        }

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        // Smooth scroll with an offset for the fixed header.
        document.querySelectorAll('a[href^="#"]').forEach(link => {
            link.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                const target = document.querySelector(href);
                if (!target) return;
                e.preventDefault();
                const top = target.getBoundingClientRect().top + window.scrollY - 80;
                window.scrollTo({ top: top, behavior: 'smooth' });
            });
        });

        // Scroll-spy: underline the section nearest the top of the viewport.
        const sections = ['home', 'about', 'map'];
        const navLinks = document.querySelectorAll('.nav-link');

        function updateActiveNavLink() {
            let current = '';
            sections.forEach(id => {
                const section = document.getElementById(id);
                if (section && section.offsetTop <= window.scrollY + 100) current = id;
            });

            navLinks.forEach(link => {
                const isActive = link.getAttribute('href') === '#' + current;
                const underline = link.querySelector('.nav-underline');

                link.classList.toggle('text-brand', isActive);
                link.classList.toggle('text-ink-muted', !isActive);
                if (underline) underline.classList.toggle('scale-x-0', !isActive);
                if (isActive) {
                    link.setAttribute('aria-current', 'true');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }

        window.addEventListener('load', updateActiveNavLink);
        window.addEventListener('scroll', updateActiveNavLink, { passive: true });
    })();
</script>
