<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // A logout page that outlived its session (expired, rotated APP_KEY,
        // another tab logged out) carries a stale CSRF token. Signing out is
        // idempotent, so send the operator to the login screen instead of a
        // dead-end 419 page. Laravel maps TokenMismatchException to a 419
        // HttpException before render callbacks run, so match on the status.
        $this->renderable(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $request->is('admin/logout')) {
                return null;
            }

            return redirect()->route('admin.login')
                ->with('error', [__('Your session expired. Please sign in again.')]);
        });
    }
}
