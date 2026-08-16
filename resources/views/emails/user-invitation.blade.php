<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to the NEP System</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f7faf9;
            font-family: Arial, sans-serif;
            font-size: 15px;
            line-height: 1.7;
            color: #152524;
        }
        .wrapper {
            max-width: 680px;
            margin: 40px auto;
            padding: 0 20px 48px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e4eae8;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(15,45,41,0.08), 0 1px 3px rgba(15,45,41,0.06);
        }
        .header {
            background-color: #0f5c56;
            padding: 32px 40px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.01em;
        }
        .header p {
            margin: 8px 0 0;
            font-size: 14px;
            color: rgba(255,255,255,0.78);
        }
        .body {
            padding: 32px 40px;
        }
        .body p {
            margin: 0 0 18px;
            font-size: 15px;
            color: #33453f;
        }
        .credentials {
            background: #f7faf9;
            border: 1px solid #e4eae8;
            border-radius: 8px;
            padding: 22px 24px;
            margin: 24px 0;
        }
        .credentials-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #647572;
            margin: 0 0 16px;
        }
        .credential-row {
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }
        .credential-row:last-child {
            margin-bottom: 0;
        }
        .credential-label {
            display: table-cell;
            font-size: 12px;
            font-weight: 600;
            color: #647572;
            white-space: nowrap;
            padding-right: 14px;
            vertical-align: middle;
            width: 1%;
        }
        .credential-value {
            display: table-cell;
            background: #ffffff;
            border: 1px solid #e4eae8;
            border-left: 3px solid #1c8479;
            border-radius: 6px;
            padding: 9px 14px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #0f5c56;
            font-weight: 600;
            vertical-align: middle;
        }
        .btn-wrap {
            text-align: center;
            margin: 28px 0 22px;
        }
        .btn {
            display: inline-block;
            background-color: #0f5c56;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 40px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .fallback-url {
            font-size: 12px;
            color: #8b9a97;
            text-align: center;
            margin: 0 0 22px;
            word-break: break-all;
        }
        .fallback-url a {
            color: #1c8479;
            text-decoration: underline;
        }
        .notice {
            background: #fdecd3;
            border: 1px solid #d97706;
            border-radius: 8px;
            padding: 16px 18px;
            margin: 24px 0;
        }
        .notice strong {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: #b45309;
        }
        .notice p {
            margin: 0;
            font-size: 13px;
            color: #b45309;
        }
        .divider {
            border: none;
            border-top: 1px solid #e4eae8;
            margin: 0;
        }
        .footer {
            padding: 22px 40px;
            background: #f7faf9;
        }
        .footer p {
            margin: 0;
            font-size: 12px;
            color: #8b9a97;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">

            <div class="header">
                <h1>Welcome to the NEP Programme Mapping System</h1>
                <p>Your account is ready — we're glad to have you on board.</p>
            </div>

            <div class="body">
                <p>Dear <strong>{{ $userName }}</strong>,</p>

                <p>
                    We are pleased to inform you that your account on the
                    <strong>NEP Programme Mapping System</strong> has been successfully created.
                    You may now log in using the credentials provided below.
                </p>

                <div class="credentials">
                    <div class="credentials-title">Your Login Credentials</div>

                    <div class="credential-row">
                        <div class="credential-label">Email Address</div>
                        <div class="credential-value">{{ $userEmail }}</div>
                    </div>

                    <div class="credential-row">
                        <div class="credential-label">Temporary Password</div>
                        <div class="credential-value">{{ $defaultPassword }}</div>
                    </div>
                </div>

                <div class="btn-wrap">
                    <a href="{{ $loginUrl }}" class="btn">Log In to NEP System</a>
                </div>

                <p class="fallback-url">
                    If the button above does not work, please copy and paste the following link into your browser:<br>
                    <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
                </p>

                <div class="notice">
                    <strong>⚠️ Please change your password after your first login</strong>
                    <p>
                        For the security of your account, we kindly ask that you update your password
                        as soon as you log in for the first time. Please do not share your credentials
                        with anyone.
                    </p>
                </div>

                <p>
                    Should you have any questions or require assistance getting started,
                    please do not hesitate to reach out to your NEP Administrator.
                    We look forward to your participation in the system.
                </p>

                <p>
                    Warm regards,<br>
                    <strong>The NEP System Team</strong>
                </p>
            </div>

            <hr class="divider">

            <div class="footer">
                <p>
                    This message was sent by the NEP Programme Mapping System on behalf of your organisation's administrator.
                    If you were not expecting this invitation, you may safely disregard this email.
                </p>
            </div>

        </div>
    </div>
</body>
</html>
