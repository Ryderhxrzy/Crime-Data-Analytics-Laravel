@php
// Handle JWT token from centralized login URL
if (request()->query('token')) {
    session(['jwt_token' => request()->query('token')]);
}
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Crime Incident - Testing Real-Time</title>
    <!-- Reverb Configuration (passed from server) -->
    <script>
        window.reverbConfig = {
            key: '{{ config("broadcasting.connections.pusher.key") }}',
            cluster: '{{ config("broadcasting.connections.pusher.options.cluster") }}'
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Echo JavaScript for real-time status -->
    @vite(['resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <!-- Header Component -->
    @include('components.header')

    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

    <!-- Sidebar -->
    @include('components.sidebar')

    <!-- Main Content -->
    <main class="lg:ml-72 ml-0 lg:mt-16 mt-16 min-h-screen bg-gray-100">
        <div class="p-6 max-w-2xl mx-auto">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Add Crime Incident</h1>
                <p class="text-gray-600 mt-2">Create a new crime incident for real-time testing</p>
            </div>

            <!-- Success Message -->
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                </div>
            @endif

            <!-- Error Messages -->
            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i>Please fix the errors below:
                    <ul class="mt-2 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Form Card -->
            <div class="bg-white rounded-lg shadow-md p-8">
                <form action="{{ route('crime-incident.store') }}" method="POST">
                    @csrf

                    <!-- Row 1: Title and Category -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-heading mr-1 text-alertara-600"></i>Incident Title
                            </label>
                            <input type="text" name="incident_title" placeholder="e.g., Robbery at 7-11"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"
                                   value="{{ old('incident_title') }}" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag mr-1 text-alertara-600"></i>Crime Category
                            </label>
                            <select name="crime_category_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                                <option value="">Select a category...</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('crime_category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->category_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: Description -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-1 text-alertara-600"></i>Description
                        </label>
                        <textarea name="incident_description" placeholder="Detailed description of the incident..."
                                  rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"
                                  required>{{ old('incident_description') }}</textarea>
                    </div>

                    <!-- Row 3: Date and Time -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-1 text-alertara-600"></i>Incident Date
                            </label>
                            <input type="date" name="incident_date"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"
                                   value="{{ old('incident_date', date('Y-m-d')) }}" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-clock mr-1 text-alertara-600"></i>Incident Time
                            </label>
                            <input type="time" name="incident_time"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"
                                   value="{{ old('incident_time', date('H:i')) }}" required>
                        </div>
                    </div>

                    <!-- Row 4: Location -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-1 text-alertara-600"></i>Latitude
                            </label>
                            <input type="number" step="0.000001" name="latitude" placeholder="e.g., 14.6091"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"
                                   value="{{ old('latitude', '14.6091') }}" required>
                            <small class="text-gray-500">QC Range: 14.5 to 14.8</small>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-compass mr-1 text-alertara-600"></i>Longitude
                            </label>
                            <input type="number" step="0.000001" name="longitude" placeholder="e.g., 121.0245"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"
                                   value="{{ old('longitude', '121.0245') }}" required>
                            <small class="text-gray-500">QC Range: 120.9 to 121.2</small>
                        </div>
                    </div>

                    <!-- Row 5: Address and Barangay -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-home mr-1 text-alertara-600"></i>Address
                            </label>
                            <input type="text" name="address_details" placeholder="Street address..."
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"
                                   value="{{ old('address_details') }}">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map mr-1 text-alertara-600"></i>Barangay
                            </label>
                            <select name="barangay_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                                <option value="">Select a barangay...</option>
                                @foreach ($barangays as $barangay)
                                    <option value="{{ $barangay->id }}" {{ old('barangay_id') == $barangay->id ? 'selected' : '' }}>
                                        {{ $barangay->barangay_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Row 6: Counts -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-users mr-1 text-alertara-600"></i>Victim Count
                            </label>
                            <input type="number" name="victim_count" placeholder="0" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"
                                   value="{{ old('victim_count', 0) }}">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-secret mr-1 text-alertara-600"></i>Suspect Count
                            </label>
                            <input type="number" name="suspect_count" placeholder="0" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent"
                                   value="{{ old('suspect_count', 0) }}">
                        </div>
                    </div>

                    <!-- Row 7: Status -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tasks mr-1 text-alertara-600"></i>Case Status
                            </label>
                            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                                <option value="reported" {{ old('status') == 'reported' ? 'selected' : '' }}>Reported</option>
                                <option value="under_investigation" {{ old('status') == 'under_investigation' ? 'selected' : '' }}>Under Investigation</option>
                                <option value="solved" {{ old('status') == 'solved' ? 'selected' : '' }}>Solved</option>
                                <option value="closed" {{ old('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                                <option value="archived" {{ old('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-check-circle mr-1 text-alertara-600"></i>Clearance Status
                            </label>
                            <select name="clearance_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-alertara-500 focus:border-transparent" required>
                                <option value="uncleared" {{ old('clearance_status') == 'uncleared' ? 'selected' : '' }}>Uncleared</option>
                                <option value="cleared" {{ old('clearance_status') == 'cleared' ? 'selected' : '' }}>Cleared</option>
                            </select>
                        </div>
                    </div>

                    <!-- Testing Instructions -->
                    <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-500 rounded">
                        <p class="text-sm text-blue-700">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Testing Real-Time Updates:</strong>
                        </p>
                        <ol class="text-sm text-blue-700 mt-2 ml-6 list-decimal">
                            <li>Fill in all fields below</li>
                            <li>Click "Create Incident"</li>
                            <li>Switch to <strong>Mapping Page</strong> in another tab</li>
                            <li>Watch the map update in real-time! 🎯</li>
                            <li>Check browser console for debug logs</li>
                            <li>Check header status should show 🟢 Live</li>
                        </ol>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex gap-4">
                        <button type="submit" class="flex-1 bg-alertara-600 hover:bg-alertara-700 text-white font-bold py-3 rounded-lg transition">
                            <i class="fas fa-plus mr-2"></i>Create Incident
                        </button>
                        <a href="{{ route('mapping') }}" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white font-bold py-3 rounded-lg transition text-center">
                            <i class="fas fa-map mr-2"></i>Go to Mapping
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Debug JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
            
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    console.log('🚨 Form submission started...');
                    console.log('📝 Form data:', new FormData(form));
                    
                    // Show loading state
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
                    
                    // Log after submission (for debugging)
                    setTimeout(() => {
                        console.log('✅ Form submitted - Check mapping page for real-time updates');
                        console.log('🔍 Expected: New incident should appear on mapping page');
                        console.log('📊 Expected: Statistics should update in real-time');
                    }, 1000);
                });
            }
        });
    </script>
</body>
</html>
