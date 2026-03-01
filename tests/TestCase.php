<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    #[\Override]
    protected function tearDown(): void
    {
        Model::reguard();

        parent::tearDown();
    }
}
