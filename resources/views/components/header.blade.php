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
                    <button id="messagesBtn" class="p-2 text-gray-600 hover:bg-gray-100 rounded-md relative transition-colors">
                        <i class="fas fa-envelope text-lg"></i>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-danger-500 rounded-full"></span>
                    </button>

                    <!-- Messages Dropdown -->
                    <div id="messagesDropdown" class="hidden absolute top-full mt-2 -right-40 w-96 bg-white rounded-lg shadow-2xl border border-gray-200 z-50 overflow-hidden">
                        <!-- Arrow pointing up -->
                        <div class="absolute -top-2 left-12 w-4 h-4 bg-white border-t border-l border-gray-200 rotate-45"></div>

                        <!-- Dropdown Header -->
                        <div class="bg-alertara-600 text-white px-4 py-3 flex justify-between items-center">
                            <h3 class="font-semibold"><i class="fas fa-envelope mr-2"></i>Messages</h3>
                        </div>

                        <!-- Dropdown Body -->
                        <div class="max-h-96 overflow-y-auto">
                            <!-- Message Item 1 -->
                            <div class="border-b border-gray-100 px-4 py-3 hover:bg-gray-50 transition-colors cursor-pointer">
                                <div class="flex gap-3">
                                    <div class="w-10 h-10 bg-alertara-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-user text-alertara-600"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-sm text-gray-900">John Smith</p>
                                        <p class="text-xs text-gray-600 mt-1">Regarding the incident report from yesterday. Please review the attached evidence document.</p>
                                        <span class="text-xs text-gray-500 mt-2 block">2 hours ago</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Item 2 -->
                            <div class="border-b border-gray-100 px-4 py-3 hover:bg-gray-50 transition-colors cursor-pointer">
                                <div class="flex gap-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-user text-blue-600"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-sm text-gray-900">Maria Garcia</p>
                                        <p class="text-xs text-gray-600 mt-1">Meeting rescheduled to 3:00 PM tomorrow. All crime analysis team members are requested to attend.</p>
                                        <span class="text-xs text-gray-500 mt-2 block">5 hours ago</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Item 3 -->
                            <div class="border-b border-gray-100 px-4 py-3 hover:bg-gray-50 transition-colors cursor-pointer">
                                <div class="flex gap-3">
                                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-user text-green-600"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-sm text-gray-900">Robert Chen</p>
                                        <p class="text-xs text-gray-600 mt-1">The crime hotspot analysis has been completed. Results are available in the dashboard.</p>
                                        <span class="text-xs text-gray-500 mt-2 block">1 day ago</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Item 4 -->
                            <div class="px-4 py-3 hover:bg-gray-50 transition-colors cursor-pointer">
                                <div class="flex gap-3">
                                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-user text-orange-600"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-sm text-gray-900">Admin Team</p>
                                        <p class="text-xs text-gray-600 mt-1">System maintenance scheduled for this weekend. Some features may be temporarily unavailable.</p>
                                        <span class="text-xs text-gray-500 mt-2 block">2 days ago</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dropdown Footer -->
                        <div class="bg-gray-50 border-t border-gray-200 px-4 py-3">
                            <button class="text-alertara-600 hover:text-alertara-700 text-sm font-medium transition-colors">View All Messages →</button>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="relative">
                    <button id="notificationsBtn" class="p-2 text-gray-600 hover:bg-gray-100 rounded-md relative transition-colors">
                        <i class="fas fa-bell text-lg"></i>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-danger-500 rounded-full"></span>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div id="notificationsDropdown" class="hidden absolute top-full mt-2 -right-40 w-96 bg-white rounded-lg shadow-2xl border border-gray-200 z-50 overflow-hidden">
                        <!-- Arrow pointing up -->
                        <div class="absolute -top-2 left-12 w-4 h-4 bg-white border-t border-l border-gray-200 rotate-45"></div>

                        <!-- Dropdown Header -->
                        <div class="bg-danger-600 text-white px-4 py-3">
                            <h3 class="font-semibold"><i class="fas fa-bell mr-2"></i>Notifications</h3>
                        </div>

                        <!-- Dropdown Body -->
                        <div class="max-h-96 overflow-y-auto">
                            <!-- Notification Item 1 - Critical -->
                            <div class="border-b border-gray-100 px-4 py-3 hover:bg-danger-50 transition-colors cursor-pointer">
                                <div class="flex gap-3">
                                    <i class="fas fa-exclamation-circle text-danger-600 text-lg mt-1 flex-shrink-0"></i>
                                    <div class="flex-1">
                                        <p class="font-semibold text-sm text-gray-900">High Crime Alert</p>
                                        <p class="text-xs text-gray-600 mt-1">Significant increase in robberies detected in Barangay 30. Immediate investigation required.</p>
                                        <span class="text-xs text-gray-500 mt-2 block">30 mins ago</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification Item 2 - Warning -->
                            <div class="border-b border-gray-100 px-4 py-3 hover:bg-yellow-50 transition-colors cursor-pointer">
                                <div class="flex gap-3">
                                    <i class="fas fa-triangle-exclamation text-warning-500 text-lg mt-1 flex-shrink-0"></i>
                                    <div class="flex-1">
                                        <p class="font-semibold text-sm text-gray-900">Data Sync Warning</p>
                                        <p class="text-xs text-gray-600 mt-1">Law Enforcement integration sync delayed. Last sync was 45 minutes ago.</p>
                                        <span class="text-xs text-gray-500 mt-2 block">1 hour ago</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification Item 3 - Info -->
                            <div class="border-b border-gray-100 px-4 py-3 hover:bg-blue-50 transition-colors cursor-pointer">
                                <div class="flex gap-3">
                                    <i class="fas fa-info-circle text-info-500 text-lg mt-1 flex-shrink-0"></i>
                                    <div class="flex-1">
                                        <p class="font-semibold text-sm text-gray-900">Report Generated</p>
                                        <p class="text-xs text-gray-600 mt-1">Monthly crime statistics report has been generated and is ready for review.</p>
                                        <span class="text-xs text-gray-500 mt-2 block">3 hours ago</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification Item 4 - Success -->
                            <div class="px-4 py-3 hover:bg-green-50 transition-colors cursor-pointer">
                                <div class="flex gap-3">
                                    <i class="fas fa-check-circle text-success-500 text-lg mt-1 flex-shrink-0"></i>
                                    <div class="flex-1">
                                        <p class="font-semibold text-sm text-gray-900">System Update Complete</p>
                                        <p class="text-xs text-gray-600 mt-1">All system updates have been successfully installed. No issues detected.</p>
                                        <span class="text-xs text-gray-500 mt-2 block">1 day ago</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dropdown Footer -->
                        <div class="bg-gray-50 border-t border-gray-200 px-4 py-3">
                            <button class="text-danger-600 hover:text-danger-700 text-sm font-medium transition-colors">View All Notifications →</button>
                        </div>
                    </div>
                </div>

                <!-- Real-time Status -->
                <div class="flex items-center space-x-2 px-3 py-1 rounded-full bg-gray-100 border border-gray-200">
                    <i id="header-realtime-icon" class="fas fa-circle text-xs text-gray-400"></i>
                    <span id="header-realtime-text" class="text-xs text-gray-600 font-medium">Checking...</span>
                </div>

                <!-- Connect Sample Data -->
                <button id="connect-sample-btn" class="p-2 text-gray-600 hover:bg-gray-100 rounded-md relative" title="Connect to Real-time Crime Data">
                    <i class="fas fa-plug text-lg"></i>
                </button>

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
                    </button>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Real-time Status Scripts -->
<script>
// Real-time status indicator
function updateRealtimeStatus(connected, message = '') {
    // Update header status
    const headerIcon = document.getElementById('header-realtime-icon');
    const headerText = document.getElementById('header-realtime-text');
    
    if (headerIcon && headerText) {
        if (connected) {
            headerIcon.className = 'fas fa-circle text-xs text-green-500';
            headerText.textContent = 'Live';
            headerText.className = 'text-xs text-green-600 font-medium';
            headerText.setAttribute('title', 'Connected to real-time crime data - receiving live updates');
        } else {
            headerIcon.className = 'fas fa-circle text-xs text-yellow-500';
            headerText.textContent = 'Offline';
            headerText.className = 'text-xs text-red-600 font-medium';
            headerText.setAttribute('title', 'Not connected to real-time crime data - click plug button to connect');
        }
    }
}

// Auto-connect to real-time channel
function autoConnectRealtime() {
    if (typeof window.Echo !== 'undefined' && window.Echo) {
        try {
            console.log('🔌 Auto-connecting to real-time channel...');
            
            // Update status to connecting
            updateRealtimeStatus(false);
            
            // Subscribe to crime-incidents channel automatically
            const channel = window.Echo.channel('crime-incidents');
            
            // Set up event listeners
            channel.subscribed(function() {
                console.log('✅ Auto-connected to crime-incidents channel');
                updateRealtimeStatus(true);
            });
            
            channel.error(function(error) {
                console.error('❌ Real-time connection error:', error);
                updateRealtimeStatus(false);
            });
            
            // Also check Pusher connection directly
            setTimeout(() => {
                checkRealtimeConnection();
            }, 2000);
            
        } catch (error) {
            console.error('❌ Failed to auto-connect:', error);
            updateRealtimeStatus(false);
        }
    } else {
        console.warn('⚠️ Echo not available - real-time features disabled');
        updateRealtimeStatus(false);
    }
}

// Check real-time connection status
function checkRealtimeConnection() {
    if (typeof window.Echo !== 'undefined' && window.Echo) {
        try {
            const pusher = window.Echo.connector.pusher;
            if (pusher && pusher.connection) {
                const state = pusher.connection.state;
                updateRealtimeStatus(state === 'connected');
            }
        } catch (error) {
            updateRealtimeStatus(false);
        }
    } else {
        updateRealtimeStatus(false);
    }
}

// Auto-connect immediately when page loads
setTimeout(() => {
    updateRealtimeStatus(false); // Show checking status first
    autoConnectRealtime();
}, 500);

// Check connection every 5 seconds
setInterval(checkRealtimeConnection, 5000);

// Connect sample data button functionality
const connectSampleBtn = document.getElementById('connect-sample-btn');
if (connectSampleBtn) {
    // Add hover effect
    connectSampleBtn.addEventListener('mouseenter', function() {
        this.setAttribute('title', 'Connect to Real-time Crime Data - Click to establish connection');
    });
    
    connectSampleBtn.addEventListener('mouseleave', function() {
        this.setAttribute('title', 'Connect to Real-time Crime Data');
    });
    
    connectSampleBtn.addEventListener('click', function() {
        // Toggle button state
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin text-lg"></i>';
        this.setAttribute('title', 'Connecting to real-time crime data...');
        
        // Force reconnection
        console.log('🔌 Manual connection triggered...');
        updateRealtimeStatus(false);
        
        // Re-attempt connection
        setTimeout(() => {
            autoConnectRealtime();
            
            // Reset button after 3 seconds
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-plug text-lg"></i>';
                this.setAttribute('title', 'Connect to Real-time Crime Data - Click to establish connection');
            }, 3000);
        }, 1000);
    });
}
</script>
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

        // Messages and Notifications Dropdown Handlers
        setupDropdowns();
    });

    // Setup Dropdowns
    function setupDropdowns() {
        const messagesBtn = document.getElementById('messagesBtn');
        const notificationsBtn = document.getElementById('notificationsBtn');
        const messagesDropdown = document.getElementById('messagesDropdown');
        const notificationsDropdown = document.getElementById('notificationsDropdown');

        // Messages Dropdown
        if (messagesBtn && messagesDropdown) {
            messagesBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                // Toggle messages dropdown
                messagesDropdown.classList.toggle('hidden');
                // Hide notifications dropdown
                notificationsDropdown?.classList.add('hidden');
            });
        }

        // Notifications Dropdown
        if (notificationsBtn && notificationsDropdown) {
            notificationsBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                // Toggle notifications dropdown
                notificationsDropdown.classList.toggle('hidden');
                // Hide messages dropdown
                messagesDropdown?.classList.add('hidden');
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (messagesBtn && !messagesBtn.contains(e.target) && !messagesDropdown?.contains(e.target)) {
                messagesDropdown?.classList.add('hidden');
            }
            if (notificationsBtn && !notificationsBtn.contains(e.target) && !notificationsDropdown?.contains(e.target)) {
                notificationsDropdown?.classList.add('hidden');
            }
        });
    }
</script>
