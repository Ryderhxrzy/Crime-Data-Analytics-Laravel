<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Login - Crime Data Analytics</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #374151;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .email-header {
            background: linear-gradient(135deg, #4c8a89 0%, #3a6b6a 100%);
            color: #ffffff;
            padding: 40px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .email-body {
            padding: 40px 30px;
        }
        .alert-box {
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
            border-left: 4px solid #f59e0b;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
        }
        .alert-box p {
            margin: 8px 0;
            color: #92400e;
            font-weight: 500;
        }
        .otp-box {
            background-color: #f0fdf4;
            border: 2px dashed #4c8a89;
            border-radius: 8px;
            padding: 24px;
            margin: 24px 0;
            text-align: center;
        }
        .otp-code {
            font-size: 48px;
            font-weight: 700;
            color: #4c8a89;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
            margin: 0;
        }
        .otp-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .info-section {
            background-color: #f0fdf4;
            border: 1px solid #dcfce7;
            border-left: 4px solid #4c8a89;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
        }
        .info-section h3 {
            margin: 0 0 12px 0;
            color: #155e75;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-section p {
            margin: 8px 0;
            color: #164e63;
            font-size: 14px;
        }
        .security-info {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
        }
        .security-info h4 {
            margin: 0 0 12px 0;
            color: #7f1d1d;
            font-size: 14px;
            font-weight: 600;
        }
        .security-info p {
            margin: 8px 0;
            color: #991b1b;
            font-size: 13px;
        }
        .email-footer {
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }
        .divider {
            border: 0;
            border-top: 1px solid #e5e7eb;
            margin: 20px 0;
        }
        strong {
            color: #1f2937;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>🔐 Verify Your Login</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p>Hello <strong>{{ $user->full_name }}</strong>,</p>

            <p>We detected an attempt to access your account from a new device. Your account has <strong>Two-Factor Authentication</strong> enabled for security. Please verify this login with the code below.</p>

            <!-- 2FA Info Box -->
            <div class="info-section">
                <h3>🔐 Two-Factor Authentication</h3>
                <p>Your account is protected with Two-Factor Authentication enabled. This adds an extra layer of security to your account.</p>
            </div>

            <!-- Alert Box -->
            <div class="alert-box">
                <p>⚠️ This is a security verification. If this wasn't you, please ignore this email and your account will remain secure.</p>
            </div>

            <!-- OTP Code -->
            <div class="otp-box">
                <div class="otp-label">Your Verification Code</div>
                <p class="otp-code">{{ $otpCode }}</p>
            </div>

            <p style="text-align: center; color: #6b7280; font-size: 13px;">
                Enter this code in the verification page to complete your login.
            </p>

            <hr class="divider">

            <!-- Login Details -->
            <div class="info-section">
                <h3>📋 Login Details</h3>
                <p><strong>Email:</strong> {{ $user->email }}</p>
                <p><strong>New Device IP:</strong> {{ $ipAddress }}</p>
                <p><strong>Time:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
            </div>

            <!-- Security Warning -->
            <div class="security-info">
                <h4>⚠️ Important Security Notice</h4>
                <p>This verification code will expire in <strong>10 minutes</strong>.</p>
                <p style="margin-bottom: 0;">If you did not attempt to login, your account may be compromised. Please change your password immediately and contact your administrator.</p>
            </div>

            <!-- Support -->
            <p style="margin-top: 24px; color: #6b7280; font-size: 13px;">
                If you need assistance, please contact the administrator at
                <strong>{{ config('app.support_email', 'admin@alertaraqc.com') }}</strong>
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p style="margin: 0;">Crime Data Analytics &copy; {{ date('Y') }}</p>
            <p style="margin: 8px 0 0;">This is an automated security alert. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
