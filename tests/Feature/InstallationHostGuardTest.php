<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallationHostGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InstallationHostGuardTest extends TestCase
{
    public function test_it_allows_the_homepage_when_no_installation_host_record_exists(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('script')
            ->andReturn(false);

        $response = (new InstallationHostGuard())->handle(
            Request::create('https://sanctum.example.com/', 'GET'),
            fn () => response('homepage')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('homepage', $response->getContent());
    }

    public function test_it_allows_the_registered_host_with_or_without_www(): void
    {
        $query = Mockery::mock();

        Schema::shouldReceive('hasTable')
            ->once()
            ->with('script')
            ->andReturn(true);

        DB::shouldReceive('table')
            ->once()
            ->with('script')
            ->andReturn($query);

        $query->shouldReceive('first')
            ->once()
            ->andReturn((object) ['client' => 'sanctum.example.com']);

        $response = (new InstallationHostGuard())->handle(
            Request::create('https://www.sanctum.example.com/', 'GET'),
            fn () => response('homepage')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('homepage', $response->getContent());
    }

    public function test_it_allows_loopback_hosts_in_local_testing_environments(): void
    {
        $query = Mockery::mock();

        Schema::shouldReceive('hasTable')
            ->once()
            ->with('script')
            ->andReturn(true);

        DB::shouldReceive('table')
            ->once()
            ->with('script')
            ->andReturn($query);

        $query->shouldReceive('first')
            ->once()
            ->andReturn((object) ['client' => 'production.example.com']);

        $response = (new InstallationHostGuard())->handle(
            Request::create('http://127.0.0.1:8000/', 'GET'),
            fn () => response('homepage')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('homepage', $response->getContent());
    }

    public function test_it_rejects_a_host_that_does_not_match_the_registered_installation(): void
    {
        $query = Mockery::mock();

        Schema::shouldReceive('hasTable')
            ->once()
            ->with('script')
            ->andReturn(true);

        DB::shouldReceive('table')
            ->once()
            ->with('script')
            ->andReturn($query);

        $query->shouldReceive('first')
            ->once()
            ->andReturn((object) ['client' => 'licensed.example.com']);

        try {
            (new InstallationHostGuard())->handle(
                Request::create('https://unlicensed.example.com/', 'GET'),
                fn () => response('homepage')
            );

            $this->fail('A host mismatch must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_it_returns_service_unavailable_when_the_database_cannot_be_checked(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('script')
            ->andThrow(new \RuntimeException('Database connection failed.'));

        try {
            (new InstallationHostGuard())->handle(
                Request::create('https://sanctum.example.com/', 'GET'),
                fn () => response('homepage')
            );

            $this->fail('An unavailable database must not be treated as a successful installation check.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
    }
}
