<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallationHostGuard
{
    /**
     * Ensure an existing production installation is accessed through its registered host.
     *
     * Local loopback and .test hosts are allowed for local/testing environments so
     * developers can run the application without changing the production host record.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $clientHost = strtolower($request->getHost());
        $clientHost = preg_replace('/^www\./', '', $clientHost);

        $isLocalDevelopmentHost = in_array($clientHost, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($clientHost, '.test');
        $enforceRegisteredHost = ! (app()->environment(['local', 'testing']) && $isLocalDevelopmentHost);
        $hostMismatch = false;

        try {
            if (Schema::hasTable('script')) {
                $installation = DB::table('script')->first();

                $hostMismatch = $installation
                    && $enforceRegisteredHost
                    && $clientHost !== strtolower((string) $installation->client);
            }
        } catch (Exception $exception) {
            report($exception);

            abort(
                503,
                'The Sanctum Scan database is unavailable. Configure the local database before loading the application.'
            );
        }

        if ($hostMismatch) {
            abort(403, 'This installation is not authorized for the current host.');
        }

        return $next($request);
    }
}
