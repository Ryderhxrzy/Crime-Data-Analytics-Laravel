<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Crime Department</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Cloudflare Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <div class="flex-1 flex items-center justify-center px-4 py-8 sm:py-12">
        <div class="max-w-md w-full bg-white p-6 sm:p-8 rounded-lg border border-gray-200 space-y-6">
        <div class="text-center">
            <!-- Logo -->
            <div class="flex justify-center mb-4">  
                <img src="{{ asset('images/logo.svg') }}" alt="Crime Data Analytics Logo" class="h-16 sm:h-20 w-auto">
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">
                Crime Data Analytics
            </h2>
            <p class="mt-2 text-xs sm:text-sm text-gray-600">
                Sign in to continue
            </p>
        </div>
        
        <form class="space-y-4" action="{{ route('login.submit') }}" method="POST" id="loginForm">
            @csrf

            <div>
                <label for="email" class="sr-only">Email address</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 z-10 text-sm sm:text-base"></i>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           class="pl-10 appearance-none relative block w-full px-3 py-2 sm:py-2.5 border border-gray-300 placeholder-gray-500 text-gray-900 bg-white rounded-md focus:outline-none focus:ring-alertara-500 focus:border-alertara-500 text-sm sm:text-base"
                           placeholder="youremail@alertaraqc.com" value="{{ old('email') }}">
                </div>
            </div>

            <div>
                <label for="password" class="sr-only">Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 z-10 text-sm sm:text-base"></i>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="pl-10 pr-10 appearance-none relative block w-full px-3 py-2 sm:py-2.5 border border-gray-300 placeholder-gray-500 text-gray-900 bg-white rounded-md focus:outline-none focus:ring-alertara-500 focus:border-alertara-500 text-sm sm:text-base"
                           placeholder="Your password">
                    <i class="fas fa-eye absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 cursor-pointer transition-colors duration-300 text-sm sm:text-base" onclick="togglePassword()"></i>
                </div>
            </div>

            <div class="text-right">
                <a href="#" class="text-xs sm:text-sm text-alertara-600 hover:text-alertara-500 transition-colors duration-300">
                    Forgot password?
                </a>
            </div>
            
            <!-- Cloudflare Turnstile CAPTCHA -->
            <div class="cf-turnstile" data-sitekey="0x4AAAAAACXojZBmrLtVaz3n" data-theme="light" data-callback="onCaptchaSuccess" style="margin: 10px 0;"></div>
            <input type="hidden" name="cf-turnstile-response" id="cf-turnstile-response" value="">
            @if ($errors->has('cf-turnstile-response'))
                <p class="text-sm text-red-600 mt-2">{{ $errors->first('cf-turnstile-response') }}</p>
            @endif

            <div>
                <button type="submit" id="submitBtn"
                        class="group relative w-full flex justify-center py-2 sm:py-2.5 px-4 border border-transparent text-sm sm:text-base font-medium rounded-md text-white bg-alertara-600 hover:bg-alertara-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors duration-300">
                    Sign In
                </button>
            </div>

        </form>

       <!-- Security Notice -->
        <div class="text-center mt-4">
            <p class="text-xs text-gray-500">
                <i class="fas fa-shield-alt text-green-600 mr-1"></i>
                This login is protected by Cloudflare Turnstile
            </p>
        </div>
        </div>
    </div>

    <!-- Include Toastr Notifications -->
    @include('partials.toastr')

    <script>
        // Cloudflare Turnstile callback
        function onCaptchaSuccess(token) {
            document.getElementById('cf-turnstile-response').value = token;
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.fa-eye');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Disable button on form submission
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');

            loginForm.addEventListener('submit', function(e) {
                // Disable the button to prevent double submission
                submitBtn.disabled = true;
                submitBtn.textContent = 'Signing In...';
            });
        });
    </script>
</body>
</html>
