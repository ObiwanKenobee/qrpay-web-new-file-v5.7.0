<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class AdminLogoutTest extends TestCase
{
    public function test_stale_csrf_token_on_admin_logout_redirects_to_login(): void
    {
        $request = Request::create('/admin/logout', 'POST');

        $response = $this->app->make(Handler::class)->render($request, new TokenMismatchException);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('admin.login'), $response->headers->get('Location'));
    }

    public function test_stale_csrf_token_on_other_admin_posts_still_returns_419(): void
    {
        $request = Request::create('/admin/profile/update', 'POST');

        $response = $this->app->make(Handler::class)->render($request, new TokenMismatchException);

        $this->assertSame(419, $response->getStatusCode());
    }
}
