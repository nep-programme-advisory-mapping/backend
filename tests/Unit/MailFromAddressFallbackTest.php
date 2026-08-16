<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * config/mail.php's from.address now falls back to MAIL_USERNAME (the
 * authenticated SMTP account) instead of Laravel's default
 * 'hello@example.com' when MAIL_FROM_ADDRESS is left unset — an unset/
 * mismatched From address is what breaks SPF/DKIM/DMARC alignment for
 * providers stricter than Gmail (see EMAIL_DELIVERABILITY.md).
 *
 * Laravel's cached config array is fixed at boot from .env.testing, so this
 * re-evaluates the actual config/mail.php file with controlled env vars
 * rather than reading config('mail.from.address') — that only reflects
 * whatever was set once at bootstrap.
 */
class MailFromAddressFallbackTest extends TestCase
{
    private ?string $originalFrom;
    private ?string $originalUsername;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalFrom = getenv('MAIL_FROM_ADDRESS') ?: null;
        $this->originalUsername = getenv('MAIL_USERNAME') ?: null;
    }

    protected function tearDown(): void
    {
        $this->setEnv('MAIL_FROM_ADDRESS', $this->originalFrom);
        $this->setEnv('MAIL_USERNAME', $this->originalUsername);
        parent::tearDown();
    }

    private function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public function test_from_address_falls_back_to_mail_username_when_unset(): void
    {
        $this->setEnv('MAIL_FROM_ADDRESS', null);
        $this->setEnv('MAIL_USERNAME', 'someone@yourdomain.org');

        $config = require base_path('config/mail.php');

        $this->assertSame('someone@yourdomain.org', $config['from']['address']);
    }

    public function test_explicit_from_address_is_still_respected(): void
    {
        $this->setEnv('MAIL_FROM_ADDRESS', 'alias@yourdomain.org');
        $this->setEnv('MAIL_USERNAME', 'someone@yourdomain.org');

        $config = require base_path('config/mail.php');

        $this->assertSame('alias@yourdomain.org', $config['from']['address']);
    }

    public function test_falls_back_to_laravel_default_when_neither_is_set(): void
    {
        $this->setEnv('MAIL_FROM_ADDRESS', null);
        $this->setEnv('MAIL_USERNAME', null);

        $config = require base_path('config/mail.php');

        $this->assertSame('hello@example.com', $config['from']['address']);
    }
}
