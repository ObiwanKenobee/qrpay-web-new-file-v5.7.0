<?php

namespace App\Exceptions;

use App\Http\Helpers\Api\Helpers;
use Exception;
use Illuminate\Http\Request;

/**
 * Thrown when the gift card provider (Reloadly) fails.
 *
 * The message is always safe to show to end users. The real provider
 * reason is logged by GiftCardHelper::fail() before this is thrown.
 */
class GiftCardProviderException extends Exception
{
    /**
     * Already logged by GiftCardHelper::fail(), so skip the default report.
     */
    public function report(): bool
    {
        return true;
    }

    public function render(Request $request)
    {
        $message = __($this->getMessage());

        if ($request->is('api/*') || $request->expectsJson()) {
            return Helpers::error(['error' => [$message]]);
        }

        // url()->previous() falls back to "/", so read the real sources directly.
        $previous = $request->headers->get('referer') ?: ($request->hasSession() ? $request->session()->previousUrl() : null);
        if (!$previous || $previous === $request->fullUrl() || $previous === $request->url()) {
            return redirect()->route('user.dashboard')->with(['error' => [$message]]);
        }

        return redirect()->to($previous)->with(['error' => [$message]]);
    }
}
