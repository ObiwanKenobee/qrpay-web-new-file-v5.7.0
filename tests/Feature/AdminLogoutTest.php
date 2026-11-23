<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class AdminLogoutTest extends TestCase
{
    public function test_stale_csrf_token_on_admin_logout_redirects_to_login(): void
    {
        $request = $this->requestForNamedRoute('/admin/logout', 'admin.logout');

        $response = $this->app->make(Handler::class)->render($request, new TokenMismatchException);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('admin.login'), $response->headers->get('Location'));
    }

    public function test_stale_csrf_token_on_admin_logout_with_custom_prefix_redirects_to_login(): void
    {
        $request = $this->requestForNamedRoute('/control-room/logout', 'admin.logout');

        $response = $this->app->make(Handler::class)->render($request, new TokenMismatchException);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('admin.login'), $response->headers->get('Location'));
    }

    public function test_stale_csrf_token_on_other_admin_posts_still_returns_419(): void
    {
        $request = $this->requestForNamedRoute('/admin/profile/update', 'admin.profile.update');

        $response = $this->app->make(Handler::class)->render($request, new TokenMismatchException);

        $this->assertSame(419, $response->getStatusCode());
    }

    private function requestForNamedRoute(string $uri, string $name): Request
    {
        $request = Request::create($uri, 'POST');
        $route = new Route(['POST'], ltrim($uri, '/'), static fn () => null);
        $route->name($name);
        $request->setRouteResolver(static fn () => $route);

        return $request;
    }
}
