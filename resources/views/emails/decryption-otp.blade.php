<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Decryption OTP</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        h1 {
            margin: 0 0 15px 0;
            font-size: 24px;
            color: #333;
        }
        .header-border {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        p {
            margin: 0 0 15px 0;
            font-size: 15px;
            line-height: 1.8;
        }
        .otp-code {
            font-size: 28px;
            font-weight: bold;
            color: #274d4c;
            letter-spacing: 3px;
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-family: monospace;
        }
        .expires {
            font-size: 13px;
            color: #666;
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-border">
            <h1>Welcome to AlerTaraQC</h1>
        </div>

        <p>Hi {{ $user_name }},</p>

        <p>You requested to decrypt sensitive data. Please use the code below to verify your identity.</p>

        <div class="otp-code">{{ $otp_code }}</div>

        <p class="expires">Valid for {{ $expires_in_minutes }} minutes only.</p>

        <p>Do not share this code with anyone.</p>
    </div>
</body>
</html>
