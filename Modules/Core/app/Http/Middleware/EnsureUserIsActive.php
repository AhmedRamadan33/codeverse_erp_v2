<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a user who was deactivated after logging in, and rejects their API tokens.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            if ($request->is('api/*')) {
                $user->currentAccessToken()?->delete();
                abort(Response::HTTP_FORBIDDEN, __('core::auth.inactive'));
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => __('core::auth.inactive')]);
        }

        return $next($request);
    }
}
