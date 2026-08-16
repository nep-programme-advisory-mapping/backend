<?php

return [

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            // Previously null (system default, effectively unbounded) — an
            // outbound port 587 that's silently firewalled would otherwise
            // hang the connection attempt indefinitely. Now that mail is
            // queued (see ShouldQueue on UserInvitationMail /
            // ResetPasswordNotification) this bounds how long a worker can
            // be stuck on one job rather than blocking a live request.
            'timeout' => (int) env('MAIL_TIMEOUT', 30),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],

    ],

    'from' => [
        // Falls back to the authenticated SMTP account (MAIL_USERNAME) rather
        // than Laravel's default 'hello@example.com' when MAIL_FROM_ADDRESS
        // isn't set. A From address on a different domain than the one SMTP
        // authenticated as breaks SPF/DKIM/DMARC alignment — Gmail-to-Gmail
        // delivery can tolerate that, but Outlook/Yahoo/iCloud/Zoho/Proton
        // and most custom-domain mail servers reject or spam-filter it. See
        // EMAIL_DELIVERABILITY.md. Set MAIL_FROM_ADDRESS explicitly only to
        // a verified alias on the same authenticated domain.
        'address' => env('MAIL_FROM_ADDRESS') ?: env('MAIL_USERNAME', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

];
