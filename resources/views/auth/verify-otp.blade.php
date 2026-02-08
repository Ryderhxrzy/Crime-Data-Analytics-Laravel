<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP / Crime Department</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/tailwind-config.js') }}"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .otp-input {
            width: 3rem;
            height: 3rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid #d1d5db;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .otp-input:focus {
            outline: none;
            border-color: #4c8a89;
            box-shadow: 0 0 0 3px rgba(76, 138, 137, 0.1);
        }

        .otp-input.filled {
            background-color: #f0fdf4;
            border-color: #4c8a89;
        }

        @media (max-width: 640px) {
            .otp-input {
                width: 2.5rem;
                height: 2.5rem;
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <div class="flex-1 flex items-center justify-center px-4 py-8 sm:py-12">
        <div class="max-w-md w-full bg-white rounded-lg border border-gray-200">
            <!-- Back Button - Top Left Inside Card -->
            <div class="flex items-center justify-between px-6 sm:px-8 pt-4 sm:pt-6 pb-2">
                <a href="{{ route('login') }}" class="text-alertara-600 hover:text-alertara-700 transition-colors duration-300" title="Back to Login">
                    <i class="fas fa-arrow-left text-lg sm:text-xl"></i>
                </a>
                <div class="flex-1"></div>
            </div>

            <!-- Card Content -->
            <div class="px-6 sm:px-8 pb-6 sm:pb-8 space-y-6">
                <div class="text-center">
                    <!-- Logo -->
                    <div class="flex justify-center mb-4">
                        <img src="{{ asset('images/logo.svg') }}" alt="Crime Data Analytics Logo" class="h-16 sm:h-20 w-auto">
                    </div>

                    <!-- 2FA Enabled Message -->
                    <div class="mb-4 inline-block bg-green-50 border border-green-200 rounded-lg px-3 sm:px-4 py-2">
                        <p class="text-xs sm:text-sm text-green-700 font-medium">
                            <i class="fas fa-shield-alt mr-2"></i>Two-Factor Authentication Enabled
                        </p>
                    </div>

                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mt-4">
                        Enter Verification Code
                    </h2>
                    <p class="mt-2 text-xs sm:text-sm text-gray-600">
                        We sent a 6-digit code to your email
                    </p>
                </div>

                <form class="space-y-6" action="{{ route('verify.otp') }}" method="POST" id="otpForm">
                    @csrf

                    <!-- OTP Input - Separate Digits -->
                    <div>
                        <div class="flex gap-2 sm:gap-3 justify-center mb-6">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="0" autocomplete="off">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="1" autocomplete="off">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="2" autocomplete="off">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="3" autocomplete="off">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="4" autocomplete="off">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric" data-index="5" autocomplete="off">
                        </div>
                        <!-- Hidden input to hold the complete OTP -->
                        <input type="hidden" id="otp_code" name="otp_code">
                    </div>

                    @if ($errors->has('otp_code'))
                        <p class="text-sm text-red-600 text-center">{{ $errors->first('otp_code') }}</p>
                    @endif

                    <!-- Timer -->
                    <div class="text-center">
                        <p class="text-sm text-gray-600">
                            Code expires in: <span id="timer" class="font-bold text-alertara-600">5:00</span>
                        </p>
                        <p id="expiredMessage" class="text-sm text-red-600 hidden mt-2">OTP has expired. Please request a new one.</p>
                    </div>

                    <!-- Verify Button -->
                    <div>
                        <button type="submit" id="submitBtn"
                                class="group relative w-full flex justify-center py-2 sm:py-2.5 px-4 border border-transparent text-sm sm:text-base font-medium rounded-md text-white bg-alertara-600 hover:bg-alertara-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors duration-300">
                            Verify Code
                        </button>
                    </div>
                </form>

                <!-- Resend OTP -->
                <div class="text-center space-y-3">
                    <p class="text-sm text-gray-600">Didn't receive the code?</p>
                    <button type="button" id="resendBtn"
                            class="text-sm sm:text-base font-medium text-alertara-600 hover:text-alertara-700 disabled:text-gray-400 disabled:cursor-not-allowed transition-colors duration-300">
                        <i class="fas fa-redo mr-2"></i>Resend Code
                    </button>
                    <p id="resendMessage" class="text-xs sm:text-sm text-gray-500 hidden">You can resend in <span id="resendTimer"></span>s</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Toastr Notifications -->
    @include('partials.toastr')

    <script>
        const OTP_TIMEOUT = 300; // 5 minutes in seconds
        const RESEND_COOLDOWN = 30; // 30 seconds
        const OTP_INPUTS = document.querySelectorAll('.otp-input');
        const OTP_CODE_INPUT = document.getElementById('otp_code');
        let timerInterval = null;
        let timerStartTime = null;

        // Handle individual OTP digit inputs
        OTP_INPUTS.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');

                // Add filled class
                if (this.value) {
                    this.classList.add('filled');
                } else {
                    this.classList.remove('filled');
                }

                // Update hidden input with full OTP
                updateOtpCode();

                // Move to next input
                if (this.value && index < OTP_INPUTS.length - 1) {
                    OTP_INPUTS[index + 1].focus();
                }
            });

            input.addEventListener('keydown', function(e) {
                // Handle backspace
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    OTP_INPUTS[index - 1].focus();
                }

                // Handle arrow keys
                if (e.key === 'ArrowRight' && index < OTP_INPUTS.length - 1) {
                    OTP_INPUTS[index + 1].focus();
                }
                if (e.key === 'ArrowLeft' && index > 0) {
                    OTP_INPUTS[index - 1].focus();
                }

                // Handle paste
                if (e.key === 'v' && e.ctrlKey) {
                    e.preventDefault();
                    navigator.clipboard.readText().then(text => {
                        const digits = text.replace(/[^0-9]/g, '').split('');
                        digits.forEach((digit, i) => {
                            if (i < OTP_INPUTS.length) {
                                OTP_INPUTS[i].value = digit;
                                OTP_INPUTS[i].classList.add('filled');
                                if (i < OTP_INPUTS.length - 1) {
                                    OTP_INPUTS[i].blur();
                                }
                            }
                        });
                        updateOtpCode();
                    });
                }
            });
        });

        function updateOtpCode() {
            const otpCode = Array.from(OTP_INPUTS).map(input => input.value).join('');
            OTP_CODE_INPUT.value = otpCode;
        }

        // Initialize timer
        function initializeTimer() {
            // Use current time as the start for new sessions
            timerStartTime = Date.now();
            localStorage.setItem('otp_timer_start', timerStartTime.toString());
            let secondsRemaining = OTP_TIMEOUT;

            updateTimer(secondsRemaining);

            // Update timer every second using elapsed time from start
            timerInterval = setInterval(() => {
                const elapsedSeconds = Math.floor((Date.now() - timerStartTime) / 1000);
                const remainingSeconds = Math.max(0, OTP_TIMEOUT - elapsedSeconds);

                if (remainingSeconds <= 0) {
                    clearInterval(timerInterval);
                    expireOtp();
                } else {
                    updateTimer(remainingSeconds);
                }
            }, 1000);
        }

        function updateTimer(seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            const formattedTime = `${minutes}:${secs.toString().padStart(2, '0')}`;
            document.getElementById('timer').textContent = formattedTime;
        }

        function expireOtp() {
            document.getElementById('timer').textContent = '00:00';
            document.getElementById('expiredMessage').classList.remove('hidden');
            OTP_INPUTS.forEach(input => input.disabled = true);
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('resendBtn').disabled = false;
        }

        // Resend OTP handler
        document.getElementById('resendBtn').addEventListener('click', async function() {
            const btn = this;
            btn.disabled = true;

            try {
                const response = await fetch('{{ route("otp.resend") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();

                if (response.ok) {
                    // Reset timer and inputs
                    timerStartTime = Date.now();
                    localStorage.setItem('otp_timer_start', timerStartTime.toString());
                    clearInterval(timerInterval);
                    OTP_INPUTS.forEach(input => {
                        input.disabled = false;
                        input.value = '';
                        input.classList.remove('filled');
                    });
                    OTP_CODE_INPUT.value = '';
                    document.getElementById('submitBtn').disabled = false;
                    document.getElementById('expiredMessage').classList.add('hidden');
                    OTP_INPUTS[0].focus();

                    // Reinitialize timer with new start time
                    updateTimer(OTP_TIMEOUT);
                    timerInterval = setInterval(() => {
                        const elapsedSeconds = Math.floor((Date.now() - timerStartTime) / 1000);
                        const secondsRemaining = Math.max(0, OTP_TIMEOUT - elapsedSeconds);

                        if (secondsRemaining <= 0) {
                            clearInterval(timerInterval);
                            expireOtp();
                        } else {
                            updateTimer(secondsRemaining);
                        }
                    }, 1000);

                    toastr.success('New OTP code sent successfully!');

                } else {
                    btn.disabled = false;
                    if (response.status === 429) {
                        // Rate limited
                        showResendCooldown(data.retryAfter || RESEND_COOLDOWN);
                    }
                    toastr.error(data.error || 'Failed to resend OTP');
                }
            } catch (error) {
                console.error('Error resending OTP:', error);
                btn.disabled = false;
                toastr.error('An error occurred. Please try again.');
            }
        });

        function showResendCooldown(seconds) {
            const btn = document.getElementById('resendBtn');
            const msg = document.getElementById('resendMessage');
            const timer = document.getElementById('resendTimer');

            btn.disabled = true;
            msg.classList.remove('hidden');

            let countdown = seconds;
            timer.textContent = countdown;

            const interval = setInterval(() => {
                countdown--;
                timer.textContent = countdown;

                if (countdown <= 0) {
                    clearInterval(interval);
                    btn.disabled = false;
                    msg.classList.add('hidden');
                }
            }, 1000);
        }

        // Disable form submission if OTP is expired
        document.getElementById('otpForm').addEventListener('submit', function(e) {
            const otpCode = OTP_CODE_INPUT.value;
            if (otpCode.length !== 6) {
                e.preventDefault();
                toastr.error('Please enter all 6 digits');
            } else if (document.getElementById('expiredMessage').classList.contains('hidden') === false) {
                e.preventDefault();
                toastr.error('OTP has expired. Please request a new one.');
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Focus first input
            OTP_INPUTS[0].focus();
            initializeTimer();
        });
    </script>
</body>
</html>
