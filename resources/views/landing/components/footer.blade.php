<footer class="bg-gray-900 text-gray-300 py-12 border-t border-gray-800">
    <div class="container mx-auto px-4 max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
            <!-- Brand Section -->
            <div>
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-alertara-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-shield-alt text-white"></i>
                    </div>
                    <span class="text-xl font-bold text-white">Crime Monitor QC</span>
                </div>
                <p class="text-sm text-gray-400">
                    Real-time crime monitoring and community safety awareness for Quezon City.
                </p>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-white font-semibold mb-4">Quick Links</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="#home" class="hover:text-alertara-400 transition-colors">Home</a></li>
                    <li><a href="#about" class="hover:text-alertara-400 transition-colors">About</a></li>
                    <li><a href="#map" class="hover:text-alertara-400 transition-colors">Crime Map</a></li>
                    <li><a href="#submit-tip" class="hover:text-alertara-400 transition-colors">Submit Tip</a></li>
                </ul>
            </div>

            <!-- Account -->
            <div>
                <h4 class="text-white font-semibold mb-4">Account</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('login') }}" class="hover:text-alertara-400 transition-colors">Login</a></li>
                    <li><a href="#" class="hover:text-alertara-400 transition-colors">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-alertara-400 transition-colors">Terms of Service</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="text-white font-semibold mb-4">Contact</h4>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-start gap-2">
                        <i class="fas fa-phone text-alertara-500 mt-1 flex-shrink-0"></i>
                        <span>(02) 8888 - 0000</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-envelope text-alertara-500 mt-1 flex-shrink-0"></i>
                        <span>info@qcpd.gov.ph</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-map-marker-alt text-alertara-500 mt-1 flex-shrink-0"></i>
                        <span>Quezon City, Philippines</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Divider -->
        <hr class="border-gray-800 mb-8">

        <!-- Bottom Footer -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 text-sm text-gray-400">
            <p>
                &copy; 2026 Crime Monitor QC. All rights reserved.
            </p>
            <p>
                Data sourced from official Quezon City Police Department records.
            </p>
        </div>
    </div>
</footer>
