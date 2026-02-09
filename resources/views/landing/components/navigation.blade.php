<nav id="mainNav" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white/0 border-b border-gray-300 h-20">
    <div class="container mx-auto px-4 max-w-7xl h-full">
        <div class="flex items-center justify-between gap-8 h-full">
            <!-- Logo/Brand on Left -->
            <div class="flex items-center gap-2 flex-shrink-0">
                <img src="{{ asset('images/logo.svg') }}" alt="Logo" class="h-20 w-28">
            </div>

            <!-- Desktop Navigation - Center -->
            <div class="hidden lg:flex items-center justify-center flex-1 space-x-12">
                <a href="#home" class="nav-link text-sm text-gray-900 hover:text-alertara-600 font-semibold transition-colors">Home</a>
                <a href="#about" class="nav-link text-sm text-gray-900 hover:text-alertara-600 font-semibold transition-colors">About</a>
                <a href="#map" class="nav-link text-sm text-gray-900 hover:text-alertara-600 font-semibold transition-colors">Map</a>
                <a href="#submit-tip" class="nav-link text-sm text-gray-900 hover:text-alertara-600 font-semibold transition-colors">Report</a>
            </div>


            <!-- Mobile Menu Button -->
            <button class="lg:hidden text-gray-900 hover:text-alertara-600 flex-shrink-0" id="mobileMenuBtn">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>

        <!-- Mobile Menu -->
        <div id="mobileMenu" class="hidden lg:hidden pt-4 border-t border-gray-200 mt-4 space-y-1">
            <a href="#home" class="block px-3 py-3 text-sm text-gray-900 hover:bg-gray-100 rounded transition-colors font-semibold">Home</a>
            <a href="#about" class="block px-3 py-3 text-sm text-gray-900 hover:bg-gray-100 rounded transition-colors font-semibold">About</a>
            <a href="#map" class="block px-3 py-3 text-sm text-gray-900 hover:bg-gray-100 rounded transition-colors font-semibold">Map</a>
            <a href="#submit-tip" class="block px-3 py-3 text-sm text-gray-900 hover:bg-gray-100 rounded transition-colors font-semibold">Report</a>
            <a href="{{ route('login') }}" class="block px-3 py-3 mt-2 text-sm font-semibold text-white bg-alertara-600 hover:bg-alertara-700 rounded transition-colors">Sign In</a>
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

    // Function to update active nav link
    function updateActiveNavLink() {
        let current = '';
        sections.forEach(id => {
            const section = document.querySelector('#' + id);
            if (section && section.offsetTop <= window.scrollY + 100) {
                current = id;
            }
        });
        navLinks.forEach(link => {
            link.classList.remove('border-b-2', 'border-alertara-600', 'pb-1');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('border-b-2', 'border-alertara-600', 'pb-1');
            }
        });
    }

    // Update active link on page load
    window.addEventListener('load', updateActiveNavLink);

    // Highlight active nav link on scroll
    window.addEventListener('scroll', updateActiveNavLink);
</script>
