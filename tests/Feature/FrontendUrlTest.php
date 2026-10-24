<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use ReflectionClass;
use Tests\TestCase;

/**
 * Links and assets that point at the public website follow FRONTEND_URL (config app.frontend_url),
 * falling back to production only when it is not set.
 */
class FrontendUrlTest extends TestCase
{
    private function logoUrl(): string
    {
        $mail = (new ReflectionClass(NotificationMail::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(NotificationMail::class))->getMethod('logoUrl');

        return $method->invoke($mail);
    }

    public function test_mail_logo_follows_frontend_url(): void
    {
        config(['app.frontend_url' => 'https://dev.dabapp.co/']);
        $this->assertSame('https://dev.dabapp.co/assets/images/logo-header.svg', $this->logoUrl());
    }

    public function test_mail_logo_falls_back_to_production(): void
    {
        config(['app.frontend_url' => null]);
        $this->assertSame('https://dabapp.co/assets/images/logo-header.svg', $this->logoUrl());
    }
}
