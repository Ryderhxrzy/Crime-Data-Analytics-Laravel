<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Locked - Crime Data Analytics</title>
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
            background-color: #fef2f2;
            border: 1px solid #fed7d7;
            border-left: 4px solid #f87171;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
        }
        .alert-box p {
            margin: 8px 0;
            color: #7f1d1d;
            font-weight: 500;
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
        .cta-button {
            display: inline-block;
            background-color: #4c8a89;
            color: #ffffff !important;
            text-decoration: none !important;
            padding: 12px 28px;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
            transition: background-color 0.3s ease;
        }
        .cta-button:hover {
            background-color: #3a6b6a;
            color: #ffffff !important;
        }
        .cta-button:visited {
            color: #ffffff !important;
        }
        .button-container {
            text-align: center;
        }
        .token-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            color: #64748b;
            font-size: 12px;
        }
        .security-info {
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
            border-left: 4px solid #f59e0b;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
        }
        .security-info h4 {
            margin: 0 0 12px 0;
            color: #92400e;
            font-size: 14px;
            font-weight: 600;
        }
        .security-info p {
            margin: 8px 0;
            color: #78350f;
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
            <h1>🔒 Account Locked</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p>Hello <strong>{{ $user->full_name }}</strong>,</p>

            <p>Your account has been locked due to <strong>3 failed login attempts</strong>. This is a security measure to protect your account from unauthorized access.</p>

            <!-- Alert Box -->
            <div class="alert-box">
                <p>⚠️ Your account is temporarily locked for security reasons.</p>
            </div>

            <!-- Unlock Instructions -->
            <h2 style="color: #1f2937; font-size: 18px; margin: 24px 0 16px;">Unlock Your Account</h2>
            <p>To unlock your account, click the button below:</p>

            <div class="button-container">
                <a href="{{ route('unlock-account', ['token' => $unlockToken]) }}" class="cta-button">
                    🔓 Unlock Account
                </a>
            </div>

            <p style="text-align: center; color: #6b7280; font-size: 13px;">
                Or copy and paste this link into your browser:
            </p>
            <div class="token-box">
                {{ route('unlock-account', ['token' => $unlockToken]) }}
            </div>

            <hr class="divider">

            <!-- Security Information -->
            <div class="info-section">
                <h3>📋 Login Attempt Details</h3>
                <p><strong>Email:</strong> {{ $user->email }}</p>
                <p><strong>IP Address:</strong> {{ $ipAddress }}</p>
                <p><strong>Time:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
            </div>

            <!-- Warning -->
            <div class="security-info">
                <h4>⚠️ Important Security Notice</h4>
                <p>This link will expire in <strong>1 hour</strong>. If you did not attempt to login, please contact the administrator immediately.</p>
                <p style="margin-bottom: 0;">Do not share this link or email with anyone else.</p>
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
