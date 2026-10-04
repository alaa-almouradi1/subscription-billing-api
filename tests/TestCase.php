<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const API_KEY = 'test-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.api_keys' => [self::API_KEY]]);
        $this->withHeader('X-Api-Key', self::API_KEY);
    }
}
