<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacad;
use Symfony\Component\HttpFoundation\Response;

class RateLimiter
{
    /**
     * Route names that skip this rate limit.
     */
    const EXCEPT_ROUTES = [
        'global.get.total.transactions',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // daily / monthly limit lookups are read-only AJAX calls fired while typing
        // (user, agent and merchant panels), so they must not use up the POST rate limit
        if ($request->routeIs(self::EXCEPT_ROUTES)) {
            return $next($request);
        }

        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $user_id = $request->user() ? $request->user()->id : $request->ip();

            // unique key per URL + IP address
            $key = 'post-rate-limit:'.$user_id.':'.$request->path();

            // max 5 attempts per minute
            if (RateLimiterFacad::tooManyAttempts($key, 5)) {
                return back()->with(['error' => [__('Too many requests for this URL. Try again in 1 minute.')]]);
            }

            // record attempt (expire after 60 seconds)
            RateLimiterFacad::hit($key, 60);
        }

        return $next($request);
    }
}
