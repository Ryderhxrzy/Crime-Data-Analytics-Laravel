<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $kind === 'reset' ? 'Password Reset' : 'Your Staff Account' }} - Crime Data Analytics</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #374151; background-color: #f3f4f6; margin: 0; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .email-header { background: linear-gradient(135deg, #4c8a89 0%, #3a6b6a 100%); color: #ffffff; padding: 36px 20px; text-align: center; }
        .email-header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .email-header p { margin: 6px 0 0; font-size: 14px; opacity: .9; }
        .email-body { padding: 32px 30px; }
        .credentials { background: #f0fdf4; border: 1px solid #dcfce7; border-left: 4px solid #4c8a89; border-radius: 6px; padding: 16px 18px; margin: 20px 0; }
        .credentials table { width: 100%; border-collapse: collapse; }
        .credentials td { padding: 6px 0; font-size: 15px; }
        .credentials td.label { color: #6b7280; width: 150px; font-size: 13px; text-transform: uppercase; letter-spacing: .03em; }
        .credentials code { font-family: Menlo, Consolas, monospace; font-size: 17px; font-weight: 700; color: #111827; letter-spacing: .06em; }
        .notice { background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 14px 16px; margin: 20px 0; font-size: 14px; color: #78350f; }
        .button { display: inline-block; background: #3a6b6a; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; margin-top: 8px; }
        .email-footer { padding: 18px 30px 26px; font-size: 12px; color: #6b7280; text-align: center; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Crime Data Analytics</h1>
            <p>{{ $kind === 'reset' ? 'Your password has been reset' : 'Your staff account is ready' }}</p>
        </div>
        <div class="email-body">
            <p>Hello {{ $staff->full_name }},</p>

            @if ($kind === 'reset')
                <p>An administrator ({{ $issuedBy }}) issued a new temporary password for your staff account.</p>
            @else
                <p>An administrator ({{ $issuedBy }}) created a staff account for you in the Crime Data Analytics system.</p>
            @endif

            <div class="credentials">
                <table>
                    <tr>
                        <td class="label">Login page</td>
                        <td><a href="{{ $loginUrl }}">{{ $loginUrl }}</a></td>
                    </tr>
                    <tr>
                        <td class="label">Email</td>
                        <td>{{ $staff->email }}</td>
                    </tr>
                    <tr>
                        <td class="label">Temporary password</td>
                        <td><code>{{ $password }}</code></td>
                    </tr>
                    @if ($staff->position)
                        <tr>
                            <td class="label">Position</td>
                            <td>{{ $staff->position }}</td>
                        </tr>
                    @endif
                </table>
            </div>

            <div class="notice">
                This password is temporary. After you sign in you will be asked to set a password of your own
                before you can use the system.
            </div>

            <p style="text-align:center;">
                <a href="{{ $loginUrl }}" class="button">Sign in now</a>
            </p>

            <p style="font-size:13px;color:#6b7280;">
                If you were not expecting this email, please contact your administrator and do not share these details.
            </p>
        </div>
        <div class="email-footer">
            Crime Data Analytics &middot; Crime Data Department<br>
            This message was sent automatically; replies are not monitored.
        </div>
    </div>
</body>
</html>
