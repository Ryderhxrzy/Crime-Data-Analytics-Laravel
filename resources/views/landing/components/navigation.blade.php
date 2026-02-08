<nav id="mainNav" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-white/0">
    <div class="container mx-auto px-4 py-4 max-w-7xl">
        <div class="flex items-center justify-between">
            <!-- Logo and Title -->
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-alertara-600 rounded-full flex items-center justify-center">
                    <i class="fas fa-shield-alt text-white text-lg"></i>
                </div>
                <span class="text-xl font-bold text-alertara-700 hidden sm:block">Crime Monitor QC</span>
            </div>

            <!-- Desktop Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="#home" class="nav-link text-gray-700 hover:text-alertara-600 font-medium transition-colors">Home</a>
                <a href="#about" class="nav-link text-gray-700 hover:text-alertara-600 font-medium transition-colors">About</a>
                <a href="#map" class="nav-link text-gray-700 hover:text-alertara-600 font-medium transition-colors">Crime Map</a>
                <a href="#submit-tip" class="nav-link text-gray-700 hover:text-alertara-600 font-medium transition-colors">Submit Tip</a>
                <a href="{{ route('login') }}" class="px-5 py-2 bg-alertara-600 text-white rounded-lg hover:bg-alertara-700 transition-colors font-medium">
                    Login
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <button class="md:hidden text-gray-700 hover:text-alertara-600 transition-colors" id="mobileMenuBtn">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobileMenu" class="hidden md:hidden pt-4 border-t border-gray-200 mt-4">
            <a href="#home" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                <i class="fas fa-home mr-2"></i>Home
            </a>
            <a href="#about" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                <i class="fas fa-info-circle mr-2"></i>About
            </a>
            <a href="#map" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                <i class="fas fa-map mr-2"></i>Crime Map
            </a>
            <a href="#submit-tip" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                <i class="fas fa-paper-plane mr-2"></i>Submit Tip
            </a>
            <a href="{{ route('login') }}" class="block px-4 py-2 text-alertara-600 hover:bg-alertara-50 rounded-lg transition-colors font-medium border-t border-gray-200 mt-2 pt-4">
                <i class="fas fa-sign-in-alt mr-2"></i>Login
            </a>
        </div>
    </div>
</nav>

<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');

    mobileMenuBtn.addEventListener('click', function() {
        mobileMenu.classList.toggle('hidden');
    });

    // Close menu when a link is clicked
    document.querySelectorAll('#mobileMenu a').forEach(link => {
        link.addEventListener('click', function() {
            mobileMenu.classList.add('hidden');
        });
    });

    // Navbar scroll effect
    const navbar = document.getElementById('mainNav');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.remove('bg-white/0');
            navbar.classList.add('bg-white', 'shadow-md');
        } else {
            navbar.classList.remove('bg-white', 'shadow-md');
            navbar.classList.add('bg-white/0');
        }
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;

            e.preventDefault();
            const element = document.querySelector(href);
            if (element) {
                const offset = 80; // Height of fixed navbar
                const top = element.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({ top: top, behavior: 'smooth' });
            }
        });
    });
</script>
