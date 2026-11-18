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
     * Ensure an existing installation is accessed through its registered host.
     *
     * The installer has been removed. A host mismatch is therefore rejected
     * directly instead of redirecting every request to a missing installer.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $clientHost = preg_replace('/^www\./', '', $request->getHttpHost());
        $hostMismatch = false;

        try {
            if (Schema::hasTable('script')) {
                $installation = DB::table('script')->first();

                $hostMismatch = $installation
                    && $clientHost !== $installation->client;
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
