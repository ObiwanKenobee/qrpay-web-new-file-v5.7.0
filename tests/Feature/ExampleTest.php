<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Smoke test replacing the stock scaffold test, which assumed a seeded
     * application database that the test suite does not provide.
     */
    public function test_the_application_boots_and_registers_the_public_scan_route(): void
    {
        $this->assertSame(
            url('/scan/snc_example'),
            route('sanctum.scan.public', ['reference' => 'snc_example'])
        );
    }
}
