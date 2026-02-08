<nav id="mainNav" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white/0">
    <div class="container mx-auto px-4 py-3 max-w-7xl">
        <div class="flex items-center justify-between">
            <!-- Logo -->
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo.svg') }}" alt="Logo" class="h-12 sm:h-14 w-auto">
                <span class="hidden sm:inline font-bold text-lg text-gray-900">Crime Monitor</span>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="#home" class="nav-link text-xs sm:text-sm text-gray-700 hover:text-alertara-600 active:text-alertara-700 font-medium transition-colors">Home</a>
                <a href="#about" class="nav-link text-xs sm:text-sm text-gray-700 hover:text-alertara-600 active:text-alertara-700 font-medium transition-colors">About</a>
                <a href="#map" class="nav-link text-xs sm:text-sm text-gray-700 hover:text-alertara-600 active:text-alertara-700 font-medium transition-colors">Map</a>
                <a href="#submit-tip" class="nav-link text-xs sm:text-sm text-gray-700 hover:text-alertara-600 active:text-alertara-700 font-medium transition-colors">Report</a>
                <a href="{{ route('login') }}" class="py-2 sm:py-2.5 px-4 border border-transparent text-xs sm:text-sm font-medium rounded-md text-white bg-alertara-600 hover:bg-alertara-700 transition-colors">
                    Sign In
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <button class="md:hidden text-gray-700 hover:text-alertara-600" id="mobileMenuBtn">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>

        <!-- Mobile Menu -->
        <div id="mobileMenu" class="hidden md:hidden pt-3 border-t border-gray-200 mt-3 space-y-1">
            <a href="#home" class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-100 rounded transition-colors">Home</a>
            <a href="#about" class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-100 rounded transition-colors">About</a>
            <a href="#map" class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-100 rounded transition-colors">Map</a>
            <a href="#submit-tip" class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-100 rounded transition-colors">Report</a>
            <a href="{{ route('login') }}" class="block px-3 py-2 text-xs text-alertara-600 hover:bg-alertara-50 rounded font-medium border-t border-gray-200 mt-2 pt-3">Sign In</a>
        </div>
    </div>
</nav>

<script>
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    const navbar = document.getElementById('mainNav');

    mobileMenuBtn.addEventListener('click', function() {
        mobileMenu.classList.toggle('hidden');
    });

    document.querySelectorAll('#mobileMenu a').forEach(link => {
        link.addEventListener('click', function() {
            mobileMenu.classList.add('hidden');
        });
    });

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.remove('bg-white/0');
            navbar.classList.add('bg-white', 'shadow-sm');
        } else {
            navbar.classList.remove('bg-white', 'shadow-sm');
            navbar.classList.add('bg-white/0');
        }
    });

    // Smooth scroll & active state
    const sections = ['home', 'about', 'map', 'submit-tip'];
    const navLinks = document.querySelectorAll('.nav-link');

    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            e.preventDefault();
            const element = document.querySelector(href);
            if (element) {
                const offset = 80;
                const top = element.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({ top: top, behavior: 'smooth' });
            }
        });
    });

    // Highlight active nav link on scroll
    window.addEventListener('scroll', function() {
        let current = '';
        sections.forEach(id => {
            const section = document.querySelector('#' + id);
            if (section && section.offsetTop <= window.scrollY + 100) {
                current = id;
            }
        });
        navLinks.forEach(link => {
            link.classList.remove('text-alertara-700');
            link.classList.add('text-gray-700');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.remove('text-gray-700');
                link.classList.add('text-alertara-700');
            }
        });
    });
</script>
