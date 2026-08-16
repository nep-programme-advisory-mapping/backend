<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your NEP System Password</title>
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
            background-color: #0a3d39;
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
        .btn-wrap {
            text-align: center;
            margin: 32px 0 26px;
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
        .expiry {
            background: #e3f0ee;
            border: 1px solid #b7c2bf;
            border-radius: 8px;
            padding: 13px 18px;
            margin: 24px 0;
            font-size: 13px;
            color: #0f5c56;
            text-align: center;
            font-weight: 600;
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
                <h1>Password Reset Request</h1>
                <p>We received a request to reset the password for your NEP System account.</p>
            </div>

            <div class="body">
                <p>Dear <strong>{{ $userName }}</strong>,</p>

                <p>
                    We received a request to reset the password associated with your account.
                    Please click the button below to proceed. This link is valid for
                    <strong>{{ $expiresIn }} minutes</strong> from the time this email was sent.
                </p>

                <div class="btn-wrap">
                    <a href="{{ $resetUrl }}" class="btn">Reset My Password</a>
                </div>

                <p class="fallback-url">
                    If the button above does not work, please copy and paste the following link into your browser:<br>
                    <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
                </p>

                <div class="expiry">
                    🕐 This password reset link will expire in {{ $expiresIn }} minutes.
                </div>

                <div class="notice">
                    <strong>🔒 Did not request this?</strong>
                    <p>
                        If you did not request a password reset, please disregard this email.
                        Your password will remain unchanged and your account will not be affected.
                        If you are concerned about the security of your account, please contact
                        your NEP Administrator immediately.
                    </p>
                </div>

                <p>
                    If you need any further assistance, please do not hesitate to contact
                    your NEP Administrator.
                </p>

                <p>
                    Kind regards,<br>
                    <strong>The NEP System Team</strong>
                </p>
            </div>

            <hr class="divider">

            <div class="footer">
                <p>
                    This is an automated message from the NEP Programme Mapping System.
                    For security purposes, please do not forward or share this email with anyone.
                </p>
            </div>

        </div>
    </div>
</body>
</html>
