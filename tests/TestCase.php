<?php

namespace Tests;

use App\Http\Middleware\RateLimiter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests run against an installed application, not the installer wizard.
        Config::set('starting-point.status', false);
        Config::set('starting-point.point', '/');

        // No test asserts throttling behaviour; per-URL limits make unrelated
        // multi-step flows fail with 429 and are verified elsewhere.
        $this->withoutMiddleware([
            RateLimiter::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
        ]);
    }
}
