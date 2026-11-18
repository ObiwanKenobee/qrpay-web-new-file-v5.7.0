<?php

namespace App\Http\Middleware\Admin;

use App\Http\Helpers\Api\Helpers;
use App\Models\Admin\SystemMaintenance as AdminSystemMaintenance;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SystemMaintenanceApi
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
            $data = [
                'base_url' => get_asset_url(),
                'image_path' => files_asset_path_basename('error-images'),
                'image' => 'maintenance-mode.webp',
                'status' => $system_maintenance->status,
                'title' => $system_maintenance->title,
                'details' => strip_tags($system_maintenance->details),
            ];
            $message = ['error' => [__($system_maintenance->title ?? '')]];

            return Helpers::maintenance($message, $data);

        }

        return $next($request);

    }
}
