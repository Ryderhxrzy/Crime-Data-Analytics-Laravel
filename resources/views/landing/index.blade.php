<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Crime Monitoring System - Real-time crime data visualization for Quezon City">
    <title>Crime Monitor QC - Quezon City Crime Monitoring System</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Leaflet Maps CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Leaflet Heatmap Plugin -->
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

    <!-- Cloudflare Turnstile CAPTCHA -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    @include('landing.components.navigation')

    <!-- Hero Section -->
    @include('landing.sections.hero')

    <!-- About Section -->
    @include('landing.sections.about')

    <!-- Crime Map Section -->
    @include('landing.sections.map')

    <!-- Submit Tip Section -->
    @include('landing.sections.submit-tip')

    <!-- Footer -->
    @include('landing.components.footer')

    <!-- Toast Notifications -->
    @if ($message = Session::get('success'))
        <div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-2 z-50" id="successToast">
            <i class="fas fa-check-circle"></i>
            <span>{{ $message }}</span>
            <button onclick="document.getElementById('successToast').remove()" class="ml-4">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    @if ($message = Session::get('error'))
        <div class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-2 z-50" id="errorToast">
            <i class="fas fa-exclamation-circle"></i>
            <span>{{ $message }}</span>
            <button onclick="document.getElementById('errorToast').remove()" class="ml-4">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    <!-- Map Initialization Script -->
    <script src="{{ asset('js/landing-map.js') }}"></script>
</body>
</html>
