<!-- Top Navigation Header -->
<nav class="bg-white border-b border-gray-200 fixed left-0 lg:left-72 right-0 top-0 z-[60] h-16">
    <div class="px-4 sm:px-6 lg:px-8 h-full">
        <div class="flex justify-between items-center h-full gap-8">
            <!-- Left: Hamburger Menu & Date/Time -->
            <div class="flex items-center space-x-4">
                <!-- Hamburger Menu Button (Mobile) -->
                <button id="sidebarToggle" class="lg:hidden p-2 rounded-md text-alertara-600 hover:bg-alertara-50">
                    <i class="fas fa-bars text-xl"></i>
                </button>

                <!-- Date & Time -->
                <div class="text-left hidden sm:block">
                    <p class="text-sm font-medium text-alertara-900" id="currentDate"></p>
                    <p class="text-xs text-alertara-500" id="currentTime"></p>
                </div>
            </div>

            <!-- Right: Messages, Notifications, Profile -->
            <div class="flex items-center space-x-6">

                <!-- Messages -->
                <div class="relative">
                    <button class="p-2 text-alertara-600 hover:bg-alertara-50 rounded-md relative">
                        <i class="fas fa-envelope text-lg"></i>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-danger-500 rounded-full"></span>
                    </button>
                </div>

                <!-- Notifications -->
                <div class="relative">
                    <button class="p-2 text-alertara-600 hover:bg-alertara-50 rounded-md relative">
                        <i class="fas fa-bell text-lg"></i>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-danger-500 rounded-full"></span>
                    </button>
                </div>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <button id="profileToggle" class="flex items-center space-x-2 pl-4 border-l border-gray-200 hover:bg-gray-50 rounded py-2 px-2 transition-colors">
                        <div class="text-right hidden sm:block">
                            @php
                                // Check if JWT token exists in session (centralized login)
                                $jwtToken = session('jwt_token');
                                $isJwtAuth = !empty($jwtToken);
                            @endphp
                            @if($isJwtAuth && getUserEmail())
                                <!-- JWT Auth: Use centralized login data -->
                                <p class="text-sm font-medium text-alertara-900">{{ getUserEmail() ?? 'User' }}</p>
                                <p class="text-xs text-alertara-500">{{ ucfirst(getUserRole() ?? 'User') }} - {{ getDepartmentName() ?? 'Department' }}</p>
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
                        <i class="fas fa-chevron-down text-alertara-600 text-xs"></i>
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
    // Update date and time
    function updateDateTime() {
        const now = new Date();
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        const dateStr = now.toLocaleDateString('en-US', options);
        const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });

        document.getElementById('currentDate').textContent = dateStr;
        document.getElementById('currentTime').textContent = timeStr;
    }

    updateDateTime();
    setInterval(updateDateTime, 1000); // Update every second

    // Perform logout with CSRF token
    function performLogout() {
        // Get CSRF token from meta tag
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                      document.querySelector('input[name="_token"]')?.value;

        if (!token) {
            console.error('CSRF token not found');
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
            // Fallback: redirect to login based on environment
            const redirectUrl = '{{ app()->environment() === "production" ? "https://login.alertaraqc.com" : "/login" }}';
            window.location.href = redirectUrl;
        });
    }

    // Profile dropdown toggle
    document.addEventListener('DOMContentLoaded', function() {
        const profileToggle = document.getElementById('profileToggle');
        const profileMenu = document.getElementById('profileMenu');

        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            profileMenu.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target) && !profileMenu.contains(e.target)) {
                profileMenu.classList.add('hidden');
            }
        });
    });
</script>
