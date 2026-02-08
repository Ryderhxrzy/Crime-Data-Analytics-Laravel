<section id="submit-tip" class="py-20 bg-gray-50">
    <div class="container mx-auto px-4 max-w-7xl">
        <!-- Section Header -->
        <div class="max-w-2xl mx-auto text-center mb-12">
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Submit Anonymous Tip</h2>
            <p class="text-lg md:text-xl text-gray-600">
                Help keep our community safe. All submissions are anonymous and confidential.
                Your identity will be protected.
            </p>
        </div>

        <!-- Form Card -->
        <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-8 md:p-12 border border-gray-200">
            <form id="tipForm" action="{{ route('submit-tip') }}" method="POST" class="space-y-6">
                @csrf

                <!-- Tip Description -->
                <div>
                    <label for="tip_content" class="block text-sm font-semibold text-gray-800 mb-2">
                        Tip Description *
                    </label>
                    <textarea
                        id="tip_content"
                        name="tip_content"
                        rows="5"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-alertara-500 focus:ring-2 focus:ring-alertara-200 transition-all duration-300 resize-vertical"
                        placeholder="Describe what you witnessed or know about. Please provide as much detail as possible..."
                        minlength="10"
                        maxlength="2000"></textarea>
                    <p class="text-sm text-gray-500 mt-1">Minimum 10 characters, maximum 2000 characters</p>
                </div>

                <!-- Location Field -->
                <div>
                    <label for="location" class="block text-sm font-semibold text-gray-800 mb-2">
                        Location
                    </label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-alertara-500 focus:ring-2 focus:ring-alertara-200 transition-all duration-300"
                        placeholder="Where did this occur? (Address, landmark, barangay, etc.)"
                        maxlength="500">
                </div>

                <!-- Contact Info -->
                <div>
                    <label for="contact_info" class="block text-sm font-semibold text-gray-800 mb-2">
                        Contact Information <span class="text-gray-500 font-normal">(Optional)</span>
                    </label>
                    <input
                        type="text"
                        id="contact_info"
                        name="contact_info"
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-alertara-500 focus:ring-2 focus:ring-alertara-200 transition-all duration-300"
                        placeholder="Phone or email (optional, only if you want follow-up contact)"
                        maxlength="255">
                    <p class="text-sm text-gray-500 mt-1">Leave blank to remain completely anonymous</p>
                </div>

                <!-- Turnstile CAPTCHA -->
                <div class="flex justify-center">
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAACXojZBmrLtVaz3n"></div>
                </div>

                <!-- Privacy Notice -->
                <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4 flex gap-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-lock text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-blue-800">
                            <strong>Your privacy is protected.</strong> Your submission is completely anonymous.
                            Contact information is optional and will only be used to follow up on your tip if you provide it.
                            We do not share your information with third parties.
                        </p>
                    </div>
                </div>

                <!-- Errors Display -->
                @if ($errors->any())
                    <div class="bg-red-50 border-2 border-red-200 rounded-lg p-4">
                        <p class="text-red-800 font-semibold mb-2">Please fix the following errors:</p>
                        <ul class="list-disc list-inside text-red-700 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Submit Button -->
                <button
                    type="submit"
                    class="w-full bg-alertara-600 hover:bg-alertara-700 text-white py-3 px-6 rounded-lg font-semibold transition-all duration-300 flex items-center justify-center gap-2 hover:shadow-lg active:scale-95">
                    <i class="fas fa-paper-plane"></i>
                    Submit Tip Anonymously
                </button>

                <!-- Additional Info -->
                <p class="text-center text-sm text-gray-600 border-t border-gray-200 pt-4 mt-4">
                    For emergency situations, please call <strong>911</strong> immediately.
                    This form is for non-emergency information only.
                </p>
            </form>
        </div>
    </div>
</section>
