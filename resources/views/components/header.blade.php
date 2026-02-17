<!-- Top Navigation Header -->
<nav class="bg-white border-b border-gray-200 fixed left-0 lg:left-72 right-0 top-0 z-30 h-16">
    <div class="px-4 sm:px-6 lg:px-8 h-full">
        <div class="flex justify-between items-center h-full gap-8">
            <!-- Left: Hamburger Menu -->
            <div class="lg:hidden flex-shrink-0">
                <!-- Hamburger Menu Button (Mobile Only) -->
                <button id="sidebarToggle" class="p-2 rounded-md text-alertara-600 hover:bg-alertara-50">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>

            <!-- Search Box - Icon only on right -->
            <div class="hidden sm:flex flex-1 max-w-md">
                <div class="relative w-full">
                    <input type="text"
                           placeholder="Search... (Ctrl K)"
                           class="w-full pl-3 pr-9 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-alertara-500 focus:border-transparent">
                    <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-alertara-600 text-sm cursor-pointer hover:text-alertara-700 transition-colors"></i>
                </div>
            </div>

            <!-- Right: Messages, Notifications, Profile -->
            <div class="flex items-center space-x-6">

                <!-- Messages -->
                <div class="relative">
                    <button class="p-2 text-gray-600 hover:bg-gray-100 rounded-md relative">
                        <i class="fas fa-envelope text-lg"></i>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-danger-500 rounded-full"></span>
                    </button>
                </div>

                <!-- Notifications -->
                <div class="relative">
                    <button class="p-2 text-gray-600 hover:bg-gray-100 rounded-md relative">
                        <i class="fas fa-bell text-lg"></i>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-danger-500 rounded-full"></span>
                    </button>
                </div>

                <!-- Fullscreen Toggle -->
                <button id="fullscreenToggle" class="p-2 text-gray-600 hover:bg-gray-100 rounded-md">
                    <i class="fas fa-expand text-lg"></i>
                </button>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <button id="profileToggle" class="flex items-center space-x-2 pl-4 border-l border-gray-200 hover:bg-gray-50 rounded py-2 px-2 transition-colors">
                        <div class="text-right hidden sm:block">
                            @php
                                $authUser = session('auth_user');
                                $isAuthenticated = !empty($authUser);
                            @endphp
                            @if($isAuthenticated)
                                <!-- JWT API Auth: Use centralized login data -->
                                <p class="text-sm font-medium text-alertara-900">{{ $authUser['email'] ?? 'User' }}</p>
                                <p class="text-xs text-alertara-500">{{ ucfirst($authUser['role'] ?? 'User') }} - {{ $authUser['department_name'] ?? 'Department' }}</p>
                            @elseif(Auth::check())
                                <!-- Local Auth: Use Laravel's built-in auth -->
                                <p class="text-sm font-medium text-alertara-900">{{ Auth::user()->full_name ?? Auth::user()->name ?? 'User' }}</p>
                                <p class="text-xs text-alertara-500">{{ Auth::user()->role ?? 'User' }}</p>
                            @else
                                <!-- Not authenticated -->
                                <p class="text-sm font-medium text-alertara-900">Guest</p>
                                <p class="text-xs text-alertara-500">Not logged in</p>
                            @endif
                        </div>
                        <div class="w-9 h-9 bg-gradient-to-br from-alertara-500 to-alertara-700 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user text-white text-xs"></i>
                        </div>
                        <i class="fas fa-chevron-down text-alertara-600 text-xs" style="transition: transform 0.3s ease;"></i>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="profileMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50">
                        <a href="#profile" class="block px-4 py-2 text-sm text-alertara-700 hover:bg-alertara-50 transition-colors">
                            <i class="fas fa-user-circle mr-2"></i>My Profile
                        </a>
                        <button onclick="performLogout()" class="w-full text-left px-4 py-2 text-sm text-alertara-700 hover:bg-alertara-50 transition-colors">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    // Perform logout with CSRF token
    function performLogout() {
        // Clear all client-side storage (localStorage, sessionStorage)
        localStorage.clear();
        sessionStorage.clear();

        // Also clear specific auth-related keys if they exist
        localStorage.removeItem('jwt_token');
        localStorage.removeItem('auth_user');
        localStorage.removeItem('otp_timer');
        sessionStorage.removeItem('jwt_token');
        sessionStorage.removeItem('auth_user');

        // Get CSRF token from meta tag
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                      document.querySelector('input[name="_token"]')?.value;

        if (!token) {
            console.error('CSRF token not found');
            console.log('Clearing client-side storage and redirecting to login');
            // Fallback: redirect to login
            window.location.href = '{{ app()->environment() === "production" ? "https://login.alertaraqc.com" : "/login" }}';
            return;
        }

        // Send logout request with proper redirect handling
        fetch('{{ route("logout") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            redirect: 'follow'  // Follow redirects
        })
        .then(response => {
            console.log('Logout request successful, clearing client-side storage');
            // If response was redirected, the URL will be in response.url
            if (response.ok || response.redirected) {
                // Redirect to the final URL (after any server redirects)
                window.location.href = response.url;
            } else {
                throw new Error('Logout failed');
            }
        })
        .catch(error => {
            console.error('Logout error:', error);
            console.log('Logout failed, but clearing client-side storage anyway');
            // Fallback: redirect to login based on environment
            const redirectUrl = '{{ app()->environment() === "production" ? "https://login.alertaraqc.com" : "/login" }}';
            window.location.href = redirectUrl;
        });
    }

    // Fullscreen toggle
    function toggleFullscreen() {
        const fullscreenToggle = document.getElementById('fullscreenToggle');
        const icon = fullscreenToggle.querySelector('i');

        if (!document.fullscreenElement) {
            // Enter fullscreen
            document.documentElement.requestFullscreen().catch(err => {
                console.error(`Error attempting to enable fullscreen: ${err.message}`);
            });
            icon.classList.remove('fa-expand');
            icon.classList.add('fa-compress');
        } else {
            // Exit fullscreen
            document.exitFullscreen();
            icon.classList.remove('fa-compress');
            icon.classList.add('fa-expand');
        }
    }

    // Update fullscreen button when fullscreen state changes
    document.addEventListener('fullscreenchange', function() {
        const fullscreenToggle = document.getElementById('fullscreenToggle');
        const icon = fullscreenToggle.querySelector('i');

        if (document.fullscreenElement) {
            icon.classList.remove('fa-expand');
            icon.classList.add('fa-compress');
        } else {
            icon.classList.remove('fa-compress');
            icon.classList.add('fa-expand');
        }
    });

    // Profile dropdown toggle
    document.addEventListener('DOMContentLoaded', function() {
        const profileToggle = document.getElementById('profileToggle');
        const profileMenu = document.getElementById('profileMenu');
        const fullscreenToggle = document.getElementById('fullscreenToggle');
        const chevron = profileToggle.querySelector('.fa-chevron-down');

        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            profileMenu.classList.toggle('hidden');

            // Rotate chevron
            if (profileMenu.classList.contains('hidden')) {
                chevron.style.transform = 'rotate(0deg)';
            } else {
                chevron.style.transform = 'rotate(180deg)';
            }
        });

        fullscreenToggle.addEventListener('click', toggleFullscreen);

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target) && !profileMenu.contains(e.target)) {
                profileMenu.classList.add('hidden');
                chevron.style.transform = 'rotate(0deg)';
            }
        });
    });
</script>
