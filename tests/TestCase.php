<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * CI uses `.env.example` (`MAIL_MAILER=resend`); synced reservation notifications mail via the queue
     * must never call the live Resend transport.
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'array',
            'services.resend.key' => '',
        ]);

        Model::unguard();
    }

    #[\Override]
    protected function tearDown(): void
    {
        Model::reguard();

        parent::tearDown();
    }
}
