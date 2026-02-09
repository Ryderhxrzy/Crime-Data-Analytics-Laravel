<section id="submit-tip" class="py-16 bg-gray-50">
    <div class="container mx-auto px-4 max-w-2xl">
        <h2 class="text-4xl font-bold text-gray-900 mb-6">Report Anonymously</h2>
        <p class="text-gray-600 mb-8">Help keep your community safe. All submissions are confidential.</p>

        <!-- Toast Container -->
        <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

        <form id="tipForm" class="space-y-6 bg-white p-8 rounded-lg border border-gray-200 shadow-sm">
            @csrf

            {{-- Crime Type --}}
            <div>
                <label for="crime_type" class="block text-sm font-medium text-gray-900 mb-2">
                    Crime Type <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="crime_type"
                    name="crime_type"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-alertara-700 focus:ring-2 focus:ring-alertara-100 bg-gray-50"
                    placeholder="e.g., Robbery, Theft, Assault..."
                    maxlength="100">
                <p class="text-xs text-gray-500 mt-2">What type of crime did you witness or learn about?</p>
            </div>

            {{-- Location --}}
            <div>
                <label for="location" class="block text-sm font-medium text-gray-900 mb-2">
                    Location <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="location"
                    name="location"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-alertara-700 focus:ring-2 focus:ring-alertara-100 bg-gray-50"
                    placeholder="e.g., Barangay name, Street, Area..."
                    maxlength="500">
                <p class="text-xs text-gray-500 mt-2">Where did this incident occur?</p>
            </div>

            {{-- Date & Time --}}
            <div>
                <label for="date_of_crime" class="block text-sm font-medium text-gray-900 mb-2">
                    Date & Time
                </label>
                <input
                    type="datetime-local"
                    id="date_of_crime"
                    name="date_of_crime"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-alertara-700 focus:ring-2 focus:ring-alertara-100 bg-gray-50"
                    placeholder="Select date and time">
                <p class="text-xs text-gray-500 mt-2">When did this occur? (Optional)</p>
            </div>

            {{-- Details --}}
            <div>
                <label for="tip_content" class="block text-sm font-medium text-gray-900 mb-2">
                    Details <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="tip_content"
                    name="tip_content"
                    rows="6"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-alertara-700 focus:ring-2 focus:ring-alertara-100 bg-gray-50 resize-none"
                    placeholder="Describe what happened in detail. Include any information that might help (suspect description, vehicle details, etc.)"
                    minlength="10"
                    maxlength="2000"></textarea>
                <p class="text-xs text-gray-500 mt-2">Provide as much detail as possible to help authorities</p>
            </div>

            {{-- Info Box --}}
            <div class="flex gap-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <p class="text-sm text-blue-800">
                    Your submission is completely anonymous. Do not include personal information.
                </p>
            </div>

            {{-- Captcha --}}
            <div class="flex justify-center">
                <div class="cf-turnstile" data-sitekey="{{ config('captcha.submit_tip_sitekey') }}" data-theme="light" data-callback="onCaptchaSuccess" style="margin: 10px 0;"></div>
            </div>
            <input type="hidden" name="cf-turnstile-response" id="cf-turnstile-response" value="">

            {{-- Submit Button --}}
            <button type="submit" id="submitBtn" class="w-full bg-alertara-700 hover:bg-alertara-800 text-white py-3 px-4 rounded-lg font-medium transition-colors shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-paper-plane mr-2"></i><span id="submitText">Submit Tip</span>
            </button>
        </form>
    </div>
</section>

<script>
// Cloudflare Turnstile callback
function onCaptchaSuccess(token) {
    document.getElementById('cf-turnstile-response').value = token;
}

// Toast notification function
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');

    // Determine colors based on type
    let bgColor = 'bg-green-500';
    let icon = '✓';
    if (type === 'error') {
        bgColor = 'bg-red-500';
        icon = '✕';
    } else if (type === 'warning') {
        bgColor = 'bg-yellow-500';
        icon = '⚠';
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `${bgColor} text-white px-6 py-4 rounded-lg shadow-lg flex items-center gap-3 animate-fade-in max-w-sm`;
    toast.innerHTML = `
        <span class="text-xl font-bold">${icon}</span>
        <span class="flex-1">${message}</span>
        <button class="ml-2 hover:opacity-80 transition-opacity" onclick="this.parentElement.remove()">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>
    `;

    toastContainer.appendChild(toast);

    // Auto remove after 4 seconds
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(400px)';
        toast.style.transition = 'all 0.3s ease-in-out';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Add fade-in animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateX(400px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    .animate-fade-in {
        animation: fadeIn 0.3s ease-out;
    }
`;
document.head.appendChild(style);

// Form submission
document.getElementById('tipForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');

    // Disable submit button
    submitBtn.disabled = true;
    submitText.textContent = 'Submitting...';

    try {
        // Get Turnstile token from hidden input
        const captchaToken = document.getElementById('cf-turnstile-response').value;
        if (!captchaToken) {
            throw new Error('Please complete the security verification');
        }

        // Prepare form data
        const formData = new FormData(this);
        const data = {
            crime_type: formData.get('crime_type'),
            location: formData.get('location'),
            date_of_crime: formData.get('date_of_crime') || null,
            details: formData.get('tip_content'),
            'cf-turnstile-response': captchaToken
        };

        // Submit to API
        const response = await fetch('/api/submit-tip', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('[name="_token"]').value
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            // Show success toast
            showToast('Thank you for your tip! We appreciate your help in keeping our community safe.', 'success');

            // Reset form
            this.reset();

            // Reset Turnstile if available
            if (window.turnstile) {
                window.turnstile.reset();
            }
        } else {
            throw new Error(result.message || 'An error occurred');
        }
    } catch (error) {
        // Show error toast
        showToast(error.message, 'error');
    } finally {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitText.textContent = 'Submit Tip';
    }
});
</script>

