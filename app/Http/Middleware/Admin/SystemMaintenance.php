<?php

namespace App\Http\Middleware\Admin;

use App\Models\Admin\SystemMaintenance as AdminSystemMaintenance;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SystemMaintenance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $system_maintenance = AdminSystemMaintenance::first();
        if ($system_maintenance?->status == 1) {
            if ($request->routeIs('admin.*')) {
                return $next($request);
            } else {
                if ($request->path() !== '/') {
                    return redirect('/'); // Redirect to home page
                }
                abort(503);
            }
        }

        return $next($request);

    }
}
