<section id="submit-tip" class="py-16 bg-gray-50">
    <div class="container mx-auto px-4 max-w-2xl">
        <h2 class="text-4xl font-bold text-gray-900 mb-6">Report Anonymously</h2>
        <p class="text-gray-600 mb-8">Help keep your community safe. All submissions are confidential.</p>

        <form id="tipForm" action="{{ route('submit-tip') }}" method="POST" class="space-y-4 bg-white p-6 rounded-lg border border-gray-200">
            @csrf

            <div>
                <label for="tip_content" class="block text-sm font-medium text-gray-900 mb-2">Description *</label>
                <textarea
                    id="tip_content"
                    name="tip_content"
                    rows="4"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-alertara-700"
                    placeholder="What did you see or know?..."
                    minlength="10"
                    maxlength="2000"></textarea>
            </div>

            <div>
                <label for="location" class="block text-sm font-medium text-gray-900 mb-2">Location</label>
                <input
                    type="text"
                    id="location"
                    name="location"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-alertara-700"
                    placeholder="Where did this occur?"
                    maxlength="500">
            </div>

            <div>
                <label for="contact_info" class="block text-sm font-medium text-gray-900 mb-2">Contact (Optional)</label>
                <input
                    type="text"
                    id="contact_info"
                    name="contact_info"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-alertara-700"
                    placeholder="Phone or email"
                    maxlength="255">
            </div>

            <div class="flex justify-center">
                <div class="cf-turnstile" data-sitekey="0x4AAAAAACXojZBmrLtVaz3n"></div>
            </div>

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded p-3">
                    <p class="text-red-800 text-sm"><strong>Error:</strong> {{ $errors->first() }}</p>
                </div>
            @endif

            <button type="submit" class="w-full bg-alertara-700 hover:bg-alertara-800 text-white py-2 px-4 rounded-lg font-medium transition-colors">
                <i class="fas fa-paper-plane mr-2"></i>Submit
            </button>
        </form>
    </div>
</section>
